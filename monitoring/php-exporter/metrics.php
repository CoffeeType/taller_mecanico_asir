<?php
/**
 * Endpoint de métricas Prometheus para la aplicación PHP
 * Expone métricas en formato Prometheus
 */

// Cabecera Content-Type antes que cualquier salida o include
header('Content-Type: text/plain; version=0.0.4');

// Desactivar salida de errores para no mezclar HTML con Prometheus
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Parser compartido para logs HTTP (tráfico app vs simulador)
$__trafficSimLib = __DIR__ . '/scripts/traffic_simulator_lib.php';
if (!is_readable($__trafficSimLib)) {
    $__trafficSimLib = __DIR__ . '/../../scripts/traffic_simulator_lib.php';
}
if (is_readable($__trafficSimLib)) {
    require_once $__trafficSimLib;
}

// Conexión a BD con variables de entorno (sin database.php: evita die() que imprime HTML)
$pdo = null;

$runningInContainer = file_exists('/.dockerenv');

// Alias de entorno Coolify / BD gestionada
$host = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: ($runningInContainer ? 'mysql' : 'localhost'));
$port = getenv('DB_PORT') ?: (getenv('MYSQL_PORT') ?: '');
$db   = getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: 'trabajo_final_php');
$user = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: 'root');
$pass = getenv('DB_PASS') ?: (getenv('MYSQL_PASSWORD') ?: ($runningInContainer ? 'rootpassword' : ''));

// Soporte host:puerto
if (is_string($host) && strpos($host, ':') !== false && strpos($host, ']') === false) {
    $parts = explode(':', $host);
    if (count($parts) >= 2) {
        $maybePort = end($parts);
        if ($maybePort !== '' && ctype_digit((string)$maybePort)) {
            if (empty($port)) $port = (string)$maybePort;
            array_pop($parts);
            $host = implode(':', $parts);
        }
    }
}

try {
    $pdo = new PDO(
        "mysql:host=$host;" . (!empty($port) ? "port=$port;" : "") . "dbname=$db;charset=utf8mb4",
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
    // Seguir sin BD: las métricas mostrarán ceros
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

function countUsersActiveRecent($pdo, int $minutes): int {
    $minutes = max(1, min(1440, $minutes));
    try {
        $sql = 'SELECT COUNT(*) AS c FROM users_login WHERE last_seen_at IS NOT NULL AND last_seen_at >= DATE_SUB(NOW(), INTERVAL ' . $minutes . ' MINUTE)';
        $stmt = $pdo->query($sql);
        $row = $stmt->fetch();
        return (int) ($row['c'] ?? 0);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Unknown column') !== false || stripos($msg, "doesn't exist") !== false) {
            error_log('countUsersActiveRecent: ejecuta database/migrations/add_users_login_last_seen_at.sql — ' . $msg);
        } else {
            error_log('countUsersActiveRecent: ' . $msg);
        }
        return 0;
    }
}

// Función para obtener métricas de la base de datos
function obtenerMetricasBD($pdo, int $activeWindowMinutes = 15) {
    global $dbConnectionHealthy;
    $metricas = [];

    // Verificar salud de la conexión PRIMERO, de forma independiente a las queries de negocio
    $dbConnectionHealthy = verificarSaludBD($pdo);

    if ($dbConnectionHealthy === 0) {
        return [];
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

    // Usuarios con actividad HTTP persistida (BD), ventana configurable
    $metricas['app_users_active'] = countUsersActiveRecent($pdo, $activeWindowMinutes);

    return $metricas;
}

// Función para obtener la ruta del directorio de logs
function obtenerDirectorioLogs() {
    $possiblePaths = [
        __DIR__ . '/logs',                    // Desarrollo local (monitoring/php-exporter/)
        __DIR__ . '/../logs',                 // Docker (/var/www/html/)
        dirname(__DIR__) . '/logs',           // Ruta alternativa
        '/var/www/html/logs'                  // Ruta absoluta típica en Docker
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
        $handle = fopen($logFile, 'r');
        if ($handle === false) {
            error_log("No se pudo abrir el archivo de métricas: " . $logFile);
            return $metricas;
        }
        
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parsed = null;
            if (function_exists('traffic_simulator_parse_metrics_log_line')) {
                $parsed = traffic_simulator_parse_metrics_log_line($line);
            } else {
                $work   = $line;
                $srcTag = 'legacy';
                if (preg_match('/\s+source=(app|simulator)\s*$/', $work, $sm)) {
                    $srcTag = $sm[1];
                    $work   = trim(substr($work, 0, -strlen($sm[0])));
                }
                if (preg_match('/^(GET|POST|PUT|DELETE|PATCH)\s+(\d{3})(?:\s+(\S+))?$/', $work, $m)) {
                    $path = isset($m[3]) && $m[3] !== '' ? $m[3] : null;
                    $src  = $srcTag === 'legacy'
                        ? (($path !== null && $path !== '') ? 'simulator' : 'app')
                        : $srcTag;
                    $parsed = [
                        'method' => $m[1],
                        'status' => (int) $m[2],
                        'source' => $src,
                    ];
                }
            }
            if ($parsed === null) {
                continue;
            }
            $method = $parsed['method'];
            $status = (string) $parsed['status'];
            $source = $parsed['source'];
            $key = 'app_http_requests_total{method="' . $method . '",status="' . $status . '",source="' . $source . '"}';

            if (!isset($metricas[$key])) {
                $metricas[$key] = 0;
            }
            $metricas[$key]++;
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
$usersActiveWindowMin = max(1, min(1440, (int)(getenv('APP_USERS_ACTIVE_WINDOW_MINUTES') ?: 15)));
try {
    if ($pdo !== null) {
        $metricasBD = obtenerMetricasBD($pdo, $usersActiveWindowMin);
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

echo "# HELP app_users_active Usuarios distintos con last_seen_at en BD dentro de la ventana (actividad HTTP registrada por la app)\n";
echo "# TYPE app_users_active gauge\n";
echo 'app_users_active{window_minutes="' . $usersActiveWindowMin . '"} ' . ($metricasBD['app_users_active'] ?? 0) . "\n\n";

echo "# HELP app_http_requests_total Total de requests HTTP\n";
echo "# TYPE app_http_requests_total counter\n";
if (empty($metricasHTTP)) {
    // Métrica vacía para que Prometheus descubra la serie con etiqueta source
    echo 'app_http_requests_total{method="GET",status="200",source="app"} 0' . "\n";
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
