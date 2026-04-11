# Stack Tecnológico del Proyecto

Este documento detalla todas las tecnologías, herramientas y dependencias utilizadas en el proyecto Taller Mecánico.

## Resumen Ejecutivo

El proyecto está construido con un stack LAMP (Linux, Apache, MySQL, PHP) modernizado con Docker, y un sistema completo de monitorización basado en Prometheus y Grafana.

## Frontend

### HTML5
- **Versión:** HTML5 (estándar actual)
- **Uso:** Estructura semántica de todas las páginas
- **Características utilizadas:**
  - Elementos semánticos (`<header>`, `<nav>`, `<section>`, `<main>`, `<footer>`)
  - Formularios HTML5 con validación nativa
  - Atributos de entrada (`type="email"`, `type="date"`, `required`, `min`, `max`)
  - Meta tags para responsive design (`viewport`)

### CSS3
- **Versión:** CSS3 (estándar actual)
- **Uso:** Estilos y diseño responsive
- **Características utilizadas:**
  - Flexbox para layouts
  - Media queries para responsive design
  - Variables CSS (custom properties)
  - Transiciones y animaciones
  - Box model moderno
  - Grid layout (si aplica)

### JavaScript
- **Versión:** ES6+ (Vanilla JavaScript)
- **Uso:** Interactividad del lado del cliente
- **Características utilizadas:**
  - Validación de formularios en cliente
  - Manipulación del DOM
  - Event listeners
  - Fetch API (si se implementa AJAX)
  - LocalStorage/SessionStorage (si aplica)

**Archivos:**
- `assets/css/style.css` - Estilos principales

## Backend

### PHP
- **Versión mínima:** PHP 7.4
- **Versión recomendada:** PHP 8.2+
- **Versión en Docker:** PHP 8.2
- **Uso:** Lógica de negocio, procesamiento de formularios, acceso a base de datos

#### Extensiones PHP Requeridas

1. **PDO (PHP Data Objects)**
   - Versión: Incluida en PHP 7.4+
   - Uso: Abstracción para acceso a base de datos
   - Configuración: Habilitada por defecto

2. **PDO_MySQL**
   - Versión: Incluida en PHP 7.4+
   - Uso: Driver específico para MySQL
   - Configuración: Habilitada por defecto

3. **GD (GNU Image Manipulation Library)**
   - Versión: 2.x
   - Uso: Procesamiento y manipulación de imágenes
   - Funciones utilizadas:
     - `imagecreatefromjpeg()`
     - `imagecreatefrompng()`
     - `imagecopyresampled()`
     - `imagejpeg()`
     - `imagedestroy()`

4. **Session**
   - Versión: Incluida en PHP
   - Uso: Gestión de sesiones de usuario
   - Funciones utilizadas:
     - `session_start()`
     - `session_status()`
     - `$_SESSION`

5. **Filter**
   - Versión: Incluida en PHP
   - Uso: Validación y filtrado de datos
   - Funciones utilizadas:
     - `filter_var()` con `FILTER_VALIDATE_EMAIL`

6. **Hash**
   - Versión: Incluida en PHP
   - Uso: Encriptación de contraseñas
   - Funciones utilizadas:
     - `password_hash()` con `PASSWORD_DEFAULT`
     - `password_verify()`

#### Características PHP Utilizadas

- **Programación Orientada a Objetos (PDO)**
- **Prepared Statements** para prevenir SQL Injection
- **Manejo de excepciones** con try-catch
- **Funciones de seguridad:**
  - `htmlspecialchars()` para prevenir XSS
  - `strip_tags()` para sanitización
  - `trim()` para limpieza de datos

**Archivos principales:**
- `config/database.php` - Configuración de conexión
- `includes/functions.php` - Funciones auxiliares
- `*.php` - Páginas de la aplicación

## Base de Datos

### MySQL
- **Versión mínima:** MySQL 5.7
- **Versión recomendada:** MySQL 8.0
- **Versión en Docker:** MySQL 8.0
- **Motor:** InnoDB (por defecto)
- **Charset:** utf8mb4
- **Collation:** utf8mb4_unicode_ci

#### Estructura de Base de Datos

**Tabla: `users_data`**
- Almacena información personal de usuarios
- Campos: idUser, nombre, apellidos, email, telefono, fecha_de_nacimiento, direccion, sexo
- Índices: PRIMARY KEY (idUser), UNIQUE (email)

**Tabla: `users_login`**
- Almacena credenciales de acceso
- Campos: idLogin, idUser, usuario, password, rol
- Índices: PRIMARY KEY (idLogin), UNIQUE (idUser), UNIQUE (usuario)
- Foreign Key: idUser → users_data(idUser) ON DELETE CASCADE

**Tabla: `citas`**
- Almacena citas de usuarios
- Campos: idCita, idUser, fecha_cita, motivo_cita
- Índices: PRIMARY KEY (idCita)
- Foreign Key: idUser → users_data(idUser) ON DELETE CASCADE

**Tabla: `noticias`**
- Almacena noticias publicadas
- Campos: idNoticia, titulo, imagen, texto, fecha, idUser
- Índices: PRIMARY KEY (idNoticia), UNIQUE (titulo)
- Foreign Key: idUser → users_data(idUser) ON DELETE CASCADE

#### Características MySQL Utilizadas

- **Foreign Keys** con CASCADE para integridad referencial
- **ENUM** para valores predefinidos (rol, sexo)
- **TEXT** para campos de longitud variable
- **DATE** para fechas
- **AUTO_INCREMENT** para claves primarias

**Archivo:**
- `database/database.sql` - Script de creación de base de datos

## Servidor Web

### Apache HTTP Server
- **Versión:** 2.4+ (incluida en imagen PHP-Apache)
- **Módulos habilitados:**
  - `mod_rewrite` - Para URLs amigables y redirecciones
  - `mod_php` - Para procesamiento de PHP
  - `mod_headers` - Para headers HTTP
  - `mod_env` - Para variables de entorno

#### Configuración Apache

- **DocumentRoot:** `/var/www/html`
- **AllowOverride:** All (para `.htaccess`)
- **MIME Types:** Configurado para PHP, CSS, JS, imágenes

**Archivo:**
- `.htaccess` - Configuración de Apache (protección de archivos, rewrite rules)

## Infraestructura y Contenedores

### Docker
- **Versión mínima:** Docker Engine 20.10
- **Uso:** Contenedorización de la aplicación

### Docker Compose
- **Versión mínima:** Docker Compose 2.0
- **Uso:** Orquestación de múltiples contenedores
- **Archivo:** `docker-compose.yml`

#### Imágenes Docker Utilizadas

1. **php:8.2-apache**
   - Base: Debian
   - Tamaño aproximado: ~500MB
   - Extensiones: PDO, PDO_MySQL, GD

2. **mysql:8.0**
   - Base: Debian
   - Tamaño aproximado: ~500MB
   - Configuración: utf8mb4, InnoDB

3. **prom/prometheus:latest**
   - Base: Alpine Linux
   - Tamaño aproximado: ~200MB
   - Uso: Recopilación de métricas

4. **grafana/grafana:latest**
   - Base: Alpine Linux
   - Tamaño aproximado: ~300MB
   - Uso: Visualización de métricas

5. **prom/node-exporter:latest**
   - Base: Alpine Linux
   - Tamaño aproximado: ~50MB
   - Uso: Métricas del sistema

6. **prom/mysqld-exporter:latest**
   - Base: Alpine Linux
   - Tamaño aproximado: ~50MB
   - Uso: Métricas de MySQL

**Archivos:**
- `Dockerfile` - Imagen de la aplicación
- `docker-compose.yml` - Orquestación de servicios
- `.dockerignore` - Archivos excluidos del build

## Monitorización

### Prometheus
- **Versión:** Latest (2.x)
- **Puerto:** 9090
- **Uso:** Recopilación y almacenamiento de métricas
- **Retención:** 15 días (configurable)
- **Scrape Interval:** 15 segundos

#### Métricas Recopiladas

1. **Métricas del Sistema (Node Exporter)**
   - CPU usage
   - Memoria (total, usada, disponible)
   - Disco (espacio usado, disponible)
   - Red (bytes recibidos/enviados)

2. **Métricas de MySQL (MySQL Exporter)**
   - Conexiones activas
   - Consultas por segundo
   - Operaciones (SELECT, INSERT, UPDATE, DELETE)
   - Uptime
   - Tamaño de base de datos

3. **Métricas de Aplicación (PHP Exporter)**
   - Requests HTTP por método y estado
   - Tiempo de respuesta
   - Sesiones activas
   - Total de usuarios
   - Usuarios por rol
   - Total de citas
   - Citas por estado
   - Total de noticias

**Archivos:**
- `monitoring/prometheus/prometheus.yml` - Configuración de Prometheus
- `monitoring/php-exporter/metrics.php` - Endpoint de métricas PHP

### Grafana
- **Versión:** Latest (10.x)
- **Puerto:** 3000
- **Uso:** Visualización de métricas
- **Autenticación:** Usuario/contraseña

#### Dashboards Incluidos

Los dashboards se cargan automáticamente desde `monitoring/grafana/dashboards/`:

1. **Dashboard de Sistema** (`sistema.json`)
   - Uso de CPU
   - Uso de memoria
   - Uso de disco
   - Tráfico de red
   - Fuente: Node Exporter

2. **Dashboard de Aplicación** (`aplicacion.json`)
   - Requests HTTP por método
   - Requests HTTP por estado
   - Tiempo de respuesta
   - Sesiones activas
   - Total de requests
   - Fuente: PHP Exporter (metrics.php)

3. **Dashboard de Base de Datos** (`base-datos.json`)
   - Conexiones MySQL
   - Consultas por segundo
   - Operaciones de lectura/escritura
   - Tamaño de base de datos
   - Uptime MySQL
   - Fuente: MySQL Exporter

4. **Dashboard de Negocio** (`negocio.json`)
   - Total de usuarios
   - Usuarios por rol
   - Total de citas
   - Citas por estado
   - Total de noticias
   - Sesiones activas
   - Fuente: PHP Exporter (metrics.php)

**Archivos:**
- `monitoring/grafana/provisioning/datasources/prometheus.yml` - Configuración automática del datasource de Prometheus
- `monitoring/grafana/provisioning/dashboards/dashboard.yml` - Configuración de carga automática de dashboards
- `monitoring/grafana/dashboards/sistema.json` - Dashboard de métricas del sistema
- `monitoring/grafana/dashboards/aplicacion.json` - Dashboard de métricas de la aplicación
- `monitoring/grafana/dashboards/base-datos.json` - Dashboard de métricas de MySQL
- `monitoring/grafana/dashboards/negocio.json` - Dashboard de métricas de negocio

## Seguridad

### Medidas de Seguridad Implementadas

1. **Encriptación de Contraseñas**
   - Algoritmo: bcrypt (a través de `password_hash()` con `PASSWORD_DEFAULT`)
   - Cost: 10 (configurable automáticamente por PHP)
   - Las contraseñas nunca se almacenan en texto plano

2. **Protección SQL Injection**
   - Método: Prepared Statements con PDO
   - Validación: Todos los parámetros son escapados automáticamente
   - `PDO::ATTR_EMULATE_PREPARES => false` para usar prepared statements nativos

3. **Protección XSS (Cross-Site Scripting)**
   - Método: `htmlspecialchars()` en toda salida de datos
   - Sanitización: `strip_tags()` y `trim()` en datos de entrada
   - Validación de entrada con `filter_var()` para emails

4. **Validación de Sesiones**
   - Verificación de sesión activa en páginas protegidas
   - Control de roles (admin/user) con verificación en cada página
   - Timeout de sesión configurado en PHP
   - Regeneración de ID de sesión en login

5. **Validación de Archivos**
   - Verificación de tipo MIME
   - Verificación de extensión (solo JPG, PNG)
   - Límite de tamaño (5MB máximo)
   - Procesamiento seguro con GD

6. **Protección de Archivos Sensibles**
   - `.htaccess` bloquea acceso directo a:
     - `config/database.php`
     - `includes/functions.php`
     - Archivos de configuración

7. **Variables de Entorno**
   - Credenciales de base de datos en variables de entorno (Docker)
   - Soporte para configuración tradicional (instalación local)
   - No hardcodeadas en el código
   - Archivo `.env.example` como plantilla

## Dependencias del Sistema

### Requisitos Mínimos del Servidor

#### Sin Docker
- **Sistema Operativo:** Linux, macOS, o Windows con WSL
- **PHP:** 7.4 o superior
- **MySQL:** 5.7 o superior (o MariaDB 10.2+)
- **Apache:** 2.4+ (o Nginx 1.18+)
- **RAM:** 512MB mínimo, 1GB recomendado
- **Disco:** 1GB mínimo

#### Con Docker
- **Docker:** 20.10+
- **Docker Compose:** 2.0+
- **RAM:** 2GB mínimo, 4GB recomendado
- **Disco:** 5GB mínimo

### Extensiones PHP Requeridas

Lista completa de extensiones necesarias:

```
- pdo
- pdo_mysql
- gd
- session
- filter
- hash
- mbstring (recomendado)
- json (incluida por defecto)
- fileinfo (recomendado)
```

## Herramientas de Desarrollo

### Control de Versiones
- **Git:** Sistema de control de versiones
- **Archivos de configuración:**
  - `.gitattributes` - Configuración de Git
  - `.gitignore` - Archivos excluidos

### Documentación
- **Markdown:** Formato de documentación
- **Archivos:**
  - `README.md` - Documentación principal
  - `STACK_TECNOLOGICO.md` - Este archivo
  - `GUIA_USUARIO.md` - Guía de usuario
  - `DOCKER_DEPLOYMENT.md` - Guía de despliegue

## Arquitectura del Sistema

### Diagrama de Arquitectura

```
┌─────────────────┐
│   Navegador     │
│   (Cliente)     │
└────────┬────────┘
         │ HTTP/HTTPS
         │
┌────────▼────────┐
│   Apache        │
│   (Servidor)    │
└────────┬────────┘
         │
┌────────▼────────┐
│   PHP 8.2       │
│   (Aplicación)  │
└────────┬────────┘
         │
    ┌────┴────┐
    │        │
┌───▼───┐ ┌──▼──────────┐
│ MySQL │ │ Prometheus  │
│  8.0  │ │ (Métricas)  │
└───────┘ └──────┬───────┘
                 │
            ┌────▼─────┐
            │ Grafana  │
            │ (Viz)    │
            └──────────┘
```

### Flujo de Datos

1. **Cliente → Servidor:** Request HTTP
2. **Apache:** Recibe y procesa request
3. **PHP:** Ejecuta lógica de aplicación
4. **MySQL:** Consultas y actualizaciones de datos
5. **PHP:** Genera respuesta HTML
6. **Apache:** Envía respuesta al cliente

### Flujo de Monitorización

1. **Exportadores:** Recopilan métricas (Node, MySQL, PHP)
2. **Prometheus:** Scraping periódico de métricas
3. **Grafana:** Consulta Prometheus y visualiza
4. **Usuario:** Accede a dashboards en Grafana

## Versiones Específicas en Docker

Cuando se despliega con Docker, las versiones exactas son:

- **PHP:** 8.2-apache
- **MySQL:** 8.0
- **Prometheus:** latest (2.x)
- **Grafana:** latest (10.x)
- **Node Exporter:** latest
- **MySQL Exporter:** latest

## Compatibilidad

### Navegadores Soportados

- Chrome/Edge: Últimas 2 versiones
- Firefox: Últimas 2 versiones
- Safari: Últimas 2 versiones
- Opera: Últimas 2 versiones

### Sistemas Operativos

- **Desarrollo:** Windows, macOS, Linux
- **Producción:** Linux (recomendado), Windows Server, macOS Server

## Actualizaciones y Mantenimiento

### Política de Versiones

- **PHP:** Mantener al menos 1 versión menor detrás de la última estable
- **MySQL:** Mantener al menos 1 versión menor detrás de la última estable
- **Docker Images:** Usar tags específicos en producción, `latest` en desarrollo

### Seguridad

- Actualizar imágenes Docker regularmente
- Revisar logs de seguridad
- Mantener extensiones PHP actualizadas
- Aplicar parches de seguridad de MySQL

## Referencias

- [Documentación PHP](https://www.php.net/docs.php)
- [Documentación MySQL](https://dev.mysql.com/doc/)
- [Documentación Apache](https://httpd.apache.org/docs/)
- [Documentación Docker](https://docs.docker.com/)
- [Documentación Prometheus](https://prometheus.io/docs/)
- [Documentación Grafana](https://grafana.com/docs/)
