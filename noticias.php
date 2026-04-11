<?php
// noticias.php - UPDATED to use database again
require_once 'config/database.php';
require_once 'includes/header.php';

try {
    // Fetch news with author name from DATABASE
    $stmt = $pdo->query("
        SELECT n.idNoticia, n.titulo, n.texto, n.fecha, n.imagen, n.enlace, u.nombre, u.apellidos 
        FROM noticias n 
        JOIN users_data u ON n.idUser = u.idUser 
        ORDER BY n.fecha DESC
        LIMIT 9
    ");
    $noticias = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error al cargar las noticias.";
}
?>

<h1 class="mb-4 text-center">Centro de Recursos del Taller</h1>
<p class="text-center text-muted mb-4">Mantente informado sobre el mundo del motor y aprende a cuidar tu vehículo</p>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs nav-fill mb-4" id="newsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="noticias-tab" data-bs-toggle="tab" data-bs-target="#noticias" type="button" role="tab">
            <i class="bi bi-newspaper me-2"></i>Noticias del Motor
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="consejos-tab" data-bs-toggle="tab" data-bs-target="#consejos" type="button" role="tab">
            <i class="bi bi-tools me-2"></i>Consejos de Mantenimiento
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="newsTabsContent">
    <!-- NOTICIAS TAB (Database) -->
    <div class="tab-pane fade show active" id="noticias" role="tabpanel">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <?php if (empty($noticias)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle me-2"></i>
                No hay noticias publicadas. <a href="populate_motor_news.php" class="alert-link">Importar noticias de motor.es</a>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($noticias as $noticia): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm border-0 hover-card">
                            <?php if ($noticia['imagen']): ?>
                                <img src="<?= htmlspecialchars($noticia['imagen']) ?>" class="card-img-top" alt="<?= htmlspecialchars($noticia['titulo']) ?>" style="height: 200px; object-fit: cover;" loading="lazy">
                            <?php else: ?>
                                <div class="card-img-top bg-gradient d-flex align-items-center justify-content-center" style="height: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="bi bi-car-front-fill text-white" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <small class="text-muted d-block mb-2">
                                    <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($noticia['fecha'])) ?>
                                    <span class="ms-2"><i class="bi bi-person me-1"></i><?= htmlspecialchars($noticia['nombre']) ?></span>
                                </small>
                                <h5 class="card-title"><?= htmlspecialchars($noticia['titulo']) ?></h5>
                                <p class="card-text text-muted"><?= nl2br(htmlspecialchars(substr($noticia['texto'], 0, 120))) ?>...</p>
                                
                                <?php if (!empty($noticia['enlace'])): ?>
                                    <div class="mt-3">
                                        <a href="<?= htmlspecialchars($noticia['enlace']) ?>" target="_blank" class="stretched-link text-decoration-none">
                                            Leer noticia completa <i class="bi bi-box-arrow-up-right small"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- CONSEJOS TAB (Database Tips) -->
    <div class="tab-pane fade" id="consejos" role="tabpanel">
        <div class="row g-4">
        <?php
        try {
            $stmtC = $pdo->query("SELECT * FROM consejos ORDER BY fecha DESC");
            $consejos = $stmtC->fetchAll();

            if ($consejos):
                foreach($consejos as $consejo):
        ?>
            <!-- Tip Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 hover-card">
                    <?php if($consejo['imagen']): ?>
                        <div class="card-img-top-wrapper" style="height: 200px; overflow: hidden;">
                             <img src="<?= htmlspecialchars($consejo['imagen']) ?>" class="card-img-top h-100 w-100" style="object-fit: cover;" alt="<?= htmlspecialchars($consejo['titulo']) ?>" loading="lazy">
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <span class="badge bg-primary mb-2">Mantenimiento</span>
                        <h5 class="card-title"><?= htmlspecialchars($consejo['titulo']) ?></h5>
                        <p class="card-text text-muted small"><?= strip_tags(substr($consejo['texto'], 0, 120)) ?>...</p>
                        <a href="consejo.php?id=<?= $consejo['idConsejo'] ?>" class="btn btn-sm btn-outline-primary stretched-link">Leer más</a>
                    </div>
                </div>
            </div>
        <?php 
                endforeach;
            else:
        ?>
            <div class="col-12 text-center text-muted">
                <i class="bi bi-info-circle me-2 mb-3 d-block" style="font-size: 2rem;"></i>
                <p>No hay consejos de mantenimiento disponibles por el momento.</p>
            </div>
        <?php 
            endif;
        } catch (PDOException $e) {
             echo '<div class="alert alert-danger">Error al cargar consejos: ' . $e->getMessage() . '</div>';
        }
        ?>

            <!-- CTA Card -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-primary">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-5">
                        <i class="bi bi-calendar-check text-primary mb-3" style="font-size: 3rem;"></i>
                        <h5 class="card-title">¿Necesitas ayuda?</h5>
                        <p class="card-text text-muted">Reserva una cita y nuestros mecánicos revisarán tu vehículo completamente.</p>
                        <a href="citaciones.php" class="btn btn-primary mt-3">Pedir Cita Ahora</a>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>



<?php require_once 'includes/footer.php'; ?>
