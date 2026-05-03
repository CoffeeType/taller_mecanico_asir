<?php
/**
 * JSON API for simulator UI — proxies to traffic-simulator control plane (token server-side).
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once '/opt/inc/traffic_simulator_lib.php';

$controlBase = rtrim(getenv('SIMULATOR_CONTAINER_URL') ?: 'http://traffic-simulator:8085', '/');
$token       = getenv('SIMULATOR_CONTROL_TOKEN') ?: '';

// Misma ruta que el volumen ./logs → /var/www/html/logs (no usar __DIR__/../logs → /var/logs).
$logsDirRaw = getenv('SIM_LOG_DIR');
$logsDir    = ($logsDirRaw !== false && $logsDirRaw !== '') ? $logsDirRaw : (__DIR__ . '/logs');
$logsDir    = rtrim($logsDir, '/\\');
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0775, true);
}

$metricsLog      = $logsDir . '/metrics.log';
$responseTimeLog = $logsDir . '/response_time.log';

/** @return array{logs_dir:string,metrics_log:string,logs_dir_exists:bool,logs_dir_writable:bool,metrics_log_readable:bool,metrics_log_size:int} */
function simulator_ui_logs_diagnostics(string $logsDir, string $metricsLog): array
{
    $size = (is_file($metricsLog) && is_readable($metricsLog)) ? (int) filesize($metricsLog) : 0;

    return [
        'logs_dir'             => $logsDir,
        'metrics_log'          => $metricsLog,
        'logs_dir_exists'      => is_dir($logsDir),
        'logs_dir_writable'    => is_dir($logsDir) && is_writable($logsDir),
        'metrics_log_readable' => is_readable($metricsLog),
        'metrics_log_size'     => $size,
    ];
}

/**
 * @return array{ok:bool, data?:mixed, http_code?:int, error?:string}
 */
function simulator_ui_control_request(string $method, string $path, ?array $body, string $base, string $token): array
{
    $url     = $base . $path;
    $headers = [
        'Content-Type: application/json',
        'X-Simulator-Token: ' . $token,
    ];
    $opts = [
        'http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'timeout'       => 20,
            'ignore_errors' => true,
        ],
    ];
    if ($body !== null) {
        $opts['http']['content'] = json_encode($body);
    }
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (!empty($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $h, $m)) {
                $code = (int) $m[1];

                break;
            }
        }
    }
    if ($raw === false) {
        return ['ok' => false, 'error' => 'No se pudo conectar al API de control. ¿traffic-simulator levantado?'];
    }
    $data = json_decode($raw, true);
    if ($code >= 400) {
        $msg = is_array($data) && isset($data['error']) ? (string) $data['error'] : $raw;

        return ['ok' => false, 'http_code' => $code, 'error' => $msg, 'data' => $data];
    }

    return ['ok' => true, 'data' => $data, 'http_code' => $code];
}

/** @return array{running:bool, raw?:array{ok:bool, data?:mixed, http_code?:int, error?:string}, detail?:array<string,mixed>} */
function simulator_ui_running_cached(string $base, string $token): array
{
    $r = simulator_ui_control_request('GET', '/status', null, $base, $token);
    if (!$r['ok'] || !is_array($r['data'])) {
        return ['running' => false, 'raw' => $r];
    }

    return ['running' => !empty($r['data']['running']), 'detail' => $r['data']];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'log_preview') {
        $lines = [];
        $total = 0;
        if (file_exists($metricsLog)) {
            $all   = file($metricsLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $total = is_array($all) ? count($all) : 0;
            $lines = is_array($all) ? array_slice($all, -20) : [];
        }

        echo json_encode(['lines' => $lines, 'total' => $total]);

        exit;
    }

    if ($token === '') {
        http_response_code(503);
        $st = traffic_simulator_read_log_stats($metricsLog, $responseTimeLog);

        echo json_encode([
            'running'           => false,
            'stats'             => $st,
            'default_base_url'  => getenv('SIM_UI_DEFAULT_BASE_URL') ?: 'http://web',
            'monitoring'        => [
                'prometheus' => getenv('PROMETHEUS_EXTERNAL_URL') ?: '',
                'grafana'    => getenv('GRAFANA_EXTERNAL_URL') ?: '',
            ],
            'error'             => 'SIMULATOR_CONTROL_TOKEN no configurado en traffic-simulator-ui',
            'diagnostics'       => simulator_ui_logs_diagnostics($logsDir, $metricsLog),
        ]);

        exit;
    }

    $runningInfo = simulator_ui_running_cached($controlBase, $token);
    $run         = !empty($runningInfo['running']);
    $st          = traffic_simulator_read_log_stats($metricsLog, $responseTimeLog);
    $diag        = simulator_ui_logs_diagnostics($logsDir, $metricsLog);

    echo json_encode([
        'running'          => $run,
        'stats'           => $st,
        'default_base_url'=> getenv('SIM_UI_DEFAULT_BASE_URL') ?: 'http://web',
        'monitoring'       => [
            'prometheus' => getenv('PROMETHEUS_EXTERNAL_URL') ?: '',
            'grafana'    => getenv('GRAFANA_EXTERNAL_URL') ?: '',
        ],
        'diagnostics'      => $diag,
        'worker_detail'    => $runningInfo['detail'] ?? null,
    ]);

    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);

    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);

    exit;
}

$action = isset($input['action']) ? (string) $input['action'] : '';

if ($token === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'SIMULATOR_CONTROL_TOKEN vacío']);

    exit;
}

switch ($action) {

    case 'probe':
        $baseUrlRaw = isset($input['base_url']) && trim((string) $input['base_url']) !== ''
            ? (string) $input['base_url']
            : (getenv('SIM_UI_DEFAULT_BASE_URL') ?: 'http://web');
        $confirmUi = !empty($input['confirm_external']);
        $chk       = traffic_simulator_validate_target_url($baseUrlRaw, [
            'confirm_external' => $confirmUi,
            'trusted_cli'      => false,
        ]);
        if (!$chk['ok']) {
            if (($chk['code'] ?? '') === 'NEED_CONFIRM_EXTERNAL') {
                http_response_code(428);
                echo json_encode([
                    'success' => false,
                    'code'    => 'NEED_CONFIRM_EXTERNAL',
                    'message' => $chk['message'] ?? 'Confirmation required',
                ]);

                exit;
            }

            echo json_encode([
                'success'   => false,
                'reachable' => false,
                'http_code' => 0,
                'message'   => $chk['message'] ?? 'URL inválida',
            ]);

            exit;
        }

        $pr = traffic_simulator_probe_base_url($baseUrlRaw, [
            'confirm_external' => $confirmUi,
            'trusted_cli'        => false,
            'timeout'            => max(3, min(20, (int) ($input['timeout'] ?? 8))),
        ]);

        echo json_encode([
            'success'   => $pr['ok'],
            'reachable' => $pr['reachable'],
            'http_code' => $pr['http_code'],
            'message'   => $pr['message'],
        ]);

        exit;

    case 'start':
        $users    = max(1, min(20, (int) ($input['users'] ?? 3)));
        $duration = max(5, min(300, (int) ($input['duration'] ?? 60)));
        $profile  = in_array($input['profile'] ?? '', ['normal', 'burst', 'idle'], true)
            ? $input['profile'] : 'normal';
        $baseUrlRaw = isset($input['base_url']) && trim((string) $input['base_url']) !== ''
            ? (string) $input['base_url']
            : (getenv('SIM_UI_DEFAULT_BASE_URL') ?: 'http://web');

        $confirmUi = !empty($input['confirm_external']);
        $chk       = traffic_simulator_validate_target_url($baseUrlRaw, [
            'confirm_external' => $confirmUi,
            'trusted_cli'      => false,
        ]);
        if (!$chk['ok']) {
            if (($chk['code'] ?? '') === 'NEED_CONFIRM_EXTERNAL') {
                http_response_code(428);
                echo json_encode([
                    'success' => false,
                    'code'    => 'NEED_CONFIRM_EXTERNAL',
                    'message' => $chk['message'] ?? 'Confirmation required',
                ]);

                exit;
            }

            echo json_encode(['success' => false, 'message' => $chk['message'] ?? 'URL inválida']);

            exit;
        }

        $baseUrl = $chk['base'] ?? '';

        $body = [
            'users'            => $users,
            'duration'         => $duration,
            'profile'          => $profile,
            'base_url'         => $baseUrl,
            'confirm_external' => $confirmUi,
        ];
        if (!empty($input['routes_file'])) {
            $body['routes_file'] = (string) $input['routes_file'];
        }

        $r = simulator_ui_control_request('POST', '/start', $body, $controlBase, $token);
        if (!$r['ok']) {
            $code = isset($r['http_code']) && (int) $r['http_code'] === 428 ? 428 : 502;
            if (isset($r['http_code'])) {
                http_response_code($code === 428 ? 428 : 502);
            } else {
                http_response_code(502);
            }
            $resp = ['success' => false, 'message' => $r['error'] ?? 'Fallo start'];
            if (isset($r['data']) && is_array($r['data'])) {
                $resp['detail'] = $r['data'];
            }

            echo json_encode($resp);

            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => "Simulación iniciada: {$users} usuarios · {$duration}s · {$profile}",
            'detail'  => $r['data'] ?? [],
        ]);

        exit;

    case 'stop':
        $r = simulator_ui_control_request('POST', '/stop', [], $controlBase, $token);
        echo json_encode([
            'success' => $r['ok'],
            'message' => $r['ok'] ? ($r['data']['message'] ?? 'Parado') : ($r['error'] ?? 'Error stop'),
            'detail'  => $r['data'] ?? null,
        ]);

        exit;

    case 'reset':
        $chk = simulator_ui_running_cached($controlBase, $token);
        if (($chk['running'] ?? false) === true) {
            echo json_encode(['success' => false, 'message' => 'Detén la simulación antes de resetear']);

            exit;
        }

        file_put_contents($metricsLog, '');
        file_put_contents($responseTimeLog, '');

        echo json_encode(['success' => true, 'message' => 'Logs reiniciados']);

        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);

        exit;
}
