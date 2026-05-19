<?php
/**
 * Segunda citación en la misma franja horaria debe recibir 409 (una Cita por franja).
 */

declare(strict_types=1);

require_once __DIR__ . '/booking_api_client.php';

$base = booking_api_base();
$slot = booking_unique_slot();

$payload = [
    'fecha' => $slot['fecha'],
    'hora' => $slot['hora'],
    'motivo' => 'Prueba conflicto franja',
    'guest_name' => 'Test Invitado',
    'guest_email' => 'test-conflict@example.com',
    'guest_phone' => '600000000',
];

$first = booking_api_post($base, $payload);
if ($first['status'] !== 200 || !is_array($first['json']) || empty($first['json']['success'])) {
    echo "FAIL: First booking expected 200 success (setup). HTTP {$first['status']}\n";
    echo "RAW: " . substr($first['body'], 0, 500) . "\n";
    echo "Hint: set BOOKING_TEST_BASE and ensure docker compose web+mysql are up.\n";
    exit(1);
}

$second = booking_api_post($base, $payload);
if ($second['status'] === 409) {
    $err = is_array($second['json']) ? ($second['json']['error'] ?? '') : '';
    if ($err !== '') {
        echo "PASS: Second booking rejected with 409 (slot conflict).\n";
        exit(0);
    }
}

echo "FAIL: Second booking expected HTTP 409 with error message.\n";
echo "HTTP {$second['status']}\n";
echo "RAW: " . substr($second['body'], 0, 500) . "\n";
exit(1);
