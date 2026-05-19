# Simulador de tráfico con Apache JMeter

En la memoria del proyecto, esta capacidad se describe como **pruebas de carga con Apache JMeter**. En Docker Compose el perfil y los servicios son `traffic`, `traffic-simulator` (worker) y `traffic-simulator-ui` (interfaz web). El worker ejecuta **JMeter en modo CLI (sin GUI)** y vuelca resultados al mismo formato de logs que consume la aplicación para Prometheus (`logs/metrics.log`, `logs/response_time.log`).

**Guía rápida (operador):** sigue los pasos numerados de la interfaz web (Destino → Carga → Ejecutar → Resultados → Grafana). El detalle técnico (API interna, variables, CLI) está al final de este documento.

---

## Guía rápida (menos de cinco minutos)

La interfaz web replica el flujo de una prueba controlada como si la aplicación estuviera expuesta: genera peticiones HTTP reales, las registra con `source=simulator` y permite verlas en la misma cadena **logs → `metrics.php` → Prometheus → Grafana** que el tráfico real (`source=app`).

### Local (Windows)

1. Ejecuta `.\scripts\start-jmeter-ui.ps1` (arranca Docker si hace falta, levanta `web`, `mysql`, `traffic-simulator`, `traffic-simulator-ui` y abre el navegador). Alternativa: `docker compose --profile traffic up -d` tras copiar `.env.example` a `.env`.
2. Abre **http://localhost:8890** (o el puerto definido en `TRAFFIC_SIMULATOR_UI_PORT`).
3. **Paso 1 — Destino:** URL base `http://web` (nombre del servicio de la app en la misma red Docker) o, si solo quieres probar la app publicada, la URL pública correspondiente.
4. **Paso 2 — Carga:** elige perfil (Normal, Burst o Idle), usuarios concurrentes (1–20) y duración (5–300 s).
5. **Paso 3 — Ejecutar:** pulsa **Comprobar destino**; luego **Iniciar prueba**. Usa **Detener** si necesitas cortar antes de tiempo.
6. **Paso 4 — Resultados:** revisa estado, éxitos/errores y la vista previa de `metrics.log` en el panel derecho.
7. **Paso 5 — Grafana e informe JMeter:** abre el enlace a Grafana (si el perfil `monitoring` está activo), entra en el dashboard principal y baja hasta la fila **Simulador** (métricas con `source=simulator`). Cuando la prueba termina, el enlace **Dashboard JMeter** abre el informe HTML generado bajo `logs/jmeter/.../html-report` (si `SIM_JMETER_HTML_REPORT=true`).

### Local (Linux / macOS) o manual

1. Copia variables desde `.env.example` a `.env` y ajusta al menos `SIMULATOR_CONTROL_TOKEN`.
2. `docker compose --profile traffic up -d` (servicios `web`, `mysql`, `traffic-simulator`, `traffic-simulator-ui`).
3. `curl -sS http://localhost:8890/health.php` debe devolver `"status":"ok"` (cambia el puerto si usaste otro en `TRAFFIC_SIMULATOR_UI_PORT`).
4. Abre la misma URL en el navegador y sigue los pasos 3–7 anteriores.

### AWS EC2 (tras despliegue)

1. El script [`scripts/deploy_aws_docker.sh`](../scripts/deploy_aws_docker.sh) imprime las URLs de aplicación (`WEB_HOST_PORT`, por defecto 80), Grafana, Prometheus, Alertmanager y la **Traffic UI** (`TRAFFIC_SIMULATOR_UI_HOST_PORT`, por defecto **8890**), si los perfiles `monitoring` y `traffic` están en `COMPOSE_PROFILES`.
2. Abre **http://IP_PUBLICA:8890** (sustituye por DNS público si lo tienes; respeta el puerto configurado).
3. Repite los mismos pasos 3–7 de la guía local: destino puede ser `http://web` (carga interna al contenedor `web`) o la URL pública de la app en esa instancia.

### Perfiles de carga (resumen)

| Perfil  | Uso recomendado |
|---------|-----------------|
| **Normal** | Carga equilibrada; punto de partida habitual. |
| **Burst** | Picos más agresivos; útil para estrés breve. |
| **Idle** | Menos presión y más pausas; exploración suave. |

### Qué no hacer

- No uses el panel `admin/` para simular carga: el control está solo en la **Traffic UI** y en la API interna del worker.
- No dirijas carga contra sitios de terceros sin **autorización explícita**; la UI exige confirmación para hosts no internos y el proyecto puede deshabilitar objetivos externos (`SIM_EXTERNAL_TARGETS_ENABLED=false`).
- No expongas el puerto **8085** del worker a Internet sin firewall o reverse proxy; la UI ya actúa como proxy **server-side** y no envía el token al navegador.

---

## Comprobar que las métricas reflejan una web “real”

Para que Prometheus y Grafana muestren el mismo tipo de series que con usuarios reales, hace falta la cadena completa de observabilidad:

- [ ] `docker compose ps` incluye `web`, `traffic-simulator`, `traffic-simulator-ui` y, para gráficos en Grafana, **Prometheus y Grafana** (perfil `monitoring`).
- [ ] Los tres servicios anteriores montan el **mismo** volumen `./logs` en `/var/www/html/logs` (si `web` no monta `./logs`, `metrics.php` no verá las líneas que escribe JMeter).
- [ ] Durante una prueba, aumentan líneas con `source=simulator` en `logs/metrics.log` (o `grep -c source=simulator` dentro del contenedor `traffic-simulator`).
- [ ] `curl` a `/metrics.php` en la app (o el target de Prometheus) muestra contadores con etiqueta `source="simulator"`.
- [ ] En Grafana, el dashboard principal muestra actividad en la fila **Simulador** y en los paneles dedicados a JMeter (18–20), filtrando por `source="simulator"`.

---

## Arranque y variables (referencia breve)

### Windows automático

```powershell
.\scripts\start-jmeter-ui.ps1
```

### Variables típicas en `.env`

```env
SIMULATOR_CONTROL_TOKEN=tu_token_largo_seguro
SIM_BASE_URL=http://web
TRAFFIC_SIMULATOR_UI_PORT=8890
# En EC2 / docker-compose.aws.yml: TRAFFIC_SIMULATOR_UI_HOST_PORT
SIM_UI_DEFAULT_BASE_URL=http://web
JMETER_VERSION=5.6.3
SIM_JMETER_HEAP="-Xms128m -Xmx256m -XX:MaxMetaspaceSize=128m"
SIM_JMETER_WORK_DIR=/var/www/html/logs/jmeter
SIM_JMETER_HTML_REPORT=true
PROMETHEUS_EXTERNAL_URL=http://localhost:9090
GRAFANA_EXTERNAL_URL=http://localhost:3000
```

### CLI opcional (operador de confianza)

```bash
docker compose run --rm traffic-simulator \
  php scripts/run_jmeter_traffic.php --users=3 --duration=30 --profile=normal --base-url=http://web
```

Sin el perfil `traffic` no se levantan worker ni UI del simulador: usa siempre **`docker compose --profile traffic ...`** cuando necesites esta función.

---

## Referencia técnica

### Dónde está el control

- **`traffic-simulator`**: API HTTP interna en **:8085**; genera un plan JMX temporal, ejecuta JMeter y convierte `results.jtl` a logs. Solo debe ser alcanzable desde la red Docker.
- **`traffic-simulator-ui`**: interfaz en el puerto publicado del host (por defecto **8890**). Variables: `TRAFFIC_SIMULATOR_UI_PORT` en [`docker-compose.yml`](../docker-compose.yml) / `.env.example`; `TRAFFIC_SIMULATOR_UI_HOST_PORT` en [`docker-compose.aws.yml`](../docker-compose.aws.yml) / `.env.aws.example`. El token `SIMULATOR_CONTROL_TOKEN` solo existe en el servidor; [`docker/traffic-simulator-ui/public/api.php`](../docker/traffic-simulator-ui/public/api.php) reenvía las peticiones con el token.

JMeter se instala en la imagen del worker en el build (Java 17; versión `JMETER_VERSION`, por defecto **5.6.3**).

### Artefactos por ejecución (bajo `SIM_JMETER_WORK_DIR`)

- `traffic-test.jmx`, `routes.csv`, `results.jtl`, `jmeter.log`, `stdout.log`.
- Comando equivalente: `jmeter -n -t traffic-test.jmx -l results.jtl -j jmeter.log`.
- Importación a `logs/metrics.log` (`GET 200 /ruta source=simulator`) y `logs/response_time.log` (tiempos en segundos).
- `SIM_JMETER_HEAP` y `TRAFFIC_SIMULATOR_MEM_LIMIT` deben subirse a la par si aumentas carga.
- `SIM_JMETER_HTML_REPORT=true`: informe en `html-report`; CSS opcional vía `SIM_JMETER_REPORT_CSS_SOURCE`.

### Webs externas (uso responsable)

- Solo **http** / **https**.
- IPs RFC1918 / loopback / reservadas rechazadas salvo **`SIM_ALLOW_PRIVATE_TARGETS=true`** (laboratorio).
- Hosts no listados en `SIM_INTERNAL_HOSTS`: confirmación en UI (`confirm_external` en API).
- **`SIM_EXTERNAL_TARGETS_ENABLED=false`**: solo objetivos “internos”.
- **`SIM_SSL_VERIFY=true`**: verificación TLS por defecto.

CLI (`run_jmeter_traffic.php`, `simulate_traffic.php`): operador confiable; misma política de IPs y externos.

### API de control (`:8085`)

| Método | Ruta      | Auth |
|--------|-----------|------|
| GET    | `/health` | No   |
| GET    | `/status` | `X-Simulator-Token` o `Bearer` |
| POST   | `/start`  | Igual; JSON: `users`, `duration`, `profile`, `base_url`, `confirm_external`, opcional `routes_file` |
| POST   | `/stop`   | Igual |

La UI expone **`/api.php`** con acciones `probe`, `start`, `stop`, `reset`.

### Métricas y Grafana

- App: `source=app` (véase `includes/metrics_logger.php`).
- JMeter: `source=simulator` tras importar el JTL.
- Exporter: [`monitoring/php-exporter/metrics.php`](../monitoring/php-exporter/metrics.php) → `app_http_requests_total{method,status,source}`.
- La UI devuelve además `success_requests`, `error_requests`, `recent_success`, `recent_errors`, `recent_window`, `statuses`.

### Si la simulación corre pero Grafana no muestra nada

1. Mismo volumen `./logs` en `web`, `traffic-simulator` y `traffic-simulator-ui`.
2. `SIM_LOG_DIR=/var/www/html/logs` en la UI; evita rutas relativas fuera del árbol de la app.
3. `docker compose exec traffic-simulator tail -80 /tmp/traffic_simulator.log` y GET `/status` para rutas de `results.jtl` y `jmeter.log`.
4. Tras cambios en `metrics.php`: `docker compose build web`.

### Tests y smoke

```bash
docker run --rm -v "%cd%:/app" -w /app php:8.2-cli php tests/test_traffic_simulator_lib.php
```

```bash
docker compose build web traffic-simulator traffic-simulator-ui
```

```bash
docker compose --profile traffic up -d web mysql traffic-simulator traffic-simulator-ui
TOKEN="${SIMULATOR_CONTROL_TOKEN:-changeme_traffic_sim_secret}"
docker compose exec -T traffic-simulator sh -lc \
  'curl -sf -H "Content-Type: application/json" -H "X-Simulator-Token: '"$TOKEN"'" \
  --data "{\"users\":1,\"duration\":5,\"profile\":\"burst\",\"base_url\":\"http://web\",\"confirm_external\":true}" \
  http://127.0.0.1:8085/start'
docker compose exec -T traffic-simulator sh -lc 'tail -20 "${SIM_LOG_DIR:-/var/www/html/logs}/metrics.log"'
```

Tras desplegar en EC2, el script `deploy_aws_docker.sh` ejecuta un smoke similar cuando el perfil `traffic` está activo (omisible con `SKIP_TRAFFIC_SMOKE=1`). Al final del despliegue y en el *user data* de EC2 se imprime también la guía operativa vía `scripts/print-jmeter-usage.sh`.
