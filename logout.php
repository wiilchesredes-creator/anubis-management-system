<?php
/**
 * logout.php — Cierra la sesión y redirige al login.
 * Llamar desde el botón de cerrar sesión del header.
 */

ini_set('session.cookie_httponly', 1);
session_name('ANUBISBOX_SESS');
session_start();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: login.html');
exit;
