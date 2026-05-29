<?php
/**
 * auth.php — Autenticación central de ANUBIS BOX
 * Rutas:
 *   GET  ?check=1  → verifica si hay sesión activa
 *   POST            → intento de login con { usuario, contrasena }
 *   GET  ?logout=1  → cierra la sesión
 */

require_once '../config/database.php';

// Configuración de sesión segura (antes de session_start)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_lifetime', 0);      // Cookie muere al cerrar el navegador
ini_set('session.gc_maxlifetime', 1800);    // 30 min de inactividad máxima
ini_set('session.cookie_secure', 0);        // localhost no usa HTTPS

session_name('ANUBISBOX_SESS');
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── LOGOUT ──────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    echo json_encode(['ok' => true, 'mensaje' => 'Sesión cerrada']);
    exit;
}

// ── CHECK DE SESIÓN ──────────────────────────────────────────────────
if (isset($_GET['check'])) {
    $inactividad = 1800; // 30 minutos
    $autenticado = false;

    if (!empty($_SESSION['admin_id']) && !empty($_SESSION['login_time'])) {
        if ((time() - $_SESSION['login_time']) < $inactividad) {
            $_SESSION['login_time'] = time(); // renovar actividad
            $autenticado = true;
        } else {
            // Sesión expirada por inactividad → destruir
            session_unset();
            session_destroy();
        }
    }

    echo json_encode([
        'autenticado' => $autenticado,
        'usuario'     => $autenticado ? ($_SESSION['admin_usuario'] ?? null) : null,
        'nombre'      => $autenticado ? ($_SESSION['admin_nombre'] ?? null) : null,
        'rol'         => $autenticado ? ($_SESSION['usuario_rol'] ?? $_SESSION['admin_rol'] ?? 'ADMIN') : null,
    ]);
    exit;
}

// ── LOGIN ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos     = json_decode(file_get_contents('php://input'), true);
    $usuario   = trim($datos['usuario']   ?? '');
    $contrasena = trim($datos['contrasena'] ?? '');
    $tipoAcceso = strtoupper(trim($datos['tipo'] ?? $datos['rol'] ?? 'ADMIN'));

    if (!$usuario || !$contrasena) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Usuario y contraseña requeridos']);
        exit;
    }

    if (!in_array($tipoAcceso, ['ADMIN', 'AFILIADO'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Tipo de acceso inválido']);
        exit;
    }

    // Destruir sesión previa para forzar login limpio cada vez
    if (!empty($_SESSION['admin_id'])) {
        session_unset();
        session_destroy();
        session_start();
    }

    // Protección brute-force del lado del servidor
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $claveRate = 'ab_rate_' . md5($ip);

    if (!isset($_SESSION[$claveRate])) {
        $_SESSION[$claveRate] = ['intentos' => 0, 'bloqueado_hasta' => 0];
    }

    $rate = &$_SESSION[$claveRate];
    if ($rate['bloqueado_hasta'] > time()) {
        $restantes = $rate['bloqueado_hasta'] - time();
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => "Demasiados intentos. Espera {$restantes}s."]);
        exit;
    }

    try {
        $pdo = conectar();

        // Asegurar que existe la tabla admins
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                usuario     VARCHAR(80) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                nombre      VARCHAR(120),
                rol         ENUM('ADMIN','AFILIADO') NOT NULL DEFAULT 'ADMIN',
                activo      TINYINT(1) NOT NULL DEFAULT 1,
                ultimo_login DATETIME DEFAULT NULL,
                creado_en   DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $colRol = $pdo->query("SHOW COLUMNS FROM admins LIKE 'rol'")->fetch(PDO::FETCH_ASSOC);
        if (!$colRol) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN rol ENUM('ADMIN','AFILIADO') NOT NULL DEFAULT 'ADMIN' AFTER nombre");
        }

        // Buscar usuario (búsqueda segura por nombre, sin exposición de hash en error)
        if ($tipoAcceso === 'AFILIADO') {
            if ($usuario !== $contrasena) {
                $rate['intentos']++;
                if ($rate['intentos'] >= 5) {
                    $rate['bloqueado_hasta'] = time() + 30;
                    $rate['intentos'] = 0;
                }
                http_response_code(401);
                echo json_encode(['ok' => false, 'error' => 'Usuario o contraseña incorrectos']);
                exit;
            }

            $stmtCliente = $pdo->prepare("
                SELECT id, nombre, cedula, estado, fecha_fin
                FROM clientes
                WHERE cedula = :cedula
                LIMIT 1
            ");
            $stmtCliente->execute([':cedula' => $usuario]);
            $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

            $clienteValido = (bool) $cliente;

            if (!$clienteValido) {
                $rate['intentos']++;
                if ($rate['intentos'] >= 5) {
                    $rate['bloqueado_hasta'] = time() + 30;
                    $rate['intentos'] = 0;
                }
                http_response_code(401);
                echo json_encode(['ok' => false, 'error' => 'Usuario o contraseña incorrectos']);
                exit;
            }

            $rate['intentos'] = 0;
            $rate['bloqueado_hasta'] = 0;
            session_regenerate_id(true);

            $_SESSION['admin_id']      = 'cliente_' . $cliente['id'];
            $_SESSION['admin_usuario'] = $cliente['cedula'];
            $_SESSION['admin_nombre']  = $cliente['nombre'];
            $_SESSION['admin_rol']     = 'AFILIADO';
            $_SESSION['usuario_rol']   = 'AFILIADO';
            $_SESSION['cliente_id']    = (int) $cliente['id'];
            $_SESSION['login_time']    = time();

            echo json_encode([
                'ok'      => true,
                'usuario' => $cliente['cedula'],
                'nombre'  => $cliente['nombre'],
                'rol'     => 'AFILIADO',
                'inicio'  => 'horarios.html',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, usuario, password_hash, nombre, rol, activo FROM admins WHERE usuario = :u LIMIT 1");
        $stmt->execute([':u' => $usuario]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        $passwordValido = $admin && password_verify($contrasena, $admin['password_hash']);

        if (!$admin || !$passwordValido || !$admin['activo'] || strtoupper($admin['rol'] ?? 'ADMIN') !== $tipoAcceso) {
            // Incrementar contador de intentos
            $rate['intentos']++;
            if ($rate['intentos'] >= 5) {
                $rate['bloqueado_hasta'] = time() + 30;
                $rate['intentos'] = 0;
            }
            // Mismo mensaje siempre para no revelar si existe el usuario
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Usuario o contraseña incorrectos']);
            exit;
        }

        // Credenciales correctas → limpiar intentos, regenerar sesión
        $rate['intentos'] = 0;
        $rate['bloqueado_hasta'] = 0;

        session_regenerate_id(true); // Previene session fixation

        $_SESSION['admin_id']      = $admin['id'];
        $_SESSION['admin_usuario'] = $admin['usuario'];
        $_SESSION['admin_nombre']  = $admin['nombre'];
        $_SESSION['admin_rol']     = strtoupper($admin['rol'] ?? 'ADMIN');
        $_SESSION['usuario_rol']   = strtoupper($admin['rol'] ?? 'ADMIN');
        $_SESSION['login_time']    = time();

        // Registrar último login
        $pdo->prepare("UPDATE admins SET ultimo_login = NOW() WHERE id = :id")
            ->execute([':id' => $admin['id']]);

        echo json_encode([
            'ok'      => true,
            'usuario' => $admin['usuario'],
            'nombre'  => $admin['nombre'],
            'rol'     => strtoupper($admin['rol'] ?? 'ADMIN'),
            'inicio'  => strtoupper($admin['rol'] ?? 'ADMIN') === 'AFILIADO' ? 'horarios.html' : 'index.html',
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error interno del servidor']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
