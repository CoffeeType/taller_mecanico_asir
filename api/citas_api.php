<?php
// includes/citas_api.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

header('Content-Type: application/json');

// CORS headers (adjust as needed for production)
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

// Helper to validate time slots (e.g. 09:00 to 17:00, 1 hour slots)
// You can adjust these constants
const START_HOUR = 9;
const END_HOUR = 17;
const SLOT_DURATION = 60; // minutes

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return booked slots for a given month
    if (!isset($_GET['year']) || !isset($_GET['month'])) {
        sendJson(['error' => 'Year and Month required'], 400);
    }

    $year = intval($_GET['year']);
    $month = intval($_GET['month']);

    // Query range
    $startDate = "$year-$month-01";
    $endDate = date("Y-m-t", strtotime($startDate));

    try {
        $stmt = $pdo->prepare("SELECT fecha_cita, hora_cita FROM citas WHERE fecha_cita BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by date
        $bookedSlots = []; // ['2023-12-01' => ['09:00:00', '10:00:00']]
        foreach ($bookings as $b) {
            $bookedSlots[$b['fecha_cita']][] = $b['hora_cita'];
        }

        sendJson(['booked' => $bookedSlots]);

    } catch (PDOException $e) {
        sendJson(['error' => 'Database error'], 500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new booking
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        sendJson(['error' => 'Invalid JSON'], 400);
    }
    
    // --- RATE LIMITING (Basic prevention) ---
    if (!isset($_SESSION['last_booking_attempt'])) {
        $_SESSION['last_booking_attempt'] = 0;
    }
    $timeSinceLast = time() - $_SESSION['last_booking_attempt'];
    
    // Allow max 1 attempt every 10 seconds for everyone (guests and users) to prevent swift spam
    if ($timeSinceLast < 10) {
        sendJson(['error' => 'Por favor, espera unos segundos antes de realizar otra reserva.'], 429);
    }
    $_SESSION['last_booking_attempt'] = time();
    // ----------------------------------------

    // Validation
    if (empty($data['fecha']) || empty($data['hora']) || empty($data['motivo'])) {
        sendJson(['error' => 'Faltan campos obligatorios'], 400);
    }
    
    // Sanitize and validate input
    $fecha = htmlspecialchars(trim($data['fecha'] ?? ''), ENT_QUOTES, 'UTF-8');
    $hora = htmlspecialchars(trim($data['hora'] ?? ''), ENT_QUOTES, 'UTF-8');
    $motivo = sanitize($data['motivo'] ?? '');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        sendJson(['error' => 'Formato de fecha inválido'], 400);
    }
    
    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
        sendJson(['error' => 'Formato de hora inválido'], 400);
    }
    
    // Validate date is not in the past
    if (strtotime($fecha . ' ' . $hora) < time()) {
        sendJson(['error' => 'No se pueden agendar citas en el pasado'], 400);
    }

    // Guest vs User
    $idUser = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $guestName = isset($data['guest_name']) ? sanitize($data['guest_name']) : null;
    $guestEmail = isset($data['guest_email']) ? filter_var($data['guest_email'], FILTER_SANITIZE_EMAIL) : null;
    $guestPhone = isset($data['guest_phone']) ? sanitize($data['guest_phone']) : null;

    if (!$idUser) {
        // Guest Validation
        if (empty($guestName) || empty($guestEmail) || empty($guestPhone)) {
            sendJson(['error' => 'Datos de contacto obligatorios para no registrados'], 400);
        }
        
        // Validate guest email format
        if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            sendJson(['error' => 'Email inválido'], 400);
        }
    } else {
        // Get User Email for confirmation if registered
        try {
            $stmtUser = $pdo->prepare("SELECT email, nombre FROM users_data WHERE idUser = ?");
            $stmtUser->execute([$idUser]);
            $userRow = $stmtUser->fetch();
            $guestEmail = $userRow['email']; // Use user's email for sending
            $guestName = $userRow['nombre'];
        } catch (Exception $e) {
            // ignore
        }
    }

    // Check availability
    try {
        $check = $pdo->prepare("SELECT idCita FROM citas WHERE fecha_cita = ? AND hora_cita = ?");
        $check->execute([$fecha, $hora]);
        if ($check->rowCount() > 0) {
            sendJson(['error' => 'Ese horario ya no está disponible'], 409);
        }

        // Insert with sanitized data
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

        // --- EMAIL CONFIRMATION ---
        $to = $guestEmail;
        $subject = "Confirmación de Cita - Taller Mecánico";
        $message = "Hola " . htmlspecialchars($guestName) . ",\n\nTu cita ha sido confirmada para el día " . $fecha . " a las " . $hora . ".\n\nMotivo: " . htmlspecialchars($motivo) . "\n\nGracias por confiar en nosotros.";
        $headers = "From: no-reply@taller.com" . "\r\n" .
                   "Reply-To: contacto@taller.com" . "\r\n" .
                   "X-Mailer: PHP/" . phpversion();

        // Attempt to send (might fail on local input, suppress error)
        @mail($to, $subject, $message, $headers);

        sendJson(['success' => true, 'message' => 'Cita agendada correctamente. Se ha enviado un email de confirmación (si el servidor lo permite).']);

    } catch (PDOException $e) {
        sendJson(['error' => 'Error al guardar cita: ' . $e->getMessage()], 500);
    }
}
// End of file
