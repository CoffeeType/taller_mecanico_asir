<?php
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config/database.php';

try {
    $stmt = $pdo->query("SHOW TABLES LIKE '%consejo%'");
    $tables = $stmt->fetchAll(PDO::FETCH_NUM);
    if (!$tables) {
        echo "No tables matching 'consejo' exist.\n";
    } else {
        foreach ($tables as $table) {
            $tableName = $table[0];
            echo "Table '$tableName' exists.\n";
            $stmt = $pdo->query("DESCRIBE `$tableName`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Columns:\n";
            print_r($columns);

            $stmt = $pdo->query("SELECT * FROM `$tableName`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Rows (" . count($rows) . "):\n";
            print_r($rows);
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
