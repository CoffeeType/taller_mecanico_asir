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

## 🚀 Configuración Rápida

### Paso 1: Iniciar el Sistema de Monitoreo

```bash
# Iniciar todos los servicios de monitoreo
docker-compose up -d prometheus alertmanager grafana node-exporter mysqld-exporter

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

> Nota: si cambias `WEB_PORT` en `.env`, el endpoint será `http://localhost:<WEB_PORT>/metrics.php` (por defecto `8081`).

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

El envío de emails se configura con variables de entorno (ver `.env.example`). En Docker, `docker-compose.yml` genera el archivo final de Alertmanager a partir de la plantilla `monitoring/prometheus/alertmanager.yml` sustituyendo los valores (para no commitear credenciales).

Variables recomendadas:

- `ALERT_EMAIL_TO`: destinatario(s) (ej: `tuemail@dominio.com` o `a@dominio.com,b@dominio.com`)
- `SMTP_SMARTHOST`: host:puerto SMTP (ej: `smtp.gmail.com:587`)
- `SMTP_FROM`: remitente (ej: `monitoring@tallermecanico.com`)
- `SMTP_AUTH_USERNAME`: usuario SMTP (normalmente el email)
- `SMTP_AUTH_PASSWORD`: contraseña o "app password" del proveedor
- `SMTP_REQUIRE_TLS`: `true`/`false` (normalmente `true` en 587)

Después de modificar `.env`:

```bash
docker-compose up -d alertmanager prometheus
```

### Enviar un email de prueba (desde Grafana)

- Entra en Grafana → Dashboard principal → link `Test Email (Alertas)` (abre `http://localhost:8081/admin/test-alert-email.php` o el valor de `WEB_PORT`).
- Requiere iniciar sesión como admin en la aplicación.

### Alertas Críticas (Rojo)

#### 1. Alta Tasa de Errores HTTP
- **Condición**: Errores 5xx > 10% durante 2 minutos
- **Acción**: Revisar logs de la aplicación, verificar base de datos
- **Notificación**: Email inmediato al administrador

#### 2. Conexión a Base de Datos Caída
- **Condición**: `app_db_connection_healthy == 0` durante 1 minuto
- **Acción**: Verificar MySQL, conexiones, credenciales
- **Notificación**: Email crítico con enlace al dashboard

#### 3. Tiempo de Respuesta Crítico
- **Condición**: p95 > 5 segundos durante 2 minutos
- **Acción**: Optimizar consultas, revisar recursos del sistema
- **Notificación**: Email con detalles de rendimiento

#### 4. Recursos del Sistema Agotados
- **CPU**: > 95% durante 2 minutos
- **Memoria**: > 95% durante 2 minutos
- **Disco**: < 5% de espacio libre
- **Acción**: Escalar recursos, limpiar logs/archivos
- **Notificación**: Email crítico

### Alertas de Advertencia (Amarillo)

#### 1. Errores HTTP Moderados
- **Condición**: Errores 5xx > 5% durante 5 minutos
- **Acción**: Investigar causas, monitorear tendencias

#### 2. Tiempo de Respuesta Alto
- **Condición**: p95 > 2 segundos durante 5 minutos
- **Acción**: Optimizar consultas lentas, revisar caché

#### 3. Uso de Recursos Alto
- **CPU**: > 70% durante 5 minutos
- **Memoria**: > 85% durante 5 minutos
- **Disco**: < 15% de espacio libre
- **Acción**: Planificar escalado, limpieza preventiva

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
# Cambiar umbrales de tiempo de respuesta
- alert: HighResponseTime
  expr: app_http_response_time_seconds{quantile="0.95"} > 3  # Cambiar de 2s a 3s
  for: 5m
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
# Usuarios totales
app_users_total

# Citas totales
app_citas_total

# Sesiones activas
app_sessions_active
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
