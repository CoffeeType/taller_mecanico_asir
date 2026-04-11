# Guía de Despliegue con Docker

Esta guía explica cómo desplegar la aplicación Taller Mecánico usando Docker y Docker Compose.

## Requisitos Previos

- Docker Engine 20.10 o superior
- Docker Compose 2.0 o superior
- Al menos 2GB de RAM disponible
- Al menos 5GB de espacio en disco

### Instalación de Docker en Windows

1. **Descargar Docker Desktop para Windows:**
   - Ve a: https://www.docker.com/products/docker-desktop
   - Descarga Docker Desktop para Windows
   - Ejecuta el instalador y sigue las instrucciones

2. **Requisitos del sistema:**
   - Windows 10 64-bit: Pro, Enterprise, o Education (Build 19041 o superior)
   - Windows 11 64-bit: Home o Pro versión 21H2 o superior
   - Habilitar WSL 2 (Windows Subsystem for Linux 2)
   - Habilitar Hyper-V y Virtualización en el BIOS

3. **Verificar la instalación:**
   - Abre PowerShell o CMD
   - Ejecuta: `docker --version`
   - Ejecuta: `docker-compose --version`

## Instalación Rápida

### 1. Clonar o Descargar el Proyecto

**En Linux/Mac:**
```bash
git clone <url-del-repositorio>
cd taller_mecanico
```

**En Windows (PowerShell o CMD):**
```powershell
git clone <url-del-repositorio>
cd taller_mecanico
```

**O si descargas el proyecto como ZIP:**
1. Extrae el archivo ZIP en una carpeta (por ejemplo: `C:\proyectos\taller_mecanico`)
2. Abre PowerShell o CMD en esa carpeta
3. Navega a la carpeta del proyecto: `cd C:\proyectos\taller_mecanico`

### 2. Configurar Variables de Entorno

Copia el archivo de ejemplo y ajusta los valores según tus necesidades:

**En Linux/Mac:**
```bash
cp .env.example .env
```

**En Windows (PowerShell):**
```powershell
Copy-Item .env.example .env
```

**En Windows (CMD):**
```cmd
copy .env.example .env
```

Edita el archivo `.env` con tus preferencias:

```env
# Configuración de Base de Datos
DB_HOST=mysql
DB_NAME=trabajo_final_php
DB_USER=root
DB_PASS=rootpassword

# Configuración de MySQL
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=trabajo_final_php
MYSQL_USER=app_user
MYSQL_PASSWORD=app_password

# Configuración de Grafana
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=admin123

# Puertos
WEB_PORT=8081
MYSQL_PORT=3306
PROMETHEUS_PORT=9090
GRAFANA_PORT=3000
```

**IMPORTANTE:** Cambia las contraseñas por defecto en producción.

### 3. Construir e Iniciar los Contenedores

**En Linux/Mac/Windows (PowerShell/CMD):**
```bash
docker-compose up -d
```

Este comando:
- Construye la imagen de la aplicación PHP
- Descarga las imágenes necesarias (MySQL, Prometheus, Grafana, etc.)
- Crea y inicia todos los contenedores
- Inicializa la base de datos automáticamente (solo la primera vez, cuando el volumen `mysql_data` está vacío)

> **Nota sobre cambios de esquema:** si ya tenías el volumen `mysql_data` creado y actualizas `database/database.sql`, MySQL **no** re-ejecuta el script de init. El contenedor `web` ejecuta una migración idempotente al arrancar (`scripts/update_schema_v2.php`) para añadir columnas nuevas como `calle`, `codigo_postal`, `ciudad` y `provincia`.

**Nota para Windows:** Si es la primera vez que ejecutas Docker Desktop, puede tardar unos minutos en iniciar. Asegúrate de que Docker Desktop esté ejecutándose (verás el icono de Docker en la bandeja del sistema).

### 4. Verificar que Todo Está Funcionando

**En Linux/Mac/Windows (PowerShell/CMD):**
```bash
docker-compose ps
```

Todos los servicios deberían estar en estado "Up".

**Alternativa en Windows (PowerShell):**
```powershell
docker ps
```

### 5. Acceder a la Aplicación

- **Aplicación Web:** http://localhost:8081 (o el valor de `WEB_PORT` en `.env`)
- **Grafana (Monitorización):** http://localhost:3000
  - **Usuario:** `admin` (o el configurado en `.env` con `GRAFANA_ADMIN_USER`)
  - **Contraseña:** `admin123` (o la configurada en `.env` con `GRAFANA_ADMIN_PASSWORD`)
- **Prometheus:** http://localhost:9090

## Servicios Incluidos

### 1. Web (Aplicación PHP)
- **Puerto:** 8081 (configurable con `WEB_PORT`)
- **Imagen:** Construida desde `Dockerfile`
- **Volúmenes:**
  - `./assets/images` - Imágenes subidas por usuarios
  - `./monitoring/php-exporter/metrics.php` - Endpoint de métricas

### 2. MySQL (Base de Datos)
- **Puerto:** 3306 (configurable)
- **Imagen:** `mysql:8.0`
- **Volúmenes:**
  - `mysql_data` - Datos persistentes de MySQL
  - `./database/database.sql` - Script de inicialización

### 3. Prometheus (Monitorización)
- **Puerto:** 9090 (configurable)
- **Imagen:** `prom/prometheus:latest`
- **Configuración:** `monitoring/prometheus/prometheus.yml`
- **Retención de datos:** 15 días (configurable)

### 4. Grafana (Visualización)
- **Puerto:** 3000 (configurable)
- **Imagen:** `grafana/grafana:latest`
- **Dashboards:** Se cargan automáticamente desde `monitoring/grafana/dashboards/`
- **Dashboards incluidos:**
  - `sistema.json` - Métricas del sistema (CPU, memoria, disco, red)
  - `aplicacion.json` - Métricas de la aplicación (requests, tiempos de respuesta)
  - `base-datos.json` - Métricas de MySQL
  - `negocio.json` - Métricas de negocio (usuarios, citas, noticias)

### 5. Node Exporter (Métricas del Sistema)
- **Puerto:** 9100
- **Imagen:** `prom/node-exporter:latest`
- Expone métricas de CPU, memoria, disco y red
- **Nota:** En Windows, las métricas del sistema pueden tener limitaciones. Para producción en Linux, descomenta los volúmenes en `docker-compose.yml`

### 6. MySQL Exporter (Métricas de Base de Datos)
- **Puerto:** 9104
- **Imagen:** `prom/mysqld-exporter:latest`
- Expone métricas de rendimiento de MySQL
- **Configuración:** Usa un script de entrypoint personalizado para compatibilidad con Windows (`monitoring/mysqld_exporter/entrypoint.sh`)

## Comandos Útiles

> **Nota:** Los siguientes comandos funcionan igual en Linux, Mac y Windows (PowerShell/CMD). Docker Compose es multiplataforma.

### Ver Logs

```bash
# Todos los servicios
docker-compose logs -f

# Servicio específico
docker-compose logs -f web
docker-compose logs -f mysql
docker-compose logs -f grafana
```

**Para salir de los logs en tiempo real:** Presiona `Ctrl + C`

### Detener los Servicios

```bash
docker-compose stop
```

### Iniciar los Servicios

```bash
docker-compose start
```

### Reiniciar un Servicio Específico

```bash
docker-compose restart web
```

### Detener y Eliminar Contenedores

```bash
docker-compose down
```

## Troubleshooting

### Error al registrar: `Unknown column 'calle' in 'field list'`

Esto suele indicar que estás usando una base de datos creada con un esquema anterior.

- Reinicia el contenedor `web` para que ejecute la migración automática: `docker-compose restart web`
- Si quieres recrear la base de datos desde cero (pierdes datos): `docker-compose down -v` y luego `docker-compose up -d`
- Para desactivar la migración automática: añade `AUTO_MIGRATE=0` en el `.env` del servicio `web`

**ADVERTENCIA:** Esto no elimina los volúmenes. Los datos de MySQL se conservan.

### Detener y Eliminar Todo (Incluyendo Datos)

```bash
docker-compose down -v
```

**ADVERTENCIA:** Esto elimina todos los datos, incluyendo la base de datos.

### Reconstruir la Aplicación

Si has modificado el código o el Dockerfile:

```bash
docker-compose build web
docker-compose up -d web
```

### Acceder al Contenedor

```bash
# Acceder al contenedor web
docker-compose exec web bash

# Acceder a MySQL
docker-compose exec mysql mysql -u root -p
```

**Nota para Windows:** Si `bash` no está disponible en el contenedor, puedes usar `sh`:
```bash
docker-compose exec web sh
```

## Backup y Restore

### Backup de la Base de Datos

**En Linux/Mac:**
```bash
# Cargar variable de entorno desde .env (si no está exportada)
export $(grep -v '^#' .env | xargs)

# Crear backup con timestamp
docker-compose exec mysql mysqldump -u root -p"${MYSQL_ROOT_PASSWORD:-rootpassword}" trabajo_final_php > backup_$(date +%Y%m%d_%H%M%S).sql

# Verificar que el backup se creó correctamente
if [ -f backup_*.sql ]; then
    echo "Backup creado exitosamente"
    ls -lh backup_*.sql
else
    echo "Error: No se pudo crear el backup"
    exit 1
fi
```

**En Windows (PowerShell):**
```powershell
# Cargar variables de entorno desde .env
$envFile = Get-Content .env | Where-Object { $_ -notmatch '^\s*#' -and $_ -match '=' } | ForEach-Object {
    $key, $value = $_.Split('=', 2)
    [PSCustomObject]@{Key = $key.Trim(); Value = $value.Trim()}
}
$mysqlPassword = ($envFile | Where-Object { $_.Key -eq 'MYSQL_ROOT_PASSWORD' }).Value
if (-not $mysqlPassword) { $mysqlPassword = "rootpassword" }

# Crear backup con timestamp
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
docker-compose exec mysql mysqldump -u root -p"$mysqlPassword" trabajo_final_php > "backup_$timestamp.sql"

# Verificar que el backup se creó correctamente
if (Test-Path "backup_$timestamp.sql") {
    Write-Host "Backup creado exitosamente"
    Get-Item "backup_$timestamp.sql" | Select-Object Name, Length, LastWriteTime
} else {
    Write-Error "Error: No se pudo crear el backup"
    exit 1
}
```

**En Windows (CMD):**
```cmd
REM Nota: Necesitas configurar MYSQL_ROOT_PASSWORD manualmente o desde .env
REM Para cargar desde .env, puedes usar un script de PowerShell o configurarlo manualmente:
REM set MYSQL_ROOT_PASSWORD=rootpassword

REM Crear backup con timestamp
docker-compose exec mysql mysqldump -u root -p%MYSQL_ROOT_PASSWORD% trabajo_final_php > backup_%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%.sql

REM Verificar que el backup se creó (revisar manualmente)
dir backup_*.sql
```

**Nota:** Reemplaza `rootpassword` con la contraseña configurada en tu archivo `.env` (variable `MYSQL_ROOT_PASSWORD`). En producción, siempre usa variables de entorno en lugar de contraseñas hardcodeadas.

### Restore de la Base de Datos

**IMPORTANTE:** Antes de restaurar un backup:
- Asegúrate de que los contenedores estén ejecutándose: `docker-compose ps`
- Verifica que el archivo de backup existe y es válido
- Si restauras sobre una base de datos existente, los datos actuales serán sobrescritos
- Para una restauración limpia, considera eliminar el volumen primero: `docker-compose down -v` (esto elimina todos los datos)

#### Restore en Linux/Mac (Bash)

```bash
# 1. Verificar que el archivo de backup existe
if [ ! -f backup.sql ]; then
    echo "Error: backup.sql no encontrado en el directorio actual"
    echo "Usa la ruta completa si el archivo está en otra ubicación"
    exit 1
fi

# 2. Cargar variable de entorno desde .env (si no está exportada)
export $(grep -v '^#' .env | xargs)
MYSQL_PASS="${MYSQL_ROOT_PASSWORD:-rootpassword}"

# 3. Verificar que MySQL está ejecutándose
if ! docker-compose ps mysql | grep -q "Up"; then
    echo "Error: El contenedor MySQL no está ejecutándose"
    echo "Inicia los contenedores con: docker-compose up -d"
    exit 1
fi

# 4. Crear la base de datos si no existe (normalmente ya existe)
echo "Verificando que la base de datos existe..."
docker-compose exec -T mysql mysql -u root -p"$MYSQL_PASS" -e "CREATE DATABASE IF NOT EXISTS trabajo_final_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || {
    echo "Error: No se pudo crear/verificar la base de datos"
    exit 1
}

# 5. Restaurar el backup
echo "Restaurando backup..."
docker-compose exec -T mysql mysql -u root -p"$MYSQL_PASS" trabajo_final_php < backup.sql || {
    echo "Error: La restauración falló"
    exit 1
}

# 6. Verificar que la restauración fue exitosa
echo "Verificando restauración..."
TABLES=$(docker-compose exec -T mysql mysql -u root -p"$MYSQL_PASS" trabajo_final_php -e "SHOW TABLES;" 2>/dev/null | wc -l)
if [ "$TABLES" -gt 1 ]; then
    echo "✅ Restauración exitosa. Se encontraron $((TABLES-1)) tablas."
    docker-compose exec -T mysql mysql -u root -p"$MYSQL_PASS" trabajo_final_php -e "SHOW TABLES;"
else
    echo "⚠️ Advertencia: No se encontraron tablas después de la restauración"
    exit 1
fi
```

#### Restore en Windows (PowerShell)

```powershell
# 1. Verificar que el archivo de backup existe
if (-not (Test-Path backup.sql)) {
    Write-Error "Error: backup.sql no encontrado en el directorio actual"
    Write-Host "Usa la ruta completa si el archivo está en otra ubicación"
    exit 1
}

# 2. Cargar variables de entorno desde .env
$envFile = Get-Content .env | Where-Object { $_ -notmatch '^\s*#' -and $_ -match '=' } | ForEach-Object {
    $key, $value = $_.Split('=', 2)
    [PSCustomObject]@{Key = $key.Trim(); Value = $value.Trim()}
}
$mysqlPassword = ($envFile | Where-Object { $_.Key -eq 'MYSQL_ROOT_PASSWORD' }).Value
if (-not $mysqlPassword) { $mysqlPassword = "rootpassword" }

# 3. Verificar que MySQL está ejecutándose
$mysqlStatus = docker-compose ps mysql
if ($mysqlStatus -notmatch "Up") {
    Write-Error "Error: El contenedor MySQL no está ejecutándose"
    Write-Host "Inicia los contenedores con: docker-compose up -d"
    exit 1
}

# 4. Crear la base de datos si no existe (normalmente ya existe)
Write-Host "Verificando que la base de datos existe..."
docker-compose exec -T mysql mysql -u root -p"$mysqlPassword" -e "CREATE DATABASE IF NOT EXISTS trabajo_final_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if ($LASTEXITCODE -ne 0) {
    Write-Error "Error: No se pudo crear/verificar la base de datos"
    exit 1
}

# 5. Restaurar el backup
Write-Host "Restaurando backup..."
Get-Content backup.sql | docker-compose exec -T mysql mysql -u root -p"$mysqlPassword" trabajo_final_php
if ($LASTEXITCODE -ne 0) {
    Write-Error "Error: La restauración falló"
    exit 1
}

# 6. Verificar que la restauración fue exitosa
Write-Host "Verificando restauración..."
$tablesOutput = docker-compose exec -T mysql mysql -u root -p"$mysqlPassword" trabajo_final_php -e "SHOW TABLES;" 2>$null
$tableCount = ($tablesOutput | Measure-Object -Line).Lines
if ($tableCount -gt 1) {
    Write-Host "✅ Restauración exitosa. Se encontraron $($tableCount - 1) tablas."
    docker-compose exec -T mysql mysql -u root -p"$mysqlPassword" trabajo_final_php -e "SHOW TABLES;"
} else {
    Write-Warning "⚠️ Advertencia: No se encontraron tablas después de la restauración"
    exit 1
}
```

#### Restore en Windows (CMD)

```cmd
REM 1. Verificar que el archivo de backup existe
if not exist backup.sql (
    echo Error: backup.sql no encontrado en el directorio actual
    echo Usa la ruta completa si el archivo está en otra ubicación
    exit /b 1
)

REM 2. Configurar la contraseña (cámbiala según tu .env)
REM Nota: En CMD, necesitas configurar la variable manualmente o usar un script
set MYSQL_ROOT_PASSWORD=rootpassword

REM 3. Verificar que MySQL está ejecutándose
docker-compose ps mysql | findstr "Up" >nul
if errorlevel 1 (
    echo Error: El contenedor MySQL no está ejecutándose
    echo Inicia los contenedores con: docker-compose up -d
    exit /b 1
)

REM 4. Crear la base de datos si no existe (normalmente ya existe)
echo Verificando que la base de datos existe...
docker-compose exec -T mysql mysql -u root -p%MYSQL_ROOT_PASSWORD% -e "CREATE DATABASE IF NOT EXISTS trabajo_final_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo Error: No se pudo crear/verificar la base de datos
    exit /b 1
)

REM 5. Restaurar el backup
echo Restaurando backup...
type backup.sql | docker-compose exec -T mysql mysql -u root -p%MYSQL_ROOT_PASSWORD% trabajo_final_php
if errorlevel 1 (
    echo Error: La restauración falló
    exit /b 1
)

REM 6. Verificar que la restauración fue exitosa
echo Verificando restauración...
docker-compose exec -T mysql mysql -u root -p%MYSQL_ROOT_PASSWORD% trabajo_final_php -e "SHOW TABLES;"
if errorlevel 1 (
    echo Advertencia: No se pudo verificar las tablas
    exit /b 1
) else (
    echo Restauración completada exitosamente
)
```

### Restore vs Inicialización Fresca

**Cuándo usar Restore:**
- Restaurar datos desde un backup anterior
- Migrar datos entre entornos
- Recuperar después de una pérdida de datos

**Cuándo usar Inicialización Fresca:**
- Primera instalación del sistema
- Después de eliminar volúmenes (`docker-compose down -v`)
- Cuando quieres empezar desde cero

**Para una inicialización fresca:**
```bash
# Eliminar todos los datos y volúmenes
docker-compose down -v

# Iniciar de nuevo (database.sql se ejecutará automáticamente)
docker-compose up -d
```

**Advertencia sobre conflictos:**
Si restauras un backup sobre una base de datos que ya fue inicializada con `database.sql`, podrías encontrar:
- Errores de claves duplicadas si el backup contiene los mismos datos
- Datos sobrescritos o mezclados

Para evitar conflictos, elimina el volumen antes de restaurar:
```bash
docker-compose down -v
docker-compose up -d mysql
# Esperar a que MySQL esté listo, luego restaurar
# ... (comandos de restore)
docker-compose up -d
```

### Backup de Imágenes

**En Linux/Mac:**
```bash
tar -czf images_backup_$(date +%Y%m%d_%H%M%S).tar.gz assets/images/
```

**En Windows (PowerShell):**
```powershell
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
Compress-Archive -Path assets\images\ -DestinationPath "images_backup_$timestamp.zip"
```

**En Windows (CMD):**
Puedes usar herramientas como 7-Zip o WinRAR para comprimir la carpeta `assets\images\`.

## Solución de Problemas

### Docker Desktop no inicia en Windows

**Problemas comunes:**
1. **WSL 2 no está instalado:**
   - Abre PowerShell como Administrador
   - Ejecuta: `wsl --install`
   - Reinicia el equipo
   - Vuelve a intentar iniciar Docker Desktop

2. **Hyper-V no está habilitado:**
   - Abre "Activar o desactivar características de Windows"
   - Marca "Hyper-V" y "Plataforma de máquina virtual"
   - Reinicia el equipo

3. **Virtualización deshabilitada en BIOS:**
   - Accede a la configuración del BIOS/UEFI
   - Habilita "Virtualization Technology" o "Intel VT-x" / "AMD-V"
   - Guarda y reinicia

### Error: "ports are not available" o "bind: address already in use"

**Problema:**
Un puerto necesario (3306 para MySQL, 8081 para web por defecto, etc.) ya está en uso por otro servicio en tu máquina.

**Causas comunes:**
- MySQL de XAMPP/WAMP está ejecutándose en el puerto 3306
- Otra aplicación (o WSL) está usando el puerto 8081 (o el definido en `WEB_PORT`)
- Otro contenedor Docker está usando los mismos puertos

**Nota (Windows + WSL):** si `http://localhost:<WEB_PORT>` devuelve “empty reply” o no carga, pero los contenedores están “Up”, suele ser un conflicto del forwarding de WSL. Prueba con `http://<IP_LOCAL>:<WEB_PORT>` o cambia `WEB_PORT` a otro puerto libre.

**Solución 1: Cambiar el puerto en `.env` (Recomendado)**

Edita tu archivo `.env` y cambia el puerto conflictivo:

**Para MySQL (puerto 3306 ocupado):**
```env
MYSQL_PORT=3307
```

**Para la aplicación web (puerto `WEB_PORT` ocupado):**
```env
WEB_PORT=8081
```

**Para Prometheus (puerto 9090 ocupado):**
```env
PROMETHEUS_PORT=9091
```

**Para Grafana (puerto 3000 ocupado):**
```env
GRAFANA_PORT=3001
```

Después de cambiar el puerto, reinicia los contenedores:
```bash
docker-compose down
docker-compose up -d
```

**Solución 2: Detener el servicio que está usando el puerto**

**Si es MySQL de XAMPP:**
1. Abre el Panel de Control de XAMPP
2. Haz clic en "Stop" junto a MySQL

**Si es otro servicio:**
1. **En Windows (PowerShell como Administrador):**
   ```powershell
   # Ver qué proceso usa el puerto 3306
   netstat -ano | findstr :3306
   
   # Detener el proceso (reemplaza PID con el número que aparezca)
   taskkill /PID <PID> /F
   ```

2. **En Linux/Mac:**
   ```bash
   # Ver qué proceso usa el puerto 3306
   lsof -i :3306
   
   # Detener el proceso
   kill -9 <PID>
   ```

**Solución 3: Verificar puertos disponibles**

**En Windows (PowerShell):**
```powershell
# Ver todos los puertos en uso
netstat -ano | findstr LISTENING
```

**En Linux/Mac:**
```bash
# Ver todos los puertos en uso
netstat -tuln
# o
ss -tuln
```

### El contenedor web no inicia

1. Verifica los logs:
   ```bash
   docker-compose logs web
   ```

2. Verifica que MySQL esté funcionando:
   ```bash
   docker-compose ps mysql
   ```

3. Verifica las variables de entorno en `.env`

### Error de conexión a la base de datos

1. Verifica que MySQL esté corriendo:
   ```bash
   docker-compose ps mysql
   ```

2. Verifica las credenciales en `.env`

3. Verifica que la base de datos se haya inicializado:
   ```bash
   docker-compose logs mysql | grep "ready for connections"
   ```

### Prometheus no recopila métricas

1. Verifica que Prometheus esté corriendo:
   ```bash
   docker-compose ps prometheus
   ```

2. Accede a Prometheus y verifica los targets:
   http://localhost:9090/targets

3. Verifica la configuración:
   ```bash
   docker-compose exec prometheus cat /etc/prometheus/prometheus.yml
   ```

### Grafana no muestra dashboards

1. Verifica que Grafana esté corriendo:
   ```bash
   docker-compose ps grafana
   ```

2. Verifica que Prometheus esté configurado como datasource:
   - El datasource se configura automáticamente desde `monitoring/grafana/provisioning/datasources/prometheus.yml`
   - Accede a Grafana: http://localhost:3000
   - Ve a Configuration > Data Sources
   - Deberías ver "Prometheus" configurado automáticamente

3. Verifica que los dashboards estén en el directorio correcto:
   
   **En Linux/Mac:**
   ```bash
   ls -la monitoring/grafana/dashboards/
   ```
   Deberías ver: `sistema.json`, `aplicacion.json`, `base-datos.json`, `negocio.json`
   
   **En Windows (PowerShell):**
   ```powershell
   Get-ChildItem monitoring\grafana\dashboards\
   ```
   
   **En Windows (CMD):**
   ```cmd
   dir monitoring\grafana\dashboards\
   ```

4. Verifica la configuración de provisioning:
   - El archivo `monitoring/grafana/provisioning/dashboards/dashboard.yml` debería estar configurado
   - Los dashboards se cargan automáticamente al iniciar Grafana

5. Si los dashboards no aparecen:
   - Reinicia Grafana: `docker-compose restart grafana`
   - Verifica los logs: `docker-compose logs grafana`

### Problemas de Permisos

Si hay problemas con permisos en `assets/images/`:

**En Linux/Mac:**
```bash
docker-compose exec web chown -R www-data:www-data /var/www/html/assets/images
docker-compose exec web chmod -R 755 /var/www/html/assets/images
```

**En Windows:**
Los permisos dentro del contenedor se manejan automáticamente. Si tienes problemas:
1. Verifica que la carpeta `assets\images\` exista
2. Asegúrate de que Docker Desktop tenga acceso a las unidades compartidas
3. En Docker Desktop, ve a Settings > Resources > File Sharing y verifica que la unidad donde está el proyecto esté compartida

## Producción

### Consideraciones de Seguridad

1. **Cambiar todas las contraseñas por defecto** en `.env`
2. **No exponer puertos innecesarios** al exterior
3. **Usar un proxy reverso** (nginx, traefik) para HTTPS
4. **Configurar firewall** para limitar acceso
5. **Habilitar SSL/TLS** para todas las conexiones
6. **Configurar backups automáticos** de la base de datos

### Optimizaciones

1. **Aumentar recursos** para MySQL en producción
2. **Configurar límites de recursos** en docker-compose.yml:
   ```yaml
   services:
     mysql:
       deploy:
         resources:
           limits:
             cpus: '2'
             memory: 2G
   ```

3. **Usar volúmenes nombrados** para mejor rendimiento
4. **Configurar retención de métricas** según necesidades

### Escalabilidad

Para escalar la aplicación web:

```bash
docker-compose up -d --scale web=3
```

Nota: Necesitarás un balanceador de carga para distribuir el tráfico.

## Actualización

Para actualizar la aplicación:

1. Detén los servicios:
   ```bash
   docker-compose stop
   ```

2. Actualiza el código:
   
   **En Linux/Mac/Windows (PowerShell/CMD):**
   ```bash
   git pull
   ```
   
   O si descargaste el proyecto como ZIP, descarga la nueva versión y reemplaza los archivos.

3. Reconstruye y reinicia:
   ```bash
   docker-compose build
   docker-compose up -d
   ```

## Limpieza

### Eliminar Contenedores Parados

```bash
docker-compose rm
```

### Limpiar Imágenes No Utilizadas

```bash
docker image prune -a
```

### Limpiar Todo (Cuidado)

```bash
docker system prune -a --volumes
```

## Soporte

Para más información, consulta:
- [README.md](README.md) - Información general del proyecto
- [STACK_TECNOLOGICO.md](STACK_TECNOLOGICO.md) - Detalles del stack tecnológico
- [GUIA_USUARIO.md](GUIA_USUARIO.md) - Guía de uso de la aplicación
