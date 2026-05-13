<?php
// consejo.php
require_once 'config/database.php';
require_once 'includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$idConsejo = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($idConsejo <= 0) {
    redirect('noticias.php');
}

try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.nombre, u.apellidos 
        FROM consejos c 
        JOIN users_data u ON c.idUser = u.idUser 
        WHERE c.idConsejo = ?
    ");
    $stmt->execute([$idConsejo]);
    $consejo = $stmt->fetch();
    if (!$consejo) {
        redirect('noticias.php');
    }
} catch (PDOException $e) {
    require_once 'includes/header.php';
    echo "<div class='container py-4'><div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    require_once 'includes/footer.php';
    exit;
}

require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="noticias.php">Recursos</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($consejo['titulo']) ?></li>
                </ol>
            </nav>

            <article class="blog-post">
                <h1 class="display-4 fw-bold mb-3"><?= htmlspecialchars($consejo['titulo']) ?></h1>
                
                <div class="d-flex align-items-center mb-4 text-muted">
                    <div class="me-3">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?= date('d/m/Y', strtotime($consejo['fecha'])) ?>
                    </div>
                    <div>
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($consejo['nombre'] . ' ' . $consejo['apellidos']) ?>
                    </div>
                </div>

                <?php if ($consejo['imagen']): ?>
                    <img src="<?= htmlspecialchars($consejo['imagen']) ?>" class="img-fluid rounded shadow-sm w-100 mb-5" alt="<?= htmlspecialchars($consejo['titulo']) ?>" loading="lazy">
                <?php endif; ?>

                <div class="blog-content fs-5 lh-lg">
                    <?= $consejo['texto'] // HTML de confianza desde administración ?>
                </div>
            </article>

            <div class="mt-5 pt-4 border-top">
                <h3 class="h4 mb-4">Otros Consejos</h3>
                <div class="row g-4">
                    <?php
                    // Consejos recientes excluyendo el actual
                    $stmtRecent = $pdo->prepare("SELECT idConsejo, titulo, imagen FROM consejos WHERE idConsejo != ? ORDER BY fecha DESC LIMIT 2");
                    $stmtRecent->execute([$idConsejo]);
                    while($recent = $stmtRecent->fetch()):
                    ?>
                        <div class="col-md-6">
                            <div class="card h-100 shadow-sm hover-card border-0">
                                <?php if($recent['imagen']): ?>
                                    <img src="<?= htmlspecialchars($recent['imagen']) ?>" class="card-img-top" alt="<?= htmlspecialchars($recent['titulo']) ?>" style="height: 150px; object-fit: cover;" loading="lazy">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title h6">
                                        <a href="consejo.php?id=<?= $recent['idConsejo'] ?>" class="text-decoration-none text-dark stretched-link">
                                            <?= htmlspecialchars($recent['titulo']) ?>
                                        </a>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="text-center mt-4">
                     <a href="noticias.php#consejos" class="btn btn-outline-primary">Ver todos los consejos</a>
                </div>
            </div>

        </div>
    </div>
</div>



<?php require_once 'includes/footer.php'; ?>
