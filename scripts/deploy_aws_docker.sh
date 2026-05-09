#!/usr/bin/env bash
# Despliegue / actualizacion idempotente en EC2 usando docker-compose.aws.yml
# Uso (en el servidor): ./scripts/deploy_aws_docker.sh
# Variables: COMPOSE_FILE (default docker-compose.aws.yml), SKIP_BACKUP=1, PROJECT_DIR
# cAdvisor: el script resuelve CADVISOR_IMAGE_TAG (semver sin v): .env, API GitHub, o pin CADVISOR_IMAGE_TAG_FALLBACK.
#
# No hacer `source .env`: contraseñas con $, #, espacios rompen el shell.
# Compose lee variables con --env-file .env

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="${PROJECT_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
cd "$PROJECT_DIR"

# EC2: ec2-user en grupo docker (Docker sin sudo tras nueva sesión SSH o `newgrp docker`).
if id ec2-user >/dev/null 2>&1; then
  sudo usermod -aG docker ec2-user || true
fi

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

set_env_value() {
  local key="$1"
  local value="$2"
  local file="$PROJECT_DIR/.env"
  [[ -f "$file" ]] || touch "$file"
  if grep -qE "^${key}=" "$file"; then
    sed -i "s|^${key}=.*|${key}=${value}|" "$file"
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$file"
  fi
  if [[ "${#COMPOSE_ENV_ARGS[@]}" -eq 0 && -f "$file" ]]; then
    COMPOSE_ENV_ARGS=(--env-file .env)
  fi
}

metadata_token() {
  curl -sf --max-time 2 -X PUT \
    -H "X-aws-ec2-metadata-token-ttl-seconds: 60" \
    "http://169.254.169.254/latest/api/token" 2>/dev/null || true
}

metadata_get() {
  local path="$1"
  local token
  token="$(metadata_token)"
  if [[ -n "$token" ]]; then
    curl -sf --max-time 2 -H "X-aws-ec2-metadata-token: ${token}" \
      "http://169.254.169.254/latest/meta-data/${path}" 2>/dev/null || true
  else
    curl -sf --max-time 2 "http://169.254.169.254/latest/meta-data/${path}" 2>/dev/null || true
  fi
}

public_browser_host() {
  local host
  host="$(read_env_var PUBLIC_ACCESS_HOST "${PUBLIC_ACCESS_HOST:-}")"
  [[ -n "$host" ]] || host="$(metadata_get public-hostname)"
  [[ -n "$host" ]] || host="$(metadata_get public-ipv4)"
  [[ -n "$host" ]] || host="PUBLIC_IP_O_DNS"
  printf '%s' "$host"
}

normalize_public_access_env() {
  local host prometheus_port grafana_port alertmanager_port traffic_ui_port
  host="$(public_browser_host)"
  prometheus_port="$(read_env_var PROMETHEUS_HOST_PORT 9090)"
  grafana_port="$(read_env_var GRAFANA_HOST_PORT 3000)"
  alertmanager_port="$(read_env_var ALERTMANAGER_HOST_PORT 9093)"
  traffic_ui_port="$(read_env_var TRAFFIC_SIMULATOR_UI_HOST_PORT 8890)"

  if [[ "$WANT_MONITORING" == "1" || "$WANT_TRAFFIC" == "1" ]]; then
    set_env_value MONITORING_UI_HOST_BIND "0.0.0.0"
    set_env_value EXPORTER_HOST_BIND "127.0.0.1"
    export MONITORING_UI_HOST_BIND="0.0.0.0"
    export EXPORTER_HOST_BIND="127.0.0.1"
  fi

  if [[ "$WANT_MONITORING" == "1" ]]; then
    set_env_value PROMETHEUS_EXTERNAL_URL "http://${host}:${prometheus_port}"
    set_env_value GRAFANA_EXTERNAL_URL "http://${host}:${grafana_port}"
    export PROMETHEUS_EXTERNAL_URL="http://${host}:${prometheus_port}"
    export GRAFANA_EXTERNAL_URL="http://${host}:${grafana_port}"
  fi

  if [[ "$WANT_TRAFFIC" == "1" ]]; then
    set_env_value TRAFFIC_SIMULATOR_UI_EXTERNAL_URL "http://${host}:${traffic_ui_port}"
    export TRAFFIC_SIMULATOR_UI_EXTERNAL_URL="http://${host}:${traffic_ui_port}"
  fi
}

normalize_alertmanager_smtp_env() {
  local ses_region smtp_smarthost alert_to smtp_from smtp_user smtp_pass derived_smarthost
  ses_region="$(read_env_var SES_SMTP_REGION "${SES_SMTP_REGION:-}")"
  smtp_smarthost="$(read_env_var SMTP_SMARTHOST "${SMTP_SMARTHOST:-}")"

  if [[ -z "$smtp_smarthost" && -n "$ses_region" ]]; then
    derived_smarthost="email-smtp.${ses_region}.amazonaws.com:587"
    set_env_value SMTP_SMARTHOST "$derived_smarthost"
    export SMTP_SMARTHOST="$derived_smarthost"
    smtp_smarthost="$derived_smarthost"
    echo "OK: SMTP_SMARTHOST derivado de SES_SMTP_REGION (${ses_region})."
  fi

  if [[ "$WANT_MONITORING" == "1" ]]; then
    alert_to="$(read_env_var ALERT_EMAIL_TO "${ALERT_EMAIL_TO:-}")"
    smtp_from="$(read_env_var SMTP_FROM "${SMTP_FROM:-}")"
    smtp_user="$(read_env_var SMTP_AUTH_USERNAME "${SMTP_AUTH_USERNAME:-}")"
    smtp_pass="$(read_env_var SMTP_AUTH_PASSWORD "${SMTP_AUTH_PASSWORD:-}")"
    if [[ -n "$alert_to" && ( -z "$smtp_smarthost" || -z "$smtp_from" || -z "$smtp_user" || -z "$smtp_pass" ) ]]; then
      echo "WARN: ALERT_EMAIL_TO esta configurado, pero SMTP/SES esta incompleto; Alertmanager usara modo noop o no podra enviar emails." >&2
      echo "WARN: Para Amazon SES verifica SMTP_FROM, crea credenciales SMTP de SES, revisa sandbox y define SMTP_SMARTHOST o SES_SMTP_REGION." >&2
    fi
  fi
}

authorize_public_ui_ingress() {
  local cidr region az mac sg port out
  command -v aws >/dev/null 2>&1 || { echo "WARN: aws CLI no disponible; no se abre Security Group automaticamente." >&2; return 0; }
  cidr="$(read_env_var MONITORING_SG_CIDR "0.0.0.0/0")"
  az="$(metadata_get placement/availability-zone)"
  [[ -n "$az" ]] || { echo "WARN: no se pudo detectar region EC2; omito Security Group automatico." >&2; return 0; }
  region="${az::-1}"
  mac="$(metadata_get network/interfaces/macs/ | head -1 | tr -d '/')"
  [[ -n "$mac" ]] || { echo "WARN: no se pudo detectar interfaz EC2; omito Security Group automatico." >&2; return 0; }
  for sg in $(metadata_get "network/interfaces/macs/${mac}/security-group-ids"); do
    for port in "$GRAFANA_HOST_PORT" "$PROMETHEUS_HOST_PORT" "$ALERTMANAGER_HOST_PORT" "$TRAFFIC_UI_PORT"; do
      [[ -z "$port" ]] && continue
      if out="$(aws ec2 authorize-security-group-ingress --region "$region" --group-id "$sg" --protocol tcp --port "$port" --cidr "$cidr" 2>&1)"; then
        echo "OK: Security Group ${sg} permite tcp/${port} desde ${cidr}"
      elif [[ "$out" == *InvalidPermission.Duplicate* ]]; then
        echo "OK: Security Group ${sg} ya permitia tcp/${port} desde ${cidr}"
      else
        echo "WARN: no pude abrir tcp/${port} en ${sg}: ${out}" >&2
      fi
    done
  done
}

assert_public_port_bind() {
  local svc="$1"
  local container_port="$2"
  local host_port="$3"
  local out
  out="$(compose port "$svc" "$container_port" 2>/dev/null || true)"
  if [[ -z "$out" ]]; then
    echo "WARN: no pude leer bind de ${svc}:${container_port}" >&2
    return 0
  fi
  if [[ "$out" == 127.0.0.1:* || "$out" == localhost:* ]]; then
    echo "ERROR: ${svc}:${container_port} sigue en loopback (${out}); debe ser publico para navegador." >&2
    return 1
  fi
  echo "OK: ${svc}:${container_port} publicado en ${out}"
}

print_browser_urls() {
  local host
  host="$(public_browser_host)"
  echo
  echo "URLs navegador:"
  if [[ "$WANT_MONITORING" == "1" ]]; then
    echo "  Grafana:      http://${host}:${GRAFANA_HOST_PORT}"
    echo "  Prometheus:   http://${host}:${PROMETHEUS_HOST_PORT}"
    echo "  Alertmanager: http://${host}:${ALERTMANAGER_HOST_PORT}"
  fi
  if [[ "$WANT_TRAFFIC" == "1" ]]; then
    echo "  Traffic UI:   http://${host}:${TRAFFIC_UI_PORT}"
  fi
  echo "Si no abre desde navegador, revisa Security Group/IAM: puertos ${GRAFANA_HOST_PORT},${PROMETHEUS_HOST_PORT},${ALERTMANAGER_HOST_PORT},${TRAFFIC_UI_PORT}."
}

# Pin si no hay .env / API (debe coincidir con docker-compose.aws.yml :-${CADVISOR_IMAGE_TAG:-...}).
CADVISOR_IMAGE_TAG_FALLBACK="${CADVISOR_IMAGE_TAG_FALLBACK:-0.56.2}"

cadvisor_tag_from_github_latest() {
  local json tag
  json="$(curl -sf --max-time 25 --retry 2 \
    -H "Accept: application/vnd.github+json" \
    -H "User-Agent: taller-deploy-aws-docker" \
    "https://api.github.com/repos/google/cadvisor/releases/latest")" || return 1
  if command -v python3 >/dev/null 2>&1; then
    tag="$(printf '%s' "$json" | python3 -c 'import json,sys; print(json.load(sys.stdin).get("tag_name","") or "")' 2>/dev/null || true)"
  fi
  if [[ -z "$tag" ]]; then
    tag="$(printf '%s' "$json" | sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -1)"
  fi
  [[ -n "$tag" ]] || return 1
  printf '%s' "${tag#v}"
}

resolve_and_export_cadvisor_image_tag() {
  local raw resolved
  raw="$(read_env_var CADVISOR_IMAGE_TAG "")"
  raw="${raw#v}"
  if [[ -n "$raw" ]]; then
    export CADVISOR_IMAGE_TAG="$raw"
    return 0
  fi
  if [[ "$WANT_MONITORING" != "1" ]]; then
    export CADVISOR_IMAGE_TAG="$CADVISOR_IMAGE_TAG_FALLBACK"
    return 0
  fi
  resolved="$(cadvisor_tag_from_github_latest 2>/dev/null || true)"
  resolved="${resolved#v}"
  if [[ -z "$resolved" ]]; then
    resolved="$CADVISOR_IMAGE_TAG_FALLBACK"
    echo "cAdvisor: sin CADVISOR_IMAGE_TAG en .env y sin respuesta de GitHub API; usando pin ${resolved}" >&2
  else
    echo "cAdvisor: ultimo release en GitHub -> CADVISOR_IMAGE_TAG=${resolved}" >&2
  fi
  export CADVISOR_IMAGE_TAG="$resolved"
}

preflight_cadvisor_pull() {
  [[ "$WANT_MONITORING" != "1" ]] && return 0
  local ref tag_try pulled=0
  for tag_try in "$CADVISOR_IMAGE_TAG" "$CADVISOR_IMAGE_TAG_FALLBACK"; do
    tag_try="${tag_try#v}"
    [[ -z "$tag_try" ]] && continue
    ref="ghcr.io/google/cadvisor:${tag_try}"
    echo "Preflight: docker pull cAdvisor (${ref})..." >&2
    if docker pull "$ref"; then
      export CADVISOR_IMAGE_TAG="$tag_try"
      pulled=1
      break
    fi
  done
  if [[ "$pulled" != "1" ]]; then
    tag_try="$(cadvisor_tag_from_github_latest 2>/dev/null || true)"
    tag_try="${tag_try#v}"
    if [[ -n "$tag_try" ]]; then
      ref="ghcr.io/google/cadvisor:${tag_try}"
      echo "Preflight: reintento cAdvisor con tag GitHub API (${ref})..." >&2
      if docker pull "$ref"; then
        export CADVISOR_IMAGE_TAG="$tag_try"
        pulled=1
      fi
    fi
  fi
  if [[ "$pulled" != "1" ]]; then
    echo "ERROR: no se pudo descargar ninguna imagen cAdvisor desde ghcr.io/google/cadvisor (red o tags)." >&2
    exit 1
  fi
}

mem_total_mb() {
  awk '/MemTotal:/ { printf "%d", $2 / 1024 }' /proc/meminfo 2>/dev/null || printf '0'
}

swap_total_mb() {
  awk '/SwapTotal:/ { printf "%d", $2 / 1024 }' /proc/meminfo 2>/dev/null || printf '0'
}

memory_budget_mb() {
  local mem swap
  mem="$(mem_total_mb)"
  swap="$(swap_total_mb)"
  printf '%d' "$((mem + swap))"
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
MIN_MONITORING_MEM_MB="$(read_env_var MIN_MONITORING_MEM_MB "${MIN_MONITORING_MEM_MB:-3200}")"
MIN_TRAFFIC_STACK_MEM_MB="$(read_env_var MIN_TRAFFIC_STACK_MEM_MB "${MIN_TRAFFIC_STACK_MEM_MB:-3600}")"
MIN_FREE_DISK_MB="$(read_env_var MIN_FREE_DISK_MB "${MIN_FREE_DISK_MB:-2048}")"

if [[ "$DEPLOY_MONITORING" == "1" ]] && ! profiles_contain monitoring; then
  if [[ -n "$COMPOSE_PROFILES" ]]; then
    COMPOSE_PROFILES="${COMPOSE_PROFILES},monitoring"
  else
    COMPOSE_PROFILES="monitoring"
  fi
fi

MEM_MB="$(mem_total_mb)"
SWAP_MB="$(swap_total_mb)"
MEM_BUDGET_MB="$(memory_budget_mb)"
WANT_MONITORING=0
WANT_TRAFFIC=0
profiles_contain monitoring && WANT_MONITORING=1
profiles_contain traffic && WANT_TRAFFIC=1

if [[ "$WANT_MONITORING" == "1" || "$WANT_TRAFFIC" == "1" ]]; then
  if [[ "$FORCE_MONITORING_ON_LOW_MEM" != "1" && "$MEM_BUDGET_MB" -gt 0 ]]; then
    if [[ "$ALLOW_DEGRADED_STACK" == "1" ]]; then
      need_strip=0
      if [[ "$WANT_MONITORING" == "1" && "$MEM_BUDGET_MB" -lt "$MIN_MONITORING_MEM_MB" ]]; then need_strip=1; fi
      if [[ "$WANT_TRAFFIC" == "1" && "$MEM_BUDGET_MB" -lt "$MIN_TRAFFIC_STACK_MEM_MB" ]]; then need_strip=1; fi
      if [[ "$need_strip" == "1" ]]; then
        echo "WARN: memoria RAM+swap ${MEM_BUDGET_MB}MB (RAM=${MEM_MB}MB swap=${SWAP_MB}MB) insuficiente para perfiles activos; modo degradado -> solo web+mysql." >&2
        COMPOSE_PROFILES=""
        WANT_MONITORING=0
        WANT_TRAFFIC=0
      fi
    else
      if [[ "$WANT_MONITORING" == "1" && "$MEM_BUDGET_MB" -lt "$MIN_MONITORING_MEM_MB" ]]; then
        echo "ERROR: memoria RAM+swap ${MEM_BUDGET_MB}MB (RAM=${MEM_MB}MB swap=${SWAP_MB}MB) < MIN_MONITORING_MEM_MB=${MIN_MONITORING_MEM_MB}. Aumenta instancia/swap, FORCE_MONITORING_ON_LOW_MEM=1, o ALLOW_DEGRADED_STACK=1." >&2
        exit 1
      fi
      if [[ "$WANT_TRAFFIC" == "1" && "$MEM_BUDGET_MB" -lt "$MIN_TRAFFIC_STACK_MEM_MB" ]]; then
        echo "ERROR: memoria RAM+swap ${MEM_BUDGET_MB}MB (RAM=${MEM_MB}MB swap=${SWAP_MB}MB) < MIN_TRAFFIC_STACK_MEM_MB=${MIN_TRAFFIC_STACK_MEM_MB}. Aumenta instancia/swap, FORCE_MONITORING_ON_LOW_MEM=1, o ALLOW_DEGRADED_STACK=1." >&2
        exit 1
      fi
    fi
  fi
fi

export COMPOSE_PROFILES

normalize_public_access_env
normalize_alertmanager_smtp_env
resolve_and_export_cadvisor_image_tag

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

authorize_public_ui_ingress

strict_secret_fail() {
  echo "ERROR: ${1}" >&2
  echo "Corrige .env en el servidor o define SKIP_SECRET_STRICT_CHECK=1 solo en laboratorio." >&2
  exit 1
}

if [[ "$SKIP_SECRET_STRICT_CHECK" != "1" ]]; then
  mp="$(read_env_var MYSQL_PASSWORD "")"
  mrp="$(read_env_var MYSQL_ROOT_PASSWORD "")"
  gap="$(read_env_var GRAFANA_ADMIN_PASSWORD "")"
  [[ "$mp" == *CAMBIAR* || "$mp" == "app_password" || "$mp" == "rootpassword" || -z "$mp" ]] && strict_secret_fail "MYSQL_PASSWORD invalido o placeholder."
  [[ "$mrp" == *CAMBIAR* || "$mrp" == "rootpassword" || "$mrp" == "app_password" || -z "$mrp" ]] && strict_secret_fail "MYSQL_ROOT_PASSWORD invalido o placeholder."
  [[ "$gap" == *CAMBIAR* || "$gap" == "admin123" || -z "$gap" ]] && strict_secret_fail "GRAFANA_ADMIN_PASSWORD invalido o placeholder."
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

preflight_cadvisor_pull

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
compose pull

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
  assert_public_port_bind grafana 3000 "$GRAFANA_HOST_PORT"
  assert_public_port_bind prometheus 9090 "$PROMETHEUS_HOST_PORT"
  assert_public_port_bind alertmanager 9093 "$ALERTMANAGER_HOST_PORT"
fi

if [[ "$WANT_TRAFFIC" == "1" ]]; then
  curl -sf "http://127.0.0.1:${TRAFFIC_UI_PORT}/health.php" >/dev/null || { echo "ERROR: traffic-simulator-ui /health.php no responde" >&2; exit 1; }
  echo "OK: traffic-simulator-ui health (localhost:${TRAFFIC_UI_PORT})"
  assert_public_port_bind traffic-simulator-ui 80 "$TRAFFIC_UI_PORT"
fi

print_browser_urls

echo "Deploy completado."
