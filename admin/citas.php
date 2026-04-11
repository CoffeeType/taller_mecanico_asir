<?php
// citas-administracion.php
require_once '../config/database.php';
require_once '../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    redirect('../index.php');
}

require_once '../includes/header.php';

$errors = [];
$success = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $idCita = $_POST['idCita'] ?? null;

    if ($action === 'delete' && $idCita) {
        try {
            $stmt = $pdo->prepare("DELETE FROM citas WHERE idCita = ?");
            $stmt->execute([$idCita]);
            $success = "Cita eliminada correctamente.";
        } catch (PDOException $e) {
            $errors[] = "Error al eliminar cita.";
        }
    } elseif ($action === 'update' && $idCita) {
        $fecha = $_POST['fecha_cita'];
        $hora = $_POST['hora_cita'];
        $motivo = sanitize($_POST['motivo_cita']);
        
        try {
            $stmt = $pdo->prepare("UPDATE citas SET fecha_cita = ?, hora_cita = ?, motivo_cita = ? WHERE idCita = ?");
            $stmt->execute([$fecha, $hora, $motivo, $idCita]);
            $success = "Cita actualizada.";
        } catch (PDOException $e) {
            $errors[] = "Error al actualizar cita.";
        }
    }
}

// Fetch All Appointments
// We join with users_data to get registered info. 
// IF idUser IS NULL, we rely on guest_name/guest_email columns.
$sql = "
    SELECT c.*, 
           u.nombre as user_nombre, u.apellidos as user_apellidos, u.email as user_email, u.telefono as user_phone
    FROM citas c
    LEFT JOIN users_data u ON c.idUser = u.idUser
    ORDER BY c.fecha_cita DESC
";
$citas = $pdo->query($sql)->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-calendar-check me-2"></i>Agenda Global de Citas</h1>
        <span class="badge bg-primary fs-6"><?= count($citas) ?> Citas Totales</span>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $success ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= implode('<br>', $errors) ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col" class="ps-3">Fecha / Hora</th>
                            <th scope="col">Cliente</th>
                            <th scope="col">Contacto</th>
                            <th scope="col">Motivo</th>
                            <th scope="col" class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($citas)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No hay citas registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($citas as $c): ?>
                                <?php 
                                    // Determine display data
                                    $isGuest = empty($c['idUser']);
                                    $clientName = $isGuest ? $c['guest_name'] : $c['user_nombre'] . ' ' . $c['user_apellidos'];
                                    $clientEmail = $isGuest ? $c['guest_email'] : $c['user_email'];
                                    $clientPhone = $isGuest ? $c['guest_phone'] : $c['user_phone']; // assuming users_data has phone
                                    $badge = $isGuest ? '<span class="badge bg-secondary">Invitado</span>' : '<span class="badge bg-success">Registrado</span>';
                                    $isPast = strtotime($c['fecha_cita']) < strtotime(date('Y-m-d'));
                                    $rowClass = $isPast ? 'text-muted bg-light' : '';
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td class="ps-3">
                                        <div class="fw-bold"><?= date('d/m/Y', strtotime($c['fecha_cita'])) ?></div>
                                        <div class="small text-muted"><i class="bi bi-clock"></i> <?= substr($c['hora_cita'], 0, 5) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($clientName) ?></div>
                                        <?= $badge ?>
                                    </td>
                                    <td class="small">
                                        <div><i class="bi bi-envelope"></i> <?= htmlspecialchars($clientEmail) ?></div>
                                        <?php if ($clientPhone): ?>
                                            <div><i class="bi bi-telephone"></i> <?= htmlspecialchars($clientPhone) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($c['motivo_cita']) ?>">
                                            <?= htmlspecialchars($c['motivo_cita']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $c['idCita'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Borrar cita?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="idCita" value="<?= $c['idCita'] ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $c['idCita'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Editar Cita</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="idCita" value="<?= $c['idCita'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Fecha</label>
                                                        <input type="date" name="fecha_cita" class="form-control" value="<?= $c['fecha_cita'] ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Hora</label>
                                                        <input type="time" name="hora_cita" class="form-control" value="<?= $c['hora_cita'] ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Motivo</label>
                                                        <textarea name="motivo_cita" class="form-control" rows="3"><?= htmlspecialchars($c['motivo_cita']) ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                    <button type="submit" class="btn btn-primary">Guardar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
