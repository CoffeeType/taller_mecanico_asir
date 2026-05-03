<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/metrics_logger.php';

// Iniciar medición del tiempo de respuesta para métricas
startResponseTimeMeasurement();

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin_dir = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;
$base_path = $is_admin_dir ? '../' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Taller mecánico líder en reparaciones, ITV y diagnóstico. Reserva tu cita online en minutos. Calidad y rapidez garantizadas.">
    <meta property="og:title" content="Taller Mecánico Profesional - Cita Previa Online">
    <meta property="og:description" content="Expertos en mecánica general y electricidad. Reserva tu cita ahora sin registro.">
    <meta property="og:image" content="<?= $base_path ?>img/hero.png">
    <link rel="icon" href="<?= $base_path ?>img/logo.png" type="image/png">
    <title>Taller Mecánico - Especialistas en Tu Vehículo</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $base_path ?>css/style.css">

</head>
<body class="d-flex flex-column min-vh-100">
    <header>
        <nav class="navbar navbar-expand-lg navbar-light py-3">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $base_path ?>index.php">
                    <img src="<?= $base_path ?>img/logo.png" alt="Taller Mecánico" width="40" height="40" class="d-inline-block align-text-top" style="object-fit: contain;">
                    <span>Taller Mecánico</span>
                </a>
                <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <!-- Common Pages -->
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="<?= $base_path ?>index.php">Inicio</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page == 'noticias.php' ? 'active' : '' ?>" href="<?= $base_path ?>noticias.php">Noticias</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav ms-auto align-items-center">
                        <?php if (isLoggedIn()): ?>
                            <?php if (isAdmin()): ?>
                                <!-- Admin Sections -->
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Administración
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                        <li><a class="dropdown-item" href="<?= $base_path ?>admin/index.php"><strong>Panel Principal</strong></a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="<?= $base_path ?>admin/usuarios.php">Usuarios</a></li>
                                        <li><a class="dropdown-item" href="<?= $base_path ?>admin/citas.php">Citas</a></li>
                                        <li><a class="dropdown-item" href="<?= $base_path ?>admin/noticias.php">Noticias</a></li>
                                        <li><a class="dropdown-item" href="<?= $base_path ?>admin/consejos.php">Consejos</a></li>
                                    </ul>
                                </li>
                            <?php else: ?>
                                <!-- User Sections -->
                                <li class="nav-item">
                                    <a class="nav-link <?= $current_page == 'citaciones.php' ? 'nav-btn-highlight' : '' ?>" href="<?= $base_path ?>citaciones.php">Mis Citas</a>
                                </li>
                            <?php endif; ?>
                            
                            <li class="nav-item dropdown ms-2">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-person-circle"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><h6 class="dropdown-header">Hola, <?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?></h6></li>
                                    <li><a class="dropdown-item" href="<?= $base_path ?>perfil.php">Perfil</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?= $base_path ?>logout.php">Cerrar Sesión</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <!-- Guest Sections -->
                            <li class="nav-item me-2">
                                <a class="nav-link" href="<?= $base_path ?>login.php">Iniciar Sesión</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link nav-btn-highlight text-white" href="<?= $base_path ?>citaciones.php">
                                    <i class="bi bi-calendar-check me-1"></i> Pedir Cita
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main class="flex-grow-1 container py-4">
        <?php if ($msg = getFlashMessage('success')): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($msg = getFlashMessage('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
