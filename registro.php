<?php
// registro.php
require_once 'config/database.php';
require_once 'includes/header.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];
$input = [
    'nombre' => '', 'apellidos' => '', 'email' => '', 'telefono' => '', 
    'fecha_nacimiento' => '', 'calle' => '', 'codigo_postal' => '', 
    'ciudad' => '', 'provincia' => '', 'sexo' => 'Masculino', 
    'usuario' => '', 'password' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and populate input
    foreach ($input as $key => $value) {
        $input[$key] = sanitize($_POST[$key] ?? '');
    }

    // Validation
    if (empty($input['nombre'])) $errors[] = "El nombre es obligatorio.";
    if (empty($input['apellidos'])) $errors[] = "Los apellidos son obligatorios.";
    if (empty($input['email'])) $errors[] = "El email es obligatorio.";
    if (empty($input['telefono'])) $errors[] = "El teléfono es obligatorio.";
    if (empty($input['fecha_nacimiento'])) $errors[] = "La fecha de nacimiento es obligatoria.";
    // Nuevos campos de dirección
    if (empty($input['calle'])) $errors[] = "La calle es obligatoria.";
    if (empty($input['codigo_postal'])) $errors[] = "El código postal es obligatorio.";
    if (empty($input['ciudad'])) $errors[] = "La ciudad es obligatoria.";
    if (empty($input['provincia'])) $errors[] = "La provincia es obligatoria.";

    if (empty($input['usuario'])) $errors[] = "El usuario es obligatorio.";
    if (empty($input['password'])) $errors[] = "La contraseña es obligatoria.";

    // Advanced Validation
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El formato del email no es válido.";
    }
    
    // Validación de teléfono (España: 9 dígitos, empieza por 6, 7, 8, 9)
    if (!preg_match('/^[6789]\d{8}$/', $input['telefono'])) {
        $errors[] = "El teléfono debe tener un formato válido de España (9 dígitos).";
    }

    // Validación de código postal (5 dígitos)
    if (!preg_match('/^\d{5}$/', $input['codigo_postal'])) {
        $errors[] = "El código postal debe tener 5 dígitos.";
    }

    // CSRF Check
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
         $errors[] = "Error de seguridad (Token inválido). Por favor recarga la página.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Check if email or user exists
            $stmt = $pdo->prepare("SELECT idUser FROM users_data WHERE email = ?");
            $stmt->execute([$input['email']]);
            if ($stmt->fetch()) {
                throw new Exception("El email ya está registrado.");
            }

            $stmt = $pdo->prepare("SELECT idLogin FROM users_login WHERE usuario = ?");
            $stmt->execute([$input['usuario']]);
            if ($stmt->fetch()) {
                throw new Exception("El nombre de usuario ya está en uso.");
            }

            // Insert into users_data
            // Insert into users_data
            $stmt = $pdo->prepare("
                INSERT INTO users_data (nombre, apellidos, email, telefono, fecha_de_nacimiento, calle, codigo_postal, ciudad, provincia, sexo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $input['nombre'], $input['apellidos'], $input['email'], $input['telefono'], 
                $input['fecha_nacimiento'], $input['calle'], $input['codigo_postal'], 
                $input['ciudad'], $input['provincia'], $input['sexo']
            ]);
            $userId = $pdo->lastInsertId();

            // Insert into users_login
            $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users_login (idUser, usuario, password, rol)
                VALUES (?, ?, ?, 'user')
            ");
            $stmt->execute([$userId, $input['usuario'], $hashedPassword]);

            $pdo->commit();
            setFlashMessage('success', "Registro completado con éxito. Por favor inicia sesión.");
            redirect('login.php');

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white text-center py-3">
                <h2 class="h5 mb-0">Registro de Cliente</h2>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="registro.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <h5 class="mb-3 text-muted border-bottom pb-2">Datos Personales</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" value="<?= $input['nombre'] ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="apellidos" class="form-label">Apellidos *</label>
                            <input type="text" name="apellidos" id="apellidos" class="form-control" value="<?= $input['apellidos'] ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?= $input['email'] ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono *</label>
                            <input type="tel" name="telefono" id="telefono" class="form-control" value="<?= $input['telefono'] ?>" pattern="[6789][0-9]{8}" title="Debe ser un número de teléfono válido de España (9 dígitos, empezando por 6, 7, 8 o 9)" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" value="<?= $input['fecha_nacimiento'] ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="calle" class="form-label">Calle/Dirección *</label>
                        <input type="text" name="calle" id="calle" class="form-control" value="<?= $input['calle'] ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="codigo_postal" class="form-label">Código Postal *</label>
                            <input type="text" name="codigo_postal" id="codigo_postal" class="form-control" value="<?= $input['codigo_postal'] ?>" pattern="\d{5}" title="El código postal debe tener 5 dígitos" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ciudad" class="form-label">Ciudad *</label>
                            <input type="text" name="ciudad" id="ciudad" class="form-control" value="<?= $input['ciudad'] ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="provincia" class="form-label">Provincia *</label>
                            <input type="text" name="provincia" id="provincia" class="form-control" value="<?= $input['provincia'] ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="sexo" class="form-label">Sexo</label>
                            <select name="sexo" id="sexo" class="form-select">
                                <option value="Masculino" <?= $input['sexo'] == 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                                <option value="Femenino" <?= $input['sexo'] == 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                                <option value="Otro" <?= $input['sexo'] == 'Otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                    </div>

                    <h5 class="mb-3 mt-4 text-muted border-bottom pb-2">Datos de Acceso</h5>
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuario *</label>
                        <input type="text" name="usuario" id="usuario" class="form-control" value="<?= $input['usuario'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña *</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Registrarse</button>
                    </div>
                    <p class="text-center mt-3 mb-0">
                        ¿Ya tienes cuenta? <a href="login.php" class="text-decoration-none">Inicia Sesión</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
