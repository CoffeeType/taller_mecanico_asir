<?php
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config/database.php';
$stmt = $pdo->query("SELECT * FROM consejos");
$res = $stmt->fetchAll();
print_r($res);
