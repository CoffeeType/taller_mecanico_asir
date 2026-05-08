#!/bin/bash
# -----------------------------------------------------------------------------
# EC2 User Data — Amazon Linux 2023 (kernel 6.1.x AMI).
#
# References (official AWS):
#   - EC2 user data (shell scripts): https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/user-data.html
#   - Installing Docker on AL2023 (same steps as Amazon ECS guide; yum = dnf on AL2023):
#       https://docs.aws.amazon.com/AmazonECS/latest/developerguide/docker-basics.html#create-container-image-install-docker
#   - AL2023: docker/containerd in core repos (not amazon-linux-extras):
#       https://docs.aws.amazon.com/linux/al2023/ug/ecs.html
#
# Paste as plain text in “User data”; do not enable “already base64 encoded” unless you encoded the file.
# Log: /var/log/taller-ec2-bootstrap.log
# After boot: rotate secrets in /opt/taller_mecanico_asir/.env (from .env.aws.example).
#
# Optional env (export before user data or inject via cloud-init env): overrides for plugin versions.
# -----------------------------------------------------------------------------

exec > >(tee /var/log/taller-ec2-bootstrap.log) 2>&1
set -euxo pipefail

retry() {
  local max="${1:-5}"
  shift
  local delay="${1:-15}"
  shift
  local i=1
  while [[ "$i" -le "$max" ]]; do
    if "$@"; then
      return 0
    fi
    echo "WARN: attempt ${i}/${max} failed: $* ; sleeping ${delay}s" >&2
    sleep "$delay"
    i=$((i + 1))
  done
  return 1
}

ensure_swap() {
  local size_gb="${SWAP_SIZE_GB:-4}"
  local swap_file="${SWAP_FILE:-/swapfile}"
  local desired_bytes current_bytes active

  if [[ "${ENABLE_SWAP:-1}" != "1" ]]; then
    echo "Swap disabled (ENABLE_SWAP=${ENABLE_SWAP})."
    return 0
  fi

  desired_bytes="$((size_gb * 1024 * 1024 * 1024))"
  current_bytes="$(stat -c%s "$swap_file" 2>/dev/null || printf '0')"
  active=0
  if swapon --show=NAME | grep -qx "$swap_file"; then
    active=1
  fi

  if [[ ! -f "$swap_file" || "$current_bytes" -lt "$desired_bytes" ]]; then
    if [[ "$active" == "1" ]]; then
      echo "Resizing active swap ${swap_file} from $((current_bytes / 1024 / 1024))MB to ${size_gb}G."
      swapoff "$swap_file"
      active=0
    else
      echo "Creating ${size_gb}G swap at ${swap_file} to avoid OOM during Docker bootstrap."
    fi
    fallocate -l "${size_gb}G" "$swap_file" || dd if=/dev/zero of="$swap_file" bs=1M count="$((size_gb * 1024))"
    chmod 600 "$swap_file"
    mkswap "$swap_file"
  else
    echo "Swap file ${swap_file} already sized at $((current_bytes / 1024 / 1024))MB."
  fi

  if ! swapon --show=NAME | grep -qx "$swap_file"; then
    swapon "$swap_file"
  fi

  if ! grep -qF "${swap_file} none swap" /etc/fstab; then
    echo "${swap_file} none swap sw 0 0" >> /etc/fstab
  fi

  cat >/etc/sysctl.d/99-taller-swap.conf <<'EOF'
vm.swappiness = 10
vm.vfs_cache_pressure = 50
EOF
  sysctl --system >/dev/null || true
  free -h || true
}

random_secret() {
  local len="${1:-32}"
  local secret
  set +o pipefail
  secret="$(LC_ALL=C tr -dc 'A-Za-z0-9_@%+=:,.~-' </dev/urandom | head -c "$len")"
  set -o pipefail
  printf '%s' "$secret"
}

set_env_value() {
  local file="$1"
  local key="$2"
  local value="$3"
  if grep -qE "^${key}=" "$file"; then
    sed -i "s|^${key}=.*|${key}=${value}|" "$file"
  else
    printf '\n%s=%s\n' "$key" "$value" >> "$file"
  fi
}

read_env_value() {
  local file="$1"
  local key="$2"
  local line val
  [[ -f "$file" ]] || return 1
  while IFS= read -r line || [[ -n "$line" ]]; do
    line="${line//$'\r'/}"
    [[ "$line" =~ ^[[:space:]]*# ]] && continue
    if [[ "$line" == "$key="* ]]; then
      val="${line#*=}"
      printf '%s' "$val"
      return 0
    fi
  done < "$file"
  return 1
}

seed_env_secret_if_placeholder() {
  local file="$1"
  local key="$2"
  local len="${3:-36}"
  local current
  current="$(read_env_value "$file" "$key" || true)"
  if [[ -z "$current" || "$current" == *CAMBIAR* || "$current" == *changeme* || "$current" == "rootpassword" || "$current" == "app_password" || "$current" == "admin123" ]]; then
    set_env_value "$file" "$key" "$(random_secret "$len")"
  fi
}

seed_env_secrets() {
  local file="$1"
  seed_env_secret_if_placeholder "$file" MYSQL_PASSWORD 36
  seed_env_secret_if_placeholder "$file" MYSQL_ROOT_PASSWORD 36
  seed_env_secret_if_placeholder "$file" GRAFANA_ADMIN_PASSWORD 36
  seed_env_secret_if_placeholder "$file" SIMULATOR_CONTROL_TOKEN 48
}

raise_env_min_if_lower() {
  local file="$1"
  local key="$2"
  local minimum="$3"
  local current
  current="$(read_env_value "$file" "$key" || true)"
  if [[ ! "$current" =~ ^[0-9]+$ ]]; then
    set_env_value "$file" "$key" "$minimum"
  elif (( current < minimum )); then
    set_env_value "$file" "$key" "$minimum"
  fi
}

normalize_env_defaults() {
  local file="$1"
  raise_env_min_if_lower "$file" MIN_MONITORING_MEM_MB 3200
  raise_env_min_if_lower "$file" MIN_TRAFFIC_STACK_MEM_MB 3600
}

# --- Config (edit if needed) ---
REPO_URL="https://github.com/CoffeeType/taller_mecanico_asir.git"
GIT_REF="main"

BOOT_USER="ec2-user"
TARGET_DIR="/opt/taller_mecanico_asir"

install_resource_guard() {
  install -m 0755 "${TARGET_DIR}/scripts/taller-docker-safe-mode.sh" /usr/local/sbin/taller-docker-safe-mode

  cat >/etc/systemd/system/taller-docker-safe-mode.service <<EOF
[Unit]
Description=Optional: stop heavy Docker services on low-memory EC2 (only if ALLOW_DEGRADED_STACK=1 in .env)
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
Environment=TALLER_ENV_FILE=${TARGET_DIR}/.env
ExecStart=/usr/local/sbin/taller-docker-safe-mode

[Install]
WantedBy=multi-user.target
EOF
  systemctl daemon-reload
  systemctl enable taller-docker-safe-mode.service
}

# Pin fallback CLI plugins (override if you need a specific release).
DOCKER_COMPOSE_VERSION="${DOCKER_COMPOSE_VERSION:-latest}"
DOCKER_BUILDX_VERSION="${DOCKER_BUILDX_VERSION:-v0.19.3}"

# Small EC2 instances can lock up while building/pulling and starting MySQL + monitoring.
ensure_swap

# AWS ECS / AL2023: “Update the installed packages and package cache” (yum update -y).
retry 5 20 dnf update -y

# Extra tooling for this project (not in ECS Docker snippet): Instance Connect + git + httpd fallback.
# Do not install package `curl`: AL2023 ships curl-minimal (/usr/bin/curl); package `curl` conflicts with it.
retry 5 10 dnf install -y ec2-instance-connect git httpd
# Docker publishes the app on port 80; keep host httpd installed but inactive unless used manually.
systemctl disable --now httpd || true

# AWS ECS / AL2023: “Install the most recent Docker Community Edition package” (yum install docker).
if ! command -v docker >/dev/null 2>&1; then
  retry 5 10 dnf install -y docker
fi

# AWS ECS: “Start the Docker service” (service docker start). On systemd, enable so it survives reboot:
systemctl enable --now docker

# AWS ECS: add ec2-user to docker group.
# Then close the SSH session with `exit` and reconnect so group membership applies.
sudo usermod -aG docker ec2-user || true

# Docker Compose V2: not in the ECS “install Docker” steps above.
mkdir -p /usr/libexec/docker/cli-plugins
if ! docker compose version >/dev/null 2>&1; then
  retry 3 10 dnf install -y docker-compose-plugin || true
fi
if ! docker compose version >/dev/null 2>&1; then
  ARCH="$(uname -m)"
  if [[ "${DOCKER_COMPOSE_VERSION}" == "latest" ]]; then
    COMPOSE_URL="https://github.com/docker/compose/releases/latest/download/docker-compose-linux-${ARCH}"
  else
    COMPOSE_URL="https://github.com/docker/compose/releases/download/${DOCKER_COMPOSE_VERSION}/docker-compose-linux-${ARCH}"
  fi
  retry 5 15 curl -fSL \
    "${COMPOSE_URL}" \
    -o /usr/libexec/docker/cli-plugins/docker-compose
  chmod +x /usr/libexec/docker/cli-plugins/docker-compose
fi

# Buildx: `docker compose build` requires buildx >= 0.17 with recent Docker
buildx_meets_minimum() {
  local cur
  cur="$(docker buildx version 2>/dev/null | grep -oE 'v[0-9]+\.[0-9]+\.[0-9]+' | head -1)"
  [[ -n "$cur" ]] || return 1
  [[ "$(printf '%s\n' 'v0.17.0' "$cur" | sort -V | head -n1)" == "v0.17.0" ]]
}
if ! buildx_meets_minimum; then
  retry 3 10 dnf install -y docker-buildx-plugin || true
fi
if ! buildx_meets_minimum; then
  case "$(uname -m)" in
    x86_64) BX_ARCH=amd64 ;;
    aarch64) BX_ARCH=arm64 ;;
    *) BX_ARCH=$(uname -m) ;;
  esac
  retry 5 15 curl -fSL \
    "https://github.com/docker/buildx/releases/download/${DOCKER_BUILDX_VERSION}/buildx-${DOCKER_BUILDX_VERSION}.linux-${BX_ARCH}" \
    -o /usr/libexec/docker/cli-plugins/docker-buildx
  chmod +x /usr/libexec/docker/cli-plugins/docker-buildx
fi

# Verify toolchain before deploy (fail fast with clear log).
docker version
docker compose version
docker buildx version

install -d -o "${BOOT_USER}" -g "${BOOT_USER}" "${TARGET_DIR}"
if [[ ! -d "${TARGET_DIR}/.git" ]]; then
  retry 5 20 runuser -u "${BOOT_USER}" -- git clone --depth 1 --branch "${GIT_REF}" "${REPO_URL}" "${TARGET_DIR}"
fi

if [[ ! -f "${TARGET_DIR}/.env" ]]; then
  runuser -u "${BOOT_USER}" -- cp "${TARGET_DIR}/.env.aws.example" "${TARGET_DIR}/.env"
fi
seed_env_secrets "${TARGET_DIR}/.env"
normalize_env_defaults "${TARGET_DIR}/.env"
chown "${BOOT_USER}:${BOOT_USER}" "${TARGET_DIR}/.env"
chmod 600 "${TARGET_DIR}/.env"

install_resource_guard

cd "${TARGET_DIR}"
chmod +x scripts/deploy_aws_docker.sh scripts/taller-docker-safe-mode.sh

deploy_hook_fail() {
  echo "ERROR: deploy_aws_docker.sh failed; extra diagnostics:" >&2
  docker compose --env-file .env -f docker-compose.aws.yml ps -a || true
  local s
  while read -r s; do
    [[ -z "${s}" ]] && continue
    echo "--- logs ${s} ---" >&2
    docker compose --env-file .env -f docker-compose.aws.yml logs --tail 160 "${s}" 2>/dev/null || true
  done < <(docker compose --env-file .env -f docker-compose.aws.yml config --services 2>/dev/null || true)
}

# Perfiles y secretos vienen de .env (por defecto full stack en .env.aws.example). Rotar claves antes de produccion.
if ! SKIP_BACKUP=1 ./scripts/deploy_aws_docker.sh; then
  deploy_hook_fail
  exit 1
fi

echo "Bootstrap done. Log: /var/log/taller-ec2-bootstrap.log"
echo "Note for SSH: user ${BOOT_USER} was added to group docker. Run 'exit' and open a NEW SSH session (or run: newgrp docker) before using docker without sudo."
