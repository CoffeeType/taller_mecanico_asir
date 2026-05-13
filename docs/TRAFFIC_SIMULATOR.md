# Simulador de tráfico con Apache JMeter (instancia separada)

En documentación de producto y en la memoria PFC esta capacidad se describe como **pruebas de carga con Apache JMeter**; en Docker Compose el perfil y los nombres de servicio siguen siendo `traffic` / `traffic-simulator` por compatibilidad con el repositorio.

Genera HTTP real con **Apache JMeter en modo CLI/no-GUI** contra la app PHP (u otra URL HTTPS/HTTP autorizada) y conserva el mismo formato de líneas que consume `metrics.php` (`logs/metrics.log`, `logs/response_time.log`).

JMeter se instala dentro de la imagen `traffic-simulator` durante el build. La imagen usa Java 17 y Apache JMeter `JMETER_VERSION` (por defecto `5.6.3`, versión estable oficial que requiere Java 8+).

**Ya no existe** panel en `admin/` ni `/api/simulate.php`. El control está en:

- **`traffic-simulator`**: API HTTP interna (`8085`) que genera un plan JMX temporal y ejecuta Apache JMeter, **solo red Docker**.
- **`traffic-simulator-ui`**: interfaz web en **puerto publicado** (por defecto **8890**). En `docker-compose.yml` / `.env.example` el puerto del host se define con `TRAFFIC_SIMULATOR_UI_PORT`; en `docker-compose.aws.yml` / `.env.aws.example` se usa `TRAFFIC_SIMULATOR_UI_HOST_PORT`. `scripts/start-jmeter-ui.ps1` intenta leer primero `TRAFFIC_SIMULATOR_UI_PORT` y si falta usa `TRAFFIC_SIMULATOR_UI_HOST_PORT`. El token `SIMULATOR_CONTROL_TOKEN` solo vive en el servidor/contenedor backend (la UI llama a su `api.php` server-side).

## Arranque (Docker Compose, recomendado)

### Windows automático

```powershell
.\scripts\start-jmeter-ui.ps1
```

El script arranca Docker Desktop si hace falta, levanta `web`, `mysql`, `traffic-simulator` y `traffic-simulator-ui`, espera la UI y abre el navegador. También puedes lanzar `start-jmeter-ui.bat` con doble clic.

### Manual / Linux / macOS

1. Variables en `.env` (los secretos reales **no** se suben a git):

```env
SIMULATOR_CONTROL_TOKEN=tu_token_largo_seguro
SIM_BASE_URL=http://web
TRAFFIC_SIMULATOR_UI_PORT=8890
# En EC2 / docker-compose.aws.yml usa TRAFFIC_SIMULATOR_UI_HOST_PORT (mismo valor por defecto).
SIM_UI_DEFAULT_BASE_URL=http://web
JMETER_VERSION=5.6.3
SIM_JMETER_HEAP="-Xms128m -Xmx256m -XX:MaxMetaspaceSize=128m"
SIM_JMETER_WORK_DIR=/var/www/html/logs/jmeter
SIM_JMETER_HTML_REPORT=true
# SIM_JMETER_REPORT_CSS_SOURCE=/opt/traffic-simulator/assets/jmeter-report-custom.css
PROMETHEUS_EXTERNAL_URL=http://localhost:9090
GRAFANA_EXTERNAL_URL=http://localhost:3000
```

2. Levanta `web`, `mysql`, `traffic-simulator` y la UI (**perfil `traffic`**):

```bash
docker compose --profile traffic up -d
```

3. Comprueba salud HTTP (sustituye el puerto si lo cambiaste: `TRAFFIC_SIMULATOR_UI_PORT` en local, `TRAFFIC_SIMULATOR_UI_HOST_PORT` en AWS):

```bash
curl -sS http://localhost:8890/health.php
```

Debe devolver JSON con `"status":"ok"`. Si Docker no responde (`Cannot connect to the Docker daemon` / error del *pipe* en Windows), arranca **Docker Desktop** y espera a que el icono quede estable antes de repetir el comando.

4. Abre la UI en el navegador (mismo puerto que en el paso anterior; en local suele ser **http://localhost:8890/**).

5. Opcional CLI en el servicio worker:

```bash
docker compose run --rm traffic-simulator \
  php scripts/run_jmeter_traffic.php --users=3 --duration=30 --profile=normal --base-url=http://web
```

El volumen `./logs` debe ser el mismo entre `traffic-simulator` y `traffic-simulator-ui` para que la vista previa y Prometheus reflejen las mismas líneas.

## Cómo encaja JMeter

- El runner genera un directorio compartido en `SIM_JMETER_WORK_DIR` (por defecto `/var/www/html/logs/jmeter`, dentro del volumen `logs`) con:
  - `traffic-test.jmx`: plan JMeter generado.
  - `routes.csv`: rutas ponderadas derivadas del JSON opcional o de las rutas por defecto.
  - `results.jtl`: resultados CSV de JMeter.
  - `jmeter.log` y `stdout.log`: diagnóstico del proceso.
- JMeter se ejecuta con `jmeter -n -t traffic-test.jmx -l results.jtl -j jmeter.log`.
- Mientras JMeter escribe el `.jtl`, el runner lo importa a:
  - `logs/metrics.log`: `GET 200 /ruta source=simulator`.
  - `logs/response_time.log`: tiempo en segundos.
- `SIM_JMETER_HEAP` controla la memoria de Java. Para EC2 pequeño se recomienda mantener el valor por defecto; para más carga, sube heap y `TRAFFIC_SIMULATOR_MEM_LIMIT` juntos.
- Con `SIM_JMETER_HTML_REPORT=true`, JMeter añade un reporte HTML en `html-report`. La **UI JMeter** lo enlaza como **Dashboard JMeter** cuando la ejecución termina.
- Tras cada run, el runner copia un CSS opcional al directorio del informe y lo enlaza en `index.html` (mejoras cosméticas). La ruta del fichero fuente se define con `SIM_JMETER_REPORT_CSS_SOURCE` (por defecto en la imagen `traffic-simulator`: `/opt/traffic-simulator/assets/jmeter-report-custom.css`).

### Sin el perfil `traffic`

Sin `traffic-simulator`, la UI puede arrancarse solo si ese servicio existe; `depends_on` requiere el worker sano primero — usa siempre **`--profile traffic`** para tener ambos servicios.

## Webs externas (uso responsable)

- La URL debe ser **`http`** o **`https`**.
- IPs **RFC1918 / loopback / reservadas** se rechazan salvo **`SIM_ALLOW_PRIVATE_TARGETS=true`** (solo entornos de laboratorio).
- Dominios/host que **no** estén listados en `SIM_INTERNAL_HOSTS` requieren marcar confirmación en la UI (y campo `confirm_external` en `/start`): no sustituye la **autorización legal** ni evita límites o bloqueos del sitio objetivo — reduce abuso automatizado desde la herramienta.
- **`SIM_EXTERNAL_TARGETS_ENABLED=false`** desactiva cualquier objetivo que no sea “interno”.
- **`SIM_SSL_VERIFY=true`** por defecto (HTTPS con verificación de certificado); `SIM_SSL_VERIFY=false` solo depuración.

Hosts internos por defecto (`SIM_INTERNAL_HOSTS`): incluye entre otros `web`, `localhost`, `127.0.0.1`, `mysql`, `host.docker.internal` (ajústalo si tu stack usa otros alias).

CLI (`run_jmeter_traffic.php`, y el wrapper compatible `simulate_traffic.php`) se considera operador confiable (`trusted_cli`): no muestra checkbox, pero igual aplica política de IP privadas y externos deshabilitados.

## API HTTP de control (`traffic-simulator :8085`)

Solo debe alcanzarse desde la Docker network (**no exponer sin reverse proxy**/firewall fuerte si publicas ese puerto en tu entorno).

| Método | Ruta       | Auth |
|--------|------------|------|
| GET    | `/health` | No |
| GET    | `/status` | `X-Simulator-Token` o `Bearer` |
| POST   | `/start`  | Mismo header; JSON `users`, `duration`, `profile`, `base_url`, `confirm_external`, opcional `routes_file`; inicia JMeter |
| POST   | `/stop`   | Mismo header |

La UI sirve **`/api.php`** (JSON GET/POST) y reenvía a `:8085` con el token de entorno; no expone el token al navegador.

Acciones POST útiles: `probe` (comprueba HTTP contra la URL base antes de cargar), `start`, `stop`, `reset`.

## Métricas

Las series Prometheus se derivan de los logs compartidos (`logs/metrics.log`). Cada línea incluye el origen:

- **App PHP:** `GET 200 source=app` (ver `includes/metrics_logger.php`).
- **Tráfico JMeter en métricas:** `GET 200 /ruta source=simulator` (el runner convierte `results.jtl`).

El exporter [`monitoring/php-exporter/metrics.php`](monitoring/php-exporter/metrics.php) expone `app_http_requests_total{method,status,source}`. Líneas antiguas sin `source=` se clasifican en el parser como `app` o `simulator` por heurística (presencia de ruta).

En **Grafana**, el dashboard principal incluye filas que filtran por **`source="simulator"`** (tráfico de JMeter). Las consultas sin filtro por `source` siguen sumando todo el tráfico.

La **UI JMeter** (`api.php`) devuelve `success_requests`, `error_requests`, `recent_success`, `recent_errors` y `recent_window` además de `statuses`.

### Si la simulación “corre” pero la UI y Grafana están vacíos

1. **Mismo volumen de logs**: `traffic-simulator`, `traffic-simulator-ui` y **`web`** deben montar el mismo `./logs:/var/www/html/logs`. Si el contenedor `web` no monta `./logs`, Prometheus (vía `/metrics.php`) no verá las líneas que escribe JMeter.
2. **Ruta por defecto en la UI**: la imagen define `SIM_LOG_DIR=/var/www/html/logs`. Si personalizas Compose, no uses rutas tipo `../logs` fuera del árbol de la app.
3. **Depurar el worker**: `docker compose exec traffic-simulator tail -80 /tmp/traffic_simulator.log` (errores del runner, JMeter o URL base). El endpoint `/status` también devuelve rutas de `results.jtl` y `jmeter.log`.
4. **Reconstruir `web`** tras cambiar `metrics.php`: `docker compose build web` (el Dockerfile copia el exporter a `/var/www/html/metrics.php`).

## Tests locales del motor

Sin PHP local:

```bash
docker run --rm -v "%cd%:/app" -w /app php:8.2-cli php tests/test_traffic_simulator_lib.php
```

## Build imágenes

```bash
docker compose build web traffic-simulator traffic-simulator-ui
```

## Smoke JMeter

```bash
docker compose --profile traffic up -d web mysql traffic-simulator traffic-simulator-ui
TOKEN="${SIMULATOR_CONTROL_TOKEN:-changeme_traffic_sim_secret}"
docker compose exec -T traffic-simulator sh -lc \
  'curl -sf -H "Content-Type: application/json" -H "X-Simulator-Token: '"$TOKEN"'" \
  --data "{\"users\":1,\"duration\":5,\"profile\":\"burst\",\"base_url\":\"http://web\",\"confirm_external\":true}" \
  http://127.0.0.1:8085/start'
docker compose exec -T traffic-simulator sh -lc 'tail -20 "${SIM_LOG_DIR:-/var/www/html/logs}/metrics.log"'
```
