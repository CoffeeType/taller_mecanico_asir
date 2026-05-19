# Documentación del proyecto

Índice de guías operativas en castellano. Glosario de dominio: [CONTEXT.md](../CONTEXT.md). Mapa del repositorio: [ESTRUCTURA_Y_HERRAMIENTAS.md](ESTRUCTURA_Y_HERRAMIENTAS.md).

## Convención Docker

Usa **`docker compose`** (V2) en los ejemplos de las guías. Los manifiestos del proyecto se llaman `docker-compose*.yml`.

## Instalación y despliegue

| Guía | Cuándo usarla |
|------|----------------|
| [INSTALL.md](INSTALL.md) | Instalación rápida sin Docker |
| [GUIA_DESPLIEGUE_LOCAL.md](GUIA_DESPLIEGUE_LOCAL.md) | XAMPP en Windows (paso a paso) |
| [DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md) | Docker en local (recomendado) |
| [AWS_DOCKER_DEPLOYMENT.md](AWS_DOCKER_DEPLOYMENT.md) | EC2 + Docker Compose + script `deploy_aws_docker.sh` |
| [COOLIFY_DEPLOYMENT.md](COOLIFY_DEPLOYMENT.md) | Plataforma Coolify |

## Uso y técnica

| Guía | Contenido |
|------|-----------|
| [GUIA_USUARIO.md](GUIA_USUARIO.md) | Visitantes, usuarios y administradores |
| [STACK_TECNOLOGICO.md](STACK_TECNOLOGICO.md) | Stack LAMP, Docker y dependencias |
| [MONITORING_SETUP_GUIDE.md](MONITORING_SETUP_GUIDE.md) | Prometheus, Grafana, Alertmanager |
| [MONITORING_CONTAINER_METRICS_RUNBOOK.md](MONITORING_CONTAINER_METRICS_RUNBOOK.md) | Métricas por contenedor (Telegraf/cAdvisor) |
| [TRAFFIC_SIMULATOR.md](TRAFFIC_SIMULATOR.md) | Apache JMeter (perfil `traffic`) |

## Entregables académicos (PFC)

En esta carpeta, sin versionar artefactos temporales de edición:

- `COPIAPFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells_con_diagramas.docx` — memoria en Word (fuente maestra); parches automatizados con [`tools/update-pfc-docx.ps1`](../tools/update-pfc-docx.ps1).
- `COPIAPFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells.pdf` — exportación PDF opcional (Word COM: [`tools/export-pfc-pdf.ps1`](../tools/export-pfc-pdf.ps1)).
- `PFC_Defensa_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells.pptx`
- `Guia PFC 2020_21 revisada Marzo 2024.pdf`

## Histórico

- [CHANGELOG.md](CHANGELOG.md) — versiones del proyecto
- [adr/](adr/) — decisiones de arquitectura
