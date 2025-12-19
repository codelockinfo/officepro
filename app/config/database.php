<?php
/**
 * Database Configuration
 * Automatically detects environment (local vs live) and uses appropriate database
 */

// Detect if running on local or live server
$isLocal = (
    ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' ||
    strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
    ($_SERVER['SERVER_NAME'] ?? '') === 'localhost' ||
    strpos($_SERVER['SERVER_NAME'] ?? '', 'localhost') !== false
);

// Local database configuration
$localConfig = [
    'host' => 'localhost',
    'dbname' => 'officepro_attendance',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

// Live database configuration
$liveConfig = [
    'host' => 'localhost', // Usually localhost for shared hosting
    'dbname' => 'u402017191_officepro',
    'username' => 'u402017191_officepro', // Update this with your actual username
    'password' => 'Codelock@63', // Update this with your actual password
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

// Return appropriate configuration based on environment
return $isLocal ? $localConfig : $liveConfig;

