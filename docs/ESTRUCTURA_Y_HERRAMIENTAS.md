# Estructura del repositorio y herramientas

Este documento resume **qué hay en cada carpeta** y **qué herramienta encaja en qué fase** (desarrollo, despliegue, monitorización, pruebas de carga). Para el glosario de dominio, ver [CONTEXT.md](../CONTEXT.md) en la raíz del repo. Para guías paso a paso, enlaza con los `.md` citados al final.

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
- **`css/`**, **`img/`** — Hoja de estilos y branding enlazados desde `includes/header.php`.
- **`assets/images/`** — Imágenes subidas por la aplicación (noticias, consejos).

## Panel admin y API

- **`admin/`** — Panel de administración **canónico** (citas, noticias, usuarios, consejos). Es la ruta enlazada desde la navegación de la app.
- **`api/`** — Endpoints consumidos por la app o integraciones (p. ej. `citas_api.php`).

### PHP legacy en la raíz (`*-administracion.php`)

Conservados por compatibilidad con capturas o enlaces del PFC v1; **no** los uses en operación ni en documentación nueva.

| Fichero legacy (raíz) | Sustituto canónico |
|----------------------|-------------------|
| `usuarios-administracion.php` | `admin/usuarios.php` |
| `citas-administracion.php` | `admin/citas.php` |
| `noticias-administracion.php` | `admin/noticias.php` |

No elimines estos ficheros sin revisar referencias en el COPIAPFC; el código activo y el menú Admin apuntan solo a `admin/`.

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
- **Scripts:** `scripts/start-jmeter-ui.ps1`, `scripts/print-jmeter-usage.sh`, `scripts/run_jmeter_traffic.php`.

Documentación: [TRAFFIC_SIMULATOR.md](TRAFFIC_SIMULATOR.md) (guía rápida del operador al inicio; referencia técnica al final).

## Scripts (`scripts/`)

Incluye, entre otros: despliegue AWS (`deploy_aws_docker.sh`, `ec2-user-data-bootstrap.sh`), simulación de tráfico, comprobaciones PHP, utilidades de base de datos. Convención: prefijos por tarea (`deploy_*`, `simulate_*`, `run_jmeter_*`, etc.).

- **`scripts/verify_local.ps1`** — Verificación local: compose, JSON Grafana, `php -l`, tests de `traffic_simulator_lib` y smoke HTTP opcional (incluye `tests/test_booking_conflict.php` si la app está arriba).
- **`scripts/scan_copiapfc_docx.ps1`** — Comprobación rápida del COPIAPFC (rutas `admin/` y términos corregidos en `word/document.xml`).

## Tests (`tests/`)

Pruebas ad hoc sin PHPUnit (código de salida 0/1):

| Fichero | Qué verifica |
|---------|----------------|
| `tests/test_traffic_simulator_lib.php` | Lógica del simulador JMeter (parseo de logs, validación URL, import JTL) |
| `tests/booking_api_client.php` | Helpers HTTP compartidos para pruebas de `citas_api` |
| `tests/test_booking_access.php` | Smoke GET `citas_api` → JSON con clave `booked` |
| `tests/test_booking_roundtrip.php` | POST citación → GET del mes incluye la franja reservada |
| `tests/test_booking_validation.php` | Citación inválida (campos, pasado, invitado incompleto) → HTTP 400 |
| `tests/test_booking_conflict.php` | Segunda citación en la misma franja → HTTP 409 (requiere app en marcha) |
| `tests/test_perfil_requires_login.php` | GET `perfil.php` sin sesión → redirección a `login.php` |

## Documentación en `docs/`

Índice resumido: [README.md](README.md) (tabla de todas las guías).

| Fichero | Contenido |
|---------|-----------|
| [README.md](README.md) | Índice de documentación en `docs/` |
| [../CONTEXT.md](../CONTEXT.md) | Glosario de dominio (lenguaje ubicuo) |
| [DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md) | Instalación con Docker en local |
| [AWS_DOCKER_DEPLOYMENT.md](AWS_DOCKER_DEPLOYMENT.md) | EC2, variables, SES/SMTP, troubleshooting |
| [COOLIFY_DEPLOYMENT.md](COOLIFY_DEPLOYMENT.md) | Despliegue con Coolify |
| [MONITORING_SETUP_GUIDE.md](MONITORING_SETUP_GUIDE.md) | Prometheus, Grafana, Alertmanager, correo |
| [MONITORING_CONTAINER_METRICS_RUNBOOK.md](MONITORING_CONTAINER_METRICS_RUNBOOK.md) | Runbook de métricas de contenedores |
| [TRAFFIC_SIMULATOR.md](TRAFFIC_SIMULATOR.md) | Guía rápida JMeter (operador), checklist de métricas, API y volúmenes de logs |
| [STACK_TECNOLOGICO.md](STACK_TECNOLOGICO.md) | Detalle de stack tecnológico |
| [GUIA_USUARIO.md](GUIA_USUARIO.md) | Uso de la aplicación |
| [GUIA_DESPLIEGUE_LOCAL.md](GUIA_DESPLIEGUE_LOCAL.md) | XAMPP / Windows sin Docker |
| [INSTALL.md](INSTALL.md) | Instalación rápida sin Docker |
| [adr/0001-jmeter-log-pipeline-for-traffic-metrics.md](adr/0001-jmeter-log-pipeline-for-traffic-metrics.md) | ADR: logs HTTP + JMeter para métricas de tráfico |

### Histórico (no es procedimiento)

| Fichero | Contenido |
|---------|-----------|
| [CHANGELOG.md](CHANGELOG.md) | Registro de versiones (v1.0.0 → estado actual) |

## CI / verificación de builds

La integración con **GitHub Actions** se ha retirado del repositorio. La verificación de imágenes Docker puede hacerse localmente con:

```bash
docker compose --profile traffic build
```

Comandos operativos documentados con **`docker compose`** (V2). El alias `docker-compose` no se usa en las guías salvo al nombrar ficheros `docker-compose*.yml`.

(o el conjunto de ficheros `docker-compose*.yml` que uses en tu entorno).

## Herramientas `tools/`

- **`tools/update-pfc-docx.ps1`** — Actualizaciones puntuales del COPIAPFC (diagramas, índices, texto) sobre [`COPIAPFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells_con_diagramas.docx`](COPIAPFC_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells_con_diagramas.docx). Parámetro opcional `-DocxPath` para otro `.docx`.
- **`tools/generate_defense_pptx.py`** — Genera la presentación de defensa (`docs/PFC_Defensa_Taller_Mecanico_ASIR_Antonio_Corredera_Cubells.pptx`) a partir del COPIAPFC; requiere `python -m pip install python-pptx pillow`.

## Fuentes Mermaid del PFC (`docs/mermaid/`)

- **`pfc-fig23-ec2.mmd`**, **`pfc-fig24-jmeter.mmd`** — Diagramas fuente para figuras del COPIAPFC; la regeneración del `.docx` se hace con `tools/update-pfc-docx.ps1`.
