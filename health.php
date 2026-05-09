<?php
/**
 * Liveness/readiness ligero para probes HTTP (sin session_start ni dependencias).
 */
header('Content-Type: text/plain; charset=utf-8');
http_response_code(200);
echo "ok\n";
