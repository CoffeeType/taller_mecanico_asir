<?php
// noticias-administracion.php
require_once '../config/database.php';
require_once '../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isAdmin()) {
    redirect('../index.php');
}

require_once '../includes/header.php';
require_once '../includes/news_importer.php'; // Add importer

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $titulo = sanitize($_POST['titulo'] ?? '');
    $texto = trim($_POST['texto'] ?? '');
    $imagen = sanitize($_POST['imagen'] ?? '');
    $enlace = sanitize($_POST['enlace'] ?? '');
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $idNoticia = $_POST['idNoticia'] ?? null;
    
    // Handle RSS Reload
    if ($action === 'reload_rss') {
        $result = importMotorNews($pdo);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $errors[] = $result['message'];
        }
    }
    // Handle specific CRUD actions
    elseif ($action === 'create') {
        try {
            $stmt = $pdo->prepare("INSERT INTO noticias (titulo, imagen, texto, fecha, idUser, enlace) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$titulo, $imagen, $texto, $fecha, $_SESSION['user_id'], $enlace]);
            $success = "Noticia creada.";
        } catch (PDOException $e) {
            $errors[] = "Error al crear: " . $e->getMessage();
        }
    } elseif ($action === 'update') {
        try {
            $stmt = $pdo->prepare("UPDATE noticias SET titulo=?, imagen=?, texto=?, fecha=?, enlace=? WHERE idNoticia=?");
            $stmt->execute([$titulo, $imagen, $texto, $fecha, $enlace, $idNoticia]);
            $success = "Noticia actualizada.";
        } catch (PDOException $e) {
            $errors[] = "Error al actualizar.";
        }
    } elseif ($action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM noticias WHERE idNoticia=?");
            $stmt->execute([$idNoticia]);
            $success = "Noticia eliminada.";
        } catch (PDOException $e) {
            $errors[] = "Error al eliminar.";
        }
    }
}

$noticias = $pdo->query("SELECT * FROM noticias ORDER BY fecha DESC")->fetchAll();
?>

<h1 class="mb-4 text-center"><i class="bi bi-newspaper me-2"></i>Administrar Noticias</h1>

<!-- System Status Diagnostics -->
<?php
$dbStatus = isset($pdo) ? '<span class="badge bg-success">DB Conectada</span>' : '<span class="badge bg-danger">DB Error</span>';
$adminUserCheck = $pdo->query("SELECT COUNT(*) FROM users_login WHERE rol='admin'")->fetchColumn();
$adminStatus = $adminUserCheck > 0 ? '<span class="badge bg-success">Admin OK</span>' : '<span class="badge bg-danger">Sin Admin</span>';
$extCurl = extension_loaded('curl') ? '<span class="badge bg-success">cURL OK</span>' : '<span class="badge bg-warning text-dark">cURL Faltante</span>';
?>
<div class="text-center mb-4">
    <small class="text-muted">Estado del Sistema: <?= $dbStatus ?> | <?= $adminStatus ?> | <?= $extCurl ?></small>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="row">
    <!-- Editor Form -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm sticky-top" style="top: 2rem; z-index: 1;">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editor</h5>
            </div>
            <div class="card-body">
                <form action="noticias.php" method="POST">
                    <input type="hidden" name="action" value="create" id="n_action">
                    <input type="hidden" name="idNoticia" id="n_id">

                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" id="n_titulo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL Imagen</label>
                        <input type="text" name="imagen" id="n_imagen" class="form-control" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Enlace Fuente</label>
                        <input type="text" name="enlace" id="n_enlace" class="form-control" placeholder="https://motor.es/...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" id="n_fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Texto</label>
                        <textarea name="texto" id="n_texto" class="form-control" rows="6" required></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" id="n_btn" class="btn btn-primary">Publicar Noticia</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">Cancelar / Limpiar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- News List -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-stars me-2"></i>Publicaciones</h5>
                <form action="noticias.php" method="POST" onsubmit="return confirm('¿Estás seguro? Esto borrará todas las noticias actuales y cargará nuevas desde Motor.es.');">
                    <input type="hidden" name="action" value="reload_rss">
                    <button type="submit" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-arrow-repeat me-1"></i> Recargar Noticias
                    </button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 100px;">Imagen</th>
                            <th>Título / Fecha</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($noticias as $n): ?>
                        <tr>
                            <td>
                                <?php if ($n['imagen']): ?>
                                    <?php 
                                        $imgSrc = htmlspecialchars($n['imagen']);
                                        if (!preg_match('/^https?:\/\//', $imgSrc)) {
                                            $imgSrc = '../' . $imgSrc;
                                        }
                                    ?>
                                    <img src="<?= $imgSrc ?>" class="rounded" style="width: 80px; height: 60px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted border" style="width: 80px; height: 60px;">
                                        <i class="bi bi-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <h6 class="mb-0"><?= htmlspecialchars($n['titulo']) ?></h6>
                                <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= $n['fecha'] ?></small>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-outline-primary btn-sm me-1" onclick='editNews(<?= json_encode($n) ?>)' title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="noticias.php" method="POST" class="d-inline" onsubmit="return confirm('¿Borrar esta noticia permanentemente?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="idNoticia" value="<?= $n['idNoticia'] ?>">
                                    <button class="btn btn-outline-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editNews(n) {
    document.getElementById('n_action').value = 'update';
    document.getElementById('n_id').value = n.idNoticia;
    document.getElementById('n_titulo').value = n.titulo;
    document.getElementById('n_imagen').value = n.imagen;
    document.getElementById('n_enlace').value = n.enlace || '';
    document.getElementById('n_fecha').value = n.fecha;
    document.getElementById('n_texto').value = n.texto;
    
    const btn = document.getElementById('n_btn');
    btn.innerText = 'Actualizar Noticia';
    btn.classList.replace('btn-primary', 'btn-warning');
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetForm() {
    document.getElementById('n_action').value = 'create';
    document.getElementById('n_id').value = '';
    document.querySelector('form').reset();
    
    const btn = document.getElementById('n_btn');
    btn.innerText = 'Publicar Noticia';
    btn.classList.replace('btn-warning', 'btn-primary');
}
</script>

<?php require_once '../includes/footer.php'; ?>
