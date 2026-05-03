#!/usr/bin/env bash
# Despliegue / actualizacion idempotente en EC2 usando docker-compose.aws.yml
# Uso (en el servidor): ./scripts/deploy_aws_docker.sh
# Variables: COMPOSE_FILE (default docker-compose.aws.yml), SKIP_BACKUP=1, PROJECT_DIR

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="${PROJECT_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
cd "$PROJECT_DIR"

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.aws.yml}"
export COMPOSE_FILE

if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

WEB_HOST_PORT="${WEB_HOST_PORT:-80}"
BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/backups}"
mkdir -p "$BACKUP_DIR"

run_backup() {
  local ts
  ts="$(date +%Y%m%d_%H%M%S)"
  echo "Backup MySQL -> ${BACKUP_DIR}/mysql_${ts}.sql.gz"
  docker compose -f "$COMPOSE_FILE" exec -T mysql sh -c \
    'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' \
    | gzip > "${BACKUP_DIR}/mysql_${ts}.sql.gz"
  echo "Backup OK ($(du -h "${BACKUP_DIR}/mysql_${ts}.sql.gz" | cut -f1))"
}

if [[ "${SKIP_BACKUP:-0}" != "1" ]]; then
  if docker compose -f "$COMPOSE_FILE" ps -q mysql 2>/dev/null | grep -q .; then
    run_backup || echo "WARN: backup fallo; continua si es primer deploy" >&2
  fi
fi

echo "Build / pull imagenes..."
docker compose -f "$COMPOSE_FILE" build --parallel
docker compose -f "$COMPOSE_FILE" pull --ignore-pull-failures || true

echo "Levantando stack..."
docker compose -f "$COMPOSE_FILE" up -d --remove-orphans

echo "Estado:"
docker compose -f "$COMPOSE_FILE" ps

echo "Smoke HTTP (web)..."
if curl -sf "http://127.0.0.1:${WEB_HOST_PORT}/" >/dev/null; then
  echo "OK: GET /"
else
  echo "ERROR: no responde http://127.0.0.1:${WEB_HOST_PORT}/" >&2
  exit 1
fi

if curl -sf "http://127.0.0.1:${PROMETHEUS_HOST_PORT:-9090}/-/healthy" >/dev/null 2>&1; then
  echo "OK: Prometheus healthy (localhost)"
else
  echo "WARN: Prometheus no accesible en loopback (revisa arranque)" >&2
fi

echo "Deploy completado."
