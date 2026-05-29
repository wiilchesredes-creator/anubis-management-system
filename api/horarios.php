<?php
/**
 * api/horarios.php
 * Módulo Horarios — ANUBIS BOX
 * 
 * GET              → Devuelve todos los horarios
 * POST accion=guardar       → Guarda/actualiza un horario
 * POST accion=verificar_pin → Verifica el PIN de admin
 * PUT campo=estado          → Actualiza solo el estado de un slot
 */

session_name('ANUBISBOX_SESS');
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* ── Verificar sesión activa ── */
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

/* ── PIN de administrador (cámbialo aquí) ── */
define('ADMIN_PIN', '1234');
define('HORARIOS_ADMIN_TTL', 1800);

/* ── Slots fijos del sistema ── */
$SLOTS_VALIDOS = [
    'h_5_6', 'h_6_7', 'h_7_8', 'h_8_9',   // Mañana
    'h_16_17', 'h_17_18', 'h_18_19', 'h_19_20' // Tarde
];

/* ================================================
   Crear tabla si no existe
   ================================================ */
function crearTablaHorarios(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS horarios (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            slot_id     VARCHAR(20) NOT NULL UNIQUE,
            instructor  VARCHAR(120) DEFAULT '',
            cupos       INT DEFAULT 0,
            cupos_max   INT DEFAULT 20,
            estado      ENUM('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
            notas       TEXT DEFAULT '',
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by  VARCHAR(60) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS horarios_reservas (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            slot_id     VARCHAR(20) NOT NULL,
            cliente_id  INT NOT NULL,
            fecha       DATE NOT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_cliente_fecha (cliente_id, fecha),
            KEY idx_slot_fecha (slot_id, fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

function requireHorarioAdmin(): void {
    if (strtoupper($_SESSION['usuario_rol'] ?? $_SESSION['admin_rol'] ?? 'ADMIN') !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(['error' => 'Solo administradores pueden editar horarios']);
        exit;
    }

    $habilitadoHasta = intval($_SESSION['horarios_admin_until'] ?? 0);
    if ($habilitadoHasta < time()) {
        unset($_SESSION['horarios_admin_until']);
        http_response_code(403);
        echo json_encode(['error' => 'Acceso de administrador requerido']);
        exit;
    }
    $_SESSION['horarios_admin_until'] = time() + HORARIOS_ADMIN_TTL;
}

function asegurarSlots(PDO $pdo, array $slotsValidos): void {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO horarios (slot_id, instructor, cupos, cupos_max, estado, notas)
        VALUES (:slot_id, '', 0, 20, 'ACTIVO', '')
    ");
    foreach ($slotsValidos as $slot) {
        $stmt->execute([':slot_id' => $slot]);
    }
}

/* ================================================
   GET → Listar horarios
   ================================================ */
function getHorarios(PDO $pdo, array $slotsValidos): void {
    crearTablaHorarios($pdo);

    // Insertar slots faltantes con valores por defecto
    asegurarSlots($pdo, $slotsValidos);

    $placeholders = implode(',', array_fill(0, count($slotsValidos), '?'));
    $rows = $pdo->prepare("
        SELECT slot_id, instructor, cupos, cupos_max, estado, notas, updated_at
        FROM horarios
        WHERE slot_id IN ($placeholders)
        ORDER BY FIELD(slot_id, " . implode(',', array_fill(0, count($slotsValidos), '?')) . ")
    ");
    // Pasar los parámetros dos veces: una para WHERE IN y otra para ORDER BY FIELD
    $params = array_merge($slotsValidos, $slotsValidos);
    $rows->execute($params);

    $horarios = $rows->fetchAll(PDO::FETCH_ASSOC);
    $fecha = date('Y-m-d');
    $clienteId = intval($_SESSION['cliente_id'] ?? 0);

    $stmtCupos = $pdo->prepare("
        SELECT COUNT(*) FROM horarios_reservas
        WHERE slot_id = :slot_id AND fecha = :fecha
    ");
    $stmtReserva = $pdo->prepare("
        SELECT COUNT(*) FROM horarios_reservas
        WHERE slot_id = :slot_id AND fecha = :fecha AND cliente_id = :cliente_id
    ");

    foreach ($horarios as &$horario) {
        $stmtCupos->execute([':slot_id' => $horario['slot_id'], ':fecha' => $fecha]);
        $horario['cupos'] = (int) $stmtCupos->fetchColumn();
        $horario['reservado_por_mi'] = false;

        if ($clienteId > 0) {
            $stmtReserva->execute([
                ':slot_id' => $horario['slot_id'],
                ':fecha' => $fecha,
                ':cliente_id' => $clienteId,
            ]);
            $horario['reservado_por_mi'] = ((int) $stmtReserva->fetchColumn()) > 0;
        }
    }

    echo json_encode($horarios);
}

/* ================================================
   POST accion=reservar / cancelar_reserva
   ================================================ */
function requireAfiliado(): int {
    if (strtoupper($_SESSION['usuario_rol'] ?? $_SESSION['admin_rol'] ?? 'ADMIN') !== 'AFILIADO' || empty($_SESSION['cliente_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Solo afiliados pueden apartar cupos']);
        exit;
    }
    return (int) $_SESSION['cliente_id'];
}

function reservarCupo(PDO $pdo, array $body, array $slotsValidos): void {
    $clienteId = requireAfiliado();
    $slotId = trim($body['slot_id'] ?? '');
    $fecha = date('Y-m-d');

    if (!in_array($slotId, $slotsValidos, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Horario inválido']);
        return;
    }

    crearTablaHorarios($pdo);
    asegurarSlots($pdo, $slotsValidos);

    $stmtHorario = $pdo->prepare("SELECT estado, cupos_max FROM horarios WHERE slot_id = :slot_id LIMIT 1");
    $stmtHorario->execute([':slot_id' => $slotId]);
    $horario = $stmtHorario->fetch(PDO::FETCH_ASSOC);

    if (!$horario || ($horario['estado'] ?? '') !== 'ACTIVO') {
        http_response_code(400);
        echo json_encode(['error' => 'Este horario no está activo']);
        return;
    }

    $stmtActual = $pdo->prepare("SELECT slot_id FROM horarios_reservas WHERE cliente_id = :cliente_id AND fecha = :fecha LIMIT 1");
    $stmtActual->execute([':cliente_id' => $clienteId, ':fecha' => $fecha]);
    $slotActual = $stmtActual->fetchColumn();

    if ($slotActual === $slotId) {
        echo json_encode(['ok' => true, 'mensaje' => 'Ya tienes este cupo apartado']);
        return;
    }

    $stmtCupos = $pdo->prepare("SELECT COUNT(*) FROM horarios_reservas WHERE slot_id = :slot_id AND fecha = :fecha");
    $stmtCupos->execute([':slot_id' => $slotId, ':fecha' => $fecha]);
    $ocupados = (int) $stmtCupos->fetchColumn();

    if ($ocupados >= (int) $horario['cupos_max']) {
        http_response_code(409);
        echo json_encode(['error' => 'Este horario ya no tiene cupos disponibles']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM horarios_reservas WHERE cliente_id = :cliente_id AND fecha = :fecha")
            ->execute([':cliente_id' => $clienteId, ':fecha' => $fecha]);

        $pdo->prepare("
            INSERT INTO horarios_reservas (slot_id, cliente_id, fecha)
            VALUES (:slot_id, :cliente_id, :fecha)
        ")->execute([
            ':slot_id' => $slotId,
            ':cliente_id' => $clienteId,
            ':fecha' => $fecha,
        ]);

        $pdo->commit();
        echo json_encode(['ok' => true, 'mensaje' => 'Cupo apartado correctamente']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo apartar el cupo']);
    }
}

function cancelarReserva(PDO $pdo): void {
    $clienteId = requireAfiliado();
    crearTablaHorarios($pdo);

    $pdo->prepare("DELETE FROM horarios_reservas WHERE cliente_id = :cliente_id AND fecha = CURDATE()")
        ->execute([':cliente_id' => $clienteId]);

    echo json_encode(['ok' => true, 'mensaje' => 'Reserva cancelada']);
}

/* ================================================
   POST → Guardar horario completo
   ================================================ */
function guardarHorario(PDO $pdo, array $body, array $slotsValidos): void {
    requireHorarioAdmin();

    $slotId    = trim($body['slot_id'] ?? '');
    $instructor= trim($body['instructor'] ?? '');
    $cuposMax  = max(1, min(200, intval($body['cupos_max'] ?? 20)));
    $notas     = trim($body['notas'] ?? '');
    $estado    = in_array($body['estado'] ?? '', ['ACTIVO', 'INACTIVO']) ? $body['estado'] : 'ACTIVO';
    $updatedBy = $_SESSION['admin_usuario'] ?? 'sistema';

    if (!in_array($slotId, $slotsValidos)) {
        http_response_code(400);
        echo json_encode(['error' => 'Slot inválido']);
        return;
    }

    crearTablaHorarios($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO horarios (slot_id, instructor, cupos_max, notas, estado, updated_by)
        VALUES (:slot_id, :instructor, :cupos_max, :notas, :estado, :updated_by)
        ON DUPLICATE KEY UPDATE
            instructor  = VALUES(instructor),
            cupos_max   = VALUES(cupos_max),
            notas       = VALUES(notas),
            estado      = VALUES(estado),
            updated_by  = VALUES(updated_by),
            updated_at  = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':slot_id'    => $slotId,
        ':instructor' => $instructor,
        ':cupos_max'  => $cuposMax,
        ':notas'      => $notas,
        ':estado'     => $estado,
        ':updated_by' => $updatedBy,
    ]);

    echo json_encode(['ok' => true, 'mensaje' => 'Horario guardado correctamente']);
}

/* ================================================
   PUT → Actualizar solo el estado
   ================================================ */
function actualizarEstado(PDO $pdo, array $body, array $slotsValidos): void {
    requireHorarioAdmin();

    $slotId = trim($body['slot_id'] ?? '');
    $campo  = trim($body['campo'] ?? '');
    $valor  = trim($body['valor'] ?? '');

    if (!in_array($slotId, $slotsValidos)) {
        http_response_code(400);
        echo json_encode(['error' => 'Slot inválido']);
        return;
    }

    // Solo se permite actualizar el campo 'estado' via PUT
    if ($campo !== 'estado' || !in_array($valor, ['ACTIVO', 'INACTIVO'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Parámetros inválidos']);
        return;
    }

    crearTablaHorarios($pdo);

    $stmt = $pdo->prepare("
        UPDATE horarios SET estado = :estado, updated_at = CURRENT_TIMESTAMP
        WHERE slot_id = :slot_id
    ");
    $stmt->execute([':estado' => $valor, ':slot_id' => $slotId]);

    echo json_encode(['ok' => true]);
}

/* ================================================
   POST accion=verificar_pin
   ================================================ */
function verificarPin(array $body): void {
    if (strtoupper($_SESSION['usuario_rol'] ?? $_SESSION['admin_rol'] ?? 'ADMIN') !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Solo administradores pueden editar horarios']);
        return;
    }

    $pin = trim($body['pin'] ?? '');
    if ($pin === ADMIN_PIN) {
        $_SESSION['horarios_admin_until'] = time() + HORARIOS_ADMIN_TTL;
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'PIN incorrecto']);
    }
}

/* ================================================
   ROUTER
   ================================================ */
try {
    $pdo = conectar(); // función definida en config/database.php
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        getHorarios($pdo, $SLOTS_VALIDOS);

    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $accion = $body['accion'] ?? 'guardar';

        if ($accion === 'verificar_pin') {
            verificarPin($body);
        } elseif ($accion === 'guardar') {
            guardarHorario($pdo, $body, $SLOTS_VALIDOS);
        } elseif ($accion === 'reservar') {
            reservarCupo($pdo, $body, $SLOTS_VALIDOS);
        } elseif ($accion === 'cancelar_reserva') {
            cancelarReserva($pdo);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Acción desconocida']);
        }

    } elseif ($method === 'PUT') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        actualizarEstado($pdo, $body, $SLOTS_VALIDOS);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
