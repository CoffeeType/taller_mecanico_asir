<?php
/**
 * Helpers HTTP para pruebas de citas_api (sin PHPUnit).
 */

declare(strict_types=1);

function booking_api_base(): string
{
    return rtrim(getenv('BOOKING_TEST_BASE') ?: 'http://127.0.0.1:8081', '/');
}

/** @return array{fecha:string, hora:string} */
function booking_unique_slot(): array
{
    $dayOffset = (int) (microtime(true) * 1000) % 28 + 1;
    $fecha = date('Y-m-d', strtotime("+{$dayOffset} days", strtotime('2099-01-01')));
    $slotHour = 9 + ((int) (microtime(true) * 1000) % 8);

    return [
        'fecha' => $fecha,
        'hora' => sprintf('%02d:00', $slotHour),
    ];
}

/** @return array{status:int, body:string, json:mixed} */
function booking_api_post(string $base, array $payload): array
{
    $url = rtrim($base, '/') . '/api/citas_api.php';
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    return booking_http_json($url, $ctx);
}

/** @return array{status:int, body:string, json:mixed} */
function booking_api_get_booked(string $base, int $year, int $month): array
{
    $url = rtrim($base, '/') . '/api/citas_api.php?year=' . rawurlencode((string) $year)
        . '&month=' . rawurlencode((string) $month);
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    return booking_http_json($url, $ctx);
}

function booking_slot_listed(string $postedHora, array $bookedForDate): bool
{
    foreach ($bookedForDate as $stored) {
        if (!is_string($stored)) {
            continue;
        }
        if ($stored === $postedHora || $stored === $postedHora . ':00') {
            return true;
        }
    }

    return false;
}

/** @return array{status:int, body:string, json:mixed} */
function booking_http_json(string $url, $ctx): array
{
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    return [
        'status' => $status,
        'body' => $body === false ? '' : $body,
        'json' => $body !== false ? json_decode($body, true) : null,
    ];
}
