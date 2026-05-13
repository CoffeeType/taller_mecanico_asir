<?php
// includes/citas_api.php (expuesto como /api/citas_api.php)
if (!defined('TALLER_DB_JSON_EXIT')) {
    define('TALLER_DB_JSON_EXIT', true);
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/metrics_logger.php';

startResponseTimeMeasurement();
register_shutdown_function(static function (): void {
    if (function_exists('logCurrentRequestMetrics')) {
        logCurrentRequestMetrics();
    }
});

session_start();

header('Content-Type: application/json');

// Cabeceras CORS (ajustar en producción si hace falta)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Ayuda: validar franjas horarias (p. ej. 09:00–17:00, slots de 1 h)
// Puedes ajustar estas constantes
const START_HOUR = 9;
const END_HOUR = 17;
const SLOT_DURATION = 60; // minutos

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Devolver huecos reservados para un mes
    if (!isset($_GET['year']) || !isset($_GET['month'])) {
        sendJson(['error' => 'Year and Month required'], 400);
    }

    $year = intval($_GET['year']);
    $month = intval($_GET['month']);

    // Rango de la consulta
    $startDate = "$year-$month-01";
    $endDate = date("Y-m-t", strtotime($startDate));

    try {
        $stmt = $pdo->prepare("SELECT fecha_cita, hora_cita FROM citas WHERE fecha_cita BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar por fecha
        $bookedSlots = []; // p. ej. '2023-12-01' => ['09:00:00', '10:00:00']
        foreach ($bookings as $b) {
            $bookedSlots[$b['fecha_cita']][] = $b['hora_cita'];
        }

        sendJson(['booked' => $bookedSlots]);

    } catch (PDOException $e) {
        sendJson(['error' => 'Database error'], 500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Crear nueva reserva
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        sendJson(['error' => 'Invalid JSON'], 400);
    }
    
    // --- LIMITACIÓN DE TASA (prevención básica) ---
    if (!isset($_SESSION['last_booking_attempt'])) {
        $_SESSION['last_booking_attempt'] = 0;
    }
    $timeSinceLast = time() - $_SESSION['last_booking_attempt'];
    
    // Máx. 1 intento cada 10 s (invitados y usuarios) para frenar spam
    if ($timeSinceLast < 10) {
        sendJson(['error' => 'Por favor, espera unos segundos antes de realizar otra reserva.'], 429);
    }
    $_SESSION['last_booking_attempt'] = time();
    // ---------------------------------------------

    // Validación
    if (empty($data['fecha']) || empty($data['hora']) || empty($data['motivo'])) {
        sendJson(['error' => 'Faltan campos obligatorios'], 400);
    }
    
    // Sanitizar y validar entrada
    $fecha = htmlspecialchars(trim($data['fecha'] ?? ''), ENT_QUOTES, 'UTF-8');
    $hora = htmlspecialchars(trim($data['hora'] ?? ''), ENT_QUOTES, 'UTF-8');
    $motivo = sanitize($data['motivo'] ?? '');
    
    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        sendJson(['error' => 'Formato de fecha inválido'], 400);
    }
    
    // Validar formato de hora
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
        sendJson(['error' => 'Formato de hora inválido'], 400);
    }
    
    // La fecha no puede ser pasada
    if (strtotime($fecha . ' ' . $hora) < time()) {
        sendJson(['error' => 'No se pueden agendar citas en el pasado'], 400);
    }

    // Invitado frente a usuario registrado
    $idUser = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $guestName = isset($data['guest_name']) ? sanitize($data['guest_name']) : null;
    $guestEmail = isset($data['guest_email']) ? filter_var($data['guest_email'], FILTER_SANITIZE_EMAIL) : null;
    $guestPhone = isset($data['guest_phone']) ? sanitize($data['guest_phone']) : null;

    if (!$idUser) {
        // Validación para invitado
        if (empty($guestName) || empty($guestEmail) || empty($guestPhone)) {
            sendJson(['error' => 'Datos de contacto obligatorios para no registrados'], 400);
        }
        
        // Validar formato de email del invitado
        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            sendJson(['error' => 'Email inválido'], 400);
        }
    } else {
        // Obtener email del usuario para confirmación si está registrado
        try {
            $stmtUser = $pdo->prepare("SELECT email, nombre FROM users_data WHERE idUser = ?");
            $stmtUser->execute([$idUser]);
            $userRow = $stmtUser->fetch();
            if ($userRow) {
                $guestEmail = $userRow['email']; // Usar email del usuario para el envío
                $guestName = $userRow['nombre'];
            }
        } catch (Exception $e) {
            // ignorar
        }
    }

    // Comprobar disponibilidad
    try {
        $check = $pdo->prepare("SELECT idCita FROM citas WHERE fecha_cita = ? AND hora_cita = ?");
        $check->execute([$fecha, $hora]);
        if ($check->rowCount() > 0) {
            sendJson(['error' => 'Ese horario ya no está disponible'], 409);
        }

        // Insertar con datos sanitizados
        $sql = "INSERT INTO citas (idUser, fecha_cita, hora_cita, motivo_cita, guest_name, guest_email, guest_phone) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $idUser,
            $fecha,
            $hora,
            $motivo,
            $guestName,
            $guestEmail,
            $guestPhone
        ]);

        // --- CONFIRMACIÓN POR EMAIL ---
        $to = $guestEmail;
        $subject = "Confirmación de Cita - Taller Mecánico";
        $message = "Hola " . htmlspecialchars($guestName) . ",\n\nTu cita ha sido confirmada para el día " . $fecha . " a las " . $hora . ".\n\nMotivo: " . htmlspecialchars($motivo) . "\n\nGracias por confiar en nosotros.";
        $headers = "From: no-reply@taller.com" . "\r\n" .
                   "Reply-To: contacto@taller.com" . "\r\n" .
                   "X-Mailer: PHP/" . phpversion();

        // Intento de envío (puede fallar en local; suprimir error)
        @mail($to, $subject, $message, $headers);

        sendJson(['success' => true, 'message' => 'Cita agendada correctamente. Se ha enviado un email de confirmación (si el servidor lo permite).']);

    } catch (PDOException $e) {
        sendJson(['error' => 'Error al guardar cita: ' . $e->getMessage()], 500);
    }
} else {
    sendJson(['error' => 'Method not allowed'], 405);
}
// Fin de archivo
