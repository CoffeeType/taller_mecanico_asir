# Guía de Instalación Rápida

Esta guía es para instalación local sin Docker. 

**Opciones de instalación:**
- **Con Docker (Recomendado):** Consulta [DOCKER_DEPLOYMENT.md](DOCKER_DEPLOYMENT.md) para una instalación completa con monitorización
- **Con XAMPP en Windows:** Consulta [GUIA_DESPLIEGUE_LOCAL.md](GUIA_DESPLIEGUE_LOCAL.md) para una guía paso a paso detallada
- **Instalación manual:** Sigue esta guía para una instalación rápida

## Paso 1: Configurar Base de Datos

1. Crea la base de datos:
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

**Alternativa para Windows (usando ruta completa de MySQL):**
```cmd
"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p trabajo_final_php < database\database.sql
```

**O usando phpMyAdmin (recomendado para Windows con XAMPP/WAMP):**
1. Abre phpMyAdmin en tu navegador (generalmente: http://localhost/phpmyadmin)
2. Selecciona la base de datos `trabajo_final_php` (o créala primero desde la pestaña "Bases de datos")
3. Ve a la pestaña "Importar"
4. Selecciona el archivo `database\database.sql`
5. Haz clic en "Continuar" o "Go"
6. Espera a que termine la importación

## Paso 2: Configurar Conexión

Edita `config/database.php` y ajusta las credenciales según tu configuración:

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

**Nota:** El archivo `config/database.php` también soporta variables de entorno (para Docker), pero en instalación local usa los valores por defecto mostrados arriba.

## Paso 3: Generar Hash de Contraseña del Admin

El hash en `database.sql` es un ejemplo. Para generar uno correcto:

**En Linux/Mac:**
```bash
php generate_password_hash.php
```

**En Windows (si PHP está en el PATH):**
```cmd
php generate_password_hash.php
```

**En Windows con XAMPP:**
```cmd
C:\xampp\php\php.exe generate_password_hash.php
```

**En Windows con WAMP:**
```cmd
C:\wamp64\bin\php\php8.2.0\php.exe generate_password_hash.php
```

**Pasos:**
1. Ejecuta el comando según tu sistema
2. Copia el hash generado
3. Actualiza la línea en `database.sql`:
```sql
VALUES (1, 'admin', 'TU_HASH_AQUI', 'admin');
```

O simplemente cambia la contraseña después de iniciar sesión desde el perfil.

## Paso 4: Permisos de Carpetas

Asegúrate de que `assets/images/` tenga permisos de escritura:

**En Linux/Mac:**
```bash
chmod 755 assets/images/
```

**En Windows:**
1. Abre el Explorador de Archivos
2. Navega a la carpeta `assets\images\`
3. Haz clic derecho en la carpeta → Propiedades
4. Ve a la pestaña "Seguridad"
5. Asegúrate de que "Usuarios" tenga permisos de "Control total" o al menos "Modificar"
6. Si no tienes permisos, haz clic en "Editar" y otorga los permisos necesarios

**Nota:** Si usas XAMPP, Apache necesita permisos de escritura en esta carpeta. Generalmente, dar permisos a "Usuarios" es suficiente.

## Paso 5: Iniciar Servidor

**Opción 1: Servidor integrado de PHP**

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

**En Windows con WAMP:**
```cmd
C:\wamp64\bin\php\php8.2.0\php.exe -S localhost:8000
```

**Opción 2: Usar Apache/Nginx (recomendado para producción)**

- **XAMPP (Windows):** 
  - Coloca el proyecto en `C:\xampp\htdocs\taller_mecanico\`
  - Accede vía http://localhost/taller_mecanico
  - Ver [GUIA_DESPLIEGUE_LOCAL.md](GUIA_DESPLIEGUE_LOCAL.md) para guía detallada
- **WAMP (Windows):** 
  - Coloca el proyecto en `C:\wamp64\www\taller_mecanico\`
  - Accede vía http://localhost/taller_mecanico
- **Apache/Nginx (Linux/Mac):** 
  - Configura un virtual host apuntando al directorio del proyecto
  - O coloca el proyecto en `/var/www/html/taller_mecanico/` y accede vía http://localhost/taller_mecanico

**URLs de acceso:**
- Servidor integrado: http://localhost:8000
- Apache/Nginx: http://localhost/taller_mecanico (ajusta según tu configuración)

## Credenciales por Defecto

Después de importar la base de datos, puedes iniciar sesión como administrador con:

- **Usuario:** `admin`
- **Contraseña:** `admin123`

**IMPORTANTE:** 
- Si la contraseña no funciona, puede que necesites regenerar el hash. Usa `generate_password_hash.php` para generar un nuevo hash.
- Cambia estas credenciales inmediatamente después de la primera instalación por seguridad.

## Verificación

1. Accede a la aplicación en tu navegador
2. Inicia sesión con las credenciales de administrador
3. Verifica que puedas:
   - Ver noticias
   - Crear usuarios
   - Administrar citas
   - Crear noticias con imágenes

Si todo funciona correctamente, ¡la instalación está completa!

