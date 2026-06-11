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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session_check.php';
requireAdmin();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$pdo = conectar();

// Migración de columnas (se mantiene por seguridad durante la transición)
try {
    $colControl = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'fecha_creditos_actualizados'")->fetch(PDO::FETCH_ASSOC);
    if (!$colControl) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN fecha_creditos_actualizados DATE DEFAULT NULL");
    }
} catch (Exception $e) {
    // Ignorar error si la columna ya existe o tabla tiene otros problemas
}

try {
    $colInactivo = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'fecha_inactivo'")->fetch(PDO::FETCH_ASSOC);
    if (!$colInactivo) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN fecha_inactivo DATE DEFAULT NULL");
    }
} catch (Exception $e) {
    // Ignorar error
}

// ── NUEVAS COLUMNAS PARA PLANES BASADOS EN DÍAS ──
try {
    $colDiasUsados = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'dias_usados'")->fetch(PDO::FETCH_ASSOC);
    if (!$colDiasUsados) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN dias_usados INT DEFAULT 0");
    }
} catch (Exception $e) {
    // Ignorar si ya existe
}

try {
    $colNotificacion = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'notificacion_5_dias'")->fetch(PDO::FETCH_ASSOC);
    if (!$colNotificacion) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN notificacion_5_dias TINYINT DEFAULT 0");
    }
} catch (Exception $e) {
    // Ignorar si ya existe
}

// ── NUEVAS COLUMNAS EN TABLA DE PLANES ──
try {
    $colBasadoDias = $pdo->query("SHOW COLUMNS FROM planes LIKE 'basado_en_dias'")->fetch(PDO::FETCH_ASSOC);
    if (!$colBasadoDias) {
        $pdo->exec("ALTER TABLE planes ADD COLUMN basado_en_dias TINYINT DEFAULT 0");
        
        // Marcar FULL y PAREJA como planes basados en días
        $pdo->exec("UPDATE planes SET basado_en_dias = 1 WHERE UPPER(nombre) LIKE '%FULL%'");
        $pdo->exec("UPDATE planes SET basado_en_dias = 1 WHERE UPPER(nombre) LIKE '%PAREJA%'");
    }
} catch (Exception $e) {
    // Ignorar si ya existe o hay error
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
use AnubisBox\Services\DayBasedPlanService;

try {
    $repo = new ClientRepository($pdo);
    $creditService = new CreditService($repo);
    $dayService = new DayBasedPlanService($repo, $pdo);

    // Procesar créditos (planes AVANZADO, INTERMEDIO, etc)
    $resultCredits = $creditService->processDailyCreditUpdate();
    
    // Procesar planes basados en días (FULL, PAREJA)
    $resultDays = $dayService->processDayBasedPlans();

    // Combinar resultados
    $result = [
        'ok' => true,
        'creditos' => $resultCredits,
        'dias' => $resultDays,
    ];

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error interno al procesar la actualización de créditos',
        'detalle' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}