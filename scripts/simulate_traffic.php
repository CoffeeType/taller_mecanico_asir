<?php
/**
 * simulate_traffic.php — CLI de compatibilidad con el runner Apache JMeter
 *
 * Uso:
 *   php scripts/simulate_traffic.php [--users=N] [--duration=S] [--profile=normal|burst|idle]
 *        [--base-url=URL] [--routes-file=/ruta/a/routes.json]
 *
 * Entorno: SIM_BASE_URL, SIM_USERS, SIM_DURATION, SIM_PROFILE, SIM_LOG_DIR,
 *   SIM_ROUTES_FILE, SIM_TARGET_NAME, SIM_CURL_TIMEOUT, SIM_CURL_CONNECT_TIMEOUT,
 *   SIM_MAX_USERS, SIM_MIN_DURATION, SIM_MAX_DURATION, etc.
 */

declare(strict_types=1);

require __DIR__ . '/run_jmeter_traffic.php';
