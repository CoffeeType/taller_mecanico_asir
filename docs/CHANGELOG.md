# Registro de versiones

Histórico de hitos del proyecto. Para procedimientos de instalación y uso, consulta [README.md](../README.md) y las guías en `docs/`.

## Versión actual (evolución respecto a v1.0.0)

Respecto al checklist inicial del PFC, el sistema incluye además:

- Panel canónico en **`admin/`** (usuarios, citas, noticias, consejos).
- **`citaciones.php`**: reserva con calendario y franjas horarias; API en `api/citas_api.php`.
- Tablas **`consejos`** y campos de invitado en **`citas`** (`hora_cita`, `guest_*`).
- Monitorización Docker (Prometheus, Grafana, Alertmanager) y simulador JMeter opcional.

Los ficheros **`*-administracion.php`** en la raíz se conservan por compatibilidad con el PFC inicial; **no** forman parte del flujo operativo documentado. Usa las rutas bajo `admin/`.

## v1.0.0 — Implementación base del PFC

Primera entrega funcional: autenticación, CRUD de usuarios/citas/noticias, estilos responsive, seguridad (PDO, bcrypt, validación de sesiones) y documentación de instalación local.
