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
# --wait: esperar healthchecks (web depende de mysql + entrypoint largo en primer arranque).
# --wait-timeout existe desde Compose ~2.23; sin el flag, --wait puede esperar indefinidamente en casos raros.
UP_HELP="$(docker compose up --help 2>/dev/null || true)"
if echo "$UP_HELP" | grep -q -- '--wait-timeout'; then
  docker compose -f "$COMPOSE_FILE" up -d --remove-orphans --wait --wait-timeout "${COMPOSE_UP_WAIT_TIMEOUT:-600}"
elif echo "$UP_HELP" | grep -qE '[[:space:]]--wait[[:space:]]'; then
  docker compose -f "$COMPOSE_FILE" up -d --remove-orphans --wait
else
  docker compose -f "$COMPOSE_FILE" up -d --remove-orphans
fi

echo "Estado:"
docker compose -f "$COMPOSE_FILE" ps

echo "Smoke HTTP (web)..."
WEB_URL="http://127.0.0.1:${WEB_HOST_PORT}/"
MAX_WAIT_SEC="${WEB_SMOKE_WAIT_SEC:-240}"
STEP=3
elapsed=0
ok=0
while [[ "$elapsed" -lt "$MAX_WAIT_SEC" ]]; do
  if curl -sf "$WEB_URL" >/dev/null; then
    echo "OK: GET / (tras ~${elapsed}s)"
    ok=1
    break
  fi
  echo "Esperando HTTP en ${WEB_URL}... (${elapsed}/${MAX_WAIT_SEC}s)" >&2
  sleep "$STEP"
  elapsed=$((elapsed + STEP))
done
if [[ "$ok" != "1" ]]; then
  echo "ERROR: no responde ${WEB_URL} tras ${MAX_WAIT_SEC}s" >&2
  docker compose -f "$COMPOSE_FILE" logs --tail 120 web || true
  exit 1
fi

if curl -sf "http://127.0.0.1:${PROMETHEUS_HOST_PORT:-9090}/-/healthy" >/dev/null 2>&1; then
  echo "OK: Prometheus healthy (localhost)"
else
  echo "WARN: Prometheus no accesible en loopback (revisa arranque)" >&2
fi

echo "Deploy completado."
