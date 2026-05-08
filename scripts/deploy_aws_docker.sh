#!/usr/bin/env bash
# Despliegue / actualizacion idempotente en EC2 usando docker-compose.aws.yml
# Uso (en el servidor): ./scripts/deploy_aws_docker.sh
# Variables: COMPOSE_FILE (default docker-compose.aws.yml), SKIP_BACKUP=1, PROJECT_DIR
#
# No hacer `source .env`: contraseñas con $, #, espacios rompen el shell.
# Compose lee variables con --env-file .env

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="${PROJECT_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
cd "$PROJECT_DIR"

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.aws.yml}"
export COMPOSE_FILE

COMPOSE_ENV_ARGS=()
if [[ -f .env ]]; then
  COMPOSE_ENV_ARGS=(--env-file .env)
fi

# Lee una clave de .env sin evaluar como shell (solo KEY=valor por línea).
read_env_var() {
  local key="$1"
  local default="${2:-}"
  local file="$PROJECT_DIR/.env"
  local line val
  [[ -f "$file" ]] || { printf '%s' "$default"; return 0; }
  while IFS= read -r line || [[ -n "$line" ]]; do
    line="${line//$'\r'/}"
    [[ "$line" =~ ^[[:space:]]*# ]] && continue
    [[ -z "${line// }" ]] && continue
    if [[ "$line" == "$key="* ]]; then
      val="${line#*=}"
      if [[ "$val" =~ ^\"(.*)\"$ ]]; then val="${BASH_REMATCH[1]}"; fi
      if [[ "$val" =~ ^\'(.*)\'$ ]]; then val="${BASH_REMATCH[1]}"; fi
      printf '%s' "$val"
      return 0
    fi
  done < "$file"
  printf '%s' "$default"
}

compose() {
  docker compose "${COMPOSE_ENV_ARGS[@]}" -f "$COMPOSE_FILE" "$@"
}

dump_diagnostics() {
  echo "Diagnostics: compose ps" >&2
  compose ps || true
  local svc
  for svc in web mysql alertmanager prometheus; do
    echo "--- compose logs --tail 120 ${svc} ---" >&2
    compose logs --tail 120 "$svc" 2>/dev/null || true
  done
}

trap 'echo "Deploy failed; diagnostics below." >&2; dump_diagnostics' ERR

WEB_HOST_PORT="$(read_env_var WEB_HOST_PORT 80)"
PROMETHEUS_HOST_PORT="$(read_env_var PROMETHEUS_HOST_PORT 9090)"

BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/backups}"
mkdir -p "$BACKUP_DIR"

run_backup() {
  local ts
  ts="$(date +%Y%m%d_%H%M%S)"
  echo "Backup MySQL -> ${BACKUP_DIR}/mysql_${ts}.sql.gz"
  compose exec -T mysql sh -c \
    'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' \
    | gzip > "${BACKUP_DIR}/mysql_${ts}.sql.gz"
  echo "Backup OK ($(du -h "${BACKUP_DIR}/mysql_${ts}.sql.gz" | cut -f1))"
}

if [[ "${SKIP_BACKUP:-0}" != "1" ]]; then
  if compose ps -q mysql 2>/dev/null | grep -q .; then
    run_backup || echo "WARN: backup fallo; continua si es primer deploy" >&2
  fi
fi

echo "Build / pull imagenes..."
compose build --parallel
compose pull --ignore-pull-failures || true

echo "Levantando stack..."
UP_HELP="$(docker compose up --help 2>/dev/null || true)"
if echo "$UP_HELP" | grep -q -- '--wait-timeout'; then
  compose up -d --remove-orphans --wait --wait-timeout "${COMPOSE_UP_WAIT_TIMEOUT:-600}"
elif echo "$UP_HELP" | grep -qE '[[:space:]]--wait[[:space:]]'; then
  compose up -d --remove-orphans --wait
else
  compose up -d --remove-orphans
fi

echo "Estado:"
compose ps

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
  compose logs --tail 120 web || true
  exit 1
fi

if curl -sf "http://127.0.0.1:${PROMETHEUS_HOST_PORT}/-/healthy" >/dev/null 2>&1; then
  echo "OK: Prometheus healthy (localhost)"
else
  echo "WARN: Prometheus no accesible en loopback (revisa arranque)" >&2
fi

echo "Deploy completado."
