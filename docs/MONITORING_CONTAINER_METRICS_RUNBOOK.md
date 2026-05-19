# Runbook: recuperación de métricas de contenedor (CPU / memoria / disco)

Este documento recoge lo realizado para pasar el dashboard de Grafana desde `No data` a métricas por **nombre de contenedor** (`container_name`), vía Prometheus y Telegraf, en entorno **Docker Desktop (Windows)**.

**Fecha de referencia:** 2026-04-26  

---

## 1. Inventario de cambios (creados, modificados, eliminados)

### Archivos nuevos

| Archivo | Descripción breve |
|--------|---------------------|
| [monitoring/telegraf/telegraf.conf](../monitoring/telegraf/telegraf.conf) | Configuración del plugin `inputs.docker` y salida `outputs.prometheus_client` (puerto 9273). |

### Archivos modificados

| Archivo | Descripción breve |
|--------|---------------------|
| [docker-compose.yml](../docker-compose.yml) | Servicios `cadvisor` y `telegraf` (montaje socket Docker, entrada root vía override de entrypoint, imagen, variables de red). |
| [monitoring/prometheus/prometheus.yml](../monitoring/prometheus/prometheus.yml) | Job de scrape para `cadvisor` (`cadvisor:8080`). |
| [monitoring/grafana/dashboards/taller-mecanico-dashboard.json](../monitoring/grafana/dashboards/taller-mecanico-dashboard.json) | Paneles de CPU/memoria por contenedor usando métricas Telegraf (`docker_container_*`) y leyendas `{{container_name}}`. |
| Guías relacionadas | [MONITORING_SETUP_GUIDE.md](MONITORING_SETUP_GUIDE.md), [DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md) |

### Archivos eliminados

| Archivo | Motivo |
|--------|--------|
| `monitoring/grafana/dashboards/docker-cadvisor-dashboard.json` | Dashboard redundante “Cadvisor exporter”; las vistas se centralizaron en el dashboard principal tras estabilizar Telegraf. |

### Artefactos locales (no versionar)

- Snapshots temporales tipo `logs/telegraf_metrics_snapshot.txt` u otros dumps de `:9273/metrics` sirven solo para depuración; no deben subirse al repositorio.

---

## 2. Incidentes y resoluciones (orden aproximado de aparición)

### A — Grafana: paneles mostrando `No data`

- **Síntoma:** paneles sin series pese a tener Prometheus configurado como datasource.
- **Causa:** Telegraf no publicaba métricas `docker_*` porque no podía usar el Docker Engine (daemon).
- **Resolución:** corregir conexión y permisos hacia Docker; reiniciar `telegraf` y comprobar `http://<host>:9273/metrics` y Prometheus (`job="telegraf"`).

### B — Conexión a `tcp://host.docker.internal:2375`

- **Síntoma:** `connection refused`.
- **Causa:** en muchos setups de Docker Desktop el daemon **no** escucha HTTP en `:2375` salvo configuración explícita.
- **Resolución:** usar **socket UNIX** dentro del VM Linux de Docker montando `/var/run/docker.sock` en el contenedor Telegraf.

### C — `permission denied` sobre `/var/run/docker.sock`

- **Síntoma:** errores repetidos `[inputs.docker] ... dial unix ... permission denied`.
- **Causas típicicas:**
  1. montaje incorrecto (`:ro` impedía operaciones válidas sobre el socket);
  2. la imagen oficial de Telegraf ejecuta el binario como usuario `telegraf` (UID no root) mediante el `/entrypoint.sh` habitual, incluso cuando el proceso del contenedor se ve como root.
- **Resolución:**
  - montar socket en **read-write**;
  - **override** de `entrypoint`/`command` para lanzar directamente `telegraf` como root efectivo donde sea necesario (según entorno Docker Desktop/WSL).

### D — Versión API del cliente Docker demasiado antigua

- **Síntoma:** `client version 1.24 is too old. Minimum supported API version is ...`
- **Causa:** Telegraf empacaba cliente Docker viejo incompatible con el Engine actual del host.
- **Resolución:** actualizar imagen Telegraf (`telegraf:latest` o una versión fija reciente) y usar variable **`DOCKER_API_VERSION`** acorde con el servidor (ej. `1.41`). Evitar campo `docker_api_version` en el plugin si la versión de Telegraf no lo admite.

### E — Opciones deprecadas del plugin `[inputs.docker]`

- **Síntoma:** error de carga de config: opciones reconocidas como no usadas (`container_names`, `perdevice`, etc.).
- **Causa:** Telegraf nuevo (p. ej. 1.38+) con validación estricta.
- **Resolución:** actualizar sintaxis (`perdevice_include`, `total_include`, etc.) según `telegraf --usage docker` de la misma imagen.

### F — Leyendas tipo `/`, `/docker`, `/docker/buildx`

- **Síntoma:** nombres poco habituales (cgroups/paths).
- **Causa temporal:** usar métricas cAdvisor por `id` antes de tener Telegraf estable con etiquetas de contenedor.
- **Resolución definitiva:** volver a usar métricas Telegraf etiquetadas con `container_name` una vez estable el scraping.

---

## 3. Paneles Grafana (consultas de referencia)

Tras stabilizar Telegraf, el dashboard principal usa (según última configuración revisada):

- **CPU por contenedor:** `docker_container_cpu_usage_percent{container_name!=""}`
- **Memoria por contenedor:** `docker_container_mem_usage{container_name!=""}`
- **Leyenda:** `{{container_name}}`

> Nota: el nombre exacto del campo puede variar ligeramente según tipo de campo en `metric_version`; validar contra el endpoint Prometheus o la pestaña *Explore* en Grafana.

---

## 4. Checklist de verificación (Telegraf → Prometheus → Grafana)

### Telegraf

- [ ] El contenedor `telegraf` está `Up`.
- [ ] `docker compose logs telegraf` no muestra errores continuos `[inputs.docker]`.
- [ ] `curl http://localhost:9273/metrics` (o dentro del contenedor) incluye líneas `docker_container_` con label `container_name`.
- [ ] Opcional: `docker compose exec telegraf telegraf ... --input-filter docker --test` muestra líneas línea Influx tipo `docker_container_*`.

### Prometheus

- [ ] Targets: `telegraf` y (si está desplegado) `cadvisor` en estado `UP` en `/targets`.
- [ ] Queries de prueba:
  - `up{job="telegraf"} == 1`
  - `count(docker_container_mem_usage{container_name!=""})` > 0
  - `docker_container_cpu_usage_percent{container_name!=""}` devuelve varias series

### Grafana

- [ ] Datasource Prometheus configurado (`uid prometheus` si se provisionó así).
- [ ] Dashboard principal carga paneles CPU/memoria con varias líneas cuando hay varios contenedores activos.
- [ ] Leyenda muestra `taller_mecanico_*` / nombres de servicio Compose.

---

## 5. Riesgos y recomendaciones

1. **Fijar versión de imagen Telegraf** (por ejemplo `1.38.x`) en lugar de `latest` para despliegues reproducibles.
2. **Evaluar mantener solo Telegraf vs Telegraf+cAdvisor** si no se necesitan ambas rutas para las mismas gráficas.
3. Mantener esta guía cuando se migre la app a **Linux**/orquestadores (Las rutas socket y seguridad pueden cambiar).

---

## Referencias de archivos

- [docker-compose.yml](../docker-compose.yml)
- [monitoring/telegraf/telegraf.conf](../monitoring/telegraf/telegraf.conf)
- [monitoring/prometheus/prometheus.yml](../monitoring/prometheus/prometheus.yml)
- [monitoring/grafana/dashboards/taller-mecanico-dashboard.json](../monitoring/grafana/dashboards/taller-mecanico-dashboard.json)
