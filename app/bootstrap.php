<?php
/**
 * bootstrap.php
 * App bootstrap file (autoloader and session initialization)
 * Follows PSR-12 coding standards.
 */

// Register PSR-4 Autoloader for namespace App
spl_autoload_register(static function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

