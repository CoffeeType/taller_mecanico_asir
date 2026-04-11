<?php
// populate_motor_news.php
// Script to populate database with real motor news from motor.es RSS feed

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/news_importer.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
session_start();
if (!isAdmin()) {
    die("Acceso Denegado");
}

echo "<!DOCTYPE html>\n";
echo "<html><head><meta charset='UTF-8'><title>Importar Noticias Motor.es</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head>";
echo "<body class='bg-light'><div class='container py-5'><div class='card shadow'><div class='card-body'>";
echo "<h1 class='mb-4'>Importación de Noticias de Motor.es</h1>";

// Run the importer
$result = importMotorNews($pdo, 20);

if ($result['success']) {
    echo "<div class='alert alert-success mt-4'><strong>🎉 ¡Proceso completado!</strong><br>";
    echo $result['message'] . "</div>";
    
    echo "<a href='../admin/noticias.php' class='btn btn-primary'>Ver Noticias en Admin</a> ";
    echo "<a href='../index.php' class='btn btn-secondary'>Ir al Inicio</a>";
} else {
    echo "<div class='alert alert-danger'><strong>Error:</strong> " . $result['message'] . "</div>";
    echo "<a href='../index.php' class='btn btn-secondary'>Volver al Inicio</a>";
}

echo "</div></div></div></body></html>";
