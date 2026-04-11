# Taller Mecánico - Sistema de Gestión Web

Sistema web completo desarrollado con PHP y MySQL para la gestión de un taller mecánico. Incluye funcionalidades de gestión de usuarios, citas, noticias y un sistema completo de monitorización con Prometheus y Grafana.

## 🚀 Características Principales

- ✅ **Gestión de Usuarios:** Sistema de registro, login y perfiles con roles (admin/user)
- ✅ **Gestión de Citas:** Los usuarios pueden crear, editar y eliminar sus citas
- ✅ **Sistema de Noticias:** Los administradores pueden publicar noticias con imágenes
- ✅ **Panel de Administración:** CRUD completo para usuarios, citas y noticias
- ✅ **Monitorización:** Sistema completo con Prometheus y Grafana (solo con Docker)
- ✅ **Seguridad:** Protección contra SQL Injection, XSS, validación de sesiones
- ✅ **Responsive:** Diseño adaptable a dispositivos móviles y tablets

## 🛠️ Tecnologías Utilizadas

- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Backend:** PHP 8.2
- **Base de Datos:** MySQL 8.0
- **Servidor Web:** Apache 2.4
- **Contenedores:** Docker & Docker Compose
- **Monitorización:** Prometheus, Grafana, Node Exporter, MySQL Exporter
- **Alertas:** Alertmanager (notificaciones por email)

## Estructura del Proyecto

```
taller_mecanico/
├── database/
│   └── database.sql          # Script SQL con todas las tablas
├── config/
│   └── database.php         # Configuración de conexión a BD
├── includes/
│   ├── header.php          # Barra de navegación y header común
│   ├── footer.php          # Footer común
│   ├── functions.php       # Funciones auxiliares
│   └── metrics_logger.php  # Sistema de logging de métricas HTTP
├── assets/
│   ├── css/
│   │   └── style.css       # Estilos principales
│   └── images/             # Imágenes del sitio y noticias
├── monitoring/
│   ├── prometheus/
│   │   └── prometheus.yml   # Configuración de Prometheus
│   ├── grafana/
│   │   ├── provisioning/   # Configuración automática de Grafana
│   │   └── dashboards/      # Dashboards de Grafana
│   └── php-exporter/
│       └── metrics.php     # Endpoint de métricas PHP
├── docker/
│   ├── init-db.sh          # Script de inicialización BD
│   └── entrypoint.sh       # Script de entrada Docker
├── logs/                    # Logs de métricas
├── index.php                # Página de inicio
├── noticias.php             # Página de noticias
├── registro.php             # Página de registro
├── login.php                # Página de inicio de sesión
├── logout.php               # Cerrar sesión
├── perfil.php               # Perfil de usuario
├── citaciones.php           # Gestión de citas para usuarios
├── usuarios-administracion.php    # CRUD usuarios para admin
├── citas-administracion.php       # CRUD citas para admin
├── noticias-administracion.php    # CRUD noticias para admin
├── Dockerfile               # Imagen Docker de la aplicación
├── docker-compose.yml       # Orquestación de servicios
├── docker-compose.dokploy.yml # Orquestación para Dokploy (producción)
├── .env.example             # Ejemplo de variables de entorno
├── README.md                # Este archivo
├── docs/
│   ├── GUIA_USUARIO.md              # Guía de usuario completa
│   ├── STACK_TECNOLOGICO.md         # Stack tecnológico detallado
│   ├── DOCKER_DEPLOYMENT.md         # Guía de despliegue Docker
│   ├── MONITORING_SETUP_GUIDE.md    # Guía del sistema de monitorización
│   ├── GUIA_DESPLIEGUE_LOCAL.md     # Guía despliegue local (XAMPP)
│   └── INSTALL.md                   # Guía de instalación rápida
```

## Requisitos Previos

### Para Instalación con Docker
- Docker Engine 20.10 o superior
- Docker Compose 2.0 o superior
- Al menos 2GB de RAM disponible
- Al menos 5GB de espacio en disco

**Para Windows:**
- Docker Desktop para Windows (incluye Docker Engine y Docker Compose)
- Windows 10 64-bit (Build 19041+) o Windows 11 64-bit
- WSL 2 habilitado (se instala automáticamente con Docker Desktop)
- Ver [docs/DOCKER_DEPLOYMENT.md](docs/DOCKER_DEPLOYMENT.md) para instrucciones detalladas de instalación en Windows

### Para Instalación Local
- PHP 7.4 o superior (recomendado PHP 8.2+)
- MySQL 5.7 o superior (recomendado MySQL 8.0)
- Servidor web (Apache 2.4+, Nginx 1.18+, o servidor integrado de PHP)
- Extensiones PHP requeridas:
  - PDO
  - PDO_MySQL
  - GD (para manejo de imágenes)
  - Session
  - Filter
  - Hash

**Para Windows:**
- **Opción recomendada:** XAMPP (incluye PHP, MySQL, Apache y phpMyAdmin)
  - Descarga desde: https://www.apachefriends.org/
  - Ver [docs/GUIA_DESPLIEGUE_LOCAL.md](docs/GUIA_DESPLIEGUE_LOCAL.md) para guía paso a paso
- **Alternativa:** WAMP Server o instalación manual de PHP y MySQL

## 📦 Instalación

### Opción 1: Instalación con Docker (Recomendado) 🐳

Para una instalación rápida y completa con monitorización incluida, consulta la [Guía de Despliegue con Docker](docs/DOCKER_DEPLOYMENT.md).

**Inicio rápido:**

**En Linux/Mac:**
```bash
# Clonar o descargar el proyecto
git clone <url-del-repositorio>
cd taller_mecanico

# Configurar variables de entorno
cp .env.example .env

# Iniciar todos los servicios
docker-compose up -d

# Verificar que todo está funcionando
docker-compose ps
```

**En Windows (PowerShell):**
```powershell
# Clonar o descargar el proyecto
git clone <url-del-repositorio>
cd taller_mecanico

# Configurar variables de entorno
Copy-Item .env.example .env

# Iniciar todos los servicios
docker-compose up -d

# Verificar que todo está funcionando
docker-compose ps
```

**En Windows (CMD):**
```cmd
REM Clonar o descargar el proyecto
git clone <url-del-repositorio>
cd taller_mecanico

REM Configurar variables de entorno
copy .env.example .env

REM Iniciar todos los servicios
docker-compose up -d

REM Verificar que todo está funcionando
docker-compose ps
```

**Acceso a los servicios:**
- 🌐 **Aplicación Web:** http://localhost:8081 (o el valor de `WEB_PORT` en `.env`)
- 📊 **Grafana (Monitorización):** http://localhost:3000
  - **Usuario:** `admin`
  - **Contraseña:** `admin123`
- 📈 **Prometheus:** http://localhost:9090
- 📊 **Endpoint de Métricas PHP:** http://localhost:8081/metrics.php (o el valor de `WEB_PORT` en `.env`)
- 🗄️ **MySQL (desde host):** localhost:3306

**Nota para Windows:** Asegúrate de que Docker Desktop esté ejecutándose antes de ejecutar los comandos. La primera vez puede tardar varios minutos en descargar las imágenes.

### Opción 2: Despliegue en Producción con Dokploy 🛳️

Dokploy te permite desplegar proyectos Docker/Compose desde un repositorio Git con dominios y HTTPS gestionados desde el panel.

#### 1) Preparar el repositorio (recomendado)

Este proyecto incluye un compose pensado para Dokploy: `docker-compose.dokploy.yml`.

- Evita bind-mounts del código (`./:/var/www/html`) y mapeos de puertos al host (`<WEB_PORT>:80`)
- Usa volúmenes nombrados para persistir `assets/images`, `logs` y `cache`

#### 2) Crear el proyecto en Dokploy

1. En Dokploy, crea un **Project/App** nuevo y conecta tu repositorio Git (elige rama).
2. Selecciona despliegue tipo **Docker Compose**.
3. Configura el **Compose file** como `docker-compose.dokploy.yml`.
4. En variables/secretos, crea las variables necesarias (ver siguiente punto).
5. Lanza el **Deploy** y revisa logs si algo falla.

#### 3) Configurar variables de entorno en Dokploy

Como base, usa `.env.example` y cambia credenciales para producción:

- `MYSQL_ROOT_PASSWORD`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DATABASE`

### Opción 3: Despliegue en Coolify 🧩

Guía rápida: `docs/COOLIFY_DEPLOYMENT.md`.
- **Recomendado**: `docker-compose.coolify.app.yml` (app) + `docker-compose.coolify.monitoring.yml` (monitoring/backup) en 2 recursos separados.
- Alternativa: `docker-compose.coolify.yml` (todo en uno).
- `GRAFANA_ADMIN_USER`, `GRAFANA_ADMIN_PASSWORD`
- `APP_ENV=production`, `APP_DEBUG=false`
- (Opcional alertas) `ALERT_EMAIL_TO`, `SMTP_SMARTHOST`, `SMTP_FROM`, `SMTP_AUTH_USERNAME`, `SMTP_AUTH_PASSWORD`

> Importante: En Coolify evita publicar puertos fijos al host (por ejemplo `9090:9090`, `9093:9093`, `3000:3000`), porque pueden colisionar con otros stacks y hacer fallar el deploy. Usa puertos internos y expón los servicios con **Domains/Routes** de Coolify.

#### 4) Configurar dominio y HTTPS (opción A: sin Cloudflare Tunnel)

1. En Dokploy, añade un **Domain** a la app/servicio `web` (puerto `80`).
2. Activa **HTTPS/Let’s Encrypt** en el dominio.
3. (Opcional) Añade dominios/rutas adicionales en Coolify:
   - `grafana.tudominio.com` → servicio `grafana` puerto `3000`
   - `prometheus.tudominio.com` → servicio `prometheus` puerto `9090`
   - `alertmanager.tudominio.com` → servicio `alertmanager` puerto `9093`

#### 5) Publicar el proyecto con Cloudflare Tunnel (opción B: sin abrir puertos 80/443)

Con Cloudflare Tunnel, el servidor no necesita exponer puertos públicos (Cloudflare se conecta “hacia dentro”).

1. En Cloudflare, añade tu dominio a tu cuenta (si aún no lo hiciste) y asegúrate de usar sus nameservers.
2. En **Cloudflare Zero Trust** → **Access** → **Tunnels** → **Create a tunnel**.
3. Elige método **Docker** y copia el **token** del conector.
4. Ejecuta el conector `cloudflared`:
   - **Recomendado (con Dokploy + Compose):** descomenta el servicio `cloudflared` en `docker-compose.dokploy.yml` y define `CLOUDFLARE_TUNNEL_TOKEN` en Dokploy.
   - **Alternativa (si expones la app en un puerto del host):** ejecuta `cloudflared` en Docker y apunta al puerto local (por ejemplo `http://127.0.0.1:8081` o el valor de `WEB_PORT`).
     ```bash
     # Linux (host networking)
     docker run -d --name cloudflared --restart unless-stopped --network host \
       cloudflare/cloudflared:latest tunnel --no-autoupdate run --token <TU_TOKEN>
     ```
5. En el túnel, crea un **Public Hostname** (esto crea/gestiona el DNS automáticamente):
   - **Subdomain:** `taller` (ejemplo) / **Domain:** `tudominio.com`
   - **Type:** `HTTP`
   - **URL/Service:** apunta al servicio de tu app (por ejemplo `http://web:80` si `cloudflared` está en el mismo Compose/red, o `http://127.0.0.1:8081`/`WEB_PORT` si usas un puerto en el host)
6. Espera a que el túnel aparezca como **Healthy** y prueba `https://taller.tudominio.com`.

> Si prefieres DNS manual, Cloudflare Tunnel usa un CNAME del hostname público hacia `UUID_DEL_TUNNEL.cfargotunnel.com`.

### Opción 3: Instalación Local sin Docker 💻

> **💡 Para usuarios de Windows con XAMPP:** Consulta la [Guía de Despliegue Local con XAMPP](docs/GUIA_DESPLIEGUE_LOCAL.md) para instrucciones paso a paso específicas de Windows.

> **📖 Para una guía rápida:** Consulta [docs/INSTALL.md](docs/INSTALL.md) para instrucciones de instalación rápida.

#### 1. Configurar Base de Datos

1. Crea una base de datos MySQL:
```sql
CREATE DATABASE trabajo_final_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importa el archivo SQL:

**En Linux/Mac:**
```bash
mysql -u root -p trabajo_final_php < database/database.sql
```

**En Windows (si MySQL está en el PATH):**
```cmd
mysql -u root -p trabajo_final_php < database\database.sql
```

**O desde phpMyAdmin (recomendado para Windows):**
- Abre phpMyAdmin en tu navegador (http://localhost/phpmyadmin)
- Selecciona la base de datos `trabajo_final_php` (o créala primero)
- Ve a la pestaña "Importar"
- Selecciona el archivo `database\database.sql`
- Haz clic en "Continuar"

#### 2. Configurar Conexión a Base de Datos

Edita el archivo `config/database.php` y ajusta los valores según tu configuración:

**Para XAMPP (sin contraseña por defecto):**
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'trabajo_final_php');
define('DB_USER', 'root');
define('DB_PASS', '');  // Vacío para XAMPP por defecto
```

**Para MySQL instalado manualmente:**
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'trabajo_final_php');
define('DB_USER', 'root');
define('DB_PASS', 'tu_contraseña');  // Tu contraseña de MySQL
```

**Nota:** El archivo también soporta variables de entorno para Docker, pero en instalación local usa los valores por defecto mostrados arriba.

#### 3. Configurar Permisos de Carpetas

Asegúrate de que la carpeta `assets/images/` tenga permisos de escritura para que se puedan subir imágenes:

**En Linux/Mac:**
```bash
chmod 755 assets/images/
```

**En Windows:**
1. Abre el Explorador de Archivos
2. Navega a la carpeta `assets\images\`
3. Haz clic derecho → Propiedades → Pestaña "Seguridad"
4. Asegúrate de que "Usuarios" tenga permisos de "Control total" o al menos "Modificar"
5. Si usas XAMPP, Apache necesita permisos de escritura en esta carpeta

#### 4. Iniciar el Servidor

#### Opción 1: Servidor integrado de PHP

**En Linux/Mac:**
```bash
php -S localhost:8000
```

**En Windows (si PHP está en el PATH):**
```cmd
php -S localhost:8000
```

**En Windows con XAMPP:**
```cmd
C:\xampp\php\php.exe -S localhost:8000
```

#### Opción 2: Apache/Nginx

**En Linux/Mac:** Configura tu servidor web para apuntar al directorio del proyecto.

**En Windows con XAMPP:**
- Coloca el proyecto en `C:\xampp\htdocs\taller_mecanico\`
- Inicia Apache desde el Panel de Control de XAMPP
- Accede vía: http://localhost/taller_mecanico

**En Windows con WAMP:**
- Coloca el proyecto en `C:\wamp64\www\taller_mecanico\`
- Inicia los servicios desde WAMP
- Accede vía: http://localhost/taller_mecanico

## 🔐 Credenciales y Accesos del Sistema

### Credenciales de la Aplicación Web

**Administrador por defecto:**
- **URL:** http://localhost:8081 (Docker, configurable con `WEB_PORT`) o http://localhost/taller_mecanico (XAMPP)
- **Usuario:** `admin`
- **Contraseña:** `admin123`

**⚠️ IMPORTANTE:** 
- Cambia estas credenciales inmediatamente después de la primera instalación por seguridad
- Si la contraseña no funciona, puede que necesites regenerar el hash usando `generate_password_hash.php`

### Credenciales de Base de Datos

#### Con Docker (configurado en `.env`):
- **Host:** `mysql` (desde contenedores) o `localhost` (desde host)
- **Puerto:** `3306` (configurable con `MYSQL_PORT` en `.env`)
- **Base de datos:** `trabajo_final_php` (configurable con `MYSQL_DATABASE` en `.env`)
- **Usuario root:** `root`
- **Contraseña root:** `rootpassword` (configurable con `MYSQL_ROOT_PASSWORD` en `.env`)
- **Usuario aplicación:** `app_user` (configurable con `MYSQL_USER` en `.env`)
- **Contraseña aplicación:** `app_password` (configurable con `MYSQL_PASSWORD` en `.env`)

#### Con XAMPP (instalación local):
- **Host:** `localhost`
- **Puerto:** `3306`
- **Base de datos:** `trabajo_final_php`
- **Usuario:** `root`
- **Contraseña:** `` (vacía por defecto en XAMPP)

**Acceso a phpMyAdmin (solo con XAMPP):**
- **URL:** http://localhost/phpmyadmin
- **Usuario:** `root`
- **Contraseña:** `` (vacía por defecto)

### Credenciales de Grafana (Solo con Docker)

**Acceso a Grafana:**
- **URL:** http://localhost:3000 (configurable con `GRAFANA_PORT` en `.env`)
- **Usuario:** `admin` (configurable con `GRAFANA_ADMIN_USER` en `.env`)
- **Contraseña:** `admin123` (configurable con `GRAFANA_ADMIN_PASSWORD` en `.env`)

**⚠️ IMPORTANTE:** 
- Cambia estas credenciales en producción
- Las credenciales se configuran en el archivo `.env`
- Para aplicar cambios, reinicia Grafana: `docker-compose restart grafana`

### Acceso a Prometheus (Solo con Docker)

**Acceso a Prometheus:**
- **URL:** http://localhost:9090 (configurable con `PROMETHEUS_PORT` en `.env`)
- **Sin autenticación:** Prometheus no tiene autenticación por defecto (configurar en producción)

### Configuración de Variables de Entorno (.env)

El archivo `.env` contiene todas las credenciales y configuraciones. Ejemplo completo:

```env
# Configuración de Base de Datos
DB_HOST=mysql
DB_NAME=trabajo_final_php
DB_USER=root
DB_PASS=rootpassword

# Configuración de la Aplicación
APP_ENV=production
APP_DEBUG=false

# Configuración de MySQL
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=trabajo_final_php
MYSQL_USER=app_user
MYSQL_PASSWORD=app_password

# Configuración de Prometheus
PROMETHEUS_RETENTION=15d
PROMETHEUS_SCRAPE_INTERVAL=15s

# Configuración de Grafana
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=admin123

# Puertos
WEB_PORT=8081
MYSQL_PORT=3306
PROMETHEUS_PORT=9090
GRAFANA_PORT=3000
```

**⚠️ IMPORTANTE:** 
- Nunca subas el archivo `.env` al repositorio (está en `.gitignore`)
- Usa `.env.example` como plantilla
- Cambia todas las contraseñas por defecto en producción

## ✨ Funcionalidades

### 👤 Para Visitantes (sin sesión)
- Ver página de inicio con información del taller
- Ver noticias publicadas
- Registrarse como nuevo usuario
- Iniciar sesión con credenciales existentes

### 👥 Para Usuarios Registrados (rol: user)
- Todas las funcionalidades de visitante
- **Gestión de Citas:**
  - Crear nuevas citas
  - Editar citas futuras
  - Eliminar citas futuras
  - Ver historial de citas
- **Gestión de Perfil:**
  - Ver y editar datos personales
  - Cambiar contraseña
  - Actualizar información de contacto

### 🔧 Para Administradores (rol: admin)
- Todas las funcionalidades de usuario
- **Administración de Usuarios:**
  - Crear nuevos usuarios
  - Editar usuarios existentes
  - Eliminar usuarios
  - Cambiar roles (admin/user)
- **Administración de Citas:**
  - Ver todas las citas del sistema
  - Crear citas para cualquier usuario
  - Editar cualquier cita
  - Eliminar cualquier cita
- **Administración de Noticias:**
  - Crear noticias con imágenes
  - Editar noticias existentes
  - Eliminar noticias
  - Subir imágenes (JPG, PNG, máximo 5MB)
- **Monitorización (solo con Docker):**
  - Acceso a Grafana para visualizar métricas
  - Dashboards de sistema, aplicación, base de datos y negocio

## 🗄️ Estructura de Base de Datos

El sistema utiliza 4 tablas principales con relaciones mediante Foreign Keys:

### Tabla: `users_data`
Almacena la información personal de los usuarios:
- `idUser` (PK, AUTO_INCREMENT) - Identificador único
- `nombre` (VARCHAR, NOT NULL) - Nombre del usuario
- `apellidos` (VARCHAR, NOT NULL) - Apellidos del usuario
- `email` (VARCHAR, UNIQUE, NOT NULL) - Email único
- `telefono` (VARCHAR, NOT NULL) - Teléfono de contacto
- `fecha_de_nacimiento` (DATE, NOT NULL) - Fecha de nacimiento
- `direccion` (TEXT) - Dirección (opcional)
- `sexo` (ENUM: 'Masculino', 'Femenino', 'Otro', NOT NULL) - Sexo

### Tabla: `users_login`
Almacena las credenciales de acceso:
- `idLogin` (PK, AUTO_INCREMENT) - Identificador único
- `idUser` (FK a users_data, UNIQUE, NOT NULL) - Relación con users_data
- `usuario` (VARCHAR, UNIQUE, NOT NULL) - Nombre de usuario único
- `password` (VARCHAR(255), NOT NULL) - Hash bcrypt de la contraseña
- `rol` (ENUM: 'admin', 'user', NOT NULL) - Rol del usuario

**Relación:** Un usuario tiene una única cuenta de login (relación 1:1)

### Tabla: `citas`
Almacena las citas de los usuarios:
- `idCita` (PK, AUTO_INCREMENT) - Identificador único
- `idUser` (FK a users_data, NOT NULL) - Usuario propietario de la cita
- `fecha_cita` (DATE, NOT NULL) - Fecha de la cita
- `motivo_cita` (TEXT) - Motivo o descripción de la cita

**Relación:** Un usuario puede tener múltiples citas (relación 1:N)

### Tabla: `noticias`
Almacena las noticias publicadas por administradores:
- `idNoticia` (PK, AUTO_INCREMENT) - Identificador único
- `titulo` (VARCHAR, UNIQUE, NOT NULL) - Título único de la noticia
- `imagen` (VARCHAR(255), NOT NULL) - Ruta al archivo de imagen
- `texto` (TEXT, NOT NULL) - Contenido de la noticia
- `fecha` (DATE, NOT NULL) - Fecha de publicación
- `idUser` (FK a users_data, NOT NULL) - Administrador que creó la noticia

**Relación:** Un administrador puede crear múltiples noticias (relación 1:N)

**Características:**
- Charset: `utf8mb4` para soporte completo de Unicode
- Collation: `utf8mb4_unicode_ci`
- Foreign Keys con `ON DELETE CASCADE` para mantener integridad referencial

## 🔒 Características de Seguridad

El proyecto implementa múltiples capas de seguridad:

### Encriptación y Autenticación
- ✅ Contraseñas encriptadas con `password_hash()` usando bcrypt
- ✅ Verificación de contraseñas con `password_verify()`
- ✅ Regeneración de ID de sesión en login

### Protección contra Ataques
- ✅ **SQL Injection:** Todas las consultas usan Prepared Statements con PDO
- ✅ **XSS (Cross-Site Scripting):** Sanitización con `htmlspecialchars()` en toda salida
- ✅ **CSRF:** Validación de sesiones en todas las operaciones críticas

### Validación y Sanitización
- ✅ Validación de entrada en cliente (HTML5) y servidor (PHP)
- ✅ Sanitización de datos con `strip_tags()` y `trim()`
- ✅ Validación de emails con `filter_var()`
- ✅ Validación de archivos subidos:
  - Tipo MIME verificado
  - Solo JPG y PNG permitidos
  - Límite de tamaño: 5MB máximo

### Control de Acceso
- ✅ Validación de sesiones en páginas protegidas
- ✅ Control de roles (admin/user) con verificación en cada página
- ✅ Protección de archivos sensibles mediante `.htaccess`
- ✅ Timeout de sesión configurado

### Configuración Segura
- ✅ Variables de entorno para credenciales (Docker)
- ✅ No hardcodeo de contraseñas en el código
- ✅ Archivo `.env.example` como plantilla segura

## 💻 Notas de Desarrollo

### Arquitectura
- **Backend:** PHP 8.2 con PDO para acceso a base de datos
- **Frontend:** HTML5 semántico, CSS3 con Flexbox, JavaScript vanilla
- **Base de Datos:** MySQL 8.0 con InnoDB y Foreign Keys
- **Patrón:** Arquitectura MVC simplificada con separación de lógica

### Características Técnicas
- ✅ PDO con Prepared Statements para todas las consultas SQL
- ✅ Gestión de sesiones con PHP sessions
- ✅ Validación dual: cliente (HTML5) y servidor (PHP)
- ✅ Diseño responsive con media queries
- ✅ Soporte para variables de entorno (Docker) y configuración tradicional
- ✅ Sistema de logs para métricas y errores

### Estructura de Código
- `config/` - Configuración de base de datos
- `includes/` - Componentes reutilizables (header, footer, functions)
- `assets/` - Recursos estáticos (CSS, imágenes)
- `admin/` - Páginas de administración
- `monitoring/` - Configuración de monitorización (Docker)

## 📊 Monitorización

El proyecto incluye un sistema completo de monitorización con Prometheus y Grafana (disponible solo con Docker).

**📖 Para información detallada sobre el sistema de monitoreo, consulta [MONITORING.md](MONITORING.md)**

### Componentes de Monitorización

**Prometheus** - Motor de métricas
- Puerto: 9090 (configurable en `.env`)
- Retención de datos: 15 días
- Intervalo de scraping: 15 segundos
- Targets configurados:
  - Prometheus mismo
  - Aplicación PHP (metrics.php)
  - Node Exporter (métricas del sistema)
  - MySQL Exporter (métricas de base de datos)

**Grafana** - Visualización de métricas
- Puerto: 3000 (configurable en `.env` con `GRAFANA_PORT`)
- **URL de acceso:** http://localhost:3000
- **Credenciales de acceso:**
  - **Usuario:** `admin` (configurable en `.env` con `GRAFANA_ADMIN_USER`)
  - **Contraseña:** `admin123` (configurable en `.env` con `GRAFANA_ADMIN_PASSWORD`)
- Datasource configurado automáticamente (Prometheus)
- Dashboards preconfigurados (se cargan automáticamente):
  - 📈 **Sistema** (`sistema.json`) - CPU, memoria, disco, red
  - 🌐 **Aplicación** (`aplicacion.json`) - Requests HTTP, tiempos de respuesta, sesiones
  - 🗄️ **Base de Datos** (`base-datos.json`) - Consultas, conexiones, rendimiento MySQL
  - 💼 **Negocio** (`negocio.json`) - Usuarios, citas, noticias, métricas de negocio

**Node Exporter** - Métricas del sistema
- Puerto: 9100
- URL de acceso: http://localhost:9100/metrics

**MySQL Exporter** - Métricas de MySQL
- Puerto: 9104
- URL de acceso: http://localhost:9104/metrics

### Métricas Disponibles

- **Sistema (Node Exporter):** CPU, memoria, disco, red, procesos
- **Aplicación (PHP Exporter):** Requests HTTP por método/estado, tiempos de respuesta, sesiones activas
- **Base de Datos (MySQL Exporter):** Conexiones, consultas por segundo, operaciones de lectura/escritura, tamaño de BD
- **Negocio (PHP Exporter):** Total de usuarios, usuarios por rol, total de citas, total de noticias

**⚠️ Nota:** La monitorización solo está disponible cuando se despliega con Docker. 

**📚 Documentación:**
- **[MONITORING.md](MONITORING.md)** - Guía completa del sistema de monitoreo
- **[docs/DOCKER_DEPLOYMENT.md](docs/DOCKER_DEPLOYMENT.md)** - Guía de despliegue con Docker

## 📚 Documentación Adicional

- 📖 **[docs/GUIA_USUARIO.md](docs/GUIA_USUARIO.md)** - Guía completa de uso para todos los tipos de usuarios (visitantes, usuarios registrados y administradores)
- 🔧 **[docs/STACK_TECNOLOGICO.md](docs/STACK_TECNOLOGICO.md)** - Detalles técnicos del stack tecnológico utilizado
- 🐳 **[docs/DOCKER_DEPLOYMENT.md](docs/DOCKER_DEPLOYMENT.md)** - Guía completa de despliegue con Docker y monitorización (incluye instrucciones para Windows, Linux y Mac)
- 💻 **[docs/GUIA_DESPLIEGUE_LOCAL.md](docs/GUIA_DESPLIEGUE_LOCAL.md)** - Guía paso a paso para desplegar con XAMPP en Windows
- ⚡ **[docs/INSTALL.md](docs/INSTALL.md)** - Guía de instalación rápida sin Docker (incluye comandos para Windows, Linux y Mac)
- 📊 **[MONITORING.md](MONITORING.md)** - Guía completa del sistema de monitoreo con Prometheus y Grafana

## 🔗 URLs y Puertos de Acceso

### Con Docker

| Servicio | URL | Puerto | Credenciales |
|----------|-----|--------|--------------|
| **Aplicación Web** | http://localhost:8081 | 8081 (`WEB_PORT`) | admin / admin123 |
| **Grafana** | http://localhost:3000 | 3000 | admin / admin123 |
| **Prometheus** | http://localhost:9090 | 9090 | Sin autenticación |
| **MySQL** | localhost:3306 | 3306 | root / rootpassword |
| **Node Exporter** | http://localhost:9100/metrics | 9100 | Sin autenticación |
| **MySQL Exporter** | http://localhost:9104/metrics | 9104 | Sin autenticación |
| **Métricas PHP** | http://localhost:8081/metrics.php | - | Sin autenticación |

### Con XAMPP (Instalación Local)

| Servicio | URL | Puerto | Credenciales |
|----------|-----|--------|--------------|
| **Aplicación Web** | http://localhost/taller_mecanico | 80 | admin / admin123 |
| **phpMyAdmin** | http://localhost/phpmyadmin | 80 | root / (vacía) |
| **MySQL** | localhost:3306 | 3306 | root / (vacía) |

**Nota:** Los puertos pueden configurarse en el archivo `.env` para Docker o en la configuración de XAMPP para instalación local.

## 🔧 Solución de Problemas

### Error de conexión a la base de datos

**Síntomas:** Mensaje "Error de conexión a la base de datos" al acceder a la aplicación

**Soluciones:**
1. Verifica que MySQL esté ejecutándose
   - **XAMPP:** Panel de Control → MySQL debe estar en "Running"
   - **Docker:** `docker-compose ps mysql`
2. Comprueba las credenciales:
   - **Local:** Revisa `config/database.php` (usuario: `root`, contraseña: vacía por defecto en XAMPP)
   - **Docker:** Revisa `.env` (variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`)
     - Por defecto: `DB_USER=root`, `DB_PASS=rootpassword`
3. Verifica que la base de datos existe:
   - **phpMyAdmin:** http://localhost/phpmyadmin
   - **Docker:** `docker-compose exec mysql mysql -u root -p -e "SHOW DATABASES;"`
4. Revisa los logs:
   - **Docker:** `docker-compose logs mysql`

### Error al subir imágenes

**Síntomas:** No se pueden subir imágenes al crear/editar noticias

**Soluciones:**
1. **Permisos de carpeta:**
   - **Windows:** Propiedades → Seguridad → Otorgar "Control total" a "Usuarios"
   - **Linux/Mac:** `chmod 755 assets/images/`
   - **Docker:** `docker-compose exec web chmod -R 755 /var/www/html/assets/images`
2. **Extensión GD de PHP:**
   - **XAMPP:** Edita `C:\xampp\php\php.ini` → Busca `;extension=gd` → Quita el `;`
   - **Docker:** Ya está incluida en la imagen
3. **Límite de tamaño:**
   - Verifica `upload_max_filesize` y `post_max_size` en `php.ini`
   - Máximo permitido: 5MB
4. **Formato de archivo:**
   - Solo se permiten JPG y PNG
   - Verifica que el archivo no esté corrupto

### Error de sesión

**Síntomas:** Sesiones que no se mantienen, redirecciones constantes al login

**Soluciones:**
1. Verifica que las sesiones estén habilitadas en PHP
2. Revisa los permisos de la carpeta de sesiones temporales
3. Verifica que las cookies estén habilitadas en el navegador
4. Limpia las cookies del sitio y vuelve a iniciar sesión

### Problemas con Docker

**Síntomas:** Contenedores que no inician, puertos ocupados, errores de conexión

**Soluciones comunes:**
- **Puertos ocupados:** Cambia los puertos en `.env` (por ejemplo, `WEB_PORT=8081`) o detén los servicios que los usan
- **Windows + WSL (localhost no responde):** Si `http://localhost:<WEB_PORT>` devuelve “empty reply” o se queda cargando, prueba `http://<IP_LOCAL>:<WEB_PORT>` (por ejemplo `http://192.168.x.x:8081`) o cambia `WEB_PORT` a otro puerto libre (suele ser un conflicto de forwarding de WSL).
- **Contenedores no inician:** Revisa `docker-compose logs` para ver errores
- **Docker Desktop no inicia (Windows):** Verifica que WSL 2 esté instalado y habilitado
- **Credenciales incorrectas:** Verifica el archivo `.env` y las variables de entorno configuradas

📖 **Para más ayuda:** Consulta la sección "Solución de Problemas" en [docs/DOCKER_DEPLOYMENT.md](docs/DOCKER_DEPLOYMENT.md) para problemas específicos de Docker.

## 🎯 Estado del Proyecto

✅ **Completado** - El proyecto está funcional y listo para uso

### Funcionalidades Implementadas
- ✅ Sistema de autenticación y autorización
- ✅ CRUD completo de usuarios, citas y noticias
- ✅ Gestión de perfiles de usuario
- ✅ Sistema de noticias con imágenes
- ✅ Panel de administración completo
- ✅ Sistema de monitorización (Docker)
- ✅ Diseño responsive
- ✅ Validación y seguridad implementadas

### Posibles Mejoras Futuras
- 🔄 Sistema de notificaciones por email
- 📅 Calendario de citas mejorado
- 📱 Aplicación móvil
- 🔍 Sistema de búsqueda avanzada
- 📊 Reportes y estadísticas adicionales

## 👤 Autor

Desarrollado como trabajo final del módulo PHP/MySQL.

## 📄 Licencia

Este proyecto es de uso educativo.

---

## 🙏 Contribuciones

Las contribuciones son bienvenidas. Si encuentras algún problema o tienes sugerencias, por favor:
1. Abre un issue describiendo el problema o sugerencia
2. Si quieres contribuir código, crea un pull request con una descripción clara de los cambios

## 📞 Soporte

Para obtener ayuda:
1. Revisa la documentación en las guías mencionadas arriba
2. Consulta la sección de "Solución de Problemas"
3. Revisa los logs de la aplicación y servicios

---

## 📋 Resumen Rápido de Credenciales

### 🔐 Credenciales Principales

#### Aplicación Web (Admin)
- **URL:** http://localhost:8081 (Docker, configurable con `WEB_PORT`) o http://localhost/taller_mecanico (XAMPP)
- **Usuario:** `admin`
- **Contraseña:** `admin123`

#### Base de Datos MySQL

**Con Docker:**
- **Host:** `mysql` (desde contenedores) o `localhost:3306` (desde host)
- **Usuario root:** `root`
- **Contraseña root:** `rootpassword`
- **Base de datos:** `trabajo_final_php`

**Con XAMPP:**
- **Host:** `localhost:3306`
- **Usuario:** `root`
- **Contraseña:** `` (vacía por defecto)
- **Base de datos:** `trabajo_final_php`

#### Grafana (Solo con Docker)
- **URL:** http://localhost:3000
- **Usuario:** `admin`
- **Contraseña:** `admin123`

#### Prometheus (Solo con Docker)
- **URL:** http://localhost:9090
- **Sin autenticación** (configurar en producción)

#### Alertmanager (Solo con Docker)
- **URL:** http://localhost:9093
- **Email:** configurable vía `.env` (ver `.env.example` y `docs/MONITORING_SETUP_GUIDE.md`)

#### phpMyAdmin (Solo con XAMPP)
- **URL:** http://localhost/phpmyadmin
- **Usuario:** `root`
- **Contraseña:** `` (vacía por defecto)

### ⚙️ Configuración de Puertos (Docker)

Todos los puertos se configuran en el archivo `.env`:

| Variable | Puerto por Defecto | Descripción |
|----------|---------------------|-------------|
| `WEB_PORT` | 8081 | Puerto de la aplicación web |
| `MYSQL_PORT` | 3306 | Puerto de MySQL |
| `PROMETHEUS_PORT` | 9090 | Puerto de Prometheus |
| `GRAFANA_PORT` | 3000 | Puerto de Grafana |
| `ALERTMANAGER_PORT` | 9093 | Puerto de Alertmanager |

### 🔄 Cambiar Credenciales

**Para cambiar credenciales de la aplicación:**
1. Inicia sesión como administrador
2. Ve a "Perfil" → "Cambiar Contraseña"
3. O edita directamente en la base de datos

**Para cambiar credenciales de Grafana (Docker):**
1. Edita el archivo `.env`
2. Cambia `GRAFANA_ADMIN_USER` y `GRAFANA_ADMIN_PASSWORD`
3. Reinicia Grafana: `docker-compose restart grafana`

**Para cambiar credenciales de MySQL (Docker):**
1. Edita el archivo `.env`
2. Cambia `MYSQL_ROOT_PASSWORD`, `MYSQL_USER`, `MYSQL_PASSWORD`
3. Actualiza `DB_PASS` en `.env` para que coincida
4. Reinicia los contenedores: `docker-compose down && docker-compose up -d`

**⚠️ IMPORTANTE:** 
- Todas las credenciales por defecto son solo para desarrollo
- **NUNCA uses estas credenciales en producción**
- Cambia todas las contraseñas antes de desplegar en producción

