<?php
// tests/test_booking_access.php — HTTP smoke (api/citas_api.php uses exit() internally)

$base = rtrim(getenv('BOOKING_TEST_BASE') ?: 'http://127.0.0.1', '/');
$url = $base . '/api/citas_api.php?year=' . rawurlencode((string) date('Y')) . '&month=' . rawurlencode((string) date('n'));

$ctx = stream_context_create([
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true,
    ],
]);

$json = @file_get_contents($url, false, $ctx);
if ($json === false) {
    echo "FAIL: Could not reach API at {$url} (set BOOKING_TEST_BASE e.g. http://127.0.0.1:8081 when testing from host)\n";
    exit(1);
}

$data = json_decode($json, true);
if (json_last_error() === JSON_ERROR_NONE && isset($data['booked'])) {
    echo "PASS: Received valid JSON with booked slots.\n";
    exit(0);
}

echo "FAIL: Invalid output or missing 'booked' key.\n";
echo "RAW_OUTPUT: " . substr((string) $json, 0, 500) . "\n";
exit(1);
