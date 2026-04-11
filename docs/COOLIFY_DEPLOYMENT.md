# Despliegue en Coolify (MySQL)

Esta guía cubre el despliegue en Coolify con MySQL y los pasos “antes de darle a Deploy” (recursos, variables, volúmenes y comprobaciones).

El fallo más común en Coolify es que la app arranca sin `DB_*` y termina intentando conectar a `localhost` (dentro del contenedor), o que Coolify expone la BD como `MYSQL_*` en lugar de `DB_*`.

Este repo ya soporta ambos: `DB_*` y alias `MYSQL_*` (y en `docker-compose.coolify.yml` la app usa `MYSQL_*` por defecto para que funcione también con DBs gestionadas por Coolify).

## Antes de empezar (recomendado)

- Para Coolify, lo más estable es desplegar **solo la app** y usar BD gestionada por Coolify.
- Si quieres monitorización (Prometheus/Grafana), despliega esos servicios en un **segundo stack** (recomendado) para evitar acoplamiento y problemas de puertos/healthchecks.
- No subas `.env` al repo. En Coolify configura variables/secretos desde la UI.
- Decide **una** de estas estrategias de BD:
  - **A) MySQL dentro del mismo Docker Compose** (más simple).
  - **B) MySQL gestionado por Coolify** y “link” a la app (Coolify suele inyectar `MYSQL_*`).

## 1) Crear recursos en Coolify

### Opción A (recomendada): app + MySQL en el mismo Compose

1. Coolify → **New Resource** → **Docker Compose** → selecciona el repo.
2. En **Compose file**, usa `docker-compose.coolify.yml`.
3. En **Domains/Routes**, apunta tu dominio a la app (servicio `web`, puerto interno `80`).
4. En **Storage/Volumes**, deja los volúmenes nombrados tal cual (persisten datos).

### Opción B: MySQL gestionado por Coolify (y app por Dockerfile o Compose)

1. Crea una **Database (MySQL)** en Coolify.
2. En tu app (resource), usa **Link/Connect to database** (nombre puede variar por versión).
3. Verifica que Coolify inyecta variables tipo `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DATABASE` (o equivalentes).

### Opción C (recomendada si quieres monitorización): 2 stacks separados

- **Stack 1 (app)**: usa `docker-compose.coolify.app.yml` (solo `web`).
- **Stack 2 (monitoring + backup)**: usa `docker-compose.coolify.monitoring.yml`.

Esto te permite desplegar/actualizar la app sin tumbar Prometheus/Grafana y reduce fallos por puertos publicados o healthchecks en servicios auxiliares.

Acceso recomendado (sin publicar puertos fijos al host):

- En Coolify, crea **Domains/Routes** para estos servicios apuntando a sus **puertos internos**:
  - `grafana.tudominio.com` → `grafana` : `3000`
  - `prometheus.tudominio.com` → `prometheus` : `9090`
  - `alertmanager.tudominio.com` → `alertmanager` : `9093`

Esto evita errores tipo `Bind for 0.0.0.0:9093 failed: port is already allocated`.

Nota sobre el scraping de métricas (importante):

- La configuración de Prometheus de este repo apunta por defecto a `web:80` (service discovery dentro de la misma red Docker).
- Si despliegas **app y monitoring en recursos separados**, `web` solo será resolvible si ambos stacks comparten una red Docker.
  - Solución simple (sin redes compartidas): despliega todo junto con `docker-compose.coolify.yml`.
  - Solución avanzada: crea/usa una red externa compartida en Coolify y conecta ambos stacks a esa red, o adapta `monitoring/prometheus/prometheus.yml` para scrapear la URL pública de la app.

## Checklist de verificación (tras el Deploy)

- En Coolify, confirma que cada Domain/Route apunta al servicio y puerto correcto:
  - `web` → `80`
  - `grafana` → `3000`
  - `prometheus` → `9090`
  - `alertmanager` → `9093`
- En Prometheus (`/targets`), valida que estos jobs están `UP`:
  - `prometheus`
  - `php-app`
  - `node-exporter`
  - `mysqld-exporter`

## 2) Variables de entorno (obligatorio)

Configura estas variables en Coolify (Resource → **Environment Variables / Secrets**).

### Variables mínimas de la app

- `APP_ENV=production`
- `APP_DEBUG=false`

### Variables de base de datos (elige un set)

**Set 1: `DB_*` (explícitas)**

- `DB_HOST` (hostname del servicio MySQL en la red Docker; en Compose suele ser `mysql`)
- `DB_PORT` (normalmente `3306`)
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

**Muy importante (evita “deploy se para” y logs “congelados”):**

- En Docker/Coolify **no uses** `DB_HOST=localhost` (ni `127.0.0.1`, ni `::1`).
  - Dentro del contenedor, eso apunta al propio contenedor (PHP), no al MySQL.
  - El `entrypoint` de la app lo detecta y lo fuerza a `mysql:3306`, dejando un aviso en logs.

**Set 2: `MYSQL_*` (si Coolify las inyecta al linkar la BD)**

- `MYSQL_HOST`
- `MYSQL_PORT`
- `MYSQL_DATABASE`
- `MYSQL_USER`
- `MYSQL_PASSWORD`

Si Coolify te crea `MYSQL_*` automáticamente, **no necesitas duplicarlas**: la app las mapeará a `DB_*`.

### Variables si usas MySQL dentro del mismo Compose

El servicio `mysql` del compose usa estas (pon valores seguros):

- `MYSQL_ROOT_PASSWORD` (obligatoria)
- `MYSQL_DATABASE`
- `MYSQL_USER`
- `MYSQL_PASSWORD`

Ejemplo típico (no copies contraseñas débiles):

```env
APP_ENV=production
APP_DEBUG=false

MYSQL_ROOT_PASSWORD=CAMBIA_ESTO
MYSQL_DATABASE=trabajo_final_php
MYSQL_USER=app_user
MYSQL_PASSWORD=CAMBIA_ESTO

# Opcional si quieres forzar el set DB_* (si no, el repo ya usa alias MYSQL_*)
DB_HOST=mysql
DB_PORT=3306
DB_NAME=trabajo_final_php
DB_USER=app_user
DB_PASS=CAMBIA_ESTO
```

## 3) Persistencia y primera inicialización (database.sql)

Si usas `mysql_data` (volumen persistente), el script `database/database.sql` **solo se ejecuta la primera vez** (cuando el volumen está vacío).

Qué revisar antes del primer deploy:

- MySQL debe tener un volumen persistente (en el compose: `mysql_data:/var/lib/mysql`).
- El SQL de arranque se carga desde `/docker-entrypoint-initdb.d/` (en `docker-compose.coolify.yml` se monta el directorio `./database:/docker-entrypoint-initdb.d:ro`).

Si cambias credenciales o necesitas reinicializar el esquema, borra el **volumen** de MySQL (o la DB) en Coolify y despliega de nuevo.

## 4) Puertos y red (muy importante)

- En Coolify, **no uses `DB_HOST=localhost`** para la app.
  - `localhost` dentro del contenedor apunta al propio contenedor de PHP, no al de MySQL.
- Si MySQL vive en el mismo Compose, el host suele ser el **nombre del servicio**: `mysql`.
- La app escucha en el puerto interno `80` (Coolify enruta el dominio a ese puerto).

## 5) Checklist rápido antes de “Deploy”

- El resource apunta al compose `docker-compose.dokploy.yml` (o equivalente sin bind-mount del código).
- Dominio/Route → servicio `web` → puerto `80`.
- Variables mínimas: `APP_ENV=production`, `APP_DEBUG=false`.
- Variables de BD presentes (`DB_*` o `MYSQL_*`).
- Si usas MySQL del compose: `MYSQL_ROOT_PASSWORD` definido y volumen `mysql_data` creado.

## 6) Diagnóstico (si falla la conexión)

Errores típicos:

- `SQLSTATE[HY000] [2002] Connection refused` → host/puerto incorrecto o MySQL aún no está listo.
- `Access denied for user` → credenciales/usuario/privilegios.
- `Unknown database` → `MYSQL_DATABASE`/`DB_NAME` no coincide o el init no se ejecutó (volumen ya existente).

Pasos:

1. Revisa logs del servicio **mysql** y confirma que está “healthy/ready”.
2. Revisa logs del servicio **web** (PHP) y confirma qué variables de entorno están configuradas.
   - Si ves algo como `ADVERTENCIA: DB_HOST=localhost dentro de Docker...` significa que venía mal configurado y se ha corregido a `mysql:3306`.
   - Si el despliegue se corta con `ERROR: MySQL no está disponible...` el log ahora mostrará el “Último error” (DNS, credenciales, TLS, etc.).
3. Si necesitas ver detalles en pantalla temporalmente: pon `APP_DEBUG=true` y vuelve a desplegar (después vuelve a `false`).

## 7) Caso real: “la BD existe pero faltan tablas”

Si ves errores como:

- `Table 'trabajo_final_php.users_data' doesn't exist`

Significa que el volumen de MySQL se creó, pero el init SQL no llegó a ejecutarse (o se desplegó una vez con un volumen vacío y luego quedó “a medias”).

Soluciones:

- **Recomendado:** borra el volumen `mysql_data` en Coolify y despliega de nuevo (esto re-ejecuta los scripts en `/docker-entrypoint-initdb.d/`).
- Alternativa (automática): el contenedor `web` intenta importar `database/database.sql` si detecta que falta `users_data`.
  - Variables opcionales:
    - `AUTO_SCHEMA_IMPORT=1` (por defecto `1`)
    - `SCHEMA_FILE=/var/www/html/database/database.sql`
