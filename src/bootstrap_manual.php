<?php
/**
 * bootstrap_manual.php
 * 
 * TEMPORARY manual autoloader for testing the new OOP classes
 * without having Composer installed yet.
 * 
 * Usage:
 *   require_once __DIR__ . '/src/bootstrap_manual.php';
 * 
 * Once you install Composer, delete this file and use:
 *   require_once __DIR__ . '/vendor/autoload.php';
 */

spl_autoload_register(function (string $class) {
    // Only handle our namespace
    if (!str_starts_with($class, 'AnubisBox\\')) {
        return;
    }

    // Convert namespace to file path
    $relative = str_replace('AnubisBox\\', '', $class);
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Convenience: also make the old database connection available
if (!function_exists('conectar')) {
    require_once __DIR__ . '/../config/database.php';
}
