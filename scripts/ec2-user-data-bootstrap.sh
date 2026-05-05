#!/bin/bash
# -----------------------------------------------------------------------------
# EC2 User Data - Ubuntu 22.04 LTS (or Packer AMI with Docker preinstalled).
# Paste into EC2 "User data" as plain text, or upload this file without the
# "already base64 encoded" option (unless you pre-encoded the whole file).
# See docs/AWS_DOCKER_DEPLOYMENT.md
#
# Optional: change REPO_URL or GIT_REF for a fork or another branch.
# After first boot: rotate secrets in /opt/taller_mecanico_asir/.env
# (bootstrap uses placeholders from .env.aws.example).
# -----------------------------------------------------------------------------

exec > >(tee /var/log/taller-ec2-bootstrap.log) 2>&1
set -euxo pipefail

# --- Config (edit if needed) ---
REPO_URL="https://github.com/CoffeeType/taller_mecanico_asir.git"
GIT_REF="main"

export DEBIAN_FRONTEND=noninteractive
TARGET_DIR="/opt/taller_mecanico_asir"

apt-get update -qq

# EC2 Instance Connect: accept ephemeral SSH keys (console / aws ec2-instance-connect send-ssh-public-key)
apt-get install -y -qq ec2-instance-connect

if ! command -v git >/dev/null 2>&1; then
  apt-get install -y -qq git
fi

# Install Docker if missing (doc step 2)
if ! command -v docker >/dev/null 2>&1; then
  apt-get install -y -qq ca-certificates curl gnupg
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  # shellcheck disable=SC1091
  . /etc/os-release
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" >/etc/apt/sources.list.d/docker.list
  apt-get update -qq
  apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi

usermod -aG docker ubuntu || true

install -d -o ubuntu -g ubuntu "${TARGET_DIR}"
if [ ! -d "${TARGET_DIR}/.git" ]; then
  sudo -u ubuntu git clone --depth 1 --branch "${GIT_REF}" "${REPO_URL}" "${TARGET_DIR}"
fi

if [ ! -f "${TARGET_DIR}/.env" ]; then
  sudo -u ubuntu cp "${TARGET_DIR}/.env.aws.example" "${TARGET_DIR}/.env"
fi

cd "${TARGET_DIR}"
chmod +x scripts/deploy_aws_docker.sh
SKIP_BACKUP=1 ./scripts/deploy_aws_docker.sh

echo "Bootstrap done. Log: /var/log/taller-ec2-bootstrap.log"
