<?php
// config/database.php - Unified Database Configuration
// Supports environment variables, Docker, and local development

function isRunningInContainer(): bool {
    if (file_exists('/.dockerenv')) {
        return true;
    }
    $cgroup = '/proc/1/cgroup';
    if (file_exists($cgroup)) {
        $contents = @file_get_contents($cgroup);
        if ($contents !== false && preg_match('/docker|containerd|kubepods/i', $contents)) {
            return true;
        }
    }
    return false;
}

function envStr(string $key): ?string {
    $value = getenv($key);
    if ($value === false) return null;
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function applyDbEnvAliases(): void {
    // Coolify / common managed DB envs often use MYSQL_* (or MARIADB_*)
    $aliases = [
        'DB_HOST' => ['MYSQL_HOST', 'MARIADB_HOST'],
        'DB_PORT' => ['MYSQL_PORT', 'MARIADB_PORT'],
        'DB_NAME' => ['MYSQL_DATABASE', 'MARIADB_DATABASE'],
        'DB_USER' => ['MYSQL_USER', 'MARIADB_USER'],
        'DB_PASS' => ['MYSQL_PASSWORD', 'MARIADB_PASSWORD'],
    ];

    foreach ($aliases as $target => $sources) {
        if (envStr($target) !== null) continue;
        foreach ($sources as $src) {
            $v = envStr($src);
            if ($v !== null) {
                putenv("$target=$v");
                $_ENV[$target] = $v;
                $_SERVER[$target] = $v;
                break;
            }
        }
    }

    // Optional: DATABASE_URL=mysql://user:pass@host:3306/dbname
    if (envStr('DB_HOST') === null && envStr('DATABASE_URL') !== null) {
        $url = envStr('DATABASE_URL');
        $parts = @parse_url($url);
        if (is_array($parts) && (($parts['scheme'] ?? '') === 'mysql' || ($parts['scheme'] ?? '') === 'mariadb')) {
            if (!empty($parts['host'])) {
                putenv('DB_HOST=' . $parts['host']);
                $_ENV['DB_HOST'] = $parts['host'];
                $_SERVER['DB_HOST'] = $parts['host'];
            }
            if (!empty($parts['port'])) {
                putenv('DB_PORT=' . $parts['port']);
                $_ENV['DB_PORT'] = (string)$parts['port'];
                $_SERVER['DB_PORT'] = (string)$parts['port'];
            }
            if (!empty($parts['user'])) {
                putenv('DB_USER=' . $parts['user']);
                $_ENV['DB_USER'] = $parts['user'];
                $_SERVER['DB_USER'] = $parts['user'];
            }
            if (array_key_exists('pass', $parts) && $parts['pass'] !== null) {
                putenv('DB_PASS=' . $parts['pass']);
                $_ENV['DB_PASS'] = (string)$parts['pass'];
                $_SERVER['DB_PASS'] = (string)$parts['pass'];
            }
            if (!empty($parts['path'])) {
                $db = ltrim((string)$parts['path'], '/');
                if ($db !== '') {
                    putenv('DB_NAME=' . $db);
                    $_ENV['DB_NAME'] = $db;
                    $_SERVER['DB_NAME'] = $db;
                }
            }
        }
    }
}

function isLocalhost(string $host): bool {
    $host = trim($host);
    if ($host === '') return false;

    // Strip port for common host:port patterns (avoid touching IPv6 in brackets).
    if (strpos($host, ':') !== false && strpos($host, ']') === false) {
        $parts = explode(':', $host);
        if (count($parts) >= 2) {
            $host = (string)$parts[0];
        }
    }

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

// Load environment variables if .env exists
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                $value = $matches[1];
            }

            // Don't override already provided environment (e.g. Docker Compose service env)
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Normalize DB envs from common aliases (Coolify/managed DBs)
applyDbEnvAliases();

$deployTarget = strtolower(envStr('DEPLOY_TARGET') ?? '');
$appEnv = strtolower(envStr('APP_ENV') ?? '');
$runningInContainer = isRunningInContainer();

$isInfinityfree = false;
if ($deployTarget === 'infinityfree') {
    $isInfinityfree = true;
} elseif ($deployTarget === '' && isset($_SERVER['HTTP_HOST']) && stripos((string)$_SERVER['HTTP_HOST'], 'infinityfree') !== false) {
    $isInfinityfree = true;
}

// Database configuration with proper fallbacks
if ($isInfinityfree) {
    $host = envStr('DB_HOST') ?? 'sql208.infinityfree.com';
    $db   = envStr('DB_NAME') ?? 'if0_40685841_trabajo_final_php';
    $user = envStr('DB_USER') ?? 'if0_40685841';
    $pass = envStr('DB_PASS') ?? '';
} else {
    // Guardrail: inside containers, `localhost` points to the container itself (not to MySQL service).
    $configuredHost = envStr('DB_HOST');
    if ($runningInContainer && $configuredHost !== null && isLocalhost($configuredHost)) {
        $replacementHost = envStr('MYSQL_HOST') ?? envStr('MARIADB_HOST') ?? 'mysql';
        error_log("ADVERTENCIA: DB_HOST={$configuredHost} dentro de un contenedor no es válido. Usando {$replacementHost}.");
        putenv('DB_HOST=' . $replacementHost);
        $_ENV['DB_HOST'] = $replacementHost;
        $_SERVER['DB_HOST'] = $replacementHost;
    }

    // Prefer Docker service name inside containers; localhost for local dev
    $host = envStr('DB_HOST') ?? ($runningInContainer ? 'mysql' : 'localhost');
    $db   = envStr('DB_NAME') ?? 'trabajo_final_php';
    $user = envStr('DB_USER') ?? 'root';
    $pass = envStr('DB_PASS') ?? ($runningInContainer ? 'rootpassword' : '');
}

$charset = 'utf8mb4';

// Support host:port (common in local .env) and/or DB_PORT
$port = envStr('DB_PORT') ?? '';
if (strpos($host, ':') !== false && strpos($host, ']') === false) {
    $parts = explode(':', $host);
    if (count($parts) >= 2) {
        $maybePort = end($parts);
        if ($maybePort !== '' && ctype_digit($maybePort)) {
            $port = $port ?: $maybePort;
            array_pop($parts);
            $host = implode(':', $parts);
        }
    }
}

// Create DSN and options
$dsn = "mysql:host=$host;" . (!empty($port) ? "port=$port;" : "") . "dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => true, // For better performance
];

// Global PDO instance
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Test connection with a simple query
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database Connection Error: " . $e->getMessage());
    error_log("DSN: $dsn, User: $user");
    
    // Provide user-friendly error message
    $isProduction = ($appEnv === 'production');
    if ($isProduction) {
        die("
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ffcccc; background-color: #fff0f0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <h2 style='color: #cc0000; margin-top: 0;'>Error de Conexión</h2>
                <p>Lo sentimos, no se puede conectar al servicio en este momento. Por favor, inténtalo más tarde.</p>
                <p style='font-size: 0.9em; color: #666;'>Si el problema persiste, contacta al administrador del sistema.</p>
            </div>
        ");
    } else {
        die("
            <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; border: 1px solid #ffcccc; background-color: #fff0f0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <h2 style='color: #cc0000; margin-top: 0;'>Error de Conexión a la Base de Datos</h2>
                <p><strong>Detalles del error:</strong></p>
                <pre style='background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9em;'>{$e->getMessage()}</pre>
                <p><strong>Configuración intentada:</strong></p>
                <ul style='background: #fff; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9em;'>
                    <li>Host: $host</li>
                    <li>Base de datos: $db</li>
                    <li>Usuario: $user</li>
                    <li>Contraseña: " . (empty($pass) ? '[vacía]' : '[oculta]') . "</li>
                </ul>
                <p><strong>Soluciones comunes:</strong></p>
                <ul>
                    <li>Verifica que el servidor MySQL/MariaDB esté en ejecución</li>
                    <li>Comprueba las credenciales en el archivo <code>.env</code></li>
                    <li>Asegúrate de que la base de datos <code>$db</code> exista</li>
                    <li>Verifica que el usuario <code>$user</code> tenga permisos</li>
                </ul>
            </div>
        ");
    }
}

// Optional: Create a function to get the PDO instance for consistency
function getDatabaseConnection() {
    global $pdo;
    return $pdo;
}

// End of file
