<?php
/**
 * api/simulate.php - Traffic Simulator API Endpoint
 *
 * JSON API used by the admin/traffic-simulator.php page.
 *
 * GET  /api/simulate.php                  -> { running, stats }
 * POST /api/simulate.php action=start     -> starts the CLI script in background
 * POST /api/simulate.php action=stop      -> kills the running process
 * POST /api/simulate.php action=reset     -> truncates both log files
 */

header('Content-Type: application/json');

// Auth check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// ======= Paths =======
$projectRoot     = dirname(__DIR__);
$logsDir         = $projectRoot . '/logs';
$metricsLog      = $logsDir . '/metrics.log';
$responseTimeLog = $logsDir . '/response_time.log';
$pidFile         = $logsDir . '/simulator.pid';
$cliScript       = $projectRoot . '/scripts/simulate_traffic.php';

// ======= Helpers =======
function isSimulatorRunning(string $pidFile): bool
{
    if (!file_exists($pidFile)) return false;
    $pid = (int)file_get_contents($pidFile);
    if ($pid <= 0) return false;

    if (PHP_OS_FAMILY === 'Windows') {
        // On Windows, check via tasklist
        $output = shell_exec("tasklist /FI \"PID eq {$pid}\" 2>NUL");
        return $output && strpos($output, (string)$pid) !== false;
    } else {
        return posix_kill($pid, 0);
    }
}

function getSimStats(string $metricsLog, string $responseTimeLog): array
{
    $stats = ['total_requests' => 0, 'statuses' => [], 'avg_response_time' => 0];

    if (file_exists($metricsLog)) {
        $lines = file($metricsLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['total_requests'] = count($lines);
        foreach ($lines as $line) {
            if (preg_match('/(\w+)\s+(\d{3})/', $line, $m)) {
                $key = $m[1] . ' ' . $m[2];
                $stats['statuses'][$key] = ($stats['statuses'][$key] ?? 0) + 1;
            }
        }
    }

    if (file_exists($responseTimeLog)) {
        $times = array_filter(
            array_map('floatval', file($responseTimeLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)),
            fn($v) => $v > 0
        );
        if (!empty($times)) {
            $stats['avg_response_time'] = round(array_sum($times) / count($times), 4);
            $stats['max_response_time'] = round(max($times), 4);
        }
    }

    return $stats;
}

// ======= Route =======
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'log_preview') {
        $lines = [];
        $total = 0;
        if (file_exists($metricsLog)) {
            $all   = file($metricsLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $total = count($all);
            $lines = array_slice($all, -20); // last 20 lines
        }
        echo json_encode(['lines' => $lines, 'total' => $total]);
        exit;
    }

    echo json_encode([
        'running' => isSimulatorRunning($pidFile),
        'stats'   => getSimStats($metricsLog, $responseTimeLog),
    ]);
    exit;
}

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? ($_POST['action'] ?? '');

    switch ($action) {

        case 'start':
            if (isSimulatorRunning($pidFile)) {
                echo json_encode(['success' => false, 'message' => 'La simulación ya está en marcha']);
                exit;
            }
            $users    = max(1, min(20, (int)($body['users']    ?? 3)));
            $duration = max(5, min(300, (int)($body['duration'] ?? 60)));
            $profile  = in_array($body['profile'] ?? '', ['normal', 'burst', 'idle'])
                        ? $body['profile'] : 'normal';

            // Determine base URL for the simulator (the CLI script runs on the server side).
            // - Inside Docker, the "web" container listens on port 80, so localhost (no port) is correct.
            // - Outside Docker (local Apache/XAMPP), use the same origin as the current request.
            // - Allow overriding via SIM_BASE_URL.
            $baseUrl = getenv('SIM_BASE_URL');
            if (!$baseUrl) {
                $inDocker = file_exists('/.dockerenv') || (getenv('RUNNING_IN_DOCKER') === '1');
                if ($inDocker) {
                    $baseUrl = 'http://localhost';
                } else {
                    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                    $scheme = $https ? 'https' : 'http';
                    $host   = $_SERVER['HTTP_HOST'] ?? ('localhost:' . (getenv('WEB_PORT') ?: '8081'));
                    $baseUrl = $scheme . '://' . $host;
                }
            }

            $cmd = sprintf(
                'php %s --users=%d --duration=%d --profile=%s --base-url=%s',
                escapeshellarg($cliScript),
                $users,
                $duration,
                escapeshellarg($profile),
                escapeshellarg($baseUrl)
            );

            if (PHP_OS_FAMILY === 'Windows') {
                // Windows: use start /B to run in background
                $fullCmd = "start /B {$cmd} > NUL 2>&1";
                pclose(popen($fullCmd, 'r'));
                // We can't easily get a PID on Windows this way, so we write a placeholder
                file_put_contents($pidFile, '1');
            } else {
                $fullCmd = "{$cmd} > /dev/null 2>&1 & echo $!";
                $pid = (int)shell_exec($fullCmd);
                file_put_contents($pidFile, $pid);
            }

            echo json_encode([
                'success' => true,
                'message' => "Simulación iniciada: {$users} usuarios, {$duration}s, perfil {$profile}",
                'base_url' => $baseUrl,
            ]);
            break;

        case 'stop':
            if (!file_exists($pidFile)) {
                echo json_encode(['success' => false, 'message' => 'No hay simulación en ejecución']);
                exit;
            }
            $pid = (int)file_get_contents($pidFile);
            @unlink($pidFile);
            if ($pid > 0) {
                if (PHP_OS_FAMILY === 'Windows') {
                    shell_exec("taskkill /PID {$pid} /F 2>NUL");
                } else {
                    shell_exec("kill {$pid} 2>/dev/null");
                }
            }
            echo json_encode(['success' => true, 'message' => 'Simulación detenida']);
            break;

        case 'reset':
            if (isSimulatorRunning($pidFile)) {
                echo json_encode(['success' => false, 'message' => 'Detén la simulación antes de resetear los logs']);
                exit;
            }
            file_put_contents($metricsLog, '');
            file_put_contents($responseTimeLog, '');
            echo json_encode(['success' => true, 'message' => 'Logs reseteados correctamente']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción desconocida: ' . htmlspecialchars($action)]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method Not Allowed']);
