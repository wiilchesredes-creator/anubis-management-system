<?php
require_once '../config/database.php';
require_once __DIR__ . '/session_check.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$pdo    = conectar();
$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fin    = $_GET['fin']    ?? date('Y-m-t');

// Suma de ingresos del período
$stmtI = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) AS total FROM ingresos WHERE fecha BETWEEN :ini AND :fin");
$stmtI->execute([':ini' => $inicio, ':fin' => $fin]);
$totalIngresos = (float) $stmtI->fetchColumn();

// Suma de egresos del período
$stmtE = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) AS total FROM egresos WHERE fecha BETWEEN :ini AND :fin");
$stmtE->execute([':ini' => $inicio, ':fin' => $fin]);
$totalEgresos = (float) $stmtE->fetchColumn();

echo json_encode([
    'inicio'   => $inicio,
    'fin'      => $fin,
    'ingresos' => $totalIngresos,
    'egresos'  => $totalEgresos,
    'ganancia' => $totalIngresos - $totalEgresos,
], JSON_UNESCAPED_UNICODE);
