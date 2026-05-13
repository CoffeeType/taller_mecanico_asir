<?php
/**
 * Pruebas unitarias para scripts/traffic_simulator_lib.php (sin PHPUnit; código 0 si todo pasa).
 */

declare(strict_types=1);

require_once __DIR__ . '/../scripts/traffic_simulator_lib.php';

$fails = [];

function t(string $name, bool $ok): void
{
    global $fails;
    if (!$ok) {
        $fails[] = $name;
        echo "FAIL: {$name}\n";
    }
}

$pl = traffic_simulator_parse_metrics_log_line('GET 200 source=app');
t('parse new app line', $pl !== null && $pl['method'] === 'GET' && $pl['status'] === 200 && $pl['source'] === 'app' && $pl['path'] === null);

$ps = traffic_simulator_parse_metrics_log_line('GET 404 /x source=simulator');
t('parse simulator line', $ps !== null && $ps['status'] === 404 && $ps['path'] === '/x' && $ps['source'] === 'simulator');

$lg = traffic_simulator_parse_metrics_log_line('GET 200');
t('parse legacy app', $lg !== null && $lg['source'] === 'app');

$lsx = traffic_simulator_parse_metrics_log_line('GET 500 /does-not-exist');
t('parse legacy sim', $lsx !== null && $lsx['source'] === 'simulator' && $lsx['status'] === 500);

t('parse rejects junk', traffic_simulator_parse_metrics_log_line('not a metric line') === null);

$td = sys_get_temp_dir() . '/traffic_sim_test_' . bin2hex(random_bytes(4));
mkdir($td, 0755, true);
$ml = $td . '/metrics.log';
$rl = $td . '/response_time.log';
traffic_simulator_append_log($ml, $rl, ['method' => 'GET', 'status' => 201, 'time' => 0.12], '/foo');
$appended = file_get_contents($ml);
t('append_log includes source=simulator', is_string($appended) && str_contains($appended, 'GET 201 /foo source=simulator'));

file_put_contents($ml, "GET 200 source=app\nGET 404 /a source=simulator\nGET 200\nbogus\nGET 500 /z source=simulator\n");
file_put_contents($rl, "0.1\n0.2\n");
$st = traffic_simulator_read_log_stats($ml, $rl, 20);
t('read_log_stats success count', ($st['success_requests'] ?? -1) === 2);
t('read_log_stats error count', ($st['error_requests'] ?? -1) === 2);
t('read_log_stats recent matches all lines', ($st['recent_success'] ?? -1) === 2 && ($st['recent_errors'] ?? -1) === 2);
t('read_log_stats has recent_window', ($st['recent_window'] ?? 0) === 20);

file_put_contents($ml, "GET 200 source=app\nGET 404 /a source=simulator\n");
$stTail = traffic_simulator_read_log_stats($ml, $rl, 2);
t('read_log_stats tail one ok one err', ($stTail['recent_success'] ?? -1) === 1 && ($stTail['recent_errors'] ?? -1) === 1);

$badProbe = traffic_simulator_probe_base_url('notaurl', ['trusted_cli' => true]);
t('probe rejects bad url', isset($badProbe['ok']) && $badProbe['ok'] === false && ($badProbe['http_code'] ?? -1) === 0);

$t = traffic_simulator_normalize_base_url_input('example.com/ruta');
t('auto https for public host', $t === 'https://example.com/ruta');

$t2 = traffic_simulator_normalize_base_url_input('web');
t('auto http for internal web', $t2 === 'http://web');

$t3 = traffic_simulator_validate_base_url('example.com');
t('schemeless validates', isset($t3['ok']) && $t3['ok'] === true && ($t3['base'] ?? '') === 'https://example.com');

$bad = traffic_simulator_validate_base_url('ftp://example.com');
t('reject non-http(s)', isset($bad['ok']) && $bad['ok'] === false);

$cred = traffic_simulator_validate_base_url('http://user:pass@example.com/');
t('reject embedded credentials', isset($cred['ok']) && $cred['ok'] === false);

$ok = traffic_simulator_validate_base_url('http://web/path');
t('accept http://web', isset($ok['ok']) && $ok['ok'] === true && ($ok['base'] ?? '') === 'http://web/path');

$needExt = traffic_simulator_validate_target_url('https://example.com', [
    'trusted_cli'      => false,
    'confirm_external' => false,
]);
t('external needs confirm', ($needExt['code'] ?? '') === 'NEED_CONFIRM_EXTERNAL');

$extOk = traffic_simulator_validate_target_url('https://example.com', [
    'trusted_cli'      => false,
    'confirm_external' => true,
]);
t('external with confirm ok', isset($extOk['ok']) && $extOk['ok'] === true);

$privDeny = traffic_simulator_validate_target_url('http://10.10.10.10', ['allow_private' => false]);
t('reject private literal IP', isset($privDeny['ok']) && $privDeny['ok'] === false);

$privOk = traffic_simulator_validate_target_url('http://192.168.0.1', ['allow_private' => true]);
t('allow private literal IP with option', isset($privOk['ok']) && $privOk['ok'] === true);

$defaults = traffic_simulator_resolve_config([]);
t('default config no error', !isset($defaults['error']));
t('default port base', ($defaults['base_url'] ?? '') === 'http://localhost:8081');

$ext = traffic_simulator_resolve_config(['base-url' => 'https://example.com/']);
t('https trim', ($ext['base_url'] ?? '') === 'https://example.com');

$inv = traffic_simulator_resolve_config(['base-url' => 'notaurl']);
t('invalid URL error', isset($inv['error']));

$jsonPath = __DIR__ . '/fixtures/traffic_routes_test.json';
$withRoutes = traffic_simulator_resolve_config(['routes-file' => $jsonPath, 'base-url' => 'http://mock.test']);
t('routes JSON loaded', !isset($withRoutes['error']) && count($withRoutes['pages'] ?? []) === 2);

$w = traffic_simulator_build_weighted([
    ['path' => '/', 'method' => 'GET', 'weight' => 2],
]);
t('weighted size', count($w) === 2);

$joined = traffic_simulator_join_paths('/base/', '/ruta');
t('join base route paths', $joined === '/base/ruta');

$rows = traffic_simulator_jmeter_route_rows([
    ['path' => '/', 'method' => 'GET', 'weight' => 1],
], ['min_sleep_ms' => 1, 'max_sleep_ms' => 2, 'error_rate' => 0], '/', 3);
t('jmeter route rows generated', count($rows) === 3 && ($rows[0]['method'] ?? '') === 'GET');

$jtl = $td . '/results.jtl';
file_put_contents($jtl, "timeStamp,elapsed,label,responseCode,responseMessage,threadName,dataType,success,failureMessage,bytes,sentBytes,grpThreads,allThreads,URL,Latency,IdleTime,Connect\n");
file_put_contents($jtl, "1,123,GET /jmeter,200,OK,t,text,true,,1,1,1,1,http://web/jmeter,100,0,10\n", FILE_APPEND);
file_put_contents($ml, '');
file_put_contents($rl, '');
$imported = traffic_simulator_import_jmeter_jtl($jtl, $ml, $rl, 0);
t('jmeter jtl imported count', $imported === 1);
t('jmeter jtl metrics line', trim((string) file_get_contents($ml)) === 'GET 200 /jmeter source=simulator');
t('jmeter jtl response seconds', trim((string) file_get_contents($rl)) === '0.123');

echo empty($fails) ? "PASS: traffic_simulator_lib tests OK.\n" : "FAILURES: " . count($fails) . "\n";
exit(empty($fails) ? 0 : 1);
