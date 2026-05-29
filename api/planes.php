<?php
require_once '../config/database.php';
require_once __DIR__ . '/session_check.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── POST: actualizar estado (activo/inactivo) de un plan ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo   = conectar();
    $datos = json_decode(file_get_contents('php://input'), true);

    if (empty($datos['id']) || !isset($datos['activo'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Se requieren id y activo']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE planes SET activo = :activo WHERE id = :id");
    $stmt->execute([
        ':activo' => (int) $datos['activo'],
        ':id'     => (int) $datos['id'],
    ]);

    // Verificar que el plan realmente existe (rowCount=0 puede ser que no cambió el valor)
    $check = $pdo->prepare("SELECT id FROM planes WHERE id = :id");
    $check->execute([':id' => (int) $datos['id']]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Plan no encontrado']);
        exit;
    }

    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

$pdo = conectar();

// Asegura que exista el campo de créditos en planes
$colCreditos = $pdo->query("SHOW COLUMNS FROM planes LIKE 'creditos_mes'")->fetch(PDO::FETCH_ASSOC);
if (!$colCreditos) {
    $pdo->exec("ALTER TABLE planes ADD COLUMN creditos_mes int(11) NOT NULL DEFAULT 0");
}

// Asegura que exista el plan Pareja con el valor correcto
try {
    // Actualizar límites de créditos para planes conocidos
    $planLimits = [
        'PAREJA'    => 27,
        'FULL'      => 24,
        'AVANZADO'  => 12,
        'INTERMEDIO'=> 8,
    ];
    foreach ($planLimits as $keyword => $limit) {
        $pdo->exec("UPDATE planes SET creditos_mes = {$limit} WHERE creditos_mes = 0 AND UPPER(nombre) LIKE '%{$keyword}%'");
    }

    // Buscar cualquier plan que contenga la palabra Pareja para evitar crear duplicados
    $stmtPareja = $pdo->query("SELECT id FROM planes WHERE UPPER(nombre) LIKE '%PAREJA%' ORDER BY id ASC");
    $parejaPlanes = $stmtPareja->fetchAll(PDO::FETCH_ASSOC);

    if (empty($parejaPlanes)) {
        $stmtInsert = $pdo->prepare("INSERT INTO planes (nombre, valor, duracion_dias, descripcion, activo, creditos_mes) VALUES (:nombre, :valor, :duracion, :descripcion, 1, :creditos)");
        $stmtInsert->execute([
            ':nombre'      => '⚱️ Plan pareja 27 creditos',
            ':valor'       => 130000.00,
            ':duracion'    => 30,
            ':descripcion' => 'Entrenen juntos, evolucionen juntos. Dos guerreros unidos bajo el poder de Anubis para conquistar sus objetivos. 🔥👑',
            ':creditos'    => 27,
        ]);
    } else {
        // Mantener solo un registro Pareja activo y consolidar duplicados
        $keepId = (int)$parejaPlanes[0]['id'];
        $duplicateIds = array_map(fn($row) => (int)$row['id'], array_slice($parejaPlanes, 1));

        if (!empty($duplicateIds)) {
            $in = implode(',', $duplicateIds);
            // Reasignar clientes a la fila principal antes de eliminar duplicados
            $pdo->exec("UPDATE clientes SET id_plan = {$keepId} WHERE id_plan IN ($in)");
            $pdo->exec("DELETE FROM planes WHERE id IN ($in)");
        }

        $stmtUpdate = $pdo->prepare("UPDATE planes SET nombre = :nombre, valor = :valor, duracion_dias = :duracion, descripcion = :descripcion, creditos_mes = :creditos WHERE id = :id");
        $stmtUpdate->execute([
            ':nombre'      => '⚱️ Plan pareja 27 creditos',
            ':valor'       => 130000.00,
            ':duracion'    => 30,
            ':descripcion' => 'Entrenen juntos, evolucionen juntos. Dos guerreros unidos bajo el poder de Anubis para conquistar sus objetivos. 🔥👑',
            ':creditos'    => 27,
            ':id'          => $keepId,
        ]);
    }
} catch (Exception $e) {
    // No interrumpe el servicio si no puede insertar el plan
}

$stmtPlanes = $pdo->query("SELECT * FROM planes ORDER BY valor ASC");
$planes = $stmtPlanes->fetchAll();

// Deduplicar por nombre normalizado
$seenNames = [];
$deduplicatedPlanes = [];
foreach ($planes as $plan) {
    $nombreUpper = strtoupper(trim($plan['nombre']));
    $normalizedName = stripos($nombreUpper, 'PAREJA') !== false ? 'PAREJA' : $nombreUpper;
    if (!isset($seenNames[$normalizedName])) {
        $seenNames[$normalizedName] = true;
        $deduplicatedPlanes[] = $plan;
    }
}
$planes = $deduplicatedPlanes;

foreach ($planes as &$plan) {
    $stmtClientes = $pdo->prepare("
        SELECT id, nombre, cedula, celular, eps, estado, fecha_inicio, fecha_fin,
               acompanante_nombre, acompanante_cedula, acompanante_celular, acompanante_eps
        FROM clientes
        WHERE id_plan = :id_plan
        ORDER BY nombre ASC
    ");
    $stmtClientes->execute([':id_plan' => $plan['id']]);
    $plan['clientes']       = $stmtClientes->fetchAll();
    $plan['total_clientes'] = count($plan['clientes']);
}

echo json_encode($planes, JSON_UNESCAPED_UNICODE);
