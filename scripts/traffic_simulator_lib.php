<?php
/**
 * traffic_simulator_lib.php - Shared logic for traffic simulation (CLI + tests).
 */

declare(strict_types=1);

/** Default pages for taller mecánico app (path, method, weight). */
function traffic_simulator_default_pages(): array
{
    return [
        ['path' => '/',               'method' => 'GET',  'weight' => 30],
        ['path' => '/index.php',      'method' => 'GET',  'weight' => 25],
        ['path' => '/noticias.php',   'method' => 'GET',  'weight' => 20],
        ['path' => '/consejo.php',    'method' => 'GET',  'weight' => 15],
        ['path' => '/citaciones.php', 'method' => 'GET',  'weight' => 15],
        ['path' => '/login.php',      'method' => 'GET',  'weight' => 10],
        ['path' => '/registro.php',   'method' => 'GET',  'weight' => 8],
        ['path' => '/perfil.php',     'method' => 'GET',  'weight' => 5],
    ];
}

function traffic_simulator_profile_config(): array
{
    return [
        'normal' => ['min_sleep_ms' => 300, 'max_sleep_ms' => 1500, 'error_rate' => 0.03],
        'burst'  => ['min_sleep_ms' => 50,  'max_sleep_ms' => 200,  'error_rate' => 0.05],
        'idle'   => ['min_sleep_ms' => 2000, 'max_sleep_ms' => 5000, 'error_rate' => 0.01],
    ];
}

/**
 * If user omits scheme, infer http for Docker/lab hosts and https for typical public URLs.
 *
 * Call before parse_url / validation.
 */
function traffic_simulator_infer_scheme_for_schemeless_url(string $bare): string
{
    $split = preg_split('~[/?#]~', $bare, 2);
    /** @var string $head */
    $head       = ($split !== false && isset($split[0])) ? $split[0] : $bare;
    $headLower  = strtolower($head);
    if (str_starts_with($headLower, 'localhost') || str_starts_with($headLower, '127.')) {
        return 'http';
    }
    if ($head !== '' && str_starts_with($head, '[')) {
        return 'http';
    }
    if (filter_var($head, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return 'http';
    }
    foreach (traffic_simulator_internal_hosts_list() as $h) {
        if ($h === '') {
            continue;
        }
        if ($headLower === $h || str_starts_with($headLower, $h . ':')) {
            return 'http';
        }
    }

    return 'https';
}

/**
 * True when a scheme-less URL is likely intentional (omit https:// deliberately).
 */
function traffic_simulator_schemeless_looks_intentional(string $bare): bool
{
    $split = preg_split('~[/?#]~', $bare, 2);
    /** @var string $head */
    $head      = ($split !== false && isset($split[0])) ? $split[0] : $bare;
    $headLower = strtolower($head);

    if (str_starts_with($headLower, 'localhost') || str_starts_with($headLower, '127.')
        || (str_starts_with($head, '[') && str_contains($head, ']'))) {
        return true;
    }
    if (filter_var($head, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return true;
    }

    foreach (traffic_simulator_internal_hosts_list() as $h) {
        if ($h === '') {
            continue;
        }
        if ($headLower === $h || str_starts_with($headLower, $h . ':')) {
            return true;
        }
    }

    if (str_contains($headLower, '.')) {
        return true;
    }

    return false;
}

/**
 * Add http:// or https:// when missing (common UX: "example.com" only).
 */
function traffic_simulator_normalize_base_url_input(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+\-.]*://#iu', $url)) {
        return $url;
    }
    if (!traffic_simulator_schemeless_looks_intentional($url)) {
        return $url;
    }

    return traffic_simulator_infer_scheme_for_schemeless_url($url) . '://' . $url;
}

/**
 * @return array{ok:bool, message?:string, base?:string}
 */
function traffic_simulator_validate_base_url(string $url): array
{
    $url = traffic_simulator_normalize_base_url_input($url);
    if ($url === '') {
        return ['ok' => false, 'message' => 'Empty base URL'];
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return ['ok' => false, 'message' => 'Invalid base URL (need scheme and host)'];
    }
    if (!empty($parts['user']) || isset($parts['pass'])) {
        return ['ok' => false, 'message' => 'URLs with embedded credentials not allowed'];
    }
    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['ok' => false, 'message' => 'Only http and https allowed'];
    }
    $base = rtrim($url, '/');
    return ['ok' => true, 'base' => $base];
}

/**
 * Hostnames/IP treated as lab / Docker internal (no confirmation for external-policy).
 *
 * @return array<int, string>
 */
function traffic_simulator_internal_hosts_list(): array
{
    $raw = getenv('SIM_INTERNAL_HOSTS')
        ?: 'web,localhost,127.0.0.1,::1,mysql,taller_mecanico_web,host.docker.internal';
    $parts = preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
    return array_unique(array_filter(array_map(
        static fn (string $h): string => strtolower(trim($h)),
        is_array($parts) ? $parts : []
    )));
}

/**
 * Normalize host for comparisons (handles [IPv6] form).
 */
function traffic_simulator_normalize_host(string $host): string
{
    $host = strtolower(trim($host));
    if ($host !== '' && str_starts_with($host, '[') && substr($host, -1) === ']') {
        return strtolower(substr($host, 1, -1));
    }
    return $host;
}

/**
 * True when host resolves as literal RFC1918/link-local/metadata etc. blocked unless SIM_ALLOW_PRIVATE_TARGETS=true.
 */
function traffic_simulator_raw_ip_is_blocked_nonpublic(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    return false;
}

function traffic_simulator_is_internal_hostname(string $host): bool
{
    $h = traffic_simulator_normalize_host($host);
    if ($h === '') {
        return false;
    }
    if (in_array($h, traffic_simulator_internal_hosts_list(), true)) {
        return true;
    }

    return false;
}

/**
 * Extra rules for simulator target (SSR/regressions/abuse mitigation).
 *
 * Options:
 *   - confirm_external bool — user acknowledged risk for hosts not internal
 *   - trusted_cli bool — CLI / automation: skip mandatory confirmation
 *   - allow_private bool|null — override getenv SIM_ALLOW_PRIVATE_TARGETS
 *
 * @return array{ok:bool, message?:string, base?:string, code?:string}
 */
function traffic_simulator_validate_target_url(string $url, array $options = []): array
{
    $v = traffic_simulator_validate_base_url($url);
    if (!$v['ok']) {
        return $v;
    }
    /** @var string $base */
    $base = $v['base'] ?? '';
    $parts = parse_url($base);
    if ($parts === false || empty($parts['host'])) {
        return ['ok' => false, 'message' => 'Missing host'];
    }
    $hostRaw = (string) $parts['host'];
    $hostNorm = traffic_simulator_normalize_host($hostRaw);

    $allowPrivate = isset($options['allow_private'])
        ? (bool) $options['allow_private']
        : (getenv('SIM_ALLOW_PRIVATE_TARGETS') === 'true');

    if ($hostNorm !== '' && filter_var($hostNorm, FILTER_VALIDATE_IP)) {
        if (traffic_simulator_raw_ip_is_blocked_nonpublic($hostNorm)) {
            if (!$allowPrivate) {
                return [
                    'ok'      => false,
                    'message' => 'Private/reserved IPs not allowed (set SIM_ALLOW_PRIVATE_TARGETS=true to override)',
                ];
            }

            return $v;
        }

        /** Public numeric IP allowed as external-like (still needs confirmation if policy applies). */
    }

    $externalEnabled = getenv('SIM_EXTERNAL_TARGETS_ENABLED') !== 'false';
    $requireConfirm  = getenv('SIM_REQUIRE_EXTERNAL_CONFIRMATION') !== 'false';
    $trustedCli      = !empty($options['trusted_cli']);
    $confirmed       = !empty($options['confirm_external']);
    $internal        = traffic_simulator_is_internal_hostname($hostNorm);

    if (!$externalEnabled && !$internal) {
        return ['ok' => false, 'message' => 'External targets disabled (SIM_EXTERNAL_TARGETS_ENABLED=false)'];
    }

    if (!$internal && $requireConfirm && !$trustedCli && !$confirmed) {
        return [
            'ok'      => false,
            'message' => 'Confirmation required before sending traffic to hosts outside SIM_INTERNAL_HOSTS',
            'code'    => 'NEED_CONFIRM_EXTERNAL',
        ];
    }

    return $v;
}

/**
 * Parse one line from logs/metrics.log (app + traffic simulator + legacy).
 *
 * Formats:
 *   - GET 200 source=app
 *   - GET 200 /path source=simulator
 *   - Legacy app: GET 200
 *   - Legacy simulator: GET 200 /path  (path present → treated as source simulator)
 *
 * @return array{method:string,status:int,path:?string,source:string}|null
 */
function traffic_simulator_parse_metrics_log_line(string $line): ?array
{
    $line = trim($line);
    if ($line === '') {
        return null;
    }

    $source     = 'legacy';
    $sourcePart = '';
    if (preg_match('/\s+source=(app|simulator)\s*$/', $line, $sm)) {
        $source     = $sm[1];
        $sourcePart = $sm[0];
        $line       = trim(substr($line, 0, -strlen($sourcePart)));
    }

    if (!preg_match('/^(GET|POST|PUT|DELETE|PATCH)\s+(\d{3})(?:\s+(\S+))?$/', $line, $m)) {
        return null;
    }

    $method = $m[1];
    $status = (int) $m[2];
    $path   = isset($m[3]) && $m[3] !== '' ? $m[3] : null;

    if ($source === 'legacy') {
        $source = ($path !== null && $path !== '') ? 'simulator' : 'app';
    }

    return [
        'method' => $method,
        'status' => $status,
        'path'   => $path,
        'source' => $source,
    ];
}

/**
 * Aggregate quick stats from simulator log files (same format as former api/simulate.php).
 *
 * @return array{
 *   total_requests:int,
 *   statuses:array<string,int>,
 *   success_requests:int,
 *   error_requests:int,
 *   recent_success:int,
 *   recent_errors:int,
 *   recent_window:int,
 *   avg_response_time:float|int,
 *   max_response_time?:float
 * }
 */
function traffic_simulator_read_log_stats(string $metricsLog, string $responseTimeLog, int $recentTail = 20): array
{
    $stats = [
        'total_requests'     => 0,
        'statuses'           => [],
        'success_requests'   => 0,
        'error_requests'     => 0,
        'recent_success'     => 0,
        'recent_errors'      => 0,
        'recent_window'      => max(0, $recentTail),
        'avg_response_time'  => 0,
    ];

    if (file_exists($metricsLog)) {
        $lines = file($metricsLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            $lines = [];
        }
        $stats['total_requests'] = count($lines);
        $recent                  = array_slice($lines, -$recentTail);

        foreach ($lines as $line) {
            $p = traffic_simulator_parse_metrics_log_line((string) $line);
            if ($p === null) {
                continue;
            }
            $key = $p['method'] . ' ' . $p['status'];
            $stats['statuses'][$key] = ($stats['statuses'][$key] ?? 0) + 1;

            $code = $p['status'];
            if ($code >= 200 && $code < 400) {
                $stats['success_requests']++;
            } elseif ($code >= 400) {
                $stats['error_requests']++;
            }
        }

        foreach ($recent as $line) {
            $p = traffic_simulator_parse_metrics_log_line((string) $line);
            if ($p === null) {
                continue;
            }
            $code = $p['status'];
            if ($code >= 200 && $code < 400) {
                $stats['recent_success']++;
            } elseif ($code >= 400) {
                $stats['recent_errors']++;
            }
        }
    }

    if (file_exists($responseTimeLog)) {
        $times = array_filter(
            array_map('floatval', file($responseTimeLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []),
            static fn ($v) => $v > 0
        );
        if (!empty($times)) {
            $stats['avg_response_time'] = round(array_sum($times) / count($times), 4);
            $stats['max_response_time'] = round(max($times), 4);
        }
    }

    return $stats;
}

/**
 * Load pages from JSON file. Expected: [ {"path": "/", "method": "GET", "weight": 10}, ... ]
 *
 * @return array<int, array{path:string, method:string, weight:int}>|null
 */
function traffic_simulator_load_pages_from_json(string $path): ?array
{
    if (!is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }
    $out = [];
    foreach ($data as $row) {
        if (!is_array($row)) {
            continue;
        }
        $p = isset($row['path']) ? (string) $row['path'] : '';
        $m = strtoupper((string) ($row['method'] ?? 'GET'));
        $w = (int) ($row['weight'] ?? 1);
        if ($p === '' || $w < 1) {
            continue;
        }
        if (!in_array($m, ['GET', 'POST'], true)) {
            $m = 'GET';
        }
        $out[] = ['path' => $p, 'method' => $m, 'weight' => min(100, max(1, $w))];
    }
    return $out === [] ? null : $out;
}

/**
 * Build weighted list for random picks.
 *
 * @param array<int, array{path:string, method:string, weight:int}> $pages
 * @return array<int, array{path:string, method:string}>
 */
function traffic_simulator_build_weighted(array $pages): array
{
    $weighted = [];
    foreach ($pages as $page) {
        for ($i = 0; $i < $page['weight']; $i++) {
            $weighted[] = ['path' => $page['path'], 'method' => $page['method']];
        }
    }
    return $weighted;
}

/**
 * @param array<string, mixed> $options getopt long options
 * @return array{
 *   users:int,
 *   duration:int,
 *   profile:string,
 *   base_url:string,
 *   logs_dir:string,
 *   curl_timeout:int,
 *   connect_timeout:int,
 *   ssl_verify_peer:bool,
 *   target_name:string,
 *   pages: array<int, array{path:string, method:string, weight:int}>
 * }|array{error:string}
 */
function traffic_simulator_resolve_config(array $options): array
{
    $profiles = traffic_simulator_profile_config();
    $maxUsers = (int) (getenv('SIM_MAX_USERS') ?: 20);
    $minUsers = max(1, (int) (getenv('SIM_MIN_USERS') ?: 1));
    $maxDur   = (int) (getenv('SIM_MAX_DURATION') ?: 300);
    $minDur   = max(1, (int) (getenv('SIM_MIN_DURATION') ?: 5));

    $users = isset($options['users']) ? (int) $options['users'] : (int) (getenv('SIM_USERS') ?: 3);
    $users = max($minUsers, min($maxUsers, $users));

    $duration = isset($options['duration']) ? (int) $options['duration'] : (int) (getenv('SIM_DURATION') ?: 60);
    $duration = max($minDur, min($maxDur, $duration));

    $profile = $options['profile'] ?? (getenv('SIM_PROFILE') ?: 'normal');
    $profile = in_array($profile, ['normal', 'burst', 'idle'], true) ? $profile : 'normal';

    $baseUrl = $options['base-url']
        ?? (getenv('SIM_BASE_URL') ?: 'http://localhost:8081');

    /** CLI ejecutado como operador fiable → no exige checkbox de URL externas. */
    $v = traffic_simulator_validate_target_url($baseUrl, [
        'trusted_cli'      => true,
        'confirm_external' => false,
    ]);
    if (!$v['ok']) {
        return ['error' => $v['message'] ?? 'Invalid URL'];
    }
    $baseUrl = $v['base'] ?? '';

    $logsDir = getenv('SIM_LOG_DIR') ?: (__DIR__ . '/../logs');
    $logsDir = rtrim($logsDir, '/\\');

    $curlTimeout = (int) (getenv('SIM_CURL_TIMEOUT') ?: 10);
    $curlTimeout = max(1, min(120, $curlTimeout));
    $connTimeout = (int) (getenv('SIM_CURL_CONNECT_TIMEOUT') ?: 5);
    $connTimeout = max(1, min(60, $connTimeout));

    /** Por defecto verificar HTTPS; desactivar con SIM_SSL_VERIFY=false (solo debug). */
    $sslVerify = getenv('SIM_SSL_VERIFY');
    $sslVerifyPeer = ($sslVerify === false || $sslVerify === '') ? true : strtolower((string) $sslVerify) !== 'false';

    $targetName = (string) (getenv('SIM_TARGET_NAME') ?: 'default');

    $routesFile = $options['routes-file'] ?? getenv('SIM_ROUTES_FILE');
    $pages = traffic_simulator_default_pages();
    if (is_string($routesFile) && $routesFile !== '') {
        $loaded = traffic_simulator_load_pages_from_json($routesFile);
        if ($loaded !== null) {
            $pages = $loaded;
        }
    }

    return [
        'users'           => $users,
        'duration'        => $duration,
        'profile'         => $profile,
        'base_url'        => $baseUrl,
        'logs_dir'        => $logsDir,
        'curl_timeout'    => $curlTimeout,
        'connect_timeout' => $connTimeout,
        'ssl_verify_peer' => $sslVerifyPeer,
        'target_name'     => $targetName,
        'profiles_map'    => $profiles,
        'pages'           => $pages,
    ];
}

/**
 * Comprueba que la URL base responde por HTTP (GET a la raíz). No cuenta peticiones en metrics.log.
 *
 * Options: confirm_external, trusted_cli, timeout (seconds), ssl_verify_peer|null
 *
 * @return array{ok:bool, reachable:bool, http_code:int, message:string, error?:string}
 */
function traffic_simulator_probe_base_url(string $url, array $options = []): array
{
    $confirm = !empty($options['confirm_external']);
    $trusted = !empty($options['trusted_cli']);
    $v       = traffic_simulator_validate_target_url($url, [
        'confirm_external' => $confirm,
        'trusted_cli'        => $trusted,
    ]);
    if (!$v['ok']) {
        $out = [
            'ok'        => false,
            'reachable' => false,
            'http_code' => 0,
            'message'   => $v['message'] ?? 'URL inválida',
        ];
        if (isset($v['code'])) {
            $out['code'] = $v['code'];
        }

        return $out;
    }

    $base      = $v['base'] ?? '';
    $probeUrl  = rtrim($base, '/') . '/';
    $timeout   = max(2, min(30, (int) ($options['timeout'] ?? 8)));
    $sslVerify = array_key_exists('ssl_verify_peer', $options)
        ? (bool) $options['ssl_verify_peer']
        : (getenv('SIM_SSL_VERIFY') === false || getenv('SIM_SSL_VERIFY') === ''
            ? true : strtolower((string) getenv('SIM_SSL_VERIFY')) !== 'false');

    $httpCode = 0;
    $curlErr  = '';

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $probeUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_USERAGENT      => 'TrafficSimulator-UI/1.0 (probe)',
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        ]);
        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 0) {
            $curlErr = (string) curl_error($ch);
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => $timeout,
                'header'        => "User-Agent: TrafficSimulator-UI/1.0 (probe)\r\nAccept: */*\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => $sslVerify,
                'verify_peer_name' => $sslVerify,
            ],
        ]);
        @file_get_contents($probeUrl, false, $ctx);
        $headers = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
        foreach ($headers as $h) {
            if (preg_match('#HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $httpCode = (int) $m[1];

                break;
            }
        }
        if ($httpCode === 0) {
            $curlErr = 'Sin respuesta HTTP (timeout o red)';
        }
    }

    $reachable = $httpCode > 0;
    $ok        = $reachable;

    if (!$reachable) {
        $msg = $curlErr !== '' ? $curlErr : 'No se pudo conectar con el destino';

        return [
            'ok'        => false,
            'reachable' => false,
            'http_code' => 0,
            'message'   => $msg,
            'error'     => $curlErr !== '' ? $curlErr : 'connection_failed',
        ];
    }

    if ($httpCode >= 200 && $httpCode < 400) {
        $message = "Destino disponible (HTTP {$httpCode})";
    } elseif ($httpCode >= 400 && $httpCode < 500) {
        $message = "El servidor responde (HTTP {$httpCode} en /). La URL existe; revisa rutas si esperabas otra página.";
    } else {
        $message = "El servidor responde pero con error HTTP {$httpCode}; la simulación puede registrar muchos fallos.";
    }

    return [
        'ok'        => $ok,
        'reachable' => true,
        'http_code' => $httpCode,
        'message'   => $message,
    ];
}

function traffic_simulator_make_request(
    string $url,
    string $method,
    int $curlTimeout,
    int $connectTimeout,
    string $userAgent = 'TrafficSimulator/1.0 (monitoring)',
    bool $sslVerifyPeer = true
): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => $curlTimeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_USERAGENT      => $userAgent,
        CURLOPT_SSL_VERIFYPEER => $sslVerifyPeer,
        CURLOPT_SSL_VERIFYHOST => $sslVerifyPeer ? 2 : 0,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, []);
    }

    $start    = microtime(true);
    curl_exec($ch);
    $elapsed  = microtime(true) - $start;
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 0) {
        $httpCode = 503;
    }

    return ['method' => $method, 'status' => $httpCode, 'time' => $elapsed];
}

function traffic_simulator_append_log(
    string $metricsLog,
    string $responseTimeLog,
    array $result,
    string $path
): void {
    file_put_contents(
        $metricsLog,
        $result['method'] . ' ' . $result['status'] . ' ' . $path . ' source=simulator' . "\n",
        FILE_APPEND | LOCK_EX
    );
    file_put_contents(
        $responseTimeLog,
        round($result['time'], 4) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Run main loop (used by CLI).
 *
 * @param array{
 *   users:int,
 *   duration:int,
 *   profile:string,
 *   base_url:string,
 *   logs_dir:string,
 *   curl_timeout:int,
 *   connect_timeout:int,
 *   ssl_verify_peer:bool,
 *   target_name:string,
 *   profiles_map:array,
 *   pages: array<int, array{path:string, method:string, weight:int}>
 * } $config
 */
function traffic_simulator_run(array $config, ?callable $logFn = null): int
{
    $logFn = $logFn ?? static function (string $m): void {
        fwrite(STDOUT, $m . "\n");
    };

    $logsDir = $config['logs_dir'];
    if (!is_dir($logsDir)) {
        if (!@mkdir($logsDir, 0755, true) && !is_dir($logsDir)) {
            $logFn("[TrafficSim] ERROR: cannot create logs directory: {$logsDir}");
            return 1;
        }
    }

    $metricsLog      = $logsDir . '/metrics.log';
    $responseTimeLog = $logsDir . '/response_time.log';

    $profiles = $config['profiles_map'];
    $prof     = $config['profile'];
    $pconf    = $profiles[$prof] ?? $profiles['normal'];

    $weighted = traffic_simulator_build_weighted($config['pages']);
    if ($weighted === []) {
        $logFn('[TrafficSim] ERROR: no pages configured');
        return 1;
    }

    $duration = $config['duration'];
    $users    = $config['users'];
    $baseUrl  = $config['base_url'];

    $logFn('[TrafficSim] Starting simulation');
    $logFn('[TrafficSim] Target   : ' . $config['target_name']);
    $logFn('[TrafficSim] Profile  : ' . $prof);
    $logFn('[TrafficSim] Users    : ' . $users);
    $logFn('[TrafficSim] Duration : ' . $duration . 's');
    $logFn('[TrafficSim] Base URL : ' . $baseUrl);
    $logFn('[TrafficSim] Logs dir : ' . $logsDir);
    $logFn(str_repeat('-', 50));

    $startTime = time();
    $endTime   = $startTime + $duration;
    $requestCount = 0;
    $userAgent = 'TrafficSimulator/1.0 (monitoring; target=' . $config['target_name'] . ')';

    while (time() < $endTime) {
        for ($u = 0; $u < $users; $u++) {
            if (time() >= $endTime) {
                break 2;
            }

            $page = $weighted[array_rand($weighted)];

            if (mt_rand() / mt_getrandmax() < (float) ($pconf['error_rate'] ?? 0)) {
                $page = ['path' => '/does-not-exist', 'method' => 'GET'];
            }

            $url    = $baseUrl . $page['path'];
            $method = $page['method'];

            $result = traffic_simulator_make_request(
                $url,
                $method,
                $config['curl_timeout'],
                $config['connect_timeout'],
                $userAgent,
                !empty($config['ssl_verify_peer'])
            );
            traffic_simulator_append_log($metricsLog, $responseTimeLog, $result, $page['path']);
            $requestCount++;

            $logFn(sprintf(
                '[TrafficSim] User%d | %s %s -> HTTP %d (%.3fs)',
                $u + 1,
                $result['method'],
                $page['path'],
                $result['status'],
                $result['time']
            ));

            $sleepMs = rand((int) $pconf['min_sleep_ms'], (int) $pconf['max_sleep_ms']);
            usleep($sleepMs * 1000);
        }
    }

    $logFn(str_repeat('-', 50));
    $logFn('[TrafficSim] Done. Total requests: ' . $requestCount);
    $logFn('[TrafficSim] Metrics written to: ' . $metricsLog);

    return 0;
}
