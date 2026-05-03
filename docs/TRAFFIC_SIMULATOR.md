# Simulador de tráfico (instancia separada)

Genera HTTP real contra la app PHP (u otra URL HTTPS/HTTP autorizada), escribe el mismo formato de líneas que consume `metrics.php` (`logs/metrics.log`, `logs/response_time.log`).

**Ya no existe** panel en `admin/` ni `/api/simulate.php`. El control está en:

- **`traffic-simulator`**: worker PHP + API HTTP interna (`8085`), **solo red Docker**.
- **`traffic-simulator-ui`**: interfaz web en **puerto publicado** (p. ej. `TRAFFIC_SIMULATOR_UI_PORT`, por defecto `8890`). El token `SIMULATOR_CONTROL_TOKEN` solo vive en el servidor/contenedor backend (la UI llama a su `api.php` server-side).

## Arranque (Docker Compose, recomendado)

1. Variables en `.env` (los secretos reales **no** se suben a git):

```env
SIMULATOR_CONTROL_TOKEN=tu_token_largo_seguro
SIM_BASE_URL=http://web
TRAFFIC_SIMULATOR_UI_PORT=8890
SIM_UI_DEFAULT_BASE_URL=http://web
PROMETHEUS_EXTERNAL_URL=http://localhost:9090
GRAFANA_EXTERNAL_URL=http://localhost:3000
```

2. Levanta `web`, `mysql`, `traffic-simulator` y la UI (**perfil `traffic`**):

```bash
docker compose --profile traffic up -d
```

3. Abre la UI: **http://localhost:8890** (puerto desde `TRAFFIC_SIMULATOR_UI_PORT`).

4. Opcional CLI en el servicio worker:

```bash
docker compose run --rm traffic-simulator \
  php scripts/simulate_traffic.php --users=3 --duration=30 --profile=normal --base-url=http://web
```

El volumen `./logs` debe ser el mismo entre `traffic-simulator` y `traffic-simulator-ui` para que la vista previa y Prometheus reflejen las mismas líneas.

### Sin el perfil `traffic`

Sin `traffic-simulator`, la UI puede arrancarse solo si ese servicio existe; `depends_on` requiere el worker sano primero — usa siempre **`--profile traffic`** para tener ambos servicios.

## Webs externas (uso responsable)

- La URL debe ser **`http`** o **`https`**.
- IPs **RFC1918 / loopback / reservadas** se rechazan salvo **`SIM_ALLOW_PRIVATE_TARGETS=true`** (solo entornos de laboratorio).
- Dominios/host que **no** estén listados en `SIM_INTERNAL_HOSTS` requieren marcar confirmación en la UI (y campo `confirm_external` en `/start`): no sustituye la **autorización legal** ni evita límites o bloqueos del sitio objetivo — reduce abuso automatizado desde la herramienta.
- **`SIM_EXTERNAL_TARGETS_ENABLED=false`** desactiva cualquier objetivo que no sea “interno”.
- **`SIM_SSL_VERIFY=true`** por defecto (HTTPS con verificación de certificado); `SIM_SSL_VERIFY=false` solo depuración.

Hosts internos por defecto (`SIM_INTERNAL_HOSTS`): incluye entre otros `web`, `localhost`, `127.0.0.1`, `mysql`, `host.docker.internal` (ajústalo si tu stack usa otros alias).

CLI (`simulate_traffic.php`) se considera operador confiable (`trusted_cli`): no muestra checkbox, pero igual aplica política de IP privadas y externos deshabilitados.

## API HTTP de control (`traffic-simulator :8085`)

Solo debe alcanzarse desde la Docker network (**no exponer sin reverse proxy**/firewall fuerte si publicas ese puerto en tu entorno).

| Método | Ruta       | Auth |
|--------|------------|------|
| GET    | `/health` | No |
| GET    | `/status` | `X-Simulator-Token` o `Bearer` |
| POST   | `/start`  | Mismo header; JSON `users`, `duration`, `profile`, `base_url`, `confirm_external`, opcional `routes_file` |
| POST   | `/stop`   | Mismo header |

La UI sirve **`/api.php`** (JSON GET/POST) y reenvía a `:8085` con el token de entorno; no expone el token al navegador.

Acciones POST útiles: `probe` (comprueba HTTP contra la URL base antes de cargar), `start`, `stop`, `reset`.

## Métricas

Las series Prometheus se derivan de los logs compartidos (`logs/metrics.log`). Cada línea incluye el origen:

- **App PHP:** `GET 200 source=app` (ver `includes/metrics_logger.php`).
- **Simulador:** `GET 200 /ruta source=simulator` (ver `traffic_simulator_append_log`).

El exporter [`monitoring/php-exporter/metrics.php`](monitoring/php-exporter/metrics.php) expone `app_http_requests_total{method,status,source}`. Líneas antiguas sin `source=` se clasifican en el parser como `app` o `simulator` por heurística (presencia de ruta).

En **Grafana**, el dashboard principal incluye filas **Simulador:** con `source="simulator"`. Las consultas sin filtro por `source` siguen sumando todo el tráfico.

La **UI del simulador** (`api.php`) devuelve `success_requests`, `error_requests`, `recent_success`, `recent_errors` y `recent_window` además de `statuses`.

### Si la simulación “corre” pero la UI y Grafana están vacíos

1. **Mismo volumen de logs**: `traffic-simulator`, `traffic-simulator-ui` y **`web`** deben montar el mismo `./logs:/var/www/html/logs`. Si el contenedor `web` no monta `./logs`, Prometheus (vía `/metrics.php`) no verá las líneas que escribe el simulador.
2. **Ruta por defecto en la UI**: la imagen define `SIM_LOG_DIR=/var/www/html/logs`. Si personalizas Compose, no uses rutas tipo `../logs` fuera del árbol de la app.
3. **Depurar el worker**: `docker compose exec traffic-simulator tail -80 /tmp/traffic_simulator.log` (errores PHP, curl, URL base).
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
