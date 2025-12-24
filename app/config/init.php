<?php
/**
 * Application Initialization
 * Include this at the top of every entry point
 */

// Configure session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_lifetime', 31536000); // 1 year (365 days) - cookie lifetime
ini_set('session.gc_maxlifetime', 31536000); // 1 year (365 days) - garbage collection max lifetime

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters for long-lasting session (1 year)
    session_set_cookie_params(31536000, '/', '', false, true); // lifetime, path, domain, secure, httponly
    session_start();
}

// Set timezone
$appConfig = __DIR__ . '/app.php';
if (file_exists($appConfig)) {
    $config = require $appConfig;
    date_default_timezone_set($config['timezone']);
}

// Error handling (for production, you'd want to log errors instead of displaying them)
// For development, we show errors
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/error.log');

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errorTypes = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice'
    ];
    
    $errorType = $errorTypes[$errno] ?? 'Unknown Error';
    $message = "[$errorType] $errstr in $errfile on line $errline";
    
    error_log($message);
    
    return true;
});

// Autoload helpers
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../helpers/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

