<?php
// config/env.php - Environment Configuration
// This file provides environment detection and database configuration
// It's designed to work with the unified database.php configuration

// Load .env file for local development
require_once __DIR__ . '/load_env.php';

// Determine environment and set database configuration
$is_production = false;
if (isset($_SERVER['HTTP_HOST'])) {
    if (strpos($_SERVER['HTTP_HOST'], 'infinityfree') !== false || 
        strpos($_SERVER['HTTP_HOST'], 'tallermecanico') !== false) {
        $is_production = true;
    }
}

// Set environment variables based on environment
if ($is_production) {
    // Production settings
    if (!getenv('DB_HOST')) putenv('DB_HOST=sql208.infinityfree.com');
    if (!getenv('DB_NAME')) putenv('DB_NAME=if0_40685841_trabajo_final_php');
    if (!getenv('DB_USER')) putenv('DB_USER=if0_40685841');
    // DB_PASS must be set via environment variable in production
} else {
    // Local development settings
    if (!getenv('DB_HOST')) putenv('DB_HOST=localhost');
    if (!getenv('DB_NAME')) putenv('DB_NAME=trabajo_final_php');
    if (!getenv('DB_USER')) putenv('DB_USER=root');
    // DB_PASS can be empty for local development
}

// Make environment available globally
$_ENV['IS_PRODUCTION'] = $is_production;
$_SERVER['IS_PRODUCTION'] = $is_production;

// Optional: Return configuration array for legacy compatibility
$config = [
    'is_production' => $is_production,
    'db_host' => getenv('DB_HOST'),
    'db_name' => getenv('DB_NAME'),
    'db_user' => getenv('DB_USER'),
    'db_pass' => getenv('DB_PASS'),
    'db_charset' => 'utf8mb4'
];

// End of file
