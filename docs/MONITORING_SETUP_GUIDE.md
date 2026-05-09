# Guía de Configuración de Monitoreo - Taller Mecánico

## 📊 Resumen del Sistema de Monitoreo

Este documento describe cómo configurar y utilizar el sistema de monitoreo completo para tu taller mecánico, incluyendo Prometheus, Grafana, alertas y notificaciones.

## 🏗️ Arquitectura del Sistema

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Aplicación    │    │   Prometheus    │    │     Grafana     │
│     PHP         │───▶│   (Métricas)    │───▶│   (Dashboards)  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │                       ▼                       │
         │              ┌─────────────────┐              │
         │              │   AlertManager  │              │
         │              │ (Notificaciones)│              │
         │              └─────────────────┘              │
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Node Exporter │    │ MySQL Exporter  │    │   Alertas por   │
│ (Sistema)       │    │ (Base de Datos) │    │     Email       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Versiones de imágenes (stack local `docker-compose.yml`)

| Componente | Imagen (pin actual) |
|------------|----------------------|
| Prometheus | `prom/prometheus:v3.11.3` |
| Alertmanager | `prom/alertmanager:v0.32.1` |
| Grafana | `grafana/grafana:13.0.1` |
| Node Exporter | `prom/node-exporter:v1.11.1` |
| cAdvisor | `ghcr.io/google/cadvisor:${CADVISOR_IMAGE_TAG}` (defecto `0.56.2`; tag semver **sin** prefijo `v`, ver [README cAdvisor](https://github.com/google/cadvisor#quick-start-running-cadvisor-in-a-docker-container)) |
| MySQL Exporter | `prom/mysqld-exporter:v0.19.0` |
| Blackbox Exporter | `prom/blackbox-exporter:v0.26.0` |

## 🚀 Configuración Rápida

### Paso 1: Iniciar el Sistema de Monitoreo

```bash
# Iniciar todos los servicios de monitoreo (incluye blackbox para probes HTTP)
docker-compose up -d prometheus alertmanager grafana node-exporter mysqld-exporter cadvisor telegraf blackbox-exporter

# Verificar que todos los servicios estén corriendo
docker-compose ps
```

### Paso 2: Acceder a los Servicios

- **Grafana Dashboard**: http://localhost:3000 (despliegue local con Docker)
  - Usuario: `admin`
  - Contraseña: `admin123` (configurable en `.env`)

- **Prometheus**: http://localhost:9090 (despliegue local con Docker)
  - Consultas y alertas

- **AlertManager**: http://localhost:9093 (despliegue local con Docker)
  - Gestión de alertas

> En Coolify, la forma recomendada es acceder a estos servicios mediante **Domains/Routes** apuntando a los puertos internos:\n> - Grafana `3000`\n> - Prometheus `9090`\n> - Alertmanager `9093`\n>\n> Así evitas publicar puertos fijos al host y no tendrás colisiones entre despliegues.

### Paso 3: Verificar Métricas

1. **Métricas de la Aplicación PHP**: http://localhost:8081/metrics.php (o el valor de `WEB_PORT`)
2. **Métricas del Sistema**: http://localhost:9100/metrics
3. **Métricas de MySQL**: http://localhost:9104/metrics
4. **Blackbox (probes / métricas del exporter)**: http://localhost:9115/metrics (puerto `BLACKBOX_EXPORTER_PORT`, por defecto `9115`)

> Nota: si cambias `WEB_PORT` en `.env`, el endpoint será `http://localhost:<WEB_PORT>/metrics.php` (por defecto `8081`).

### Probes HTTP sintéticos (Blackbox)

Prometheus hace probe GET desde `blackbox-exporter` hacia `web` (Docker DNS): **`/health.php`** (sin `session_start`, solo texto `ok`) y **`/metrics.php`** (endpoint Prometheus). Así las comprobaciones de disponibilidad no crean ficheros de sesión PHP. La alerta `BlackboxProbeFailed` indica caída real de Apache/app aunque los exporters sigan vivos. Configuración del módulo: [monitoring/prometheus/blackbox.yml](monitoring/prometheus/blackbox.yml).

### Diferenciar métricas en Grafana (usuarios vs MySQL)

| Métrica | Fuente |
|---------|--------|
| `app_users_active{window_minutes="…"}` | **COUNT** en `users_login` donde `last_seen_at` cae en la ventana (actualizado por peticiones HTTP reales con usuario logueado; escritura a BD como máximo cada ~60 s por sesión). Sin ficheros `sess_*`. |
| `app_users_total` | **COUNT** de `users_data` (cuentas registradas). |
| `mysql_global_status_threads_connected` | Conexiones al servidor MySQL (pool, exporters, PHP), no usuarios web. |

Antes de usar `app_users_active`, aplicar la migración `database/migrations/add_users_login_last_seen_at.sql` en la base existente.

Variables de entorno opcionales:

- `APP_USERS_ACTIVE_WINDOW_MINUTES` (por defecto `15`): ancho de ventana para “activo reciente” en la consulta SQL y en la etiqueta Prometheus `window_minutes`.

Prometheus: usa `max(app_users_active)` si varios targets scrapean la misma app (misma BD); **no** `sum()` para evitar duplicar el mismo recuento.

### Simulador de tráfico y línea base HTTP

Las series `app_http_requests_total{method,status,source}`, `app_http_response_time_*`, etc., se derivan de `logs/metrics.log` y `logs/response_time.log` (volumen `./logs` montado en `web`). La app registra **`source=app`**; el simulador **`source=simulator`**. El endpoint **`api/citas_api.php`** también escribe en los mismos logs vía `register_shutdown_function` para que las reservas cuenten en Prometheus.

- Carga aislada fuera del proceso Apache: perfil Compose `traffic`, servicio worker + UI separada (ver [TRAFFIC_SIMULATOR.md](TRAFFIC_SIMULATOR.md)).
- Paneles **Simulador:** en el dashboard principal usan `source="simulator"`.

## 📈 Dashboard Principal

### Secciones del Dashboard

#### 1. Estado General del Sistema (Panel 1)
- **Monitorea**: Estado de todos los servicios (Prometheus, PHP App, Node Exporter, MySQL Exporter)
- **Indicadores**: Verde = En línea, Rojo = Fuera de línea
- **Importancia**: Visión general rápida del estado del sistema

#### 2. Tráfico Web en Tiempo Real (Panel 2)
- **Métricas**: Requests por segundo por método HTTP (GET, POST, etc.)
- **Uso**: Identificar picos de tráfico y patrones de uso
- **Alertas**: Detectar sobrecarga del sistema

#### 3. Tasa de Errores HTTP (Panel 3)
- **Métricas**: Porcentaje de errores 4xx y 5xx
- **Umbrales**: Amarillo > 1%, Rojo > 5%
- **Importancia**: Calidad del servicio y experiencia del usuario

#### Simulador de tráfico (paneles 18–20)
- **Requests/s por método** solo con `source="simulator"`.
- **Éxitos vs errores** (códigos 2xx–3xx frente a 4xx–5xx) del simulador.
- **Tasa de error %** del simulador (solo útil con perfil Compose `traffic` y mismo volumen `./logs`).

#### 4. Tiempo de Respuesta (Panel 4)
- **Métricas**: Percentiles p50, p95, p99 del tiempo de respuesta
- **Umbrales**: Amarillo > 2s, Rojo > 5s
- **Uso**: Rendimiento de la aplicación

#### 5. Métricas de Negocio (Paneles 10-13)
- **Usuarios Totales**: Crecimiento de la base de usuarios
- **Citas Totales**: Volumen de reservas
- **Noticias Publicadas**: Contenido actualizado
- **Consejos Técnicos**: Recursos educativos

#### 6. Salud de la Base de Datos (Paneles 6, 14, 15)
- **Conexiones Activas**: Uso de conexiones MySQL
- **Salud de Conexión**: Estado de la conexión a la base de datos
- **Consultas Lentas**: Rendimiento de consultas

#### 7. Recursos del Sistema (Paneles 8, 9, 16)
- **Uso de CPU**: Porcentaje de utilización
- **Uso de Memoria**: Consumo de RAM
- **Espacio en Disco**: Almacenamiento disponible
- **Tráfico de Red**: Entrada/salida de datos

#### 8. Alertas Activas (Panel 17)
- **Visualización**: Tabla de alertas en tiempo real
- **Filtros**: Alertas críticas vs. de advertencia
- **Acciones**: Enlace directo a Grafana para análisis

## ⚠️ Sistema de Alertas

### Configurar notificaciones por email (Alertmanager)

El envío de emails se configura con variables de entorno (ver `.env.example`). En Docker, `docker-compose.yml` arranca Alertmanager con la plantilla [monitoring/alertmanager/alertmanager.yml](monitoring/alertmanager/alertmanager.yml) procesada por [monitoring/alertmanager/alertmanager-entrypoint.sh](monitoring/alertmanager/alertmanager-entrypoint.sh) (sustituye SMTP y destinatarios).

Variables recomendadas:

- `ALERT_EMAIL_TO`: destinatario(s) (ej: `tuemail@dominio.com` o `a@dominio.com,b@dominio.com`)
- `SMTP_SMARTHOST`: host:puerto SMTP (ej: `smtp.gmail.com:587`)
- `SMTP_FROM`: remitente (ej: `monitoring@tallermecanico.com`)
- `SMTP_AUTH_USERNAME`: usuario SMTP (normalmente el email)
- `SMTP_AUTH_PASSWORD`: contraseña o "app password" del proveedor
- `SMTP_REQUIRE_TLS`: `true`/`false` (normalmente `true` en 587)

En AWS, Amazon SES funciona como SMTP estándar. Consulta la guía específica de SES en [`docs/AWS_DOCKER_DEPLOYMENT.md`](AWS_DOCKER_DEPLOYMENT.md#4b-alertmanager-con-amazon-ses-smtp) para verificar identidad, crear credenciales SMTP y configurar `SES_SMTP_REGION` o `SMTP_SMARTHOST`.

Después de modificar `.env`:

```bash
docker-compose up -d alertmanager prometheus
```

### Enviar un email de prueba (desde Grafana)

- En **despliegue AWS** con [`scripts/deploy_aws_docker.sh`](../scripts/deploy_aws_docker.sh), el script actualiza en disco los valores por defecto del dashboard **App PHP (URL base)** y **Prometheus (URL base)** según metadata EC2 / `PUBLIC_ACCESS_HOST` y `WEB_HOST_PORT`, para que no tengas que editarlos a mano en Grafana (el fichero se reescribe en `monitoring/grafana/dashboards/`; Grafana los recarga por provisioning).
- Entra en Grafana → Dashboard **Taller Mecánico - Dashboard Principal** → enlace del menú **Test Email (Alertas)** → `${taller_app_base}/admin/test-alert-email.php`.
- Requiere iniciar sesión como admin en la aplicación.

### Alertmanager: la UI no incluye «enviar email de prueba»

La interfaz web oficial de **Alertmanager** no trae un botón para enviar un correo de prueba (limitación habitual del proyecto Prometheus). Para validar SMTP y receptores puedes:

1. **Panel admin** del taller: [`admin/test-alert-email.php`](../admin/test-alert-email.php) (envía una alerta sintética por la API interna a `alertmanager:9093`; los enlaces «Abrir Alertmanager» / «Prometheus Alerts» usan `ALERTMANAGER_EXTERNAL_URL` y `PROMETHEUS_EXTERNAL_URL` en AWS).
2. **API desde la instancia** (requiere SMTP configurado), misma idea que el PHP — POST a `/api/v2/alerts`:

```bash
curl -sS -X POST "http://127.0.0.1:9093/api/v2/alerts" \
  -H "Content-Type: application/json" \
  -d '[{"labels":{"alertname":"ManualTestEmail","severity":"warning","service":"taller-mecanico","component":"monitoring","instance":"manual"},"annotations":{"summary":"Email de prueba (curl)","description":"Prueba manual."},"startsAt":"2026-01-01T12:00:00.000Z","endsAt":"2026-01-01T12:15:00.000Z"}]'
```

Sustituye `startsAt` / `endsAt` por timestamps RFC3339 recientes (Alertmanager ignora alertas demasiado antiguas).

### Alertas Críticas (severidad `critical`)

| Alerta | Qué indica |
|--------|------------|
| `ApplicationMetricsDown` | Prometheus no scrapea `/metrics.php` del job `php-app`. |
| `CriticalApp5xxRate` | Ratio 5xx/total (solo `source="app"`, ventana 15m) > 15 % con volumen mínimo de requests. |
| `DatabaseConnectionDown` | `app_db_connection_healthy == 0`. |
| `MySQLDown` | Job `mysqld-exporter` no responde. |
| `MySQLConnectionsCritical` | `threads_connected / max_connections` > 95 %. |
| `CriticalCPUUsage` / `CriticalMemoryUsage` / `CriticalDiskSpace` | Saturación de host (disco en `/`, excluye tmpfs/ramfs típicos). |
| `NodeExporterDown` | Sin métricas de sistema. |
| `PrometheusDown` | Prometheus no scrapeable. |
| `BlackboxProbeFailed` | Fallo de probe HTTP sintético hacia la app (`probe_success == 0`). |

### Alertas de Advertencia (severidad `warning`)

| Alerta | Qué indica |
|--------|------------|
| `HighApp5xxRate` | Ratio 5xx/total (`source="app"`) > 5 % con volumen mínimo. |
| `HighAppLatencyP95` | p95 desde logs > 2 s (métrica aproximada; requiere tráfico mínimo). |
| `MySQLConnectionsExhausted` | Uso de conexiones > 80 %. |
| `MySQLSlowQueries` | Alta tasa de slow queries. |
| `HighCPUUsage` / `HighMemoryUsage` / `LowDiskSpace` | Umbrales por debajo de los críticos. |
| `CadvisorDown` / `TelegrafDown` | Monitoreo de contenedores opcional degradado. |
| `BlackboxExporterDown` | No hay probes sintéticos hasta recuperar blackbox. |

**Eliminadas por ruido o duplicidad:** `NoHTTPRequests` (sitios de bajo tráfico), `PrometheusTargetDown` genérico (`up == 0` para todos los jobs).

**Alertmanager:** agrupación por `alertname`, `component`, `severity`; reglas de inhibición para que la alerta crítica suprima la advertencia equivalente (p. ej. `CriticalApp5xxRate` → `HighApp5xxRate`).

## 📧 Configuración de Notificaciones

### Email (Configuración Básica)

1. **Configurar `.env`** (ver `.env.example`):
   - `ALERT_EMAIL_TO`
   - `SMTP_SMARTHOST`
   - `SMTP_FROM`
   - `SMTP_AUTH_USERNAME`
   - `SMTP_AUTH_PASSWORD`
   - `SMTP_REQUIRE_TLS`

2. **Para Gmail**:
   - Habilitar 2FA
   - Crear "App Password" en Google Account
   - Usar la App Password en lugar de tu contraseña

3. **Para otros proveedores**:
   - **Outlook**: `smtp-mail.outlook.com:587`
   - **Yahoo**: `smtp.mail.yahoo.com:587`
   - **Amazon SES**: `email-smtp.<region>.amazonaws.com:587` con credenciales SMTP de SES e identidad `SMTP_FROM` verificada
   - **Servidor propio**: Configurar según tu proveedor

### Slack Integration (Opcional)

1. **Crear Webhook en Slack**:
   - Ir a tu workspace de Slack
   - Apps → Incoming Webhooks
   - Crear nuevo webhook para tu canal

2. **Configurar en AlertManager**:
```yaml
receivers:
- name: 'slack-notifications'
  slack_configs:
  - api_url: 'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX'
    channel: '#alertas-taller'
    title: '🚨 Alerta Taller Mecánico'
    text: |
      {{ range .Alerts }}
      *{{ .Annotations.summary }}*
      {{ .Annotations.description }}
      {{ end }}
```

### Teams Integration (Opcional)

```yaml
receivers:
- name: 'teams-notifications'
  webhook_configs:
  - url: 'https://your-tenant.webhook.office.com/webhookb2/...'
    send_resolved: true
```

## 🔧 Configuración Avanzada

### Personalizar Umbrales de Alertas

Editar `monitoring/prometheus/alerts.yml`:

```yaml
# Cambiar umbral de tiempo de respuesta (solo warning)
- alert: HighAppLatencyP95
  expr: |
    app_http_response_time_seconds{quantile="0.95"} > 3
    and on()
    sum(increase(app_http_requests_total{source="app"}[15m])) > 10
  for: 10m
```

### Añadir Métricas Personalizadas

En tu código PHP, añade métricas personalizadas:

```php
// En cualquier parte de tu código
echo "app_custom_metric_total " . $valor . "\n";
echo "app_custom_gauge{label=\"valor\"} " . $otro_valor . "\n";
```

### Dashboard Personalizado

1. **En Grafana**: Crear → Dashboard
2. **Añadir Paneles**: Click en "Add panel"
3. **Consultas Prometheus**: Usar consultas como:
   - `rate(app_http_requests_total[5m])`
   - `app_users_total`
   - `mysql_global_status_threads_connected`

---

## Validación de configuración

```bash
# Sintaxis de Compose
docker compose config

# Prometheus + reglas (imagen v3 usa entrypoint prometheus; invocar promtool explícito)
docker run --rm --entrypoint promtool -v "$(pwd)/monitoring/prometheus:/etc/prometheus:ro" prom/prometheus:v3.11.3 \
  check config /etc/prometheus/prometheus.yml
docker run --rm --entrypoint promtool -v "$(pwd)/monitoring/prometheus:/etc/prometheus:ro" prom/prometheus:v3.11.3 \
  check rules /etc/prometheus/alerts.yml
```

En Windows (PowerShell), sustituye `$(pwd)` por la ruta absoluta al repo o usa `${PWD}`.

La plantilla de Alertmanager contiene placeholders (`__SMTP_*__`); la validación final ocurre al arrancar el contenedor tras el entrypoint.

## 🛠️ Troubleshooting

### Dashboard no visible en Grafana

1. **Verificar provisionamiento**:
```bash
docker-compose logs grafana | grep -i dashboard
```

2. **Reiniciar Grafana**:
```bash
docker-compose restart grafana
```

3. **Verificar permisos**:
```bash
ls -la monitoring/grafana/dashboards/
```

### Métricas no aparecen

1. **Verificar endpoint de métricas**:
```bash
curl http://localhost:80/metrics.php
```

2. **Verificar Prometheus targets**:
   - Ir a http://localhost:9090/targets
   - Verificar estado de "php-app"

3. **Verificar logs**:
```bash
docker-compose logs prometheus
```

### Alertas no se disparan

1. **Verificar reglas de alertas**:
   - Ir a http://localhost:9090/rules
   - Verificar que las reglas estén cargadas

2. **Verificar AlertManager**:
   - Ir a http://localhost:9093
   - Verificar estado de alertas

3. **Probar notificaciones**:
```bash
curl -XPOST http://localhost:9093/api/v1/alerts -d '[{
  "labels": {
    "alertname": "test_alert",
    "severity": "warning"
  },
  "annotations": {
    "summary": "Test alert"
  }
}]'
```

## 📊 Consultas Prometheus Útiles

### Tráfico Web
```promql
# Requests por segundo
rate(app_http_requests_total[5m])

# Errores 5xx
rate(app_http_requests_total{status=~"5.."}[5m])

# Tiempo de respuesta p95
app_http_response_time_seconds{quantile="0.95"}
```

### Base de Datos
```promql
# Conexiones MySQL
mysql_global_status_threads_connected

# Consultas por segundo
rate(mysql_global_status_questions[5m])

# Consultas lentas
rate(mysql_global_status_slow_queries[5m])
```

### Sistema
```promql
# Uso de CPU
100 - (avg(irate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)

# Uso de Memoria
(node_memory_MemTotal_bytes - node_memory_MemAvailable_bytes) / node_memory_MemTotal_bytes * 100

# Espacio en Disco
(node_filesystem_size_bytes{mountpoint="/"} - node_filesystem_avail_bytes{mountpoint="/"}) / node_filesystem_size_bytes{mountpoint="/"} * 100
```

### Negocio
```promql
# Usuarios totales registrados
app_users_total

# Usuarios con actividad HTTP reflejada en BD (ventana en etiqueta window_minutes)
app_users_active

# Citas totales
app_citas_total
```

## 🔒 Seguridad

### Acceso a Grafana
- Cambiar contraseña predeterminada
- Habilitar autenticación LDAP/SSO si es necesario
- Configurar roles y permisos

### Acceso a Prometheus
- Restringir acceso mediante firewall
- Considerar autenticación básica

### Métricas Sensibles
- No exponer credenciales en métricas
- Filtrar datos sensibles antes de exponerlos

## 📈 Mejores Prácticas

### 1. Monitoreo Proactivo
- Revisar dashboards diariamente
- Establecer alertas antes de que ocurran problemas
- Monitorear tendencias a largo plazo

### 2. Documentación
- Documentar causas de alertas comunes
- Mantener registro de incidentes
- Actualizar umbrales según el crecimiento

### 3. Optimización
- Limpiar logs regularmente
- Optimizar consultas lentas
- Escalar recursos según necesidad

### 4. Pruebas
- Probar alertas regularmente
- Simular fallos para validar respuestas
- Verificar notificaciones en diferentes canales

## 🚨 Procedimientos de Incidentes

### Sitio Caído (HTTP 5xx > 50%)
1. **Verificar**: Dashboard de Grafana → Estado General
2. **Investigar**: Logs de la aplicación PHP
3. **Acciones**:
   - Reiniciar contenedor web si es necesario
   - Verificar base de datos
   - Revisar recursos del sistema

### Base de Datos Lenta
1. **Verificar**: Conexiones activas, consultas lentas
2. **Investigar**: Logs de MySQL, consultas problemáticas
3. **Acciones**:
   - Optimizar consultas
   - Aumentar conexiones máximas
   - Considerar indexación

### Recursos del Sistema Altos
1. **Verificar**: CPU, Memoria, Disco
2. **Investigar**: Procesos que consumen recursos
3. **Acciones**:
   - Escalar recursos
   - Limpiar logs/archivos temporales
   - Optimizar consultas

## 📞 Soporte

### Recursos Útiles
- [Documentación de Grafana](https://grafana.com/docs/)
- [Documentación de Prometheus](https://prometheus.io/docs/)
- [Documentación de AlertManager](https://prometheus.io/docs/alerting/latest/alertmanager/)

### Comandos Útiles
```bash
# Ver logs de todos los servicios
docker-compose logs -f

# Reiniciar servicios de monitoreo
docker-compose restart prometheus grafana

# Ver estado de contenedores
docker-compose ps

# Limpiar logs antiguos
docker-compose exec grafana grafana-cli admin reset-admin-password newpass
```

---

**Nota**: Este sistema de monitoreo proporciona visibilidad completa de tu taller mecánico, permitiéndote detectar y resolver problemas antes de que afecten a tus clientes. Revisa regularmente los dashboards y ajusta los umbrales según el crecimiento de tu negocio.
