<?php
$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/config/database.php';
$sql = file_get_contents($projectRoot . '/database/seed_consejos.sql');
try {
    $pdo->exec($sql);
    echo "Seed executed successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
