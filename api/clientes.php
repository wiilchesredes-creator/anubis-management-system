<?php
require_once '../config/database.php';
require_once __DIR__ . '/session_check.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// Cargar autoloader de la nueva arquitectura OOP (temprano, a nivel de archivo)
$autoloaderLoaded = false;
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $autoloaderLoaded = true;
} elseif (file_exists(__DIR__ . '/../src/bootstrap_manual.php')) {
    require_once __DIR__ . '/../src/bootstrap_manual.php';
    $autoloaderLoaded = true;
}

use AnubisBox\Services\ClientService;
use AnubisBox\Repositories\ClientRepository;

$pdo    = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

function esPlanUnaSolaClase(array $plan): bool {
    $nombre = strtoupper(trim($plan['nombre'] ?? ''));
    $nombre = strtr($nombre, [
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
        'Ü' => 'U', 'Ñ' => 'N',
    ]);
    return (strpos($nombre, 'CLASE UNICA') !== false && strpos($nombre, '1 DIA') !== false)
        || strpos($nombre, '1 SOLA CLASE') !== false
        || strpos($nombre, 'UNA SOLA CLASE') !== false
        || strpos($nombre, '1 CLASE') !== false
        || strpos($nombre, 'CLASE SUELTA') !== false;
}

// Asegura columnas necesarias en la tabla clientes
try {
    $existeGenero = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'genero'")->fetch(PDO::FETCH_ASSOC);
    if (!$existeGenero) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN genero enum('MASCULINO','FEMENINO','NO ESPECIFICAR') NOT NULL DEFAULT 'NO ESPECIFICAR'");
    }
    $existeCreditos = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'creditos_usados'")->fetch(PDO::FETCH_ASSOC);
    if (!$existeCreditos) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN creditos_usados int(11) NOT NULL DEFAULT 0");
    }
    $existeActualizacion = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'fecha_creditos_actualizados'")->fetch(PDO::FETCH_ASSOC);
    if (!$existeActualizacion) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN fecha_creditos_actualizados DATE DEFAULT NULL");
    }
    $existeInactivo = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'fecha_inactivo'")->fetch(PDO::FETCH_ASSOC);
    if (!$existeInactivo) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN fecha_inactivo DATE DEFAULT NULL");
    }
    $acompananteCols = [
        'acompanante_nombre' => "varchar(150) DEFAULT NULL",
        'acompanante_cedula' => "varchar(20) DEFAULT NULL",
        'acompanante_celular' => "varchar(20) DEFAULT NULL",
        'acompanante_eps' => "varchar(100) DEFAULT NULL",
    ];
    foreach ($acompananteCols as $col => $type) {
        $existe = $pdo->query("SHOW COLUMNS FROM clientes LIKE '{$col}'")->fetch(PDO::FETCH_ASSOC);
        if (!$existe) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN {$col} {$type}");
        }
    }
} catch (Exception $e) {
    // Si no se puede crear, se continúa para permitir que el resto de la API funcione.
}

// ── GET ──────────────────────────────────────────────────────────
if ($metodo === 'GET') {

    if (isset($_GET['vencimientos'])) {
        $dias   = (int) $_GET['vencimientos'];
        $hoy    = date('Y-m-d');
        $limite = date('Y-m-d', strtotime("+{$dias} days"));
        $stmt   = $pdo->prepare("
            SELECT c.*, p.nombre AS plan_nombre, p.valor AS plan_valor, p.creditos_mes AS plan_creditos_mes
            FROM clientes c
            LEFT JOIN planes p ON c.id_plan = p.id
            WHERE c.fecha_fin BETWEEN :hoy AND :limite
            AND c.estado = 'ACTIVO'
        ");
        $stmt->execute([':hoy' => $hoy, ':limite' => $limite]);
        echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (isset($_GET['creditos_faltantes'])) {
        $limiteCreditos = (int) $_GET['creditos_faltantes'];
        $stmt = $pdo->prepare("
            SELECT c.*, p.nombre AS plan_nombre, p.valor AS plan_valor, p.creditos_mes AS plan_creditos_mes
            FROM clientes c
            LEFT JOIN planes p ON c.id_plan = p.id
            WHERE c.estado = 'ACTIVO'
              AND p.creditos_mes > 0
              AND c.creditos_usados <= p.creditos_mes
              AND (p.creditos_mes - c.creditos_usados) < :limite
            ORDER BY (p.creditos_mes - c.creditos_usados) ASC, c.created_at DESC
        ");
        $stmt->execute([':limite' => $limiteCreditos]);
        echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ── Notificaciones de planes basados en días (5 días restantes) ──
    if (isset($_GET['notificacion_dias'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    c.id, c.nombre, c.cedula, c.fecha_inicio, c.estado,
                    p.nombre AS plan_nombre, 
                    p.valor AS plan_valor
                FROM clientes c
                JOIN planes p ON c.id_plan = p.id
                WHERE c.estado = 'ACTIVO'
                  AND p.basado_en_dias = 1
                  AND c.notificacion_5_dias = 1
                ORDER BY c.fecha_inicio ASC
            ");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Fallback si los campos no existen aún
            $result = [];
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ── GET: Consulta principal de clientes (con manejo defensivo) ──
    try {
        $stmt = $pdo->query("
            SELECT c.*, p.nombre AS plan_nombre, p.valor AS plan_valor, p.creditos_mes AS plan_creditos_mes, p.basado_en_dias
            FROM clientes c
            LEFT JOIN planes p ON c.id_plan = p.id
            ORDER BY c.created_at DESC
        ");
    } catch (Exception $e) {
        // Si el campo basado_en_dias no existe, usar fallback
        $stmt = $pdo->query("
            SELECT c.*, p.nombre AS plan_nombre, p.valor AS plan_valor, p.creditos_mes AS plan_creditos_mes, 0 AS basado_en_dias
            FROM clientes c
            LEFT JOIN planes p ON c.id_plan = p.id
            ORDER BY c.created_at DESC
        ");
    }
    
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── POST: Registrar cliente + ingreso automático ──────────────────
if ($metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);

    try {
        $repo = new ClientRepository($pdo);
        $service = new ClientService($repo, $pdo);

        $result = $service->createClientWithIngreso($datos);

        http_response_code(201);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── PUT: Editar cliente + sincronizar ingreso de inscripción ─
if ($metodo === 'PUT') {
    $id    = (int) ($_GET['id'] ?? 0);
    $datos = json_decode(file_get_contents('php://input'), true);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requerido']);
        exit;
    }

    // fecha_nacimiento no se exige en renovaciones (clientes legacy pueden tenerla nula)
    $esRenovacionValidacion = !empty($datos['es_renovacion']);
    $requeridos = $esRenovacionValidacion
        ? ['nombre', 'cedula', 'genero', 'celular', 'eps', 'fecha_inicio', 'fecha_fin']
        : ['nombre', 'cedula', 'fecha_nacimiento', 'genero', 'celular', 'eps', 'fecha_inicio', 'fecha_fin'];
    foreach ($requeridos as $campo) {
        if (empty($datos[$campo])) {
            http_response_code(400);
            echo json_encode(['error' => "El campo '$campo' es obligatorio"]);
            exit;
        }
    }

    $stmtClienteActual = $pdo->prepare("SELECT id_plan, estado, fecha_inactivo FROM clientes WHERE id = :id");
    $stmtClienteActual->execute([':id' => $id]);
    $clienteActual = $stmtClienteActual->fetch(PDO::FETCH_ASSOC);
    $esPareja = false;
    if ($clienteActual) {
        $stmtPlan = $pdo->prepare("SELECT nombre FROM planes WHERE id = :id");
        $stmtPlan->execute([':id' => $clienteActual['id_plan']]);
        $planActual = $stmtPlan->fetch(PDO::FETCH_ASSOC);
        $esPareja = $planActual && stripos($planActual['nombre'], 'pareja') !== false;
    }

    if ($esPareja) {
        $parejaRequeridos = ['acompanante_nombre', 'acompanante_cedula', 'acompanante_celular', 'acompanante_eps'];
        foreach ($parejaRequeridos as $campo) {
            if (empty($datos[$campo])) {
                http_response_code(400);
                echo json_encode(['error' => "El campo '$campo' es obligatorio para el plan Pareja"]);
                exit;
            }
        }
    }

    $estadoAnterior = $clienteActual['estado'] ?? 'ACTIVO';
    $fechaInactivo = $clienteActual['fecha_inactivo'] ?? null;
    $nuevoEstado = $datos['estado'] ?? 'ACTIVO';
    $fechaActualizadaAlCambiarEstado = null;

    if ($nuevoEstado === 'INACTIVO') {
        if ($estadoAnterior !== 'INACTIVO' || !$fechaInactivo) {
            $fechaInactivo = date('Y-m-d');
            $fechaActualizadaAlCambiarEstado = date('Y-m-d');
        }
    } else {
        if ($estadoAnterior === 'INACTIVO' || $fechaInactivo) {
            $fechaInactivo = null;
            $fechaActualizadaAlCambiarEstado = date('Y-m-d');
        }
    }

    $planId = !empty($datos['id_plan']) ? $datos['id_plan'] : $clienteActual['id_plan'];
    $stmtPlanCreditos = $pdo->prepare("SELECT creditos_mes FROM planes WHERE id = :id");
    $stmtPlanCreditos->execute([':id' => $planId]);
    $planCreditos = $stmtPlanCreditos->fetch(PDO::FETCH_ASSOC);

    if (isset($datos['creditos_usados'])) {
        $creditosUsados = (int) $datos['creditos_usados'];
        if ($creditosUsados < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Los créditos usados no pueden ser negativos']);
            exit;
        }
        if ($planCreditos && $planCreditos['creditos_mes'] > 0 && $creditosUsados > $planCreditos['creditos_mes']) {
            http_response_code(400);
            echo json_encode(['error' => 'No puedes usar más créditos de los permitidos por el plan']);
            exit;
        }
    }

    $pdo->beginTransaction();
    try {
        // 1. Actualizar cliente
        $sqlUpdate = "
            UPDATE clientes
            SET nombre           = :nombre,
                cedula           = :cedula,
                fecha_nacimiento = :fecha_nacimiento,
                genero           = :genero,
                celular          = :celular,
                eps              = :eps,
                estado           = :estado,
                fecha_inicio     = :fecha_inicio,
                fecha_fin        = :fecha_fin,
                acompanante_nombre = :acompanante_nombre,
                acompanante_cedula = :acompanante_cedula,
                acompanante_celular = :acompanante_celular,
                acompanante_eps = :acompanante_eps,
                fecha_inactivo   = :fecha_inactivo";
        if (!empty($datos['id_plan'])) {
            $sqlUpdate .= ", id_plan = :id_plan";
        }
        if (isset($datos['creditos_usados'])) {
            $sqlUpdate .= ", creditos_usados = :creditos_usados";
        }
        if ($fechaActualizadaAlCambiarEstado !== null) {
            $sqlUpdate .= ", fecha_creditos_actualizados = :fecha_creditos_actualizados";
        }
        $sqlUpdate .= "
            WHERE id = :id";

        $params = [
            ':nombre'           => trim($datos['nombre']),
            ':cedula'           => trim($datos['cedula']),
            ':fecha_nacimiento' => $datos['fecha_nacimiento'],
            ':genero'           => strtoupper(trim($datos['genero'])),
            ':celular'          => trim($datos['celular']),
            ':eps'              => trim($datos['eps']),
            ':estado'           => $datos['estado'] ?? 'ACTIVO',
            ':fecha_inicio'     => $datos['fecha_inicio'],
            ':fecha_fin'        => $datos['fecha_fin'],
            ':acompanante_nombre' => $datos['acompanante_nombre'] ?? null,
            ':acompanante_cedula' => $datos['acompanante_cedula'] ?? null,
            ':acompanante_celular' => $datos['acompanante_celular'] ?? null,
            ':acompanante_eps' => $datos['acompanante_eps'] ?? null,
            ':fecha_inactivo'  => $fechaInactivo,
            ':id'               => $id,
        ];

        if (!empty($datos['id_plan'])) {
            $params[':id_plan'] = $datos['id_plan'];
        }
        if (isset($datos['creditos_usados'])) {
            $params[':creditos_usados'] = (int) $datos['creditos_usados'];
        }

        $stmt = $pdo->prepare($sqlUpdate);
        $stmt->execute($params);

        // 2. Sincronizar nombre en el ingreso de inscripción original (si existe)
        // REGLA: Solo se actualiza el concepto cuando el nombre del cliente cambia.
        //        NUNCA se actualiza la fecha del ingreso original — hacerlo borraría
        //        el historial (la inscripción quedaría con la fecha de la renovación).
        //        Las renovaciones insertan su PROPIO ingreso desde el frontend via
        //        POST /ingresos.php — no se tocan aquí.
        $esRenovacion = !empty($datos['es_renovacion']);

        if (!$esRenovacion) {
            // Buscar el ingreso de inscripción original del cliente
            $stmtVerificar = $pdo->prepare("
                SELECT id FROM ingresos
                WHERE id_cliente = :id_cliente
                  AND concepto LIKE 'Inscripción%'
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmtVerificar->execute([':id_cliente' => $id]);
            $ingresoOriginalId = $stmtVerificar->fetchColumn();

            if ($ingresoOriginalId) {
                // Solo actualiza concepto (nombre) y método de pago.
                // La fecha se preserva intacta para mantener el historial correcto.
                $sqlIngreso = "
                    UPDATE ingresos
                    SET concepto = CONCAT('Inscripción - ', :nombre)";

                if (!empty($datos['metodo_pago'])) {
                    $sqlIngreso .= ", metodo_pago = :metodo_pago";
                }

                $sqlIngreso .= " WHERE id = :id_ingreso";

                $paramsIngreso = [
                    ':nombre'     => trim($datos['nombre']),
                    ':id_ingreso' => $ingresoOriginalId,
                ];

                if (!empty($datos['metodo_pago'])) {
                    $paramsIngreso[':metodo_pago'] = strtoupper($datos['metodo_pago']);
                }

                $stmtIngreso = $pdo->prepare($sqlIngreso);
                $stmtIngreso->execute($paramsIngreso);
            }
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'actualizado' => $id]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

/* ══════════════════════════════════════════════════════
   PATCH ?id=X  →  Cambiar solo el estado del cliente
   Body: { "estado": "INACTIVO" | "ACTIVO" }
   - Al pasar a INACTIVO: guarda fecha_inactivo = hoy,
     congela créditos (no se descuentan durante 8 días)
   - A los 8 días: actualizar_creditos.php lo reactiva
     automáticamente y reanuda el descuento
   - Al pasar manualmente a ACTIVO: limpia fecha_inactivo
══════════════════════════════════════════════════════ */
if ($metodo === 'PATCH') {
    $id    = (int) ($_GET['id'] ?? 0);
    $datos = json_decode(file_get_contents('php://input'), true);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID requerido']);
        exit;
    }

    $nuevoEstado = strtoupper(trim($datos['estado'] ?? ''));
    if (!in_array($nuevoEstado, ['ACTIVO', 'INACTIVO'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Estado debe ser ACTIVO o INACTIVO']);
        exit;
    }

    // Leer estado actual
    $stmtActual = $pdo->prepare("SELECT estado, fecha_inactivo FROM clientes WHERE id = :id");
    $stmtActual->execute([':id' => $id]);
    $actual = $stmtActual->fetch(PDO::FETCH_ASSOC);

    if (!$actual) {
        http_response_code(404);
        echo json_encode(['error' => 'Cliente no encontrado']);
        exit;
    }

    $estadoAnterior = $actual['estado'];
    $fechaInactivo  = $actual['fecha_inactivo'];

    if ($nuevoEstado === 'INACTIVO') {
        // Solo registrar fecha si recién se pone inactivo
        if ($estadoAnterior !== 'INACTIVO' || !$fechaInactivo) {
            $fechaInactivo = date('Y-m-d');
        }
        $pdo->prepare("
            UPDATE clientes
            SET estado = 'INACTIVO',
                fecha_inactivo = :fecha_inactivo
            WHERE id = :id
        ")->execute([':fecha_inactivo' => $fechaInactivo, ':id' => $id]);

    } else {
        // Reactivar manualmente: limpiar fecha_inactivo
        $pdo->prepare("
            UPDATE clientes
            SET estado = 'ACTIVO',
                fecha_inactivo = NULL
            WHERE id = :id
        ")->execute([':id' => $id]);
        $fechaInactivo = null;
    }

    echo json_encode([
        'ok'            => true,
        'id'            => $id,
        'estado'        => $nuevoEstado,
        'fecha_inactivo'=> $fechaInactivo,
        'mensaje'       => $nuevoEstado === 'INACTIVO'
            ? "Cliente pausado. Se reactivará automáticamente en 8 días ({$fechaInactivo})"
            : 'Cliente reactivado manualmente',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}