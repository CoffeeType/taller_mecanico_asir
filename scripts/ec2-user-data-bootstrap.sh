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
# -----------------------------------------------------------------------------

exec > >(tee /var/log/taller-ec2-bootstrap.log) 2>&1
set -euxo pipefail

# --- Config (edit if needed) ---
REPO_URL="https://github.com/CoffeeType/taller_mecanico_asir.git"
GIT_REF="main"

BOOT_USER="ec2-user"
TARGET_DIR="/opt/taller_mecanico_asir"

# AWS ECS / AL2023: “Update the installed packages and package cache” (yum update -y).
dnf update -y

# Extra tooling for this project (not in ECS Docker snippet): Instance Connect + git.
# Do not install package `curl`: AL2023 ships curl-minimal (/usr/bin/curl); package `curl` conflicts with it.
dnf install -y ec2-instance-connect git

# AWS ECS / AL2023: “Install the most recent Docker Community Edition package” (yum install docker).
if ! command -v docker >/dev/null 2>&1; then
  dnf install -y docker
fi

# AWS ECS: “Start the Docker service” (service docker start). On systemd, enable so it survives reboot:
systemctl enable --now docker

# AWS ECS: add ec2-user to docker group (logout/login applies group; deploy runs as root here).
usermod -a -G docker "${BOOT_USER}" || true

# Docker Compose V2: not in the ECS “install Docker” steps above.
# Prefer Amazon Linux package when present; else plugin install per Docker Compose docs:
# https://docs.docker.com/compose/install/linux/#install-the-plugin-manually
mkdir -p /usr/libexec/docker/cli-plugins
if ! docker compose version >/dev/null 2>&1; then
  dnf install -y docker-compose-plugin || true
fi
if ! docker compose version >/dev/null 2>&1; then
  ARCH="$(uname -m)"
  curl -fSL "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-${ARCH}" \
    -o /usr/libexec/docker/cli-plugins/docker-compose
  chmod +x /usr/libexec/docker/cli-plugins/docker-compose
fi

install -d -o "${BOOT_USER}" -g "${BOOT_USER}" "${TARGET_DIR}"
if [ ! -d "${TARGET_DIR}/.git" ]; then
  runuser -u "${BOOT_USER}" -- git clone --depth 1 --branch "${GIT_REF}" "${REPO_URL}" "${TARGET_DIR}"
fi

if [ ! -f "${TARGET_DIR}/.env" ]; then
  runuser -u "${BOOT_USER}" -- cp "${TARGET_DIR}/.env.aws.example" "${TARGET_DIR}/.env"
fi

cd "${TARGET_DIR}"
chmod +x scripts/deploy_aws_docker.sh
SKIP_BACKUP=1 ./scripts/deploy_aws_docker.sh

echo "Bootstrap done. Log: /var/log/taller-ec2-bootstrap.log"
