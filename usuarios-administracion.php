<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
iniciarSesion();

// Verificar que sea administrador
if (!verificarRol('admin')) {
    header("Location: index.php");
    exit();
}

$tituloPagina = "Administración de Usuarios";
require_once __DIR__ . '/includes/header.php';

$errores = [];
$mensajeExito = '';
$idUsuarioEditar = null;
$usuarioEditar = null;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = sanitizarDatos($_POST);
    
    // Crear nuevo usuario
    if (isset($datos['crear_usuario'])) {
        $camposObligatorios = ['nombre', 'apellidos', 'email', 'telefono', 'fecha_de_nacimiento', 'sexo', 'usuario', 'password', 'rol'];
        $errores = validarCamposObligatorios($datos, $camposObligatorios);
        
        // Validar email
        if (!empty($datos['email']) && !validarEmail($datos['email'])) {
            $errores[] = "El email no es válido";
        }
        
        // Validar email único
        if (empty($errores) && !empty($datos['email'])) {
            try {
                $stmt = $pdo->prepare("SELECT idUser FROM users_data WHERE email = ?");
                $stmt->execute([$datos['email']]);
                if ($stmt->fetch()) {
                    $errores[] = "El email ya está registrado";
                }
            } catch (PDOException $e) {
                $errores[] = "Error al verificar el email";
            }
        }
        
        // Validar usuario único
        if (empty($errores) && !empty($datos['usuario'])) {
            try {
                $stmt = $pdo->prepare("SELECT idLogin FROM users_login WHERE usuario = ?");
                $stmt->execute([$datos['usuario']]);
                if ($stmt->fetch()) {
                    $errores[] = "El usuario ya está registrado";
                }
            } catch (PDOException $e) {
                $errores[] = "Error al verificar el usuario";
            }
        }
        
        if (empty($errores)) {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    INSERT INTO users_data (nombre, apellidos, email, telefono, fecha_de_nacimiento, direccion, sexo)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $datos['nombre'],
                    $datos['apellidos'],
                    $datos['email'],
                    $datos['telefono'],
                    $datos['fecha_de_nacimiento'],
                    $datos['direccion'] ?? null,
                    $datos['sexo']
                ]);
                
                $idUser = $pdo->lastInsertId();
                $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users_login (idUser, usuario, password, rol)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$idUser, $datos['usuario'], $passwordHash, $datos['rol']]);
                
                $pdo->commit();
                $mensajeExito = "Usuario creado correctamente";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errores[] = "Error al crear el usuario";
            }
        }
    }
    
    // Actualizar usuario
    if (isset($datos['actualizar_usuario'])) {
        $idUsuario = intval($datos['idUser']);
        $camposObligatorios = ['nombre', 'apellidos', 'email', 'telefono', 'fecha_de_nacimiento', 'sexo', 'usuario', 'rol'];
        $errores = validarCamposObligatorios($datos, $camposObligatorios);
        
        // Validar email único (excepto el del usuario actual)
        if (empty($errores) && !empty($datos['email'])) {
            try {
                $stmt = $pdo->prepare("SELECT idUser FROM users_data WHERE email = ? AND idUser != ?");
                $stmt->execute([$datos['email'], $idUsuario]);
                if ($stmt->fetch()) {
                    $errores[] = "El email ya está registrado por otro usuario";
                }
            } catch (PDOException $e) {
                $errores[] = "Error al verificar el email";
            }
        }
        
        // Validar usuario único (excepto el del usuario actual)
        if (empty($errores) && !empty($datos['usuario'])) {
            try {
                $stmt = $pdo->prepare("SELECT idLogin FROM users_login WHERE usuario = ? AND idUser != ?");
                $stmt->execute([$datos['usuario'], $idUsuario]);
                if ($stmt->fetch()) {
                    $errores[] = "El usuario ya está registrado por otro usuario";
                }
            } catch (PDOException $e) {
                $errores[] = "Error al verificar el usuario";
            }
        }
        
        if (empty($errores)) {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE users_data 
                    SET nombre = ?, apellidos = ?, email = ?, telefono = ?, 
                        fecha_de_nacimiento = ?, direccion = ?, sexo = ?
                    WHERE idUser = ?
                ");
                $stmt->execute([
                    $datos['nombre'],
                    $datos['apellidos'],
                    $datos['email'],
                    $datos['telefono'],
                    $datos['fecha_de_nacimiento'],
                    $datos['direccion'] ?? null,
                    $datos['sexo'],
                    $idUsuario
                ]);
                
                $stmt = $pdo->prepare("UPDATE users_login SET usuario = ?, rol = ? WHERE idUser = ?");
                $stmt->execute([$datos['usuario'], $datos['rol'], $idUsuario]);
                
                // Si se proporciona nueva contraseña, actualizarla
                if (!empty($datos['nueva_password'])) {
                    $passwordHash = password_hash($datos['nueva_password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users_login SET password = ? WHERE idUser = ?");
                    $stmt->execute([$passwordHash, $idUsuario]);
                }
                
                $pdo->commit();
                $mensajeExito = "Usuario actualizado correctamente";
                $idUsuarioEditar = null;
                $usuarioEditar = null;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errores[] = "Error al actualizar el usuario";
            }
        }
    }
}

// Eliminar usuario
if (isset($_GET['eliminar'])) {
    $idUsuario = intval($_GET['eliminar']);
    
    // No permitir eliminar al propio usuario
    if ($idUsuario == $_SESSION['idUser']) {
        $errores[] = "No puedes eliminar tu propio usuario";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Eliminar citas relacionadas primero (por CASCADE se eliminan automáticamente, pero lo hacemos explícito)
            $stmt = $pdo->prepare("DELETE FROM citas WHERE idUser = ?");
            $stmt->execute([$idUsuario]);
            
            // Eliminar noticias relacionadas
            $stmt = $pdo->prepare("DELETE FROM noticias WHERE idUser = ?");
            $stmt->execute([$idUsuario]);
            
            // Eliminar login
            $stmt = $pdo->prepare("DELETE FROM users_login WHERE idUser = ?");
            $stmt->execute([$idUsuario]);
            
            // Eliminar datos personales
            $stmt = $pdo->prepare("DELETE FROM users_data WHERE idUser = ?");
            $stmt->execute([$idUsuario]);
            
            $pdo->commit();
            $mensajeExito = "Usuario eliminado correctamente";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errores[] = "Error al eliminar el usuario";
        }
    }
}

// Obtener usuario para editar
if (isset($_GET['editar'])) {
    $idUsuarioEditar = intval($_GET['editar']);
    try {
        $stmt = $pdo->prepare("
            SELECT ud.*, ul.usuario, ul.rol 
            FROM users_data ud
            INNER JOIN users_login ul ON ud.idUser = ul.idUser
            WHERE ud.idUser = ?
        ");
        $stmt->execute([$idUsuarioEditar]);
        $usuarioEditar = $stmt->fetch();
        
        if (!$usuarioEditar) {
            $errores[] = "Usuario no encontrado";
            $idUsuarioEditar = null;
        }
    } catch (PDOException $e) {
        $errores[] = "Error al obtener el usuario";
    }
}

// Obtener todos los usuarios
try {
    $stmt = $pdo->query("
        SELECT ud.*, ul.usuario, ul.rol 
        FROM users_data ud
        INNER JOIN users_login ul ON ud.idUser = ul.idUser
        ORDER BY ud.idUser ASC
    ");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $usuarios = [];
    $errores[] = "Error al cargar los usuarios";
}
?>

<h1>Administración de Usuarios</h1>

<?php if (!empty($errores)): ?>
    <div class="mensaje error">
        <ul>
            <?php foreach ($errores as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($mensajeExito): ?>
    <div class="mensaje exito">
        <?php echo htmlspecialchars($mensajeExito); ?>
    </div>
<?php endif; ?>

<section class="admin-formulario">
    <h2><?php echo $idUsuarioEditar ? 'Editar Usuario' : 'Crear Nuevo Usuario'; ?></h2>
    <form method="POST" action="" class="form-usuario-admin">
        <?php if ($idUsuarioEditar): ?>
            <input type="hidden" name="actualizar_usuario" value="1">
            <input type="hidden" name="idUser" value="<?php echo $idUsuarioEditar; ?>">
        <?php else: ?>
            <input type="hidden" name="crear_usuario" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" required
                       value="<?php echo $usuarioEditar ? htmlspecialchars($usuarioEditar['nombre']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="apellidos">Apellidos *</label>
                <input type="text" id="apellidos" name="apellidos" required
                       value="<?php echo $usuarioEditar ? htmlspecialchars($usuarioEditar['apellidos']) : ''; ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo $usuarioEditar ? htmlspecialchars($usuarioEditar['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="telefono">Teléfono *</label>
                <input type="text" id="telefono" name="telefono" required
                       value="<?php echo $usuarioEditar ? htmlspecialchars($usuarioEditar['telefono']) : ''; ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="fecha_de_nacimiento">Fecha de Nacimiento *</label>
                <input type="date" id="fecha_de_nacimiento" name="fecha_de_nacimiento" required
                       value="<?php echo $usuarioEditar ? htmlspecialchars($usuarioEditar['fecha_de_nacimiento']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="sexo">Sexo *</label>
                <select id="sexo" name="sexo" required>
                    <option value="">Seleccione...</option>
                    <option value="Masculino" <?php echo ($usuarioEditar && $usuarioEditar['sexo'] === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                    <option value="Femenino" <?php echo ($usuarioEditar && $usuarioEditar['sexo'] === 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                    <option value="Otro" <?php echo ($usuarioEditar && $usuarioEditar['sexo'] === 'Otro') ? 'selected' : ''; ?>>Otro</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="direccion">Dirección</label>
            <textarea id="direccion" name="direccion" rows="2"><?php echo $usuarioEditar ? htmlspecialchars($usuarioEditar['direccion'] ?? '') : ''; ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="usuario">Usuario *</label>
                <input type="text" id="usuario" name="usuario" required
                       value="<?php echo $usuarioEditar ? htmlspecialchars($usuarioEditar['usuario']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="rol">Rol *</label>
                <select id="rol" name="rol" required>
                    <option value="user" <?php echo ($usuarioEditar && $usuarioEditar['rol'] === 'user') ? 'selected' : ''; ?>>Usuario</option>
                    <option value="admin" <?php echo ($usuarioEditar && $usuarioEditar['rol'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="nueva_password"><?php echo $idUsuarioEditar ? 'Nueva Contraseña (dejar vacío para mantener la actual)' : 'Contraseña *'; ?></label>
            <input type="password" id="nueva_password" name="nueva_password" <?php echo $idUsuarioEditar ? '' : 'required'; ?>>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <?php echo $idUsuarioEditar ? 'Actualizar Usuario' : 'Crear Usuario'; ?>
            </button>
            <?php if ($idUsuarioEditar): ?>
                <a href="usuarios-administracion.php" class="btn btn-secondary">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="admin-lista">
    <h2>Lista de Usuarios</h2>
    <?php if (empty($usuarios)): ?>
        <p>No hay usuarios registrados.</p>
    <?php else: ?>
        <table class="tabla-admin">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($usuario['idUser']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['rol']); ?></td>
                        <td>
                            <a href="?editar=<?php echo $usuario['idUser']; ?>" class="btn btn-small">Editar</a>
                            <?php if ($usuario['idUser'] != $_SESSION['idUser']): ?>
                                <a href="?eliminar=<?php echo $usuario['idUser']; ?>" class="btn btn-small btn-danger"
                                   onclick="return confirm('¿Estás seguro de eliminar este usuario?');">Eliminar</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

