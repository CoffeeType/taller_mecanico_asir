# Context — Taller Mecánico ASIR

Glosario de lenguaje ubicuo del dominio. Sin rutas de código ni detalles de despliegue.

## Glossary

- **Cita** — Registro persistido de una reserva (fecha, hora, motivo; usuario registrado o datos de invitado).
- **Citación** — Acción del usuario de solicitar una reserva. Una citación exitosa crea una Cita.
- **Eliminar cita** — Acción del administrador que borra físicamente el registro de la Cita (no es «cancelar» en sentido de estado pendiente).
- **Panel de administración** — Superficie donde el administrador gestiona usuarios, citas, noticias y consejos.
- **Usuario** — Persona registrada con cuenta y sesión; rol `user`.
- **Administrador** — Usuario con rol `admin`; gestiona usuarios, citas, noticias y consejos.
- **Invitado** — Persona sin cuenta que realiza una citación; la Cita se guarda con datos de contacto del invitado e identificador de usuario nulo.
- **Franja horaria** — Par fecha + hora de una Cita. Como máximo una Cita activa por franja.
- **Noticia** — Artículo del sector motor; puede importarse por RSS y enlazar a fuente externa.
- **Consejo** — Recomendación práctica de mantenimiento redactada por el taller; contenido editorial propio.
- **Tráfico de aplicación** — Peticiones HTTP de usuarios reales; en logs y métricas se distingue con la etiqueta `source=app`.
- **Tráfico simulado** — Peticiones HTTP generadas por JMeter para pruebas de carga; en logs y métricas se distingue con la etiqueta `source=simulator`.
- **Confirmación de cita** — La Cita existe y es válida en cuanto se persiste (citación exitosa).
- **Correo de confirmación** — Aviso best-effort al usuario o invitado; un fallo de envío no invalida la Cita.
