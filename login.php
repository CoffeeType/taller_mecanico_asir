<?php
// login.php
session_start();
require_once 'config/database.php';
require_once 'includes/header.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = sanitize($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf_token)) {
        $error = "Token de seguridad inválido. Por favor recarga la página.";
    } elseif (empty($usuario) || empty($password)) {
        $error = "Todos los campos son obligatorios.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users_login WHERE usuario = ?");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login Success
                $_SESSION['user_id'] = $user['idUser'];
                $_SESSION['username'] = $user['usuario'];
                $_SESSION['user_role'] = $user['rol'];
                
                // Regenerate session ID and CSRF token for security
                session_regenerate_id(true);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                setFlashMessage('success', "Bienvenido " . $user['usuario']);
                redirect('index.php');
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }

        } catch (PDOException $e) {
            $error = "Error de conexión.";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white text-center py-3">
                <h2 class="h5 mb-0">Iniciar Sesión</h2>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" name="usuario" id="usuario" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">Entrar</button>
                    <p class="text-center mb-0">
                        ¿No tienes cuenta? <a href="registro.php" class="text-decoration-none">Regístrate aquí</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
