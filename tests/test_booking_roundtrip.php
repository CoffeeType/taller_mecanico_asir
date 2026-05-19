<?php
/**
 * Tras una citación exitosa, GET del mes debe incluir la franja reservada.
 */

declare(strict_types=1);

require_once __DIR__ . '/booking_api_client.php';

$base = booking_api_base();
$slot = booking_unique_slot();

$payload = [
    'fecha' => $slot['fecha'],
    'hora' => $slot['hora'],
    'motivo' => 'Prueba roundtrip GET',
    'guest_name' => 'Test Roundtrip',
    'guest_email' => 'test-roundtrip@example.com',
    'guest_phone' => '600000001',
];

$post = booking_api_post($base, $payload);
if ($post['status'] !== 200 || !is_array($post['json']) || empty($post['json']['success'])) {
    echo "FAIL: POST booking expected 200 success. HTTP {$post['status']}\n";
    echo "RAW: " . substr($post['body'], 0, 500) . "\n";
    exit(1);
}

$year = (int) substr($slot['fecha'], 0, 4);
$month = (int) substr($slot['fecha'], 5, 2);
$get = booking_api_get_booked($base, $year, $month);

if ($get['status'] !== 200 || !is_array($get['json']) || !isset($get['json']['booked'])) {
    echo "FAIL: GET booked slots expected 200 with booked key. HTTP {$get['status']}\n";
    echo "RAW: " . substr($get['body'], 0, 500) . "\n";
    exit(1);
}

$booked = $get['json']['booked'];
$slotsForDate = is_array($booked[$slot['fecha']] ?? null) ? $booked[$slot['fecha']] : [];

if (!booking_slot_listed($slot['hora'], $slotsForDate)) {
    echo "FAIL: Booked slot {$slot['fecha']} {$slot['hora']} not listed in GET response.\n";
    echo "RAW booked: " . json_encode($booked) . "\n";
    exit(1);
}

echo "PASS: POST booking appears in GET booked slots for the month.\n";
exit(0);
