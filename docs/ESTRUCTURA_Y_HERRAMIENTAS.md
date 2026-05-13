# Estructura del repositorio y herramientas

Este documento resume **qué hay en cada carpeta** y **qué herramienta encaja en qué fase** (desarrollo, despliegue, monitorización, pruebas de carga). Para guías paso a paso, enlaza con los `.md` citados al final.

## Vista rápida por capas

| Capa | Rutas principales | Herramientas |
|------|-------------------|--------------|
| Aplicación web | Raíz del repo (`*.php`), `includes/`, `assets/` | PHP 8.x, Apache, HTML/CSS/JS |
| Configuración | `config/` | `database.php`, variables de entorno (ver `config/load_env.php`, `config/env.php`) |
| Administración y API | `admin/`, `api/` | Sesiones PHP, endpoints JSON/HTML |
| Datos | `database/` | MySQL, script `database.sql`, herramientas en `scripts/` (generación, comprobaciones) |
| Contenedores | `Dockerfile`, `docker-compose*.yml`, `docker/` | Docker, Docker Compose, perfiles (`traffic`, stacks Coolify/AWS) |
| Monitorización | `monitoring/` | Prometheus, Grafana, Alertmanager, exporters (`php-exporter`, blackbox, node, mysqld, cAdvisor, Telegraf según compose) |
| Carga (JMeter) | `docker/traffic-simulator/`, `docker/traffic-simulator-ui/`, `scripts/run_jmeter_traffic.php` | Apache JMeter en contenedor, UI web opcional |
| Automatización / operación | `scripts/` | PowerShell/Bash/PHP para despliegue AWS, simulación de tráfico, comprobaciones |

## Aplicación (`/` del repo)

- **`index.php`, `login.php`, `registro.php`, `perfil.php`, `noticias.php`, `citaciones.php`, …** — Páginas públicas o de usuario.
- **`includes/`** — Cabecera, pie, funciones comunes (`functions.php`), importación de noticias, logging de métricas HTTP (`metrics_logger.php` cuando aplique).
- **`config/`** — Conexión a BD y carga de `.env` para Docker o entornos con variables.
- **`assets/`** — Estáticos servidos por Apache (`css/`, `images/`).

## Panel admin y API

- **`admin/`** — Citas, noticias, usuarios, utilidades (p. ej. prueba de correo de alertas si está desplegado el stack de monitorización).
- **`api/`** — Endpoints consumidos por la app o integraciones (p. ej. citas).

## Base de datos

- **`database/database.sql`** — Esquema y datos iniciales orientativos.
- Scripts relacionados en **`scripts/`** (`generate_sql.js`, `db_tool.js`, comprobaciones PHP de admin/BD, etc.).

## Docker

- **`docker-compose.yml`** — Desarrollo local: `web`, `mysql`, stack de monitorización opcional, perfil **`traffic`** para JMeter (no arranca sin `--profile traffic`).
- **`docker-compose.aws.yml`**, **`docker-compose.coolify*.yml`**, **`docker-compose.dokploy.yml`** — Variantes de producción o plataforma.
- **`docker/`** — Dockerfiles auxiliares, `init-db.sh`, entrypoints del simulador, imágenes de la UI de tráfico.

Variables de ejemplo: **`.env.example`**, **`.env.aws.example`**.

## Monitorización (`monitoring/`)

- **`monitoring/prometheus/`** — `prometheus.yml`, reglas de alerta (`alerts.yml`), variantes por entorno.
- **`monitoring/grafana/`** — Dashboards JSON (`dashboards/taller-mecanico-dashboard.json`) y provisioning (`provisioning/`).
- **`monitoring/alertmanager/`** — Plantilla y entrypoint que inyectan SMTP (o modo *noop* si faltan datos).
- **`monitoring/php-exporter/`** — Métricas expuestas para Prometheus desde PHP.

Guía detallada: [MONITORING_SETUP_GUIDE.md](MONITORING_SETUP_GUIDE.md).

## Pruebas de carga (Apache JMeter)

- **Worker:** servicio Compose `traffic-simulator` (API interna de control).
- **UI web:** `traffic-simulator-ui`, puerto publicado en el host (por defecto **8890**). En [`docker-compose.yml`](../docker-compose.yml) la variable es `TRAFFIC_SIMULATOR_UI_PORT`; en [`docker-compose.aws.yml`](../docker-compose.aws.yml) y `.env` de EC2 es `TRAFFIC_SIMULATOR_UI_HOST_PORT` (mismo rol, distinto nombre).
- **Scripts:** `scripts/start-jmeter-ui.ps1`, `start-jmeter-ui.bat`, `scripts/run_jmeter_traffic.php`.

Documentación: [TRAFFIC_SIMULATOR.md](TRAFFIC_SIMULATOR.md).

## Scripts (`scripts/`)

Incluye, entre otros: despliegue AWS (`deploy_aws_docker.sh`, `ec2-user-data-bootstrap.sh`), simulación de tráfico, comprobaciones PHP, utilidades de base de datos. Convención: prefijos por tarea (`deploy_*`, `simulate_*`, `run_jmeter_*`, etc.).

## Documentación en `docs/`

| Fichero | Contenido |
|---------|-----------|
| [DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md) | Instalación con Docker en local |
| [AWS_DOCKER_DEPLOYMENT.md](AWS_DOCKER_DEPLOYMENT.md) | EC2, variables, SES/SMTP, troubleshooting |
| [MONITORING_SETUP_GUIDE.md](MONITORING_SETUP_GUIDE.md) | Prometheus, Grafana, Alertmanager, correo |
| [TRAFFIC_SIMULATOR.md](TRAFFIC_SIMULATOR.md) | Perfil `traffic`, seguridad, volúmenes de logs |
| [STACK_TECNOLOGICO.md](STACK_TECNOLOGICO.md) | Detalle de stack y decisiones |
| [GUIA_USUARIO.md](GUIA_USUARIO.md) | Uso de la aplicación |
| [GUIA_DESPLIEGUE_LOCAL.md](GUIA_DESPLIEGUE_LOCAL.md) | XAMPP / Windows sin Docker |

## CI / verificación de builds

La integración con **GitHub Actions** se ha retirado del repositorio. La verificación de imágenes Docker puede hacerse localmente con:

```bash
docker compose --profile traffic build
```

(o el conjunto de ficheros `docker-compose*.yml` que uses en tu entorno).

## Herramientas `tools/`

- **`tools/update-pfc-docx.ps1`** — Actualizaciones puntuales del documento PFC (diagramas, índices, texto) sobre el `.docx` en `docs/`.
