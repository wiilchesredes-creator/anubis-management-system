<?php
require_once '../config/database.php';
require_once __DIR__ . '/session_check.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$pdo    = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $inicio = $_GET['inicio'] ?? date('Y-m-01');
    $fin    = $_GET['fin']    ?? date('Y-m-t');

    $stmt = $pdo->prepare("
        SELECT * FROM egresos
        WHERE fecha BETWEEN :inicio AND :fin
        ORDER BY fecha DESC
    ");
    $stmt->execute([':inicio' => $inicio, ':fin' => $fin]);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($metodo === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true);

    if (empty($datos['concepto']) || empty($datos['monto']) || empty($datos['fecha'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Concepto, monto y fecha son obligatorios']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO egresos (concepto, categoria, monto, fecha)
        VALUES (:concepto, :categoria, :monto, :fecha)
    ");
    $stmt->execute([
        ':concepto'  => trim($datos['concepto']),
        ':categoria' => $datos['categoria'] ?? 'OTRO',
        ':monto'     => $datos['monto'],
        ':fecha'     => $datos['fecha'],
    ]);

    http_response_code(201);
    echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true]);
    exit;
}
