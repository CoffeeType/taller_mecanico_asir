<?php
// setup_check.php
$config_file = '../config/db.php';
$status_messages = [];

function addStatus($message, $type = 'info') {
    global $status_messages;
    $status_messages[] = ['type' => $type, 'message' => $message];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificación del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h1 class="h4 mb-0">Diagnóstico del Sistema</h1>
        </div>
        <div class="card-body">
            
            <!-- 1. Configuration File -->
            <h5 class="border-bottom pb-2">1. Archivo de Configuración</h5>
            <?php if (file_exists($config_file)): ?>
                <div class="alert alert-success py-2">✅ Encontrado: <?= $config_file ?></div>
                
                <?php
                // Try to read config manually to check vars without including (avoiding immediate die)
                $content = file_get_contents($config_file);
                if (strpos($content, '$host =') !== false && strpos($content, '$db   =') !== false) {
                    echo "<div class='alert alert-success py-2'>✅ Variables de configuración detectadas.</div>";
                } else {
                    echo "<div class='alert alert-warning py-2'>⚠️ El archivo existe pero parece no tener la estructura esperada.</div>";
                }
                ?>
            <?php else: ?>
                <div class="alert alert-danger py-2">❌ No encontrado: <?= $config_file ?></div>
            <?php endif; ?>

            <!-- 2. Database Connection -->
            <h5 class="border-bottom pb-2 mt-4">2. Conexión a Base de Datos</h5>
            <?php
            $pdo = null;
            if (file_exists($config_file)) {
                // We define variables locally to avoid including the file if it has the die() logic we just added
                // but actually we want to test THAT logic or duplicate it.
                // Let's try to parse the file simply or just require it inside a try block is hard if it has die()
                // So we will try to connect manually using what we assume are defaults or regex read them
                
                // Let's just try to include it. If it dies, the user sees the styled error from db.php which is technically a success for "testing connection"
                // But to make this script useful even on failure, we should try to replicate the connection logic here safely.
                
                if (file_exists(__DIR__ . '/../config/env.php')) {
                    $env = require __DIR__ . '/../config/env.php';
                    $host = $env['DB_HOST'] ?? 'localhost';
                    $db   = $env['DB_NAME'] ?? 'trabajo_final_php';
                    $user = $env['DB_USER'] ?? 'root';
                    $pass = $env['DB_PASS'] ?? '';
                    
                    echo "<p>Cargando configuración desde <code>env.php</code>...</p>";
                } else {
                    // Fallback regex (though unlikely if file uses require)
                    $host = 'localhost';
                    $db   = 'trabajo_final_php';
                    $user = 'root';
                    $pass = ''; 
                    // Regex ... (legacy)
                    if (preg_match('/\$host\s*=\s*[\'"](.*?)[\'"];/', $content, $m)) $host = $m[1];
                    if (preg_match('/\$db\s*=\s*[\'"](.*?)[\'"];/', $content, $m)) $db = $m[1];
                }

                echo "<p>Intentando conectar con: <strong>User:</strong> $user | <strong>Host:</strong> $host | <strong>DB:</strong> $db</p>";

                try {
                   $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
                   $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                   echo "<div class='alert alert-success py-2'>✅ Conexión Exitosa.</div>";
                } catch (PDOException $e) {
                    echo "<div class='alert alert-danger py-2'>❌ Error de Conexión: " . $e->getMessage() . "</div>";
                    echo "<div class='alert alert-info py-2'>ℹ️ Si el error es 'Access denied', verifica el password en <code>config/db.php</code>.</div>";
                }
            }
            ?>

            <!-- 3. Tables Check -->
            <?php if ($pdo): ?>
                <h5 class="border-bottom pb-2 mt-4">3. Verificación de Tablas</h5>
                <ul class="list-group">
                <?php
                $required_tables = ['users_data', 'users_login', 'citas', 'noticias'];
                foreach ($required_tables as $table) {
                    try {
                        $pdo->query("SELECT 1 FROM $table LIMIT 1");
                        echo "<li class='list-group-item list-group-item-success'>✅ Tabla <strong>$table</strong> existe.</li>";
                    } catch (Exception $e) {
                         echo "<li class='list-group-item list-group-item-danger'>❌ Tabla <strong>$table</strong> no encontrada. <br><small>Ejecuta el script SQL en <code>database.sql</code></small></li>";
                    }
                }
                ?>
                </ul>
            <?php endif; ?>

            <!-- 4. Uploads Directory -->
            <h5 class="border-bottom pb-2 mt-4">4. Directorio de Subidas</h5>
            <?php
            $upload_dir = __DIR__ . '/../uploads';
            if (is_dir($upload_dir)) {
                 echo "<div class='alert alert-success py-2'>✅ Directorio <code>uploads/</code> existe.</div>";
            } else {
                 echo "<div class='alert alert-warning py-2'>⚠️ Directorio <code>uploads/</code> no existe. Intentando crear...</div>";
                 if (@mkdir($upload_dir, 0755, true)) {
                     echo "<div class='alert alert-success py-2'>✅ Directorio creado exitosamente.</div>";
                 } else {
                     echo "<div class='alert alert-danger py-2'>❌ No se pudo crear el directorio. Créalo manualmente.</div>";
                 }
            }
            ?>

        </div>
        <div class="card-footer text-center">
            <a href="index.php" class="btn btn-primary">Ir al Inicio</a>
        </div>
    </div>
</div>
</body>
</html>
