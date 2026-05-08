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

mem_total_mb() {
  awk '/MemTotal:/ { printf "%d", $2 / 1024 }' /proc/meminfo 2>/dev/null || printf '0'
}

disk_avail_mb() {
  local avail_kb
  avail_kb="$(df -Pk "$PROJECT_DIR" 2>/dev/null | tail -1 | awk '{print $4}')"
  [[ -n "${avail_kb:-}" ]] || { printf '0'; return; }
  printf '%d' "$((avail_kb / 1024))"
}

compose() {
  docker compose "${COMPOSE_ENV_ARGS[@]}" -f "$COMPOSE_FILE" "$@"
}

compose_config_services() {
  compose config --services 2>/dev/null | sort -u
}

profiles_contain() {
  local needle="$1"
  local raw IFS=',' p
  raw="${COMPOSE_PROFILES//[[:space:]]/}"
  [[ -z "$raw" ]] && return 1
  IFS=',' read -ra _prof_parts <<<"$raw"
  for p in "${_prof_parts[@]}"; do
    [[ -z "$p" ]] && continue
    [[ "$p" == "$needle" ]] && return 0
  done
  return 1
}

DEPLOY_MONITORING="$(read_env_var DEPLOY_MONITORING "${DEPLOY_MONITORING:-0}")"
COMPOSE_PROFILES="$(read_env_var COMPOSE_PROFILES "${COMPOSE_PROFILES:-}")"
FORCE_MONITORING_ON_LOW_MEM="$(read_env_var FORCE_MONITORING_ON_LOW_MEM "${FORCE_MONITORING_ON_LOW_MEM:-0}")"
ALLOW_DEGRADED_STACK="$(read_env_var ALLOW_DEGRADED_STACK "${ALLOW_DEGRADED_STACK:-0}")"
SKIP_SECRET_STRICT_CHECK="$(read_env_var SKIP_SECRET_STRICT_CHECK "${SKIP_SECRET_STRICT_CHECK:-0}")"
MIN_MONITORING_MEM_MB="$(read_env_var MIN_MONITORING_MEM_MB "${MIN_MONITORING_MEM_MB:-1900}")"
MIN_TRAFFIC_STACK_MEM_MB="$(read_env_var MIN_TRAFFIC_STACK_MEM_MB "${MIN_TRAFFIC_STACK_MEM_MB:-2000}")"
MIN_FREE_DISK_MB="$(read_env_var MIN_FREE_DISK_MB "${MIN_FREE_DISK_MB:-2048}")"

if [[ "$DEPLOY_MONITORING" == "1" ]] && ! profiles_contain monitoring; then
  if [[ -n "$COMPOSE_PROFILES" ]]; then
    COMPOSE_PROFILES="${COMPOSE_PROFILES},monitoring"
  else
    COMPOSE_PROFILES="monitoring"
  fi
fi

MEM_MB="$(mem_total_mb)"
WANT_MONITORING=0
WANT_TRAFFIC=0
profiles_contain monitoring && WANT_MONITORING=1
profiles_contain traffic && WANT_TRAFFIC=1

if [[ "$WANT_MONITORING" == "1" || "$WANT_TRAFFIC" == "1" ]]; then
  if [[ "$FORCE_MONITORING_ON_LOW_MEM" != "1" && "$MEM_MB" -gt 0 ]]; then
    if [[ "$ALLOW_DEGRADED_STACK" == "1" ]]; then
      need_strip=0
      if [[ "$WANT_MONITORING" == "1" && "$MEM_MB" -lt "$MIN_MONITORING_MEM_MB" ]]; then need_strip=1; fi
      if [[ "$WANT_TRAFFIC" == "1" && "$MEM_MB" -lt "$MIN_TRAFFIC_STACK_MEM_MB" ]]; then need_strip=1; fi
      if [[ "$need_strip" == "1" ]]; then
        echo "WARN: RAM ${MEM_MB}MB insuficiente para perfiles activos; modo degradado -> solo web+mysql." >&2
        COMPOSE_PROFILES=""
        WANT_MONITORING=0
        WANT_TRAFFIC=0
      fi
    else
      if [[ "$WANT_MONITORING" == "1" && "$MEM_MB" -lt "$MIN_MONITORING_MEM_MB" ]]; then
        echo "ERROR: RAM ${MEM_MB}MB < MIN_MONITORING_MEM_MB=${MIN_MONITORING_MEM_MB}. Aumenta instancia/swap, FORCE_MONITORING_ON_LOW_MEM=1, o ALLOW_DEGRADED_STACK=1." >&2
        exit 1
      fi
      if [[ "$WANT_TRAFFIC" == "1" && "$MEM_MB" -lt "$MIN_TRAFFIC_STACK_MEM_MB" ]]; then
        echo "ERROR: RAM ${MEM_MB}MB < MIN_TRAFFIC_STACK_MEM_MB=${MIN_TRAFFIC_STACK_MEM_MB}. Aumenta instancia/swap, FORCE_MONITORING_ON_LOW_MEM=1, o ALLOW_DEGRADED_STACK=1." >&2
        exit 1
      fi
    fi
  fi
fi

export COMPOSE_PROFILES

dump_diagnostics() {
  echo "Diagnostics: compose ps -a" >&2
  compose ps -a || true
  local svc
  while read -r svc; do
    [[ -z "$svc" ]] && continue
    echo "--- compose logs --tail 160 ${svc} ---" >&2
    compose logs --tail 160 "$svc" 2>/dev/null || true
  done < <(compose_config_services || true)
}

trap 'echo "Deploy failed; diagnostics below." >&2; dump_diagnostics' ERR

WEB_HOST_PORT="$(read_env_var WEB_HOST_PORT 80)"
PROMETHEUS_HOST_PORT="$(read_env_var PROMETHEUS_HOST_PORT 9090)"
GRAFANA_HOST_PORT="$(read_env_var GRAFANA_HOST_PORT 3000)"
ALERTMANAGER_HOST_PORT="$(read_env_var ALERTMANAGER_HOST_PORT 9093)"
TRAFFIC_UI_PORT="$(read_env_var TRAFFIC_SIMULATOR_UI_HOST_PORT 8890)"
CADVISOR_HOST_PORT="$(read_env_var CADVISOR_HOST_PORT 8080)"
TELEGRAF_HOST_PORT="$(read_env_var TELEGRAF_HOST_PORT 9273)"

strict_secret_fail() {
  echo "ERROR: ${1}" >&2
  echo "Rota secretos en .env o define SKIP_SECRET_STRICT_CHECK=1 (solo lab)." >&2
  exit 1
}

if [[ "$SKIP_SECRET_STRICT_CHECK" != "1" ]]; then
  mp="$(read_env_var MYSQL_PASSWORD "")"
  mrp="$(read_env_var MYSQL_ROOT_PASSWORD "")"
  gap="$(read_env_var GRAFANA_ADMIN_PASSWORD "")"
  [[ "$mp" == *CAMBIAR* || -z "$mp" ]] && strict_secret_fail "MYSQL_PASSWORD invalido o placeholder."
  [[ "$mrp" == *CAMBIAR* || -z "$mrp" ]] && strict_secret_fail "MYSQL_ROOT_PASSWORD invalido o placeholder."
  [[ "$gap" == *CAMBIAR* || -z "$gap" ]] && strict_secret_fail "GRAFANA_ADMIN_PASSWORD invalido o placeholder."
  if [[ "$WANT_TRAFFIC" == "1" ]]; then
    tok="$(read_env_var SIMULATOR_CONTROL_TOKEN "")"
    [[ "$tok" == *changeme* || "$tok" == *CAMBIAR* || -z "$tok" ]] && strict_secret_fail "SIMULATOR_CONTROL_TOKEN debil o placeholder con perfil traffic."
  fi
fi

preflight_paths=(
  "database/database.sql"
  "monitoring/prometheus/prometheus.aws.yml"
  "monitoring/prometheus/alerts.yml"
  "monitoring/prometheus/blackbox.yml"
  "monitoring/alertmanager/alertmanager.yml"
  "monitoring/alertmanager/alertmanager-entrypoint.sh"
  "monitoring/grafana/provisioning"
  "monitoring/grafana/dashboards"
  "monitoring/mysqld_exporter/entrypoint.sh"
  "monitoring/telegraf/telegraf.conf"
)
for rel in "${preflight_paths[@]}"; do
  [[ -e "$PROJECT_DIR/$rel" ]] || { echo "ERROR: falta ruta requerida: $rel" >&2; exit 1; }
done

avail_mb="$(disk_avail_mb)"
if [[ "${avail_mb:-0}" -lt "$MIN_FREE_DISK_MB" ]]; then
  echo "ERROR: espacio libre ${avail_mb}MB < MIN_FREE_DISK_MB=${MIN_FREE_DISK_MB} en ${PROJECT_DIR}" >&2
  exit 1
fi

if command -v ss >/dev/null 2>&1; then
  check_listen_warn() {
    local p="$1" tag="$2"
    ss -tlnH 2>/dev/null | awk '{print $4}' | grep -qE ":${p}\$" && echo "WARN: puerto ${p} (${tag}) ya en uso; confirma que es este stack." >&2 || true
  }
  check_listen_warn "$WEB_HOST_PORT" "web"
  if [[ "$WANT_MONITORING" == "1" ]]; then
    check_listen_warn "$PROMETHEUS_HOST_PORT" "prometheus"
    check_listen_warn "$GRAFANA_HOST_PORT" "grafana"
    check_listen_warn "$ALERTMANAGER_HOST_PORT" "alertmanager"
    check_listen_warn "$CADVISOR_HOST_PORT" "cadvisor"
    check_listen_warn "$TELEGRAF_HOST_PORT" "telegraf"
  fi
  if [[ "$WANT_TRAFFIC" == "1" ]]; then
    check_listen_warn "$TRAFFIC_UI_PORT" "traffic-ui"
  fi
fi

echo "Compose config (validacion)..."
compose config >/dev/null

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
if [[ "${COMPOSE_BUILD_PARALLEL:-0}" == "1" ]]; then
  compose build --parallel
else
  compose build
fi
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
compose ps -a

mapfile -t EXPECTED_SERVICES < <(compose_config_services)
if [[ "${#EXPECTED_SERVICES[@]}" -eq 0 ]]; then
  echo "ERROR: compose config --services vacio" >&2
  exit 1
fi

service_line() {
  local svc="$1"
  compose ps -a --format '{{.Service}}\t{{.Status}}' | awk -F'\t' -v s="$svc" '$1==s {print $2; exit}'
}

service_ok_status() {
  local st="$1"
  [[ "$st" == *Up* || "$st" == *running* ]] || return 1
  if [[ "$st" == *"(health:"* ]]; then
    [[ "$st" == *"(healthy)"* ]] || return 1
  fi
  return 0
}

verify_services() {
  local svc st
  for svc in "${EXPECTED_SERVICES[@]}"; do
    st="$(service_line "$svc")"
    [[ -n "$st" ]] || { echo "ERROR: servicio no encontrado en compose ps: ${svc}" >&2; return 1; }
    service_ok_status "$st" || { echo "ERROR: servicio ${svc} estado: ${st}" >&2; return 1; }
  done
  return 0
}

echo "Verificando servicios esperados (${#EXPECTED_SERVICES[@]})..."
deadline_ts=$(($(date +%s) + ${STACK_VERIFY_TIMEOUT_SEC:-420}))
while [[ "$(date +%s)" -lt "$deadline_ts" ]]; do
  if verify_services; then
    echo "OK: todos los servicios activos (y healthy si aplica)."
    break
  fi
  echo "Esperando servicios sanos... ($((deadline_ts - $(date +%s)))s restantes)" >&2
  sleep 5
done
if ! verify_services; then
  echo "ERROR: verificacion de servicios fallo tras timeout" >&2
  exit 1
fi

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

if [[ "$WANT_MONITORING" == "1" ]]; then
  curl -sf "http://127.0.0.1:${PROMETHEUS_HOST_PORT}/-/healthy" >/dev/null || { echo "ERROR: Prometheus no healthy en loopback" >&2; exit 1; }
  echo "OK: Prometheus healthy (localhost)"
  curl -sf "http://127.0.0.1:${GRAFANA_HOST_PORT}/api/health" >/dev/null || { echo "ERROR: Grafana no responde /api/health" >&2; exit 1; }
  echo "OK: Grafana health (localhost)"
  curl -sf "http://127.0.0.1:${ALERTMANAGER_HOST_PORT}/-/healthy" >/dev/null || { echo "ERROR: Alertmanager no healthy" >&2; exit 1; }
  echo "OK: Alertmanager healthy (localhost)"
fi

if [[ "$WANT_TRAFFIC" == "1" ]]; then
  curl -sf "http://127.0.0.1:${TRAFFIC_UI_PORT}/health.php" >/dev/null || { echo "ERROR: traffic-simulator-ui /health.php no responde" >&2; exit 1; }
  echo "OK: traffic-simulator-ui health (localhost:${TRAFFIC_UI_PORT})"
fi

echo "Deploy completado."
