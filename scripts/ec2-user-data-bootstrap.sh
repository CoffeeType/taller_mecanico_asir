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
  local size_gb="${SWAP_SIZE_GB:-2}"
  local swap_file="${SWAP_FILE:-/swapfile}"

  if [[ "${ENABLE_SWAP:-1}" != "1" ]]; then
    echo "Swap disabled (ENABLE_SWAP=${ENABLE_SWAP})."
    return 0
  fi

  if swapon --show=NAME | grep -qx "$swap_file"; then
    echo "Swap already active at ${swap_file}."
    return 0
  fi

  if [[ ! -f "$swap_file" ]]; then
    echo "Creating ${size_gb}G swap at ${swap_file} to avoid OOM during Docker bootstrap."
    fallocate -l "${size_gb}G" "$swap_file" || dd if=/dev/zero of="$swap_file" bs=1M count="$((size_gb * 1024))"
    chmod 600 "$swap_file"
    mkswap "$swap_file"
  fi

  swapon "$swap_file" || true
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

# Pin fallback CLI plugins (override if Compose demands newer Buildx).
DOCKER_COMPOSE_VERSION="${DOCKER_COMPOSE_VERSION:-v5.1.3}"
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

# AWS ECS: add ec2-user to docker group (logout/login applies group; deploy runs as root here).
usermod -a -G docker "${BOOT_USER}" || true

# Docker Compose V2: not in the ECS “install Docker” steps above.
mkdir -p /usr/libexec/docker/cli-plugins
if ! docker compose version >/dev/null 2>&1; then
  retry 3 10 dnf install -y docker-compose-plugin || true
fi
if ! docker compose version >/dev/null 2>&1; then
  ARCH="$(uname -m)"
  retry 5 15 curl -fSL \
    "https://github.com/docker/compose/releases/download/${DOCKER_COMPOSE_VERSION}/docker-compose-linux-${ARCH}" \
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
echo "Note for SSH: user ${BOOT_USER} was added to group docker. Open a NEW SSH session (or run: newgrp docker) before using docker without sudo."
