<?php
/**
 * simulate_traffic.php - Traffic Simulator CLI
 *
 * Usage:
 *   php scripts/simulate_traffic.php [--users=N] [--duration=S] [--profile=normal|burst|idle]
 *        [--base-url=URL] [--routes-file=/path/to/routes.json]
 *
 * Environment: SIM_BASE_URL, SIM_USERS, SIM_DURATION, SIM_PROFILE, SIM_LOG_DIR,
 *   SIM_ROUTES_FILE, SIM_TARGET_NAME, SIM_CURL_TIMEOUT, SIM_CURL_CONNECT_TIMEOUT,
 *   SIM_MAX_USERS, SIM_MIN_DURATION, SIM_MAX_DURATION, etc.
 */

declare(strict_types=1);

require_once __DIR__ . '/traffic_simulator_lib.php';

$options = getopt('', [
    'users::',
    'duration::',
    'profile::',
    'base-url::',
    'routes-file::',
]);

$config = traffic_simulator_resolve_config($options);
if (isset($config['error'])) {
    fwrite(STDERR, '[TrafficSim] ' . $config['error'] . "\n");
    exit(1);
}

exit(traffic_simulator_run($config));
