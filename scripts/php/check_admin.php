<?php
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config/database.php';
try {
    $stmt = $pdo->query("SELECT * FROM users_login WHERE rol = 'admin'");
    $admins = $stmt->fetchAll();
    echo "Admin users in 'users_login':\n";
    foreach ($admins as $admin) {
        echo "- User: " . $admin['usuario'] . ", idUser: " . $admin['idUser'] . "\n";
        
        // Check if this idUser exists in users_data
        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM users_data WHERE idUser = ?");
        $stmt2->execute([$admin['idUser']]);
        $exists = $stmt2->fetchColumn();
        echo "  Exists in users_data: " . ($exists ? "YES" : "NO") . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
