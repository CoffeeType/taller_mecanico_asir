<?php
// citaciones.php - Unified Booking & History Page
require_once 'config/database.php';
require_once 'includes/header.php';

$isLoggedIn = isLoggedIn();
$idUser = $isLoggedIn ? $_SESSION['user_id'] : null;

// --- 1. My Citations Logic (from old citaciones.php) ---
$myCitas = [];
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM citas WHERE idUser = ? ORDER BY fecha_cita DESC");
        $stmt->execute([$idUser]);
        $myCitas = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Error cargando tus citas.";
    }
}

// --- 2. Calendar Logic (from booking-calendar.php) ---
// Get current month and year from URL or use current
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Ensure valid month/year
if ($selectedMonth < 1 || $selectedMonth > 12) $selectedMonth = intval(date('n'));
if ($selectedYear < 2020 || $selectedYear > 2030) $selectedYear = intval(date('Y'));

// Fetch booked dates for the current month
$bookedDatesArray = getBookedDates($pdo, $selectedYear, $selectedMonth);

$monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
?>

<div class="container py-4">
    <!-- Section 1: BOOKING CALENDAR -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-12">
            <h1 class="mb-4 text-center">Reserva tu Cita</h1>
            
            <div class="card shadow-sm">
                <!-- Calendar Header -->
                <div class="card-header bg-white border-bottom py-3">
                    <div class="month-navigation">
                        <button class="btn btn-sm btn-outline-secondary" onclick="changeMonth(-1)">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <h5 class="mb-0 text-center flex-grow-1" id="monthYearDisplay">
                            <?php echo $monthNames[$selectedMonth - 1] . ' ' . $selectedYear; ?>
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="changeMonth(1)">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Calendar Body -->
                <div class="card-body p-0">
                    <div class="row g-0">
                        <!-- Calendar Section -->
                        <div class="col-md-7 p-4 border-end">
                            <div class="calendar-header">
                                <div>Dom</div><div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div>
                            </div>
                            
                            <div class="calendar-grid" id="calendarGrid">
                                <?php
                                $firstDayOfMonth = new DateTime("$selectedYear-$selectedMonth-01");
                                $firstDayWeekday = $firstDayOfMonth->format('w');
                                $daysInMonth = $firstDayOfMonth->format('t');
                                $daysInPrevMonth = (clone $firstDayOfMonth)->modify('-1 day')->format('d');
                                
                                // Prev Month
                                for ($i = $firstDayWeekday - 1; $i >= 0; $i--) {
                                    $day = $daysInPrevMonth - $i;
                                    echo "<div class='calendar-day outside'>$day</div>";
                                }
                                
                                // Current Month
                                for ($day = 1; $day <= $daysInMonth; $day++) {
                                    $dateStr = sprintf("%04d-%02d-%02d", $selectedYear, $selectedMonth, $day);
                                    $isBooked = in_array($dateStr, $bookedDatesArray);
                                    $isPast = strtotime($dateStr) < strtotime(date('Y-m-d'));
                                    
                                    $classes = ['calendar-day'];
                                    if ($isPast) $classes[] = 'disabled';
                                    if ($isBooked) $classes[] = 'booked';
                                    
                                    $onclick = $isPast ? '' : "selectDate('$dateStr')";
                                    
                                    echo "<div class='" . implode(' ', $classes) . "' onclick=\"$onclick\" data-date='$dateStr'>$day</div>";
                                }
                                
                                // Next Month
                                $totalCells = $firstDayWeekday + $daysInMonth;
                                $remainingCells = (7 - ($totalCells % 7)) % 7;
                                for ($day = 1; $day <= $remainingCells; $day++) {
                                    echo "<div class='calendar-day outside'>$day</div>";
                                }
                                ?>
                            </div>
                        </div>
                        
                        <!-- Time Slots Section -->
                        <div class="col-md-5 p-4">
                            <div id="time-slot-placeholder" class="text-center text-muted py-5">
                                <i class="bi bi-calendar-event fs-1 d-block mb-3"></i>
                                <p>Selecciona una fecha para ver horarios</p>
                            </div>
                            
                            <div id="time-slot-section" style="display: none;">
                                <h6 class="mb-3">Horarios Disponibles</h6>
                                <div class="time-slot-container" id="timeSlotsContainer">
                                    <div class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Booking Form -->
            <div id="booking-form-section" class="card shadow-sm mt-4" style="display:none;">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-check-circle-fill me-2"></i>Confirmar Reserva: <span id="selected-summary"></span></h5>
                </div>
                <div class="card-body">
                    <form id="booking-form">
                        <input type="hidden" id="selected-date" name="fecha">
                        <input type="hidden" id="selected-time" name="hora">
                        
                        <?php if (!$isLoggedIn): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>Estás reservando como invitado. O <a href="login.php" class="alert-link">inicia sesión</a>.
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre Completo *</label>
                                    <input type="text" class="form-control" name="guest_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="guest_email" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Teléfono *</label>
                                    <input type="tel" class="form-control" name="guest_phone" required>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="bi bi-person-check me-2"></i>Reservando como <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Usuario'); ?></strong>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Motivo de la Cita *</label>
                            <textarea class="form-control" name="motivo" rows="3" required placeholder="Describe el servicio que necesitas..."></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" onclick="cancelBooking()">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="btn-submit-booking">Confirmar Reserva</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: MY CITATIONS (Logged In Only) -->
    <?php if ($isLoggedIn): ?>
    <hr class="my-5">
    <div class="row">
        <div class="col-12">
            <h3 class="h4 mb-4"><i class="bi bi-clock-history me-2"></i>Mis Citas Agendadas</h3>
            
            <?php if (empty($myCitas)): ?>
                <div class="alert alert-light text-center border">
                    No tienes citas registradas. ¡Reserva una arriba!
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle shadow-sm bg-white rounded">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myCitas as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['fecha_cita']) ?></td>
                                    <td><?= isset($c['hora_cita']) ? substr($c['hora_cita'],0,5) : '--:--' ?></td>
                                    <td><?= htmlspecialchars($c['motivo_cita']) ?></td>
                                    <td>
                                        <?php if ($c['fecha_cita'] >= date('Y-m-d')): ?>
                                            <span class="badge bg-success">Próxima</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pasada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// JS Logic from booking-calendar.php
let currentMonth = <?php echo $selectedMonth; ?>;
let currentYear = <?php echo $selectedYear; ?>;
let selectedDate = null;
let selectedTime = null;
const monthNames = <?php echo json_encode($monthNames); ?>;

function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth > 12) { currentMonth = 1; currentYear++; } 
    else if (currentMonth < 1) { currentMonth = 12; currentYear--; }
    window.location.href = `?month=${currentMonth}&year=${currentYear}`;
}

function selectDate(date) {
    selectedDate = date;
    document.querySelectorAll('.calendar-day').forEach(day => {
        day.classList.remove('selected');
        if (day.dataset.date === date) day.classList.add('selected');
    });
    document.getElementById('time-slot-placeholder').style.display = 'none';
    document.getElementById('time-slot-section').style.display = 'block';
    loadTimeSlots(date);
}

function loadTimeSlots(date) {
    const container = document.getElementById('timeSlotsContainer');
    container.innerHTML = '<div class="text-center"><div class="spinner-border text-primary spinner-border-sm"></div> Cargando...</div>';
    const [year, month, day] = date.split('-');
    
    const timestamp = new Date().getTime();
    fetch(`api/citas_api.php?year=${year}&month=${month}&v=${timestamp}`)
        .then(res => res.json())
        .then(data => {
            if(data.error) throw new Error(data.error);
            const bookedTimes = data.booked[date] || [];
            renderTimeSlots(date, bookedTimes);
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<div class="alert alert-danger py-2">Error cargando horarios</div>';
        });
}

function renderTimeSlots(date, bookedTimes) {
    const container = document.getElementById('timeSlotsContainer');
    container.innerHTML = '';
    const startHour = 9;
    const endHour = 17;
    let hasAvailable = false;
    
    for (let hour = startHour; hour < endHour; hour++) {
        const timeStr = `${hour.toString().padStart(2, '0')}:00`;
        const isBooked = bookedTimes.some(t => t.startsWith(timeStr));
        
        const btn = document.createElement('button');
        btn.className = `btn time-slot-btn ${isBooked ? 'btn-outline-secondary disabled' : 'btn-outline-primary'}`;
        btn.textContent = timeStr;
        btn.type = 'button';
        btn.disabled = isBooked;
        
        if (!isBooked) {
            btn.onclick = (e) => selectTime(date, timeStr, e);
            hasAvailable = true;
        } else {
            btn.innerHTML = `${timeStr} <small class='text-muted'>(Ocupado)</small>`;
        }
        container.appendChild(btn);
    }
    
    if (!hasAvailable) {
        container.innerHTML = '<div class="alert alert-warning py-2">No hay horarios disponibles.</div>';
    }
}

function selectTime(date, time, event) {
    selectedTime = time;
    document.querySelectorAll('.time-slot-btn').forEach(btn => {
        btn.classList.remove('btn-primary');
        if(!btn.disabled) btn.classList.add('btn-outline-primary');
    });
    event.target.classList.remove('btn-outline-primary');
    event.target.classList.add('btn-primary');
    
    document.getElementById('selected-date').value = date;
    document.getElementById('selected-time').value = time;
    
    const dateObj = new Date(date + 'T00:00:00');
    const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const dayName = dayNames[dateObj.getDay()];
    const monthName = monthNames[dateObj.getMonth()];
    
    document.getElementById('selected-summary').textContent = `${dayName}, ${dateObj.getDate()} de ${monthName} a las ${time}`;
    
    const formSection = document.getElementById('booking-form-section');
    formSection.style.display = 'block';
    formSection.scrollIntoView({ behavior: 'smooth' });
}

function cancelBooking() {
    document.getElementById('booking-form-section').style.display = 'none';
    document.getElementById('booking-form').reset();
    selectedTime = null;
    document.querySelectorAll('.time-slot-btn').forEach(btn => {
        if (!btn.disabled) {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-primary');
        }
    });
}

document.getElementById('booking-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-booking');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    fetch('api/citas_api.php?v=' + new Date().getTime(), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            alert('✓ ' + res.message);
            window.location.reload(); 
        } else {
            alert('✗ Error: ' + (res.error || 'Desconocido'));
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    })
    .catch(err => {
        alert('✗ Error de conexión');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
