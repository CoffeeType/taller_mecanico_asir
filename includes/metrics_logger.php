<?php
/**
 * Sistema de logging de métricas para Prometheus
 * Captura métricas HTTP (método, status, tiempo de respuesta)
 */

/**
 * Obtiene la ruta del directorio de logs
 * Funciona tanto en Docker como en desarrollo local
 */
function getLogsDirectory() {
    $possiblePaths = [
        __DIR__ . '/../logs',
        dirname(__DIR__) . '/logs',
        '/var/www/html/logs',
        __DIR__ . '/logs'
    ];
    
    foreach ($possiblePaths as $path) {
        if (is_dir($path) && is_writable($path)) {
            return $path;
        }
    }
    
    // Si no existe, intentar crear el directorio
    $defaultPath = dirname(__DIR__) . '/logs';
    if (!is_dir($defaultPath)) {
        @mkdir($defaultPath, 0755, true);
    }
    
    return $defaultPath;
}

/**
 * Inicia la medición del tiempo de respuesta
 * Debe llamarse al inicio del request (en header.php)
 */
function startResponseTimeMeasurement() {
    if (!isset($_SERVER['REQUEST_START_TIME'])) {
        $_SERVER['REQUEST_START_TIME'] = microtime(true);
    }
}

/**
 * Obtiene el tiempo transcurrido desde el inicio del request
 * @return float Tiempo en segundos
 */
function getElapsedTime() {
    if (!isset($_SERVER['REQUEST_START_TIME'])) {
        return 0.0;
    }
    return microtime(true) - $_SERVER['REQUEST_START_TIME'];
}

/**
 * Escribe una entrada en el log de métricas HTTP
 * @param string $method Método HTTP (GET, POST, etc.)
 * @param int $status Código de estado HTTP
 */
function logHttpRequest($method, $status) {
    $logsDir = getLogsDirectory();
    $logFile = $logsDir . '/metrics.log';
    
    // Formato: METHOD STATUS
    $logEntry = $method . ' ' . $status . "\n";
    
    // Escribir de forma atómica (append)
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Escribe el tiempo de respuesta en el log
 * @param float $responseTime Tiempo en segundos
 */
function logResponseTime($responseTime) {
    $logsDir = getLogsDirectory();
    $logFile = $logsDir . '/response_time.log';
    
    // Escribir solo el número (una línea por request)
    $logEntry = number_format($responseTime, 6, '.', '') . "\n";
    
    // Escribir de forma atómica (append)
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Limpia logs antiguos para evitar que crezcan indefinidamente
 * Mantiene solo las últimas N líneas
 * @param int $maxLines Número máximo de líneas a mantener
 */
function rotateLogs($maxLines = 10000) {
    $logsDir = getLogsDirectory();
    $files = ['metrics.log', 'response_time.log'];
    
    foreach ($files as $file) {
        $filePath = $logsDir . '/' . $file;
        if (!file_exists($filePath)) {
            continue;
        }
        
        // Solo rotar si el archivo es muy grande (> 5MB)
        if (filesize($filePath) > 5 * 1024 * 1024) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (count($lines) > $maxLines) {
                // Mantener solo las últimas N líneas
                $lines = array_slice($lines, -$maxLines);
                file_put_contents($filePath, implode("\n", $lines) . "\n", LOCK_EX);
            }
        }
    }
}

/**
 * Registra las métricas del request actual
 * Debe llamarse al final del request (en footer.php)
 */
function logCurrentRequestMetrics() {
    // Obtener método HTTP
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Obtener código de estado HTTP
    // Si no se ha establecido explícitamente, asumir 200
    $status = http_response_code();
    if ($status === false) {
        $status = 200;
    }
    
    // Obtener tiempo de respuesta
    $responseTime = getElapsedTime();
    
    // Loggear métricas
    logHttpRequest($method, $status);
    logResponseTime($responseTime);
    
    // Rotar logs si es necesario (solo ocasionalmente para no afectar performance)
    // Ejecutar rotación solo en 1 de cada 100 requests aproximadamente
    if (rand(1, 100) === 1) {
        rotateLogs();
    }
}
