<?php
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config/database.php';
try {
    $stmt = $pdo->query("DESCRIBE users_data");
    $columns = $stmt->fetchAll();
    echo "Columns in 'users_data' table:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
