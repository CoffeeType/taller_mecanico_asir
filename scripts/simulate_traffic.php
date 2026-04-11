<?php
/**
 * simulate_traffic.php - Traffic Simulator CLI Script
 *
 * Simulates user traffic by making real HTTP requests to the website
 * and appending to the metrics log files that Prometheus reads.
 *
 * Usage:
 *   php scripts/simulate_traffic.php [--users=N] [--duration=S] [--profile=normal|burst|idle]
 *
 * Environment variables:
 *   SIM_BASE_URL  - Base URL to hit (default: http://localhost:8081)
 */

// ====== Parse CLI arguments ======
$options = getopt('', ['users::', 'duration::', 'profile::', 'base-url::']);
$users     = max(1, (int)($options['users']    ?? 3));
$duration  = max(1, (int)($options['duration'] ?? 60));
$profile   = $options['profile']  ?? 'normal';
$baseUrl   = $options['base-url'] ?? (getenv('SIM_BASE_URL') ?: 'http://localhost:8081');

// Strip trailing slash
$baseUrl = rtrim($baseUrl, '/');

// ====== Resolve log directory ======
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}
$metricsLog      = $logsDir . '/metrics.log';
$responseTimeLog = $logsDir . '/response_time.log';

// ====== Define site pages with weights (higher = more likely to be hit) ======
$pages = [
    ['path' => '/',               'method' => 'GET',  'weight' => 30],
    ['path' => '/index.php',      'method' => 'GET',  'weight' => 25],
    ['path' => '/noticias.php',   'method' => 'GET',  'weight' => 20],
    ['path' => '/consejo.php',    'method' => 'GET',  'weight' => 15],
    ['path' => '/citaciones.php', 'method' => 'GET',  'weight' => 15],
    ['path' => '/login.php',      'method' => 'GET',  'weight' => 10],
    ['path' => '/registro.php',   'method' => 'GET',  'weight' => 8],
    ['path' => '/perfil.php',     'method' => 'GET',  'weight' => 5],
];

// Build weighted array
$weightedPages = [];
foreach ($pages as $page) {
    for ($i = 0; $i < $page['weight']; $i++) {
        $weightedPages[] = $page;
    }
}

// ====== Profile settings ======
$profiles = [
    'normal' => ['min_sleep_ms' => 300, 'max_sleep_ms' => 1500, 'error_rate' => 0.03],
    'burst'  => ['min_sleep_ms' => 50,  'max_sleep_ms' => 200,  'error_rate' => 0.05],
    'idle'   => ['min_sleep_ms' => 2000,'max_sleep_ms' => 5000, 'error_rate' => 0.01],
];
$config = $profiles[$profile] ?? $profiles['normal'];

// ====== Helper: make HTTP request via curl ======
function makeRequest(string $url, string $method = 'GET'): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => 'TrafficSimulator/1.0 (monitoring)',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, []);
    }

    $start    = microtime(true);
    curl_exec($ch);
    $elapsed  = microtime(true) - $start;
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // If curl completely failed, treat as 503
    if ($httpCode === 0) {
        $httpCode = 503;
    }

    return ['method' => $method, 'status' => $httpCode, 'time' => $elapsed];
}

// ====== Helper: write to log files ======
function appendLog(string $metricsLog, string $responseTimeLog, array $result, string $path): void
{
    // Keep "METHOD CODE" at the beginning for Prometheus parsing; include path for debugging.
    file_put_contents($metricsLog,      $result['method'] . ' ' . $result['status'] . ' ' . $path . "\n", FILE_APPEND | LOCK_EX);
    file_put_contents($responseTimeLog, round($result['time'], 4) . "\n",                   FILE_APPEND | LOCK_EX);
}

// ====== Main simulation loop ======
$startTime    = time();
$endTime      = $startTime + $duration;
$requestCount = 0;

echo "[TrafficSim] Starting simulation\n";
echo "[TrafficSim] Profile  : {$profile}\n";
echo "[TrafficSim] Users    : {$users}\n";
echo "[TrafficSim] Duration : {$duration}s\n";
echo "[TrafficSim] Base URL : {$baseUrl}\n";
echo "[TrafficSim] Logs dir : {$logsDir}\n";
echo str_repeat('-', 50) . "\n";

while (time() < $endTime) {
    // Each "virtual user" makes one request per iteration
    for ($u = 0; $u < $users; $u++) {
        if (time() >= $endTime) break;

        // Pick a random page (weighted)
        $page   = $weightedPages[array_rand($weightedPages)];

        // Occasionally generate a 404 to validate dashboards/alerts (controlled by profile error_rate).
        if (mt_rand() / mt_getrandmax() < ($config['error_rate'] ?? 0)) {
            $page = ['path' => '/does-not-exist', 'method' => 'GET'];
        }

        $url    = $baseUrl . $page['path'];
        $method = $page['method'];

        $result = makeRequest($url, $method);
        appendLog($metricsLog, $responseTimeLog, $result, $page['path']);

        $requestCount++;
        echo sprintf(
            "[TrafficSim] User%d | %s %s -> HTTP %d (%.3fs)\n",
            $u + 1,
            $result['method'],
            $page['path'],
            $result['status'],
            $result['time']
        );

        // Inter-request sleep (milliseconds)
        $sleepMs = rand($config['min_sleep_ms'], $config['max_sleep_ms']);
        usleep($sleepMs * 1000);
    }
}

echo str_repeat('-', 50) . "\n";
echo "[TrafficSim] Done. Total requests: {$requestCount}\n";
echo "[TrafficSim] Metrics written to: {$metricsLog}\n";
