<?php
require_once '../config/database.php';
require_once __DIR__ . '/session_check.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$pdo = conectar();

// Asegura que exista el campo banco_origen en la tabla ingresos
$colBanco = $pdo->query("SHOW COLUMNS FROM ingresos LIKE 'banco_origen'")->fetch(PDO::FETCH_ASSOC);
if (!$colBanco) {
    $pdo->exec("ALTER TABLE ingresos ADD COLUMN banco_origen VARCHAR(100) DEFAULT NULL");
}

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    // ?todos=1  → todos los ingresos sin filtro de fecha
    if (isset($_GET['todos'])) {
        $stmt = $pdo->query("
            SELECT i.*, c.nombre AS cliente_nombre
            FROM ingresos i
            LEFT JOIN clientes c ON i.id_cliente = c.id
            ORDER BY i.fecha DESC, i.id DESC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ?cliente_id=X  → historial completo de un cliente (todas las fechas)
    if (isset($_GET['cliente_id'])) {
        $clienteId = (int) $_GET['cliente_id'];
        if (!$clienteId) {
            http_response_code(400);
            echo json_encode(['error' => 'cliente_id inválido']);
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT i.*, c.nombre AS cliente_nombre
            FROM ingresos i
            LEFT JOIN clientes c ON i.id_cliente = c.id
            WHERE i.id_cliente = :cliente_id
            ORDER BY i.fecha ASC, i.id ASC
        ");
        $stmt->execute([':cliente_id' => $clienteId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $inicio = $_GET['inicio'] ?? date('Y-m-01');   // Primer día del mes si no se especifica
    $fin    = $_GET['fin']    ?? date('Y-m-t');     // Último día del mes

    $stmt = $pdo->prepare("
        SELECT i.*, c.nombre AS cliente_nombre
        FROM ingresos i
        LEFT JOIN clientes c ON i.id_cliente = c.id
        WHERE i.fecha BETWEEN :inicio AND :fin
        ORDER BY i.fecha DESC
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
        INSERT INTO ingresos (id_cliente, concepto, monto, fecha, metodo_pago, banco_origen)
        VALUES (:id_cliente, :concepto, :monto, :fecha, :metodo_pago, :banco_origen)
    ");
    $stmt->execute([
        ':id_cliente'   => $datos['id_cliente']  ?? null,
        ':concepto'     => trim($datos['concepto']),
        ':monto'        => $datos['monto'],
        ':fecha'        => $datos['fecha'],
        ':metodo_pago'  => $datos['metodo_pago'] ?? 'EFECTIVO',
        ':banco_origen' => ($datos['metodo_pago'] ?? '') === 'TRANSFERENCIA' ? ($datos['banco_origen'] ?? null) : null,
    ]);

    http_response_code(201);
    echo json_encode(['id' => $pdo->lastInsertId(), 'ok' => true]);
    exit;
}