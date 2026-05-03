<?php
/**
 * HTTP control plane for traffic-simulator container (php -S router).
 * Endpoints: GET /health (no auth), GET /status, POST /start, POST /stop
 * Header: X-Simulator-Token: <token> (must match SIMULATOR_CONTROL_TOKEN env)
 */

declare(strict_types=1);

$bundleLib = __DIR__ . '/scripts/traffic_simulator_lib.php';
$repoLib   = dirname(__DIR__, 2) . '/scripts/traffic_simulator_lib.php';
if (is_readable($bundleLib)) {
    require_once $bundleLib;
} elseif (is_readable($repoLib)) {
    require_once $repoLib;
} else {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'traffic_simulator_lib.php not found']);
    exit;
}

$bundleSim = __DIR__ . '/scripts/simulate_traffic.php';
$repoSim   = dirname(__DIR__, 2) . '/scripts/simulate_traffic.php';
$simulateScript = getenv('SIMULATE_SCRIPT_PATH')
    ?: (is_readable($bundleSim) ? $bundleSim : $repoSim);
$pidFile = getenv('SIM_PID_FILE') ?: '/tmp/traffic_simulator_worker.pid';

function tc_send_headers(int $code, string $type = 'application/json'): void
{
    http_response_code($code);
    header('Content-Type: ' . $type);
}

function tc_read_token(): string
{
    $h = $_SERVER['HTTP_X_SIMULATOR_TOKEN'] ?? '';
    if ($h !== '') {
        return (string) $h;
    }
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return '';
}

function tc_expected_token(): string
{
    return (string) (getenv('SIMULATOR_CONTROL_TOKEN') ?: '');
}

function tc_require_auth(): bool
{
    $exp = tc_expected_token();
    if ($exp === '') {
        tc_send_headers(503);
        echo json_encode(['ok' => false, 'error' => 'SIMULATOR_CONTROL_TOKEN not set']);
        return false;
    }
    $got = tc_read_token();
    if (!hash_equals($exp, $got)) {
        tc_send_headers(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        return false;
    }
    return true;
}

function tc_is_pid_running(int $pid): bool
{
    if ($pid <= 0) {
        return false;
    }
    if (PHP_OS_FAMILY === 'Windows') {
        $out = shell_exec('tasklist /FI "PID eq ' . (int) $pid . '" 2>NUL');
        return $out !== null && strpos($out, (string) $pid) !== false;
    }
    exec(sprintf('kill -0 %d 2>/dev/null', $pid), $_, $exit);
    return $exit === 0;
}

function tc_get_running_pid(string $pidFile): int
{
    if (!is_file($pidFile)) {
        return 0;
    }
    $pid = (int) trim((string) file_get_contents($pidFile));
    if ($pid <= 0 || !tc_is_pid_running($pid)) {
        if (is_file($pidFile)) {
            @unlink($pidFile);
        }

        return 0;
    }

    return $pid;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($uri === '/health' && $method === 'GET') {
    tc_send_headers(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($method === 'GET' && $uri === '/status') {
    if (!tc_require_auth()) {
        exit;
    }
    $pid = tc_get_running_pid($pidFile);
    $probeDir    = getenv('SIM_LOG_DIR') ?: '/var/www/html/logs';
    $metricsFile = rtrim($probeDir, '/\\') . '/metrics.log';
    $lineCount   = 0;
    if (is_readable($metricsFile)) {
        $cnt = 0;
        $h   = fopen($metricsFile, 'r');
        if ($h !== false) {
            while (fgets($h) !== false) {
                $cnt++;
            }
            fclose($h);
            $lineCount = $cnt;
        }
    }
    tc_send_headers(200);
    echo json_encode([
        'running' => $pid > 0,
        'pid'     => $pid > 0 ? $pid : null,
        'logs'    => [
            'dir'                  => $probeDir,
            'dir_writable'         => is_dir($probeDir) && is_writable($probeDir),
            'metrics_log_lines'    => $lineCount,
            'metrics_log_readable' => is_readable($metricsFile),
        ],
    ]);
    exit;
}

if ($method === 'POST' && $uri === '/start') {
    if (!tc_require_auth()) {
        exit;
    }
    $pid = tc_get_running_pid($pidFile);
    if ($pid > 0) {
        tc_send_headers(409);
        echo json_encode(['ok' => false, 'error' => 'Simulation already running']);
        exit;
    }

    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = [];
    }

    $users = max(1, min(20, (int) ($body['users'] ?? (getenv('SIM_USERS') ?: 3))));
    $duration = max(5, min(300, (int) ($body['duration'] ?? (getenv('SIM_DURATION') ?: 60))));
    $profile = in_array($body['profile'] ?? '', ['normal', 'burst', 'idle'], true)
        ? $body['profile'] : 'normal';

    $baseUrl = isset($body['base_url']) ? (string) $body['base_url'] : (getenv('SIM_BASE_URL') ?: 'http://web');
    $confirmExternal = !empty($body['confirm_external']);
    $tv = traffic_simulator_validate_target_url($baseUrl, [
        'confirm_external' => $confirmExternal,
        'trusted_cli'      => false,
    ]);
    if (!$tv['ok']) {
        if (($tv['code'] ?? '') === 'NEED_CONFIRM_EXTERNAL') {
            tc_send_headers(428);
            echo json_encode([
                'ok'    => false,
                'error' => $tv['message'] ?? 'Confirmation required',
                'code'  => 'NEED_CONFIRM_EXTERNAL',
            ]);
            exit;
        }
        tc_send_headers(400);
        echo json_encode(['ok' => false, 'error' => $tv['message'] ?? 'Invalid base_url']);

        exit;
    }
    $baseUrl = $tv['base'] ?? '';

    $routesFile = isset($body['routes_file']) ? (string) $body['routes_file'] : (getenv('SIM_ROUTES_FILE') ?: '');
    $logsDir = getenv('SIM_LOG_DIR') ?: '/var/www/html/logs';

    $php = PHP_BINARY;
    $cmd = sprintf(
        '%s %s --users=%d --duration=%d --profile=%s --base-url=%s',
        escapeshellarg($php),
        escapeshellarg($simulateScript),
        $users,
        $duration,
        escapeshellarg((string) $profile),
        escapeshellarg($baseUrl)
    );
    if ($routesFile !== '') {
        $cmd .= ' --routes-file=' . escapeshellarg($routesFile);
    }

    if (PHP_OS_FAMILY === 'Windows') {
        tc_send_headers(500);
        echo json_encode(['ok' => false, 'error' => 'Container mode not supported on Windows host']);
        exit;
    }

    $workDir = dirname($simulateScript);
    // SIM_LOG_DIR applies only to php process; background then echo $!
    $full = 'cd ' . escapeshellarg($workDir)
        . ' && SIM_LOG_DIR=' . escapeshellarg($logsDir)
        . ' ' . $cmd . ' > /tmp/traffic_simulator.log 2>&1 & echo $!';
    $newPid = (int) shell_exec($full);
    if ($newPid <= 0) {
        tc_send_headers(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to start worker']);
        exit;
    }
    file_put_contents($pidFile, (string) $newPid);

    tc_send_headers(200);
    echo json_encode([
        'ok'       => true,
        'pid'      => $newPid,
        'users'    => $users,
        'duration' => $duration,
        'profile'  => $profile,
        'base_url' => $baseUrl,
    ]);
    exit;
}

if ($method === 'POST' && $uri === '/stop') {
    if (!tc_require_auth()) {
        exit;
    }
    if (!is_file($pidFile)) {
        tc_send_headers(200);
        echo json_encode(['ok' => true, 'message' => 'Not running']);
        exit;
    }
    $pid = (int) file_get_contents($pidFile);
    @unlink($pidFile);
    if ($pid > 0) {
        if (PHP_OS_FAMILY === 'Windows') {
            shell_exec('taskkill /PID ' . $pid . ' /F 2>NUL');
        } else {
            shell_exec('kill ' . $pid . ' 2>/dev/null');
        }
    }
    tc_send_headers(200);
    echo json_encode(['ok' => true, 'message' => 'Stopped']);
    exit;
}

tc_send_headers(404);
echo json_encode(['error' => 'Not found']);
