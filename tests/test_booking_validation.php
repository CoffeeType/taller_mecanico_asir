<?php
/**
 * Citación con datos inválidos debe rechazarse sin crear Cita (HTTP 400).
 */

declare(strict_types=1);

require_once __DIR__ . '/booking_api_client.php';

$base = booking_api_base();

function assertBookingRejected(string $label, array $response, int $expectedStatus, string $expectedErrorFragment): bool
{
    if ($response['status'] !== $expectedStatus) {
        echo "FAIL: {$label} — expected HTTP {$expectedStatus}, got {$response['status']}\n";
        echo "RAW: " . substr($response['body'], 0, 500) . "\n";
        return false;
    }
    $err = is_array($response['json']) ? (string) ($response['json']['error'] ?? '') : '';
    if ($err === '' || !str_contains($err, $expectedErrorFragment)) {
        echo "FAIL: {$label} — expected error containing \"{$expectedErrorFragment}\", got \"{$err}\"\n";
        echo "RAW: " . substr($response['body'], 0, 500) . "\n";
        return false;
    }
    echo "PASS: {$label}\n";
    return true;
}

$slot = booking_unique_slot();
$ok = true;

// Campos obligatorios ausentes
$ok = assertBookingRejected(
    'POST sin motivo devuelve 400',
    booking_api_post($base, [
        'fecha' => $slot['fecha'],
        'hora' => $slot['hora'],
        'guest_name' => 'Test',
        'guest_email' => 'test@example.com',
        'guest_phone' => '600000000',
    ]),
    400,
    'Faltan campos obligatorios'
) && $ok;

// Fecha en el pasado
$ok = assertBookingRejected(
    'POST con fecha pasada devuelve 400',
    booking_api_post($base, [
        'fecha' => '2000-01-01',
        'hora' => '09:00',
        'motivo' => 'Prueba pasado',
        'guest_name' => 'Test',
        'guest_email' => 'test@example.com',
        'guest_phone' => '600000000',
    ]),
    400,
    'No se pueden agendar citas en el pasado'
) && $ok;

// Invitado sin datos de contacto
$ok = assertBookingRejected(
    'POST invitado sin contacto devuelve 400',
    booking_api_post($base, [
        'fecha' => $slot['fecha'],
        'hora' => $slot['hora'],
        'motivo' => 'Prueba invitado incompleto',
    ]),
    400,
    'Datos de contacto obligatorios'
) && $ok;

exit($ok ? 0 : 1);
