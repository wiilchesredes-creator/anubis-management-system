<?php
/**
 * session_check.php — Guard reutilizable para todos los endpoints de la API.
 * Incluir al inicio de cada api/*.php con:
 *   require_once __DIR__ . '/session_check.php';
 *
 * Si la sesión no es válida devuelve 401 y corta la ejecución.
 * No muestra nada al usuario; la respuesta es siempre JSON.
 */

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600);

session_name('ANUBISBOX_SESS');

// Solo arrancar la sesión si no está ya iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$SESSION_TIMEOUT = 1800; // 30 min de inactividad

$sesionValida =
    !empty($_SESSION['admin_id']) &&
    !empty($_SESSION['login_time']) &&
    (time() - $_SESSION['login_time']) < $SESSION_TIMEOUT;

if (!$sesionValida) {
    // Limpiar sesión expirada
    $_SESSION = [];
    session_destroy();

    // Solo enviar cabecera JSON si no se enviaron ya
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(401);
    echo json_encode([
        'error'        => 'No autorizado. Inicia sesión.',
        'redirigir_a'  => '../login.html',
    ]);
    exit;
}

// Renovar timestamp de actividad en cada request válido
$_SESSION['login_time'] = time();

function usuarioRol(): string {
    return strtoupper($_SESSION['usuario_rol'] ?? $_SESSION['admin_rol'] ?? 'ADMIN');
}

function requireRol(array $rolesPermitidos): void {
    $rol = usuarioRol();
    $rolesPermitidos = array_map('strtoupper', $rolesPermitidos);

    if (!in_array($rol, $rolesPermitidos, true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code(403);
        echo json_encode([
            'error' => 'No tienes permiso para acceder a este recurso.',
            'rol'   => $rol,
        ]);
        exit;
    }
}

function requireAdmin(): void {
    requireRol(['ADMIN']);
}
