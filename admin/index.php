<?php
// admin.php - Dashboard
require_once '../config/database.php';
require_once '../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    redirect('../index.php');
}

require_once '../includes/header.php';

// Fetch basic stats
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users_data")->fetchColumn(),
    'citas' => $pdo->query("SELECT COUNT(*) FROM citas WHERE fecha_cita >= CURDATE()")->fetchColumn(),
    'noticias' => $pdo->query("SELECT COUNT(*) FROM noticias")->fetchColumn(),
    'consejos' => $pdo->query("SELECT COUNT(*) FROM consejos")->fetchColumn(),
];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-speedometer2 me-2"></i>Panel de Administración</h1>
        <span class="badge bg-secondary">Bienvenido, <?= htmlspecialchars($_SESSION['username']) ?></span>
    </div>

    <div class="row g-4 mb-4">
        <!-- Stats Cards -->
        <div class="col-md-6 col-lg-3">
            <div class="card bg-primary text-white h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 opacity-75">Usuarios Registrados</h6>
                            <h2 class="card-title mb-0"><?= $stats['users'] ?></h2>
                        </div>
                        <i class="bi bi-people fs-1 opacity-50"></i>
                    </div>
                </div>
                <a href="usuarios.php" class="card-footer bg-transparent border-0 text-white text-decoration-none small">
                    Gestionar Usuarios <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card bg-success text-white h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 opacity-75">Citas Pendientes</h6>
                            <h2 class="card-title mb-0"><?= $stats['citas'] ?></h2>
                        </div>
                        <i class="bi bi-calendar-check fs-1 opacity-50"></i>
                    </div>
                </div>
                <a href="citas.php" class="card-footer bg-transparent border-0 text-white text-decoration-none small">
                    Ver Agenda <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card bg-info text-white h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 opacity-75">Noticias</h6>
                            <h2 class="card-title mb-0"><?= $stats['noticias'] ?></h2>
                        </div>
                        <i class="bi bi-newspaper fs-1 opacity-50"></i>
                    </div>
                </div>
                <a href="noticias.php" class="card-footer bg-transparent border-0 text-white text-decoration-none small">
                    Editar Noticias <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="card bg-warning text-dark h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 opacity-75">Consejos</h6>
                            <h2 class="card-title mb-0"><?= $stats['consejos'] ?></h2>
                        </div>
                        <i class="bi bi-tools fs-1 opacity-50"></i>
                    </div>
                </div>
                <a href="consejos.php" class="card-footer bg-transparent border-0 text-dark text-decoration-none small">
                    Gestionar Consejos <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Accesos Rápidos</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="../index.php" class="btn btn-outline-secondary w-100 py-3">
                                <i class="bi bi-house me-2"></i> Ir al Inicio
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="test-alert-email.php" class="btn btn-outline-secondary w-100 py-3">
                                <i class="bi bi-envelope-check me-2"></i> Test Email Alertas
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="../perfil.php" class="btn btn-outline-secondary w-100 py-3">
                                <i class="bi bi-person me-2"></i> Mi Perfil Admin
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="../logout.php" class="btn btn-outline-danger w-100 py-3">
                                <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
