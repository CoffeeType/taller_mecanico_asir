<?php
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config/database.php';
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM consejos");
    $count = $stmt->fetchColumn();
    echo "Total consejos: " . $count . "\n";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT * FROM consejos LIMIT 1");
        $row = $stmt->fetch();
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
