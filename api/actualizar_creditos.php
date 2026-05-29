<?php
/**
 * actualizar_creditos.php
 * 
 * LEGACY ENTRY POINT (thin wrapper)
 * 
 * Este endpoint sigue siendo llamado desde el frontend (index.html).
 * La lógica de negocio real fue movida a CreditService.
 * 
 * NO agregar nueva lógica aquí.
 * Cualquier cambio en las reglas de créditos debe hacerse en:
 *   src/Services/CreditService.php
 */

require_once __DIR__ . '/session_check.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$pdo = conectar();

// Migración de columnas (se mantiene por seguridad durante la transición)
$colControl = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'fecha_creditos_actualizados'")->fetch(PDO::FETCH_ASSOC);
if (!$colControl) {
    $pdo->exec("ALTER TABLE clientes ADD COLUMN fecha_creditos_actualizados DATE DEFAULT NULL");
}
$colInactivo = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'fecha_inactivo'")->fetch(PDO::FETCH_ASSOC);
if (!$colInactivo) {
    $pdo->exec("ALTER TABLE clientes ADD COLUMN fecha_inactivo DATE DEFAULT NULL");
}

// Cargar la nueva arquitectura OOP
$autoloaderLoaded = false;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $autoloaderLoaded = true;
} elseif (file_exists(__DIR__ . '/../src/bootstrap_manual.php')) {
    require_once __DIR__ . '/../src/bootstrap_manual.php';
    $autoloaderLoaded = true;
}

if (!$autoloaderLoaded) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'No se pudo cargar el autoloader de clases OOP'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

use AnubisBox\Repositories\ClientRepository;
use AnubisBox\Services\CreditService;

try {
    $repo = new ClientRepository($pdo);
    $service = new CreditService($repo);

    $result = $service->processDailyCreditUpdate();

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error interno al procesar la actualización de créditos',
        'detalle' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
