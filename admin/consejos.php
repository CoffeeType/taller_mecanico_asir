<?php
// consejos-administracion.php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $titulo = sanitize($_POST['titulo'] ?? '');
    $texto = trim($_POST['texto'] ?? ''); 
    $imagen = sanitize($_POST['imagen'] ?? '');
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $idConsejo = $_POST['idConsejo'] ?? null;

    if ($action === 'seed_councils') {
        try {
            $sql = file_get_contents(__DIR__ . '/../database/seed_consejos.sql');
            $pdo->exec($sql);
            $success = "Consejos predeterminados cargados.";
        } catch (PDOException $e) {
            $errors[] = "Error al sembrar: " . $e->getMessage();
        }
    } elseif ($action === 'create') {
        try {
            $stmt = $pdo->prepare("INSERT INTO consejos (titulo, imagen, texto, fecha, idUser) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$titulo, $imagen, $texto, $fecha, $_SESSION['user_id']]);
            $success = "Consejo creado.";
        } catch (PDOException $e) {
            $errors[] = "Error al crear: " . $e->getMessage();
        }
    } elseif ($action === 'update') {
        try {
            $stmt = $pdo->prepare("UPDATE consejos SET titulo=?, imagen=?, texto=?, fecha=? WHERE idConsejo=?");
            $stmt->execute([$titulo, $imagen, $texto, $fecha, $idConsejo]);
            $success = "Consejo actualizado.";
        } catch (PDOException $e) {
            $errors[] = "Error al actualizar: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM consejos WHERE idConsejo=?");
            $stmt->execute([$idConsejo]);
            $success = "Consejo eliminado.";
        } catch (PDOException $e) {
            $errors[] = "Error al eliminar: " . $e->getMessage();
        }
    }
}

$consejos = $pdo->query("SELECT * FROM consejos ORDER BY fecha DESC")->fetchAll();
?>

<h1 class="mb-4 text-center"><i class="bi bi-tools me-2"></i>Administrar Consejos</h1>

<!-- System Status Diagnostics -->
<?php
$dbStatus = isset($pdo) ? '<span class="badge bg-success">DB Conectada</span>' : '<span class="badge bg-danger">DB Error</span>';
$adminUserCheck = $pdo->query("SELECT COUNT(*) FROM users_login WHERE rol='admin'")->fetchColumn();
$adminStatus = $adminUserCheck > 0 ? '<span class="badge bg-success">Admin OK</span>' : '<span class="badge bg-danger">Sin Admin</span>';
?>
<div class="text-center mb-4">
    <small class="text-muted">Estado del Sistema: <?= $dbStatus ?> | <?= $adminStatus ?></small>
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
                <form action="consejos.php" method="POST">
                    <input type="hidden" name="action" value="create" id="c_action">
                    <input type="hidden" name="idConsejo" id="c_id">

                    <div class="mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" id="c_titulo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL Imagen</label>
                        <input type="text" name="imagen" id="c_imagen" class="form-control" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" id="c_fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Texto (HTML permitido)</label>
                        <textarea name="texto" id="c_texto" class="form-control" rows="10" required></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" id="c_btn" class="btn btn-primary">Publicar Consejo</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">Cancelar / Limpiar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tips List -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Lista de Consejos</h5>
                <form action="consejos.php" method="POST" onsubmit="return confirm('¿Cargar consejos recomendados por defecto?');">
                    <input type="hidden" name="action" value="seed_councils">
                    <button type="submit" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-magic me-1"></i> Cargar Predeterminados
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
                        <?php foreach ($consejos as $c): ?>
                        <tr>
                            <td>
                                <?php if ($c['imagen']): ?>
                                    <?php 
                                        $imgSrc = htmlspecialchars($c['imagen']);
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
                                <h6 class="mb-0"><?= htmlspecialchars($c['titulo']) ?></h6>
                                <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= $c['fecha'] ?></small>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-outline-primary btn-sm me-1" onclick='editTip(<?= json_encode($c) ?>)' title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="consejos.php" method="POST" class="d-inline" onsubmit="return confirm('¿Borrar este consejo permanentemente?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="idConsejo" value="<?= $c['idConsejo'] ?>">
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
function editTip(c) {
    document.getElementById('c_action').value = 'update';
    document.getElementById('c_id').value = c.idConsejo;
    document.getElementById('c_titulo').value = c.titulo;
    document.getElementById('c_imagen').value = c.imagen;
    document.getElementById('c_fecha').value = c.fecha;
    document.getElementById('c_texto').value = c.texto;
    
    const btn = document.getElementById('c_btn');
    btn.innerText = 'Actualizar Consejo';
    btn.classList.replace('btn-primary', 'btn-warning');
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetForm() {
    document.getElementById('c_action').value = 'create';
    document.getElementById('c_id').value = '';
    document.querySelector('form').reset();
    
    const btn = document.getElementById('c_btn');
    btn.innerText = 'Publicar Consejo';
    btn.classList.replace('btn-warning', 'btn-primary');
}
</script>

<?php require_once '../includes/footer.php'; ?>
