<?php
require_once '../config/database.php';
require_once __DIR__ . '/session_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$pdo = conectar();

// Asegurar que existe la tabla de registro diario de asistencia
$pdo->exec("
    CREATE TABLE IF NOT EXISTS asistencia_diaria (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        fecha       DATE NOT NULL,
        cedula      VARCHAR(30) NOT NULL,
        nombre      VARCHAR(150) NOT NULL,
        plan_nombre VARCHAR(120),
        tipo        VARCHAR(20) DEFAULT 'ok',
        detalle     VARCHAR(200),
        hora        TIME NOT NULL,
        id_cliente  INT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ══════════════════════════════════════════
   GET ?historial=1  →  Registros de hoy
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['historial'])) {
    $hoy = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM asistencia_diaria
        WHERE fecha = :hoy
        ORDER BY id DESC
    ");
    $stmt->execute([':hoy' => $hoy]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($registros);
    $totalOk  = count(array_filter($registros, fn($r) => $r['tipo'] === 'ok'));

    echo json_encode([
        'fecha'     => $hoy,
        'total'     => $total,
        'total_ok'  => $totalOk,
        'registros' => $registros,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ══════════════════════════════════════════
   POST { cedula }  →  Registrar asistencia
══════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos  = json_decode(file_get_contents('php://input'), true);
    $cedula = trim($datos['cedula'] ?? '');

    if (!$cedula) {
        http_response_code(400);
        echo json_encode(['error' => 'Cédula requerida']);
        exit;
    }

    $hoy  = date('Y-m-d');
    $hora = date('H:i:s');

    // Buscar cliente (titular o acompañante)
    $stmt = $pdo->prepare("
        SELECT c.id, c.nombre, c.cedula, c.estado, c.fecha_fin,
               c.creditos_usados, c.acompanante_cedula, c.acompanante_nombre,
               c.dias_usados, c.fecha_inicio, c.notificacion_5_dias,
               p.creditos_mes, p.nombre AS plan_nombre, p.basado_en_dias
        FROM clientes c
        JOIN planes p ON c.id_plan = p.id
        WHERE c.cedula = :cedula OR c.acompanante_cedula = :cedula2
        ORDER BY c.id DESC LIMIT 1
    ");
    $stmt->execute([':cedula' => $cedula, ':cedula2' => $cedula]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    // Helper para guardar en historial diario
    $guardarRegistro = function($nombre, $planNombre, $tipo, $detalle, $idCliente) use ($pdo, $hoy, $hora, $cedula) {
        $pdo->prepare("
            INSERT INTO asistencia_diaria (fecha, cedula, nombre, plan_nombre, tipo, detalle, hora, id_cliente)
            VALUES (:fecha, :cedula, :nombre, :plan, :tipo, :detalle, :hora, :id_cliente)
        ")->execute([
            ':fecha'      => $hoy,
            ':cedula'     => $cedula,
            ':nombre'     => $nombre,
            ':plan'       => $planNombre,
            ':tipo'       => $tipo,
            ':detalle'    => $detalle,
            ':hora'       => $hora,
            ':id_cliente' => $idCliente,
        ]);
    };

    if (!$cliente) {
        // No se guarda en historial — cédula no existe en el sistema
        http_response_code(404);
        echo json_encode(['error' => 'No se encontró ningún cliente con esa cédula']);
        exit;
    }

    $esAcompanante = ($cliente['acompanante_cedula'] === $cedula && $cliente['cedula'] !== $cedula);
    $nombreMostrar = $esAcompanante ? $cliente['acompanante_nombre'] : $cliente['nombre'];

    // ── Verificar si ya marcó asistencia hoy ──
    $stmtDup = $pdo->prepare("
        SELECT id FROM asistencia_diaria
        WHERE fecha = :hoy AND cedula = :cedula AND tipo = 'ok'
        LIMIT 1
    ");
    $stmtDup->execute([':hoy' => $hoy, ':cedula' => $cedula]);
    if ($stmtDup->fetch()) {
        http_response_code(409);
        echo json_encode([
            'error' => "{$nombreMostrar} ya registró asistencia hoy.",
            'tipo'  => 'duplicado',
            'nombre'=> $nombreMostrar,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validaciones
    if ($cliente['estado'] !== 'ACTIVO') {
        $guardarRegistro($nombreMostrar, $cliente['plan_nombre'], 'err', 'Cliente inactivo', $cliente['id']);
        http_response_code(403);
        echo json_encode(['error' => 'El cliente está INACTIVO. Debe renovar su plan.', 'tipo' => 'inactivo']);
        exit;
    }
    if ($cliente['fecha_fin'] < $hoy) {
        $guardarRegistro($nombreMostrar, $cliente['plan_nombre'], 'err', 'Plan vencido desde ' . $cliente['fecha_fin'], $cliente['id']);
        http_response_code(403);
        echo json_encode(['error' => 'El plan del cliente está vencido desde ' . $cliente['fecha_fin'], 'tipo' => 'vencido']);
        exit;
    }

    $creditosMax    = (int) $cliente['creditos_mes'];
    $creditosUsados = (int) $cliente['creditos_usados'];

    // ── PLANES BASADOS EN DÍAS (FULL Y PAREJA) ──
    if ((int)$cliente['basado_en_dias'] === 1) {
        // Verificar si aún tiene días disponibles
        $fechaInicio = new DateTime($cliente['fecha_inicio']);
        $fechaHoy = new DateTime($hoy);
        
        // Contar días laborales (sin domingos) desde la fecha de inicio
        $diasUsados = 0;
        $cursor = clone $fechaInicio;
        while ($cursor <= $fechaHoy) {
            $dayOfWeek = (int)$cursor->format('N'); // 1=Monday ... 7=Sunday
            if ($dayOfWeek <= 6) {
                $diasUsados++;
            }
            $cursor->modify('+1 day');
        }

        $diasRestantes = 30 - $diasUsados;

        if ($diasRestantes <= 0) {
            // Plan vencido - cambiar a INACTIVO
            $pdo->prepare("UPDATE clientes SET estado = 'INACTIVO' WHERE id = :id")->execute([':id' => $cliente['id']]);
            $guardarRegistro($nombreMostrar, $cliente['plan_nombre'], 'err', 'Plan vencido (30 días agotados)', $cliente['id']);
            http_response_code(403);
            echo json_encode(['error' => 'Tu plan de 30 días ha vencido. Debes renovar.', 'tipo' => 'plan_vencido']);
            exit;
        }

        // Permitir acceso y guardar registro
        $guardarRegistro($nombreMostrar, $cliente['plan_nombre'], 'libre', "Acceso por días. Días restantes: $diasRestantes", $cliente['id']);

        // Si faltan 5 días y no se ha notificado, notificar
        $tieneAlerta = false;
        if ($diasRestantes === 5 && (int)$cliente['notificacion_5_dias'] === 0) {
            $pdo->prepare("UPDATE clientes SET notificacion_5_dias = 1 WHERE id = :id")->execute([':id' => $cliente['id']]);
            $tieneAlerta = true;
        }

        echo json_encode([
            'ok'                  => true,
            'nombre'              => $nombreMostrar,
            'plan_nombre'         => $cliente['plan_nombre'],
            'creditos_descontados'=> false,
            'plan_tipo'           => 'basado_en_dias',
            'dias_restantes'      => $diasRestantes,
            'alerta_5_dias'       => $tieneAlerta,
            'mensaje'             => $tieneAlerta 
                ? "¡Alerta! Te quedan $diasRestantes días en tu plan. Renueva pronto."
                : "Acceso permitido. Te quedan $diasRestantes días en tu plan.",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── PLANES BASADOS EN CRÉDITOS (AVANZADO, INTERMEDIO, ETC) ──
    if ($creditosMax === 0) {
        $guardarRegistro($nombreMostrar, $cliente['plan_nombre'], 'libre', 'Acceso libre', $cliente['id']);
        echo json_encode([
            'ok'                  => true,
            'nombre'              => $nombreMostrar,
            'plan_nombre'         => $cliente['plan_nombre'],
            'creditos_descontados'=> false,
            'mensaje'             => 'Acceso libre — plan sin límite de créditos',
            'creditos_restantes'  => null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Sin créditos
    if ($creditosUsados >= $creditosMax) {
        $guardarRegistro($nombreMostrar, $cliente['plan_nombre'], 'warn', 'Créditos agotados', $cliente['id']);
        http_response_code(403);
        echo json_encode(['error' => 'Sin créditos disponibles este mes. Créditos agotados.', 'tipo' => 'sin_creditos']);
        exit;
    }

    // Descontar 1 crédito
    $nuevoUsado = $creditosUsados + 1;
    $pdo->prepare("
        UPDATE clientes
        SET creditos_usados = :nuevo, fecha_creditos_actualizados = :hoy
        WHERE id = :id
    ")->execute([':nuevo' => $nuevoUsado, ':hoy' => $hoy, ':id' => $cliente['id']]);

    $restantes = $creditosMax - $nuevoUsado;
    $guardarRegistro($nombreMostrar, $cliente['plan_nombre'], 'ok',
        "Créditos: $nuevoUsado/$creditosMax", $cliente['id']);

    echo json_encode([
        'ok'                  => true,
        'nombre'              => $nombreMostrar,
        'plan_nombre'         => $cliente['plan_nombre'],
        'creditos_usados'     => $nuevoUsado,
        'creditos_max'        => $creditosMax,
        'creditos_restantes'  => $restantes,
        'creditos_descontados'=> true,
        'alerta_pocos'        => $restantes <= 3,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);