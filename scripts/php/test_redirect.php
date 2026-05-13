<?php
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config/database.php';
session_start();
// Simular sesión no administrador
$_SESSION['user_id'] = 999;
$_SESSION['user_role'] = 'user';

// Debería redirigir a ../index.php
require_once $projectRoot . '/admin/index.php';
