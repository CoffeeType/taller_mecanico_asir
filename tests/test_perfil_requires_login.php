<?php
/**
 * perfil.php debe redirigir a login cuando no hay sesión.
 */

declare(strict_types=1);

$base = rtrim(getenv('BOOKING_TEST_BASE') ?: 'http://127.0.0.1:8081', '/');
$url = $base . '/perfil.php';

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'ignore_errors' => true,
        'follow_location' => 0,
    ],
]);

$body = @file_get_contents($url, false, $ctx);
$status = 0;
$location = '';
if (isset($http_response_header)) {
    foreach ($http_response_header as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $m)) {
            $status = (int) $m[1];
        }
        if (stripos($line, 'Location:') === 0) {
            $location = trim(substr($line, strlen('Location:')));
        }
    }
}

if ($status >= 300 && $status < 400 && str_contains($location, 'login.php')) {
    echo "PASS: Unauthenticated GET /perfil.php redirects to login.php (HTTP {$status}).\n";
    exit(0);
}

echo "FAIL: Expected redirect to login.php for unauthenticated profile access.\n";
echo "HTTP {$status}\n";
echo "Location: {$location}\n";
echo "BODY: " . substr((string) $body, 0, 300) . "\n";
exit(1);
