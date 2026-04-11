<?php
// perfil.php
require_once 'config/database.php';
require_once 'includes/header.php';

if (isLoggedIn()) {
    redirect('login.php');
}

$idUser = $_SESSION['user_id'];
$errors = [];
$success = '';

// Fetch current data
try {
    $stmt = $pdo->prepare("
        SELECT d.*, l.usuario 
        FROM users_data d 
        JOIN users_login l ON d.idUser = l.idUser 
        WHERE d.idUser = ?
    ");
    $stmt->execute([$idUser]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    die("Error al cargar datos del usuario.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $nombre = sanitize($_POST['nombre']);
    $apellidos = sanitize($_POST['apellidos']);
    $email = sanitize($_POST['email']);
    $telefono = sanitize($_POST['telefono']);
    $fecha_nacimiento = sanitize($_POST['fecha_nacimiento']);
    $direccion = sanitize($_POST['direccion']);
    $sexo = sanitize($_POST['sexo']);
    $new_password = $_POST['new_password'];

    // Validations (simplified)
    if (empty($nombre) || empty($apellidos) || empty($email)) {
        $errors[] = "Campos obligatorios faltantes.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update user data
            $stmt = $pdo->prepare("
                UPDATE users_data 
                SET nombre = ?, apellidos = ?, email = ?, telefono = ?, fecha_de_nacimiento = ?, direccion = ?, sexo = ? 
                WHERE idUser = ?
            ");
            $stmt->execute([$nombre, $apellidos, $email, $telefono, $fecha_nacimiento, $direccion, $sexo, $idUser]);

            // Update password if provided
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users_login SET password = ? WHERE idUser = ?");
                $stmt->execute([$hashed, $idUser]);
            }

            $pdo->commit();
            $success = "Perfil actualizado correctamente.";
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT d.*, l.usuario FROM users_data d JOIN users_login l ON d.idUser = l.idUser WHERE d.idUser = ?");
            $stmt->execute([$idUser]);
            $user = $stmt->fetch();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Error al actualizar: " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white py-3">
                <h2 class="h5 mb-0"><i class="bi bi-person-circle me-2"></i>Mi Perfil</h2>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= implode('<br>', $errors) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="perfil.php" method="POST">
                    <div class="row mb-3">
                        <label class="col-sm-3 col-form-label fw-bold">Usuario</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control-plaintext" value="<?= htmlspecialchars($user['usuario']) ?>" readonly>
                        </div>
                    </div>

                    <h5 class="mb-3 text-muted border-bottom pb-2">Datos Personales</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($user['nombre']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="apellidos" class="form-label">Apellidos</label>
                            <input type="text" name="apellidos" id="apellidos" class="form-control" value="<?= htmlspecialchars($user['apellidos']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" name="telefono" id="telefono" class="form-control" value="<?= htmlspecialchars($user['telefono']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" value="<?= htmlspecialchars($user['fecha_de_nacimiento']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="sexo" class="form-label">Sexo</label>
                            <select name="sexo" id="sexo" class="form-select">
                                <option value="Masculino" <?= $user['sexo'] == 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                                <option value="Femenino" <?= $user['sexo'] == 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                                <option value="Otro" <?= $user['sexo'] == 'Otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" name="direccion" id="direccion" class="form-control" value="<?= htmlspecialchars($user['direccion']) ?>">
                        </div>
                    </div>

                    <h5 class="mb-3 mt-4 text-muted border-bottom pb-2">Seguridad</h5>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva Contraseña (Dejar en blanco para cancelar)</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="********">
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary px-4">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
