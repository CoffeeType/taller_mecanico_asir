<?php
// includes/functions.php

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function sanitize($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo '<script type="text/javascript">';
        echo 'window.location.href="' . $url . '";';
        echo '</script>';
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
        echo '</noscript>';
    }
    exit();
}

function getFlashMessage($key) {
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

function setFlashMessage($key, $message) {
    $_SESSION[$key] = $message;
}

function getBookedDates($pdo, $year, $month) {
    try {
        $startDate = "$year-$month-01";
        $endDate = date("Y-m-t", strtotime($startDate));
        
        $stmt = $pdo->prepare("SELECT DISTINCT fecha_cita FROM citas WHERE fecha_cita BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}
// functions.php

function getLatestTips($pdo, $limit = 3) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM consejos ORDER BY fecha DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Alias functions for Spanish naming convention (used in admin files)
function iniciarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function verificarRol($rolRequerido) {
    if (!isLoggedIn()) {
        return false;
    }
    if ($rolRequerido === 'admin') {
        return isAdmin();
    }
    // For 'user' role, any logged in user qualifies
    return isset($_SESSION['user_role']);
}

function sanitizarDatos($datos) {
    if (is_array($datos)) {
        $sanitizados = [];
        foreach ($datos as $key => $value) {
            $sanitizados[$key] = sanitizarDatos($value);
        }
        return $sanitizados;
    }
    return sanitize($datos);
}

function validarCamposObligatorios($datos, $camposObligatorios) {
    $errores = [];
    foreach ($camposObligatorios as $campo) {
        if (!isset($datos[$campo]) || empty(trim($datos[$campo]))) {
            $errores[] = "El campo '$campo' es obligatorio.";
        }
    }
    return $errores;
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// File upload validation functions
function validarArchivoImagen($archivo) {
    $errores = [];
    
    if (!isset($archivo) || $archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Error al subir el archivo.";
        return $errores;
    }
    
    // Size validation (5MB max)
    $tamanoMaximo = 5 * 1024 * 1024;
    if ($archivo['size'] > $tamanoMaximo) {
        $errores[] = "La imagen es demasiado grande (máximo 5MB).";
    }
    
    // MIME type validation
    $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $mimeType = mime_content_type($archivo['tmp_name']);
    if (!in_array($mimeType, $tiposPermitidos)) {
        $errores[] = "El archivo debe ser una imagen válida (JPG, PNG o GIF).";
    }
    
    // Magic byte validation (more secure than MIME type alone)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    $magicBytesMap = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'image/gif' => ["\x47\x49\x46\x38\x37\x61", "\x47\x49\x46\x38\x39\x61"]
    ];
    
    $validMagicBytes = false;
    if (isset($magicBytesMap[$detectedMime])) {
        $handle = fopen($archivo['tmp_name'], 'rb');
        if ($handle) {
            $header = fread($handle, 10);
            fclose($handle);
            foreach ($magicBytesMap[$detectedMime] as $magic) {
                if (substr($header, 0, strlen($magic)) === $magic) {
                    $validMagicBytes = true;
                    break;
                }
            }
        }
    }
    
    if (!$validMagicBytes) {
        $errores[] = "El archivo no es una imagen válida (validación de contenido fallida).";
    }
    
    // Filename sanitization
    $nombreOriginal = $archivo['name'];
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($extension, $extensionesPermitidas)) {
        $errores[] = "Extensión de archivo no permitida.";
    }
    
    return $errores;
}

function sanitizarNombreArchivo($nombreOriginal) {
    // Remove any path components
    $nombre = basename($nombreOriginal);
    // Remove special characters, keep only alphanumeric, dots, hyphens, underscores
    $nombre = preg_replace('/[^a-zA-Z0-9._-]/', '', $nombre);
    // Generate unique name with timestamp and random string
    $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $nombreBase = uniqid('img_', true) . '_' . time();
    return $nombreBase . '.' . $extension;
}
?>
