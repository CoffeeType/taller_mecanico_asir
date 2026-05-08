#!/usr/bin/env bash
# Ejecutado por systemd al arrancar (Amazon Linux / EC2).
# Por defecto NO para contenedores: full stack debe mantenerse.
# Si ALLOW_DEGRADED_STACK=1 y RAM baja, para monitorizacion + trafico + cadvisor + telegraf.

set -euo pipefail

ENV_FILE="${TALLER_ENV_FILE:-/opt/taller_mecanico_asir/.env}"

read_kv() {
  local key="$1"
  local def="${2:-}"
  local line val
  [[ -f "$ENV_FILE" ]] || { printf '%s' "$def"; return 0; }
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
  done < "$ENV_FILE"
  printf '%s' "$def"
}

ALLOW="$(read_kv ALLOW_DEGRADED_STACK 0)"
if [[ "$ALLOW" != "1" ]]; then
  exit 0
fi

MIN_MB="$(read_kv MIN_MONITORING_MEM_MB 1900)"
FORCE="$(read_kv FORCE_MONITORING_ON_LOW_MEM 0)"
MEM_MB="$(awk '/MemTotal:/ { printf "%d", $2 / 1024 }' /proc/meminfo 2>/dev/null || printf '0')"

if [[ "$FORCE" == "1" || "$MEM_MB" -eq 0 || "$MEM_MB" -ge "$MIN_MB" ]]; then
  exit 0
fi

echo "Low memory (${MEM_MB}MB < ${MIN_MB}MB) and ALLOW_DEGRADED_STACK=1: stopping optional Docker services."

mapfile -t containers < <(docker ps -a --format '{{.Names}}' | grep -E '^taller_mecanico_asir-(prometheus|grafana|alertmanager|node-exporter|mysqld-exporter|blackbox-exporter|cadvisor|telegraf|traffic-simulator|traffic-simulator-ui)-[0-9]+$' || true)
if [[ "${#containers[@]}" -eq 0 ]]; then
  exit 0
fi

docker update --restart=no "${containers[@]}" >/dev/null || true
docker stop --time 20 "${containers[@]}" >/dev/null || true
