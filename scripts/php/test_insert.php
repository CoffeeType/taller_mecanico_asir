<?php
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config/database.php';
try {
    $titulo = "Consejo de Prueba";
    $imagen = "https://example.com/image.jpg";
    $texto = "Este es un texto de prueba para el consejo.";
    $fecha = date('Y-m-d');
    $idUser = 1; // Existing admin user

    $stmt = $pdo->prepare("INSERT INTO consejos (titulo, imagen, texto, fecha, idUser) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$titulo, $imagen, $texto, $fecha, $idUser]);
    echo "Insertion successful! idConsejo: " . $pdo->lastInsertId() . "\n";
    
    // Clean up
    $pdo->prepare("DELETE FROM consejos WHERE titulo = ?")->execute([$titulo]);
    echo "Cleanup successful.\n";
} catch (PDOException $e) {
    echo "PDO Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
