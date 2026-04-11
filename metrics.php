<?php
/**
 * Endpoint de métricas Prometheus para la aplicación PHP
 * Expone métricas en formato Prometheus
 */

// Set Content-Type header FIRST before any output or includes
header('Content-Type: text/plain; version=0.0.4');

// Disable error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Create database connection directly using environment variables
// We don't include database.php to avoid die() calls that output HTML
$pdo = null;
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'trabajo_final_php';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'rootpassword';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Metrics: Failed to connect to database: " . $e->getMessage());
    // Continue without database connection - metrics will show 0 values
}

// Variable para almacenar estado de conexión a BD
$dbConnectionHealthy = 0;

// Función para verificar salud de la conexión a la base de datos
function verificarSaludBD($pdo) {
    try {
        $pdo->query("SELECT 1");
        return 1;
    } catch (PDOException $e) {
        error_log("Error verificando salud BD: " . $e->getMessage());
        return 0;
    }
}

// Función auxiliar para contar filas de una tabla, devolviendo 0 si la tabla no existe
function countTableRows($pdo, $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM " . $table);
        $result = $stmt->fetch();
        return (int)$result['total'];
    } catch (PDOException $e) {
        error_log("Error consultando tabla $table: " . $e->getMessage());
        return 0;
    }
}

// Función para obtener métricas de la base de datos
function obtenerMetricasBD($pdo) {
    global $dbConnectionHealthy;
    $metricas = [];

    // Verificar salud de la conexión PRIMERO, de forma independiente a las queries de negocio
    $dbConnectionHealthy = verificarSaludBD($pdo);

    if ($dbConnectionHealthy === 0) {
        return $metricas;
    }

    // Total de usuarios
    $metricas['app_users_total'] = countTableRows($pdo, 'users_data');

    // Usuarios por rol
    try {
        $stmt = $pdo->query("SELECT rol, COUNT(*) as total FROM users_login GROUP BY rol");
        while ($row = $stmt->fetch()) {
            $metricas['app_users_by_role{role="' . $row['rol'] . '"}'] = (int)$row['total'];
        }
    } catch (PDOException $e) {
        error_log("Error consultando users_login: " . $e->getMessage());
    }

    // Total de citas
    $metricas['app_citas_total'] = countTableRows($pdo, 'citas');

    // Citas por estado (futuras vs pasadas)
    try {
        $stmt = $pdo->query("
            SELECT
                CASE
                    WHEN fecha_cita >= CURDATE() THEN 'futura'
                    ELSE 'pasada'
                END as estado,
                COUNT(*) as total
            FROM citas
            GROUP BY estado
        ");
        while ($row = $stmt->fetch()) {
            $metricas['app_citas_by_status{status="' . $row['estado'] . '"}'] = (int)$row['total'];
        }
    } catch (PDOException $e) {
        error_log("Error consultando citas por estado: " . $e->getMessage());
    }

    // Total de noticias
    $metricas['app_noticias_total'] = countTableRows($pdo, 'noticias');

    // Total de consejos
    $metricas['app_consejos_total'] = countTableRows($pdo, 'consejos');

    // Sesiones activas (aproximado por sesiones PHP)
    $sessionCount = obtenerSesionesActivas();
    $metricas['app_sessions_active'] = $sessionCount;

    return $metricas;
}

// Función para obtener sesiones activas (aproximado)
function obtenerSesionesActivas() {
    try {
        $sessionPath = session_save_path();
        if (empty($sessionPath)) {
            $sessionPath = sys_get_temp_dir();
        }
        
        $count = 0;
        if (is_dir($sessionPath)) {
            $files = glob($sessionPath . '/sess_*');
            if ($files) {
                // Contar solo sesiones modificadas en la última hora
                $oneHourAgo = time() - 3600;
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) > $oneHourAgo) {
                        $count++;
                    }
                }
            }
        }
        return $count;
    } catch (Exception $e) {
        error_log("Error obteniendo sesiones activas: " . $e->getMessage());
        return 0;
    }
}

// Función para obtener la ruta del directorio de logs
function obtenerDirectorioLogs() {
    $possiblePaths = [
        __DIR__ . '/logs',                    // Local development (desde monitoring/php-exporter/)
        __DIR__ . '/../logs',                 // Docker (desde /var/www/html/)
        dirname(__DIR__) . '/logs',           // Alternative path
        '/var/www/html/logs'                  // Docker absolute path
    ];
    
    foreach ($possiblePaths as $path) {
        if (is_dir($path)) {
            return $path;
        }
    }
    
    // Fallback: intentar crear el directorio
    $defaultPath = dirname(__DIR__) . '/logs';
    if (!is_dir($defaultPath)) {
        @mkdir($defaultPath, 0755, true);
    }
    return $defaultPath;
}

// Función para obtener métricas de requests HTTP desde archivo de log
function obtenerMetricasHTTP() {
    $logsDir = obtenerDirectorioLogs();
    $logFile = $logsDir . '/metrics.log';
    $metricas = [];
    
    if (!file_exists($logFile)) {
        return $metricas;
    }
    
    try {
        // Leer todo el archivo de manera eficiente
        $handle = fopen($logFile, 'r');
        if ($handle === false) {
            error_log("No se pudo abrir el archivo de métricas: " . $logFile);
            return $metricas;
        }
        
        // Leer línea por línea para manejar archivos grandes
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            if (preg_match('/(GET|POST|PUT|DELETE|PATCH)\s+(\d{3})/', $line, $matches)) {
                $method = $matches[1];
                $status = $matches[2];
                $key = 'app_http_requests_total{method="' . $method . '",status="' . $status . '"}';
                
                if (!isset($metricas[$key])) {
                    $metricas[$key] = 0;
                }
                $metricas[$key]++;
            }
        }
        fclose($handle);
    } catch (Exception $e) {
        error_log("Error leyendo métricas HTTP: " . $e->getMessage());
    }
    
    return $metricas;
}

// Función para obtener métricas de tiempo de respuesta
function obtenerMetricasTiempoRespuesta() {
    $logsDir = obtenerDirectorioLogs();
    $responseTimeFile = $logsDir . '/response_time.log';
    $metricas = [];
    
    if (!file_exists($responseTimeFile)) {
        return $metricas;
    }
    
    try {
        $times = file($responseTimeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($times)) {
            return $metricas;
        }
        
        // Convertir a números y filtrar valores inválidos
        $times = array_filter(array_map('floatval', $times), function($v) {
            return $v >= 0 && is_finite($v);
        });
        
        if (empty($times)) {
            return $metricas;
        }
        
        $count = count($times);
        $sum = array_sum($times);
        sort($times);
        
        // Calcular quantiles
        $metricas['count'] = $count;
        $metricas['sum'] = $sum;
        $metricas['quantile_0_5'] = $times[floor($count * 0.5)];
        $metricas['quantile_0_9'] = $times[floor($count * 0.9)];
        $metricas['quantile_0_95'] = $times[floor($count * 0.95)];
        $metricas['quantile_0_99'] = $times[min(floor($count * 0.99), $count - 1)];
        $metricas['max'] = max($times);
        $metricas['min'] = min($times);
        
    } catch (Exception $e) {
        error_log("Error obteniendo métricas de tiempo de respuesta: " . $e->getMessage());
    }
    
    return $metricas;
}

// Obtener todas las métricas
try {
    if ($pdo !== null) {
        $metricasBD = obtenerMetricasBD($pdo);
    } else {
        $metricasBD = [];
        $dbConnectionHealthy = 0;
    }
    $metricasHTTP = obtenerMetricasHTTP();
    $metricasTiempo = obtenerMetricasTiempoRespuesta();
} catch (Exception $e) {
    error_log("Error crítico obteniendo métricas: " . $e->getMessage());
    $metricasBD = [];
    $metricasHTTP = [];
    $metricasTiempo = [];
    $dbConnectionHealthy = 0;
}

// Output en formato Prometheus
echo "# HELP app_db_connection_healthy Estado de salud de la conexión a la base de datos (1=healthy, 0=unhealthy)\n";
echo "# TYPE app_db_connection_healthy gauge\n";
echo "app_db_connection_healthy " . $dbConnectionHealthy . "\n\n";

echo "# HELP app_users_total Total de usuarios registrados\n";
echo "# TYPE app_users_total gauge\n";
echo "app_users_total " . ($metricasBD['app_users_total'] ?? 0) . "\n\n";

echo "# HELP app_users_by_role Usuarios por rol\n";
echo "# TYPE app_users_by_role gauge\n";
foreach ($metricasBD as $key => $value) {
    if (strpos($key, 'app_users_by_role') === 0) {
        echo $key . " " . $value . "\n";
    }
}
echo "\n";

echo "# HELP app_citas_total Total de citas\n";
echo "# TYPE app_citas_total gauge\n";
echo "app_citas_total " . ($metricasBD['app_citas_total'] ?? 0) . "\n\n";

echo "# HELP app_citas_by_status Citas por estado\n";
echo "# TYPE app_citas_by_status gauge\n";
foreach ($metricasBD as $key => $value) {
    if (strpos($key, 'app_citas_by_status') === 0) {
        echo $key . " " . $value . "\n";
    }
}
echo "\n";

echo "# HELP app_noticias_total Total de noticias\n";
echo "# TYPE app_noticias_total gauge\n";
echo "app_noticias_total " . ($metricasBD['app_noticias_total'] ?? 0) . "\n\n";

echo "# HELP app_consejos_total Total de consejos\n";
echo "# TYPE app_consejos_total gauge\n";
echo "app_consejos_total " . ($metricasBD['app_consejos_total'] ?? 0) . "\n\n";

echo "# HELP app_sessions_active Sesiones activas\n";
echo "# TYPE app_sessions_active gauge\n";
echo "app_sessions_active " . ($metricasBD['app_sessions_active'] ?? 0) . "\n\n";

echo "# HELP app_http_requests_total Total de requests HTTP\n";
echo "# TYPE app_http_requests_total counter\n";
if (empty($metricasHTTP)) {
    // Emitir al menos una métrica con valor 0 para que Prometheus reconozca la métrica
    echo 'app_http_requests_total{method="GET",status="200"} 0' . "\n";
} else {
    foreach ($metricasHTTP as $key => $value) {
        echo $key . " " . $value . "\n";
    }
}
echo "\n";

// Métricas de tiempo de respuesta usando histogram (más apropiado que summary)
if (!empty($metricasTiempo)) {
    echo "# HELP app_http_response_time_seconds Tiempo de respuesta HTTP en segundos\n";
    echo "# TYPE app_http_response_time_seconds summary\n";
    echo 'app_http_response_time_seconds{quantile="0.5"} ' . ($metricasTiempo['quantile_0_5'] ?? 0) . "\n";
    echo 'app_http_response_time_seconds{quantile="0.9"} ' . ($metricasTiempo['quantile_0_9'] ?? 0) . "\n";
    echo 'app_http_response_time_seconds{quantile="0.95"} ' . ($metricasTiempo['quantile_0_95'] ?? 0) . "\n";
    echo 'app_http_response_time_seconds{quantile="0.99"} ' . ($metricasTiempo['quantile_0_99'] ?? 0) . "\n";
    echo 'app_http_response_time_seconds_sum ' . ($metricasTiempo['sum'] ?? 0) . "\n";
    echo 'app_http_response_time_seconds_count ' . ($metricasTiempo['count'] ?? 0) . "\n";
    echo "\n";
    
    // Métricas adicionales de tiempo
    echo "# HELP app_http_response_time_seconds_max Tiempo máximo de respuesta HTTP\n";
    echo "# TYPE app_http_response_time_seconds_max gauge\n";
    echo "app_http_response_time_seconds_max " . ($metricasTiempo['max'] ?? 0) . "\n\n";
    
    echo "# HELP app_http_response_time_seconds_min Tiempo mínimo de respuesta HTTP\n";
    echo "# TYPE app_http_response_time_seconds_min gauge\n";
    echo "app_http_response_time_seconds_min " . ($metricasTiempo['min'] ?? 0) . "\n\n";
}

?>