# AMI Ubuntu con Docker Engine + Compose plugin para desplegar taller_mecanico_asir en EC2.
# Coste de build: instancia EC2 + EBS durante el provisionamiento (10-30 min tipico).
#
# Requisitos: AWS credenciales (env o ~/.aws/credentials), packer init/build.
#
#   cd packer
#   packer init aws-docker-ami.pkr.hcl
#   packer validate aws-docker-ami.pkr.hcl
#   packer build aws-docker-ami.pkr.hcl

packer {
  required_plugins {
    amazon = {
      source  = "github.com/hashicorp/amazon"
      version = "~> 1.3"
    }
  }
}

variable "region" {
  type        = string
  description = "Region AWS donde construir la AMI"
  default     = "eu-west-1"
}

locals {
  timestamp = regex_replace(timestamp(), "[- TZ:]", "")
}

source "amazon-ebs" "ubuntu" {
  region        = var.region
  instance_type = "t3.micro"

  source_ami_filter {
    filters = {
      name                = "ubuntu/images/*ubuntu-jammy-22.04-amd64-server-*"
      root-device-type    = "ebs"
      virtualization-type = "hvm"
    }
    most_recent = true
    owners      = ["099720109477"] # Canonical
  }

  ssh_username = "ubuntu"
  ami_name     = "taller-mecanico-docker-${local.timestamp}"

  tags = {
    Name      = "taller-mecanico-docker-base"
    Project   = "taller_mecanico_asir"
    BuildDate = local.timestamp
  }
}

build {
  sources = ["source.amazon-ebs.ubuntu"]

  provisioner "shell" {
    inline = [
      "sudo apt-get update",
      "sudo DEBIAN_FRONTEND=noninteractive apt-get upgrade -y",
      "sudo apt-get install -y ca-certificates curl gnupg git",
      "sudo install -m 0755 -d /etc/apt/keyrings",
      "curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg",
      "echo \"deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo \"$VERSION_CODENAME\") stable\" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null",
      "sudo apt-get update",
      "sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin",
      "sudo usermod -aG docker ubuntu",
      "sudo systemctl enable docker",
      "sudo systemctl start docker",
      "docker --version",
      "docker compose version"
    ]
  }
}
