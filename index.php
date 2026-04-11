<?php
// index.php
require_once 'includes/header.php';
?>

<div class="hero-section text-center p-5 mb-5 rounded shadow-lg d-flex align-items-center justify-content-center">
    <div class="hero-content">
        <h1 class="display-3 fw-bold mb-3">Tu Coche, En Las Mejores Manos</h1>
        <p class="lead mb-4 fs-4">Expertos en Mecánica General, Electricidad y Preparación ITV.<br>Garantía de calidad y rapidez.</p>
        <?php if (!isLoggedIn()): ?>
            <div class="d-flex gap-3 justify-content-center">
                <a href="citaciones.php" class="btn btn-primary btn-lg px-5 py-3 fw-bold">Pedir Cita Ahora</a>
                <a href="registro.php" class="btn btn-outline-light btn-lg px-5 py-3">Registrarse</a>
            </div>
        <?php else: ?>
            <a href="citaciones.php" class="btn btn-primary btn-lg px-5 py-3 fw-bold">Reservar Cita Previa</a>
        <?php endif; ?>
    </div>
</div>

<!-- Trust Signals Section -->
<div class="row text-center mb-5 py-4 bg-light rounded mx-1">
    <div class="col-md-3 mb-3 mb-md-0">
        <h2 class="h1 fw-bold text-primary mb-0">15+</h2>
        <p class="text-muted">Años de Experiencia</p>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <h2 class="h1 fw-bold text-primary mb-0">5000+</h2>
        <p class="text-muted">Clientes Satisfechos</p>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <h2 class="h1 fw-bold text-primary mb-0">100%</h2>
        <p class="text-muted">Garantía en Reparaciones</p>
    </div>
    <div class="col-md-3">
        <h2 class="h1 fw-bold text-primary mb-0">24h</h2>
        <p class="text-muted">Atención Urgente</p>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card h-100 feature-card shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="card-icon mb-3">
                    <i class="bi bi-shield-check text-primary"></i>
                </div>
                <h3 class="card-title h4 fw-bold">Certificaciones ITV</h3>
                <p class="card-text text-muted">Olvídate de rechazos. Preparamos tu coche al detalle para que pase la ITV a la primera.</p>
                <a href="consejo.php?id=2" class="btn btn-outline-primary mt-3 stretched-link">Saber más</a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 feature-card shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="card-icon mb-3">
                    <i class="bi bi-wrench text-primary"></i>
                </div>
                <h3 class="card-title h4 fw-bold">Mecánica Integral</h3>
                <p class="card-text text-muted">Desde cambios de aceite hasta reparaciones de motor complejas. Usamos recambios de primeras marcas.</p>
                <a href="citaciones.php" class="btn btn-primary mt-3 stretched-link">Pedir Cita</a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 feature-card shadow-sm border-0">
            <div class="card-body text-center p-4">
                <div class="card-icon mb-3">
                    <i class="bi bi-cpu text-primary"></i>
                </div>
                <h3 class="card-title h4 fw-bold">Diagnóstico Avanzado</h3>
                <p class="card-text text-muted">Detectamos averías "fantasmas" con equipos de última generación. Ahorra tiempo y dinero.</p>
            </div>
        </div>
    </div>
</div>

<section class="mb-5 bg-white p-5 rounded shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Consejos para tu Vehículo</h2>
            <p class="text-muted mb-0">Guías y recomendaciones de nuestros mecánicos expertos.</p>
        </div>
        <a href="noticias.php#consejos" class="btn btn-outline-primary btn-sm">Ver todos</a>
    </div>
    
    <div class="row g-4">
        <?php
        // Fetch latest 3 tips
        // Ensure $pdo is available. If included via header it might not be, so checking/requiring just in case.
        if (!isset($pdo)) {
            require_once 'config/database.php';
        }
        
        $consejos = getLatestTips($pdo, 3);
            
        if ($consejos):
            foreach($consejos as $consejo):
        ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm hover-card">
                    <?php if($consejo['imagen']): ?>
                        <div class="card-img-wrapper">
                            <img src="<?= htmlspecialchars($consejo['imagen']) ?>" class="card-img-custom" alt="<?= htmlspecialchars($consejo['titulo']) ?>">
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="badge bg-primary">Consejo</span>
                        </div>
                        <h5 class="card-title"><?= htmlspecialchars($consejo['titulo']) ?></h5>
                        <p class="card-text text-muted small"><?= strip_tags(substr($consejo['texto'], 0, 100)) ?>...</p>
                        <a href="consejo.php?id=<?= $consejo['idConsejo'] ?>" class="btn btn-sm btn-outline-primary stretched-link">Leer más</a>
                    </div>
                </div>
            </div>
        <?php 
                endforeach;
            else:
        ?>
            <div class="col-12 text-center text-muted">
                <p>No hay consejos disponibles en este momento.</p>
            </div>
        <?php 
            endif;
        ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
