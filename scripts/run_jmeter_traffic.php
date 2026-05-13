<?php
/**
 * run_jmeter_traffic.php — Ejecutor Apache JMeter para el worker del traffic-simulator.
 *
 * Genera un plan JMX desde la config del simulador, ejecuta JMeter en modo CLI
 * y vuelca resultados JTL en logs/metrics.log + logs/response_time.log.
 */

declare(strict_types=1);

require_once __DIR__ . '/traffic_simulator_lib.php';

$options = getopt('', [
    'users::',
    'duration::',
    'profile::',
    'base-url::',
    'routes-file::',
    'work-dir::',
]);

$config = traffic_simulator_resolve_config($options);
if (isset($config['error'])) {
    fwrite(STDERR, '[JMeterTraffic] ' . $config['error'] . "\n");
    exit(1);
}

$logsDir = $config['logs_dir'];
if (!is_dir($logsDir)) {
    if (!@mkdir($logsDir, 0775, true) && !is_dir($logsDir)) {
        fwrite(STDERR, '[JMeterTraffic] Cannot create logs directory: ' . $logsDir . "\n");
        exit(1);
    }
}

$metricsLog      = $logsDir . '/metrics.log';
$responseTimeLog = $logsDir . '/response_time.log';
$workDir         = isset($options['work-dir']) && is_string($options['work-dir']) && $options['work-dir'] !== ''
    ? $options['work-dir']
    : (getenv('SIM_JMETER_WORK_DIR') ?: $logsDir . '/jmeter');
$statusFile      = getenv('SIM_STATUS_FILE') ?: '/tmp/traffic_simulator_status.json';
$jmeterPidFile   = getenv('SIM_JMETER_PID_FILE') ?: '/tmp/traffic_simulator_jmeter.pid';
$jmeterBin       = getenv('SIM_JMETER_BIN') ?: (getenv('JMETER_HOME') ? rtrim((string) getenv('JMETER_HOME'), '/\\') . '/bin/jmeter' : 'jmeter');
$heap            = getenv('SIM_JMETER_HEAP') ?: '-Xms128m -Xmx256m -XX:MaxMetaspaceSize=128m';
$htmlReport      = strtolower((string) (getenv('SIM_JMETER_HTML_REPORT') ?: 'true')) === 'true';

function jt_write_status(string $path, array $payload): void
{
    @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
}

function jt_report_ready(string $reportDir): bool
{
    return is_file(rtrim($reportDir, '/\\') . '/index.html');
}

try {
    $files = traffic_simulator_prepare_jmeter_run($config, $workDir);
} catch (Throwable $e) {
    fwrite(STDERR, '[JMeterTraffic] ' . $e->getMessage() . "\n");
    jt_write_status($statusFile, [
        'running' => false,
        'error'   => $e->getMessage(),
    ]);
    exit(1);
}

@unlink($files['results_jtl']);
@unlink($jmeterPidFile);

putenv('HEAP=' . $heap);

$cmd = [
    $jmeterBin,
    '-n',
    '-t',
    $files['test_jmx'],
    '-l',
    $files['results_jtl'],
    '-j',
    $files['jmeter_log'],
    '-Jjmeter.save.saveservice.output_format=csv',
    '-Jjmeter.save.saveservice.print_field_names=true',
    '-Jjmeter.save.saveservice.timestamp_format=ms',
    '-Jjmeter.save.saveservice.response_code=true',
    '-Jjmeter.save.saveservice.response_message=true',
    '-Jjmeter.save.saveservice.label=true',
    '-Jjmeter.save.saveservice.time=true',
    '-Jjmeter.save.saveservice.latency=true',
    '-Jjmeter.save.saveservice.connect_time=true',
    '-Jjmeter.save.saveservice.successful=true',
    '-Jjmeter.save.saveservice.url=true',
    '-Jjmeter.save.saveservice.thread_counts=true',
];
if ($htmlReport) {
    $cmd[] = '-e';
    $cmd[] = '-o';
    $cmd[] = $files['report_dir'];
}

$descriptorSpec = [
    0 => ['file', '/dev/null', 'r'],
    1 => ['file', $files['stdout_log'], 'ab'],
    2 => ['file', $files['stdout_log'], 'ab'],
];

$process = proc_open($cmd, $descriptorSpec, $pipes, $files['run_dir']);
if (!is_resource($process)) {
    jt_write_status($statusFile, [
        'running' => false,
        'error'   => 'Cannot start JMeter process',
        'files'   => $files,
    ]);
    fwrite(STDERR, "[JMeterTraffic] Cannot start JMeter process\n");
    exit(1);
}

$status = proc_get_status($process);
$jmeterPid = (int) ($status['pid'] ?? 0);
if ($jmeterPid > 0) {
    @file_put_contents($jmeterPidFile, (string) $jmeterPid);
}

$baseStatus = [
    'tool'      => 'apache-jmeter',
    'running'   => true,
    'pid'       => $jmeterPid > 0 ? $jmeterPid : null,
    'started'   => gmdate('c'),
    'base_url'  => $config['base_url'],
    'profile'   => $config['profile'],
    'users'     => $config['users'],
    'duration'  => $config['duration'],
    'heap'      => $heap,
    'html_report_enabled' => $htmlReport,
    'files'     => $files,
];

$imported = 0;
jt_write_status($statusFile, $baseStatus + [
    'imported_samples' => $imported,
    'report_ready'     => jt_report_ready($files['report_dir']),
]);

do {
    $imported = traffic_simulator_import_jmeter_jtl($files['results_jtl'], $metricsLog, $responseTimeLog, $imported);
    $status = proc_get_status($process);
    jt_write_status($statusFile, $baseStatus + [
        'running'          => (bool) ($status['running'] ?? false),
        'imported_samples' => $imported,
        'report_ready'     => jt_report_ready($files['report_dir']),
        'updated'          => gmdate('c'),
    ]);
    if (!($status['running'] ?? false)) {
        break;
    }
    sleep(1);
} while (true);

$exitCode = proc_close($process);
$imported = traffic_simulator_import_jmeter_jtl($files['results_jtl'], $metricsLog, $responseTimeLog, $imported);
@unlink($jmeterPidFile);

jt_write_status($statusFile, $baseStatus + [
    'running'          => false,
    'pid'              => null,
    'imported_samples' => $imported,
    'exit_code'        => $exitCode,
    'report_ready'     => jt_report_ready($files['report_dir']),
    'finished'         => gmdate('c'),
]);

exit($exitCode === 0 ? 0 : 1);
