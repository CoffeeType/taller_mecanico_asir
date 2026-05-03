#!/bin/sh
set -e
LOG_DIR="${SIM_LOG_DIR:-/var/www/html/logs}"
mkdir -p "$LOG_DIR"
PORT="${SIMULATOR_CONTROL_PORT:-8085}"
exec php -S "0.0.0.0:${PORT}" "/opt/traffic-simulator/control_router.php"
