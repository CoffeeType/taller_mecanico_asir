<?php
// tests/test_booking_access.php

// Mock environment for GET request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['year'] = date('Y');
$_GET['month'] = date('n');

// Prevent session headers already sent warning if possible, though strict usage might fail
// We will suppress stderr for cleaner output check
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Buffer output to verify JSON
ob_start();

try {
    require __DIR__ . '/../api/citas_api.php';
} catch (Exception $e) {
    echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
}

$output = ob_get_clean();

// Check if output is valid JSON
$data = json_decode($output, true);

if (json_last_error() === JSON_ERROR_NONE && isset($data['booked'])) {
    echo "PASS: Received valid JSON with booked slots.\n";
    echo "DEBUG_OUTPUT_START\n" . substr($output, 0, 100) . "...\nDEBUG_OUTPUT_END\n";
    exit(0);
} else {
    echo "FAIL: Invalid output or missing 'booked' key.\n";
    echo "RAW_OUTPUT: $output\n";
    exit(1);
}
