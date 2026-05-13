<?php
// config/env.php — Configuración por entorno
// Detección de entorno y configuración de base de datos
// Pensado para trabajar junto a config/database.php unificado

// Cargar .env en desarrollo local
require_once __DIR__ . '/load_env.php';

// Detectar entorno y aplicar configuración de BD
$is_production = false;
if (isset($_SERVER['HTTP_HOST'])) {
    if (strpos($_SERVER['HTTP_HOST'], 'infinityfree') !== false || 
        strpos($_SERVER['HTTP_HOST'], 'tallermecanico') !== false) {
        $is_production = true;
    }
}

// Variables de entorno según producción o desarrollo
if ($is_production) {
    // Ajustes de producción
    if (!getenv('DB_HOST')) putenv('DB_HOST=sql208.infinityfree.com');
    if (!getenv('DB_NAME')) putenv('DB_NAME=if0_40685841_trabajo_final_php');
    if (!getenv('DB_USER')) putenv('DB_USER=if0_40685841');
    // DB_PASS debe definirse por variable de entorno en producción
} else {
    // Ajustes de desarrollo local
    if (!getenv('DB_HOST')) putenv('DB_HOST=localhost');
    if (!getenv('DB_NAME')) putenv('DB_NAME=trabajo_final_php');
    if (!getenv('DB_USER')) putenv('DB_USER=root');
    // DB_PASS puede ir vacía en local
}

// Exponer el entorno globalmente
$_ENV['IS_PRODUCTION'] = $is_production;
$_SERVER['IS_PRODUCTION'] = $is_production;

// Opcional: array de configuración por compatibilidad con código antiguo
$config = [
    'is_production' => $is_production,
    'db_host' => getenv('DB_HOST'),
    'db_name' => getenv('DB_NAME'),
    'db_user' => getenv('DB_USER'),
    'db_pass' => getenv('DB_PASS'),
    'db_charset' => 'utf8mb4'
];

// Fin de archivo
