# Guía de Usuario - Taller Mecánico

Esta guía explica cómo usar todas las funcionalidades del sistema Taller Mecánico.

## Tabla de Contenidos

1. [Instalación y Configuración Inicial](#instalación-y-configuración-inicial)
2. [Guía para Visitantes](#guía-para-visitantes)
3. [Guía para Usuarios Registrados](#guía-para-usuarios-registrados)
4. [Guía para Administradores](#guía-para-administradores)
5. [Características de Seguridad](#características-de-seguridad)
6. [Solución de Problemas](#solución-de-problemas)

## Instalación y Configuración Inicial

### Opción 1: Instalación con Docker (Recomendado)

Ver la guía completa en [DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md).

**Pasos rápidos:**

1. Copiar archivo de configuración:
   ```bash
   cp .env.example .env
   ```

2. Iniciar servicios:
   ```bash
   docker compose up -d
   ```

3. Acceder a la aplicación:
   - Web: http://localhost:8081 (o el valor de `WEB_PORT`)
   - Grafana: http://localhost:3000

### Opción 2: Instalación Local sin Docker

#### Requisitos Previos

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Apache 2.4+ o servidor web compatible
- Extensiones PHP: PDO, PDO_MySQL, GD

#### Pasos de Instalación

1. **Configurar Base de Datos:**
   ```sql
   CREATE DATABASE trabajo_final_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar Base de Datos:**
   ```bash
   mysql -u root -p trabajo_final_php < database/database.sql
   ```

3. **Configurar Conexión:**
   Editar `config/database.php` con tus credenciales:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'trabajo_final_php');
   define('DB_USER', 'tu_usuario');
   define('DB_PASS', 'tu_contraseña');
   ```

4. **Configurar Permisos:**
   ```bash
   chmod 755 assets/images/
   ```

5. **Iniciar Servidor:**
   ```bash
   php -S localhost:8000
   ```

### Credenciales por Defecto

Después de la instalación inicial, puedes iniciar sesión como administrador:

- **Usuario:** `admin`
- **Contraseña:** `admin123`

**IMPORTANTE:** Cambia estas credenciales inmediatamente después de la primera instalación.

## Guía para Visitantes

Los visitantes (usuarios no registrados) pueden acceder a las siguientes funcionalidades:

### Navegación del Sitio

El menú de navegación incluye:
- **Inicio:** Página principal con información del sitio
- **Noticias:** Visualización de noticias publicadas
- **Registro:** Crear una nueva cuenta
- **Iniciar Sesión:** Acceder con credenciales existentes

### Visualizar Noticias

1. Haz clic en **"Noticias"** en el menú de navegación
2. Verás todas las noticias publicadas ordenadas por fecha (más recientes primero)
3. Cada noticia muestra:
   - Título
   - Imagen (si está disponible)
   - Fecha de publicación
   - Autor
   - Contenido completo

### Reservar cita como invitado (sin cuenta)

1. Abre **Citaciones** (`citaciones.php`) sin iniciar sesión.
2. Elige fecha y franja horaria en el calendario.
3. Completa **nombre**, **correo electrónico** y **teléfono** (obligatorios para invitados).
4. Indica el motivo y confirma. Se crea una **cita** asociada a esos datos de contacto.

### Registrarse como Usuario

1. Haz clic en **"Registro"** en el menú de navegación
2. Completa el formulario con la siguiente información:
   - **Nombre** (obligatorio)
   - **Apellidos** (obligatorio)
   - **Correo electrónico** (obligatorio, debe ser único; campo `email`)
   - **Teléfono** (obligatorio)
   - **Fecha de Nacimiento** (obligatorio)
   - **Dirección** (opcional)
   - **Sexo** (obligatorio: Masculino, Femenino, Otro)
   - **Usuario** (obligatorio, debe ser único)
   - **Contraseña** (obligatorio, mínimo 6 caracteres)
   - **Confirmar Contraseña** (obligatorio)

3. Haz clic en **"Registrarse"**
4. Si el registro es exitoso, serás redirigido a la página de inicio de sesión
5. Inicia sesión con tus nuevas credenciales

**Notas:**
- El correo electrónico y el usuario deben ser únicos en el sistema
- La contraseña debe tener al menos 6 caracteres
- Todos los campos marcados con * son obligatorios

## Guía para Usuarios Registrados

Una vez que hayas iniciado sesión, tendrás acceso a funcionalidades adicionales.

### Iniciar Sesión

1. Haz clic en **"Iniciar Sesión"** en el menú de navegación
2. Ingresa tu **Usuario** y **Contraseña**
3. Haz clic en **"Iniciar Sesión"**
4. Si las credenciales son correctas, serás redirigido al inicio

**Nota:** Si ya estás logueado, serás redirigido automáticamente al inicio.

### Navegación para Usuarios

El menú de navegación para usuarios registrados incluye:
- **Inicio:** Página principal
- **Noticias:** Visualización de noticias
- **Citaciones:** Reservar citas y ver historial
- **Perfil:** Ver y editar tu perfil
- **Cerrar Sesión:** Salir de tu cuenta

### Gestión de Perfil

#### Ver y Editar Datos Personales

1. Haz clic en **"Perfil"** en el menú de navegación
2. En la sección **"Datos Personales"**, puedes editar:
   - Nombre
   - Apellidos
   - Email (debe ser único)
   - Teléfono
   - Fecha de Nacimiento
   - Dirección
   - Sexo

3. Haz clic en **"Actualizar Perfil"** para guardar los cambios

**Nota:** El nombre de usuario no se puede cambiar.

#### Cambiar Contraseña

1. En la página de **"Perfil"**, ve a la sección **"Cambiar Contraseña"**
2. Ingresa tu **Nueva Contraseña** (mínimo 6 caracteres)
3. Confirma la contraseña en **"Confirmar Nueva Contraseña"**
4. Haz clic en **"Cambiar Contraseña"**

**Nota:** Las contraseñas deben coincidir y tener al menos 6 caracteres.

### Citaciones (`citaciones.php`)

Una **citación** es la acción de reservar; si tiene éxito, el sistema crea una **cita** (véase [CONTEXT.md](../CONTEXT.md)).

#### Solicitar una citación

1. Abre **Citaciones** en el menú (o la página `citaciones.php`).
2. En el calendario, elige un día disponible (los días sin huecos aparecen bloqueados).
3. Selecciona una **franja horaria** libre en la lista de horarios.
4. Escribe el **motivo** de la visita.
5. Confirma la reserva. Si la franja ya está ocupada, verás un error (no se crea la cita).

#### Ver tus citas

1. En la misma página, baja a **Mis Citas Agendadas** (solo con sesión iniciada).
2. La tabla muestra fecha, hora, motivo y estado (próxima o pasada).
3. **No** puedes editar ni eliminar citas desde aquí; para cambios, contacta con el taller o pide al administrador que gestione la cita en `admin/citas.php`.

### Cerrar Sesión

1. Haz clic en **"Cerrar Sesión"** en el menú de navegación
2. Serás desconectado y redirigido a la página de inicio

## Guía para Administradores

Los administradores tienen acceso a todas las funcionalidades de usuarios, más herramientas de administración.

### Navegación para Administradores

El menú de administración (desplegable **Admin**) enlaza al panel en `admin/`:
- **Panel Principal** (`admin/index.php`)
- **Usuarios** (`admin/usuarios.php`)
- **Citas** (`admin/citas.php`)
- **Noticias** (`admin/noticias.php`)
- **Consejos** (`admin/consejos.php`)

Desde la barra pública siguen disponibles Inicio, Noticias, Citaciones (reserva) y Perfil.

> Los ficheros `*-administracion.php` en la raíz del proyecto son **legacy** del PFC inicial; no uses esas rutas: el panel canónico es `admin/`.

### Administración de Usuarios

#### Ver Todos los Usuarios

1. En el menú Admin, abre **Usuarios** (`admin/usuarios.php`)
2. Verás una tabla con todos los usuarios registrados en el sistema
3. La tabla muestra:
   - ID
   - Nombre completo
   - Email
   - Teléfono
   - Rol (admin/user)
   - Acciones disponibles

#### Crear un Nuevo Usuario

1. En la página **"Administración de Usuarios"**, completa el formulario:
   - **Nombre** (obligatorio)
   - **Apellidos** (obligatorio)
   - **Email** (obligatorio, único)
   - **Teléfono** (obligatorio)
   - **Fecha de Nacimiento** (obligatorio)
   - **Dirección** (opcional)
   - **Sexo** (obligatorio)
   - **Usuario** (obligatorio, único)
   - **Contraseña** (obligatorio)
   - **Rol** (obligatorio: admin o user)

2. Haz clic en **"Crear Usuario"**

#### Editar un Usuario

1. En la lista de usuarios, haz clic en **"Editar"** en el usuario deseado
2. El formulario se actualizará con los datos del usuario
3. Modifica los campos necesarios
4. Haz clic en **"Actualizar Usuario"**

**Nota:** Puedes cambiar el rol de un usuario (admin/user).

#### Eliminar un Usuario

1. En la lista de usuarios, haz clic en **"Eliminar"** en el usuario deseado
2. Confirma la eliminación
3. El usuario y todos sus datos relacionados (citas, noticias) serán eliminados

**ADVERTENCIA:** Esta acción no se puede deshacer.

### Administración de citas (`admin/citas.php`)

#### Ver todas las citas

1. En el menú Admin, abre **Citas**.
2. Verás citas de usuarios registrados e invitados (datos de contacto del invitado cuando `idUser` es nulo).
3. La tabla incluye fecha, **hora**, motivo y acciones.

#### Editar o eliminar una cita

1. Usa **Editar** para cambiar fecha, hora o motivo (todos obligatorios).
2. Usa **Eliminar** para borrar el registro (**eliminar cita** en el glosario: borrado físico, no es un estado «cancelada»).

### Administración de noticias (`admin/noticias.php`)

#### Ver todas las noticias

1. En el menú Admin, abre **Noticias** (`admin/noticias.php`)
2. Verás una tabla con todas las noticias publicadas
3. La tabla muestra:
   - ID
   - Título
   - Fecha
   - Autor
   - Acciones disponibles

#### Crear una Nueva Noticia

1. En `admin/noticias.php`, completa el formulario:
   - **Título** (obligatorio, único)
   - **Imagen** (obligatorio, formato JPG/PNG, máximo 5MB)
   - **Texto** (obligatorio)

2. Haz clic en **"Crear Noticia"**

**Notas:**
- La imagen se subirá automáticamente a `assets/images/`
- El título debe ser único en el sistema
- La fecha se establece automáticamente a la fecha actual

#### Editar una Noticia

1. En la lista de noticias, haz clic en **"Editar"** en la noticia deseada
2. El formulario se actualizará con los datos de la noticia
3. Modifica los campos necesarios:
   - Título (debe seguir siendo único)
   - Imagen (opcional, solo si deseas cambiarla)
   - Texto

4. Haz clic en **"Actualizar Noticia"**

**Nota:** Si no subes una nueva imagen, se mantendrá la imagen anterior.

#### Eliminar una Noticia

1. En la lista de noticias, haz clic en **"Eliminar"** en la noticia deseada
2. Confirma la eliminación
3. La noticia y su imagen asociada serán eliminadas

### Administración de consejos (`admin/consejos.php`)

1. En el menú Admin, abre **Consejos**.
2. Crea, edita o elimina consejos de mantenimiento (título, texto, imagen opcional, fecha).
3. Los visitantes los consultan en la sección pública de consejos del sitio.

### Acceso a monitorización (Grafana)

Los administradores pueden acceder al sistema de monitorización cuando el proyecto está desplegado con Docker:

1. Accede a Grafana: http://localhost:3000 (o el puerto configurado en `.env`)
2. Inicia sesión con las credenciales configuradas en `.env`:
   - Usuario: `admin` (por defecto, configurable con `GRAFANA_ADMIN_USER`)
   - Contraseña: `admin123` (por defecto, configurable con `GRAFANA_ADMIN_PASSWORD`)

3. Explora los dashboards disponibles (preconfigurados automáticamente):
   - **Dashboard de Sistema:** Métricas de CPU, memoria, disco, red (Node Exporter)
   - **Dashboard de Aplicación:** Requests HTTP, tiempos de respuesta, sesiones activas
   - **Dashboard de Base de Datos:** Métricas de MySQL (conexiones, consultas, rendimiento)
   - **Dashboard de Negocio:** Usuarios, citas, noticias, métricas de negocio

**Nota:** 
- La monitorización solo está disponible cuando se despliega con Docker
- Los dashboards se cargan automáticamente desde `monitoring/grafana/dashboards/`
- Para más información sobre monitorización, consulta [DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md)

## Características de Seguridad

### Encriptación de Contraseñas

- Todas las contraseñas se almacenan usando `password_hash()` de PHP
- Algoritmo: bcrypt
- Las contraseñas nunca se almacenan en texto plano

### Protección contra SQL Injection

- Todas las consultas usan **Prepared Statements** con PDO
- Los parámetros se escapan automáticamente
- No se permite concatenación directa de variables en consultas SQL

### Protección contra XSS (Cross-Site Scripting)

- Todos los datos de salida se sanitizan con `htmlspecialchars()`
- Los datos de entrada se limpian con `strip_tags()` y `trim()`

### Validación de Sesiones

- Las sesiones se validan en todas las páginas protegidas
- Control de roles: solo usuarios con el rol adecuado pueden acceder a funciones administrativas
- Las sesiones expiran después de un período de inactividad

### Validación de Archivos

- Solo se permiten imágenes (JPG, PNG)
- Límite de tamaño: 5MB por archivo
- Validación de tipo MIME

### Protección de Archivos Sensibles

- Los archivos de configuración (`config/database.php`, `includes/functions.php`) están protegidos por `.htaccess`
- No se puede acceder directamente a estos archivos desde el navegador

## Solución de Problemas

### No puedo iniciar sesión

**Posibles causas:**
- Usuario o contraseña incorrectos
- La base de datos no está funcionando
- Error de conexión a la base de datos

**Soluciones:**
1. Verifica tus credenciales
2. Si usas Docker, verifica que MySQL esté corriendo: `docker compose ps mysql`
3. Verifica la configuración en `config/database.php` o `.env`

### Error al subir imágenes

**Posibles causas:**
- Permisos insuficientes en `assets/images/`
- Tamaño de archivo excede el límite
- Formato de archivo no permitido

**Soluciones:**
1. Verifica permisos: `chmod 755 assets/images/`
2. Verifica que el archivo sea JPG o PNG
3. Verifica que el tamaño sea menor a 5MB
4. Si usas Docker: `docker compose exec web chmod -R 755 /var/www/html/assets/images`

### Error de conexión a la base de datos

**Posibles causas:**
- MySQL no está corriendo
- Credenciales incorrectas
- Base de datos no existe

**Soluciones:**
1. Verifica que MySQL esté corriendo
2. Verifica las credenciales en `config/database.php` o `.env`
3. Verifica que la base de datos exista
4. Si usas Docker, verifica los registros: `docker compose logs mysql`

### No puedo cambiar una cita desde Citaciones

**Causa:** En `citaciones.php` solo puedes **solicitar** citaciones y **consultar** el historial; no hay edición ni borrado para usuarios.

**Solución:** Pide al administrador que actualice o elimine la cita en `admin/citas.php`.

### El correo o usuario ya está registrado

**Causa:** El correo electrónico o el nombre de usuario que intentas usar ya existe en el sistema.

**Solución:** Usa otro correo o usuario, o inicia sesión con las credenciales existentes.

### No tengo acceso a funciones de administrador

**Causa:** Tu cuenta no tiene el rol de administrador.

**Solución:** Contacta a un administrador para que cambie tu rol, o inicia sesión con una cuenta de administrador.

### Grafana no muestra datos

**Posibles causas:**
- Prometheus no está recopilando métricas
- Datasource no está configurado correctamente
- Los exportadores no están funcionando
- El proyecto no está desplegado con Docker (la monitorización solo funciona con Docker)

**Soluciones:**
1. Verifica que estés usando Docker: `docker compose ps`
2. Verifica que Prometheus esté en ejecución: `docker compose ps prometheus`
3. Accede a Prometheus: http://localhost:9090/targets y verifica que todos los targets estén "UP"
   - Deberías ver: prometheus, php-app, node-exporter, mysqld-exporter
4. En Grafana, el datasource de Prometheus se configura automáticamente desde `monitoring/grafana/provisioning/datasources/`
5. Verifica los registros: `docker compose logs prometheus`
6. Verifica que los dashboards estén en `monitoring/grafana/dashboards/` (deberían cargarse automáticamente)

## Recursos adicionales

- [README.md](../README.md) — Información general del proyecto
- [docs/README.md](README.md) — Índice de toda la documentación
- [CONTEXT.md](../CONTEXT.md) — Glosario (cita, citación, roles, etc.)
- [STACK_TECNOLOGICO.md](STACK_TECNOLOGICO.md) — Detalles técnicos del stack
- [DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md) — Despliegue con Docker

## Soporte

Si encuentras problemas no cubiertos en esta guía:

1. Revisa los logs de la aplicación
2. Verifica la configuración
3. Consulta la documentación técnica
4. Revisa los issues conocidos en el repositorio
