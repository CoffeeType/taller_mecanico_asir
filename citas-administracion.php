<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
iniciarSesion();

// Verificar que sea administrador
if (!verificarRol('admin')) {
    header("Location: index.php");
    exit();
}

$tituloPagina = "Administración de Citas";
require_once __DIR__ . '/includes/header.php';

$errores = [];
$mensajeExito = '';
$idCitaEditar = null;
$citaEditar = null;
$usuarioSeleccionado = isset($_GET['usuario']) ? intval($_GET['usuario']) : (isset($_POST['idUser']) ? intval($_POST['idUser']) : null);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = sanitizarDatos($_POST);
    
    // Crear nueva cita
    if (isset($datos['crear_cita'])) {
        $camposObligatorios = ['idUser', 'fecha_cita', 'motivo_cita'];
        $errores = validarCamposObligatorios($datos, $camposObligatorios);
        
        if (empty($errores)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO citas (idUser, fecha_cita, motivo_cita) VALUES (?, ?, ?)");
                $stmt->execute([$datos['idUser'], $datos['fecha_cita'], $datos['motivo_cita']]);
                $mensajeExito = "Cita creada correctamente";
                $usuarioSeleccionado = $datos['idUser'];
            } catch (PDOException $e) {
                $errores[] = "Error al crear la cita";
            }
        }
    }
    
    // Actualizar cita
    if (isset($datos['actualizar_cita'])) {
        $idCita = intval($datos['idCita']);
        $camposObligatorios = ['fecha_cita', 'motivo_cita'];
        $errores = validarCamposObligatorios($datos, $camposObligatorios);
        
        if (empty($errores)) {
            try {
                // Obtener idUser de la cita
                $stmt = $pdo->prepare("SELECT idUser FROM citas WHERE idCita = ?");
                $stmt->execute([$idCita]);
                $cita = $stmt->fetch();
                
                if ($cita) {
                    $stmt = $pdo->prepare("UPDATE citas SET fecha_cita = ?, motivo_cita = ? WHERE idCita = ?");
                    $stmt->execute([$datos['fecha_cita'], $datos['motivo_cita'], $idCita]);
                    $mensajeExito = "Cita actualizada correctamente";
                    $usuarioSeleccionado = $cita['idUser'];
                    $idCitaEditar = null;
                    $citaEditar = null;
                } else {
                    $errores[] = "Cita no encontrada";
                }
            } catch (PDOException $e) {
                $errores[] = "Error al actualizar la cita";
            }
        }
    }
}

// Eliminar cita
if (isset($_GET['eliminar'])) {
    $idCita = intval($_GET['eliminar']);
    
    try {
        $stmt = $pdo->prepare("SELECT idUser FROM citas WHERE idCita = ?");
        $stmt->execute([$idCita]);
        $cita = $stmt->fetch();
        
        if ($cita) {
            $stmt = $pdo->prepare("DELETE FROM citas WHERE idCita = ?");
            $stmt->execute([$idCita]);
            $mensajeExito = "Cita eliminada correctamente";
            $usuarioSeleccionado = $cita['idUser'];
        } else {
            $errores[] = "Cita no encontrada";
        }
    } catch (PDOException $e) {
        $errores[] = "Error al eliminar la cita";
    }
}

// Obtener cita para editar
if (isset($_GET['editar'])) {
    $idCitaEditar = intval($_GET['editar']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM citas WHERE idCita = ?");
        $stmt->execute([$idCitaEditar]);
        $citaEditar = $stmt->fetch();
        
        if ($citaEditar) {
            $usuarioSeleccionado = $citaEditar['idUser'];
        } else {
            $errores[] = "Cita no encontrada";
            $idCitaEditar = null;
        }
    } catch (PDOException $e) {
        $errores[] = "Error al obtener la cita";
    }
}

// Obtener lista de usuarios
try {
    $stmt = $pdo->query("SELECT idUser, nombre, apellidos FROM users_data ORDER BY nombre ASC");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $usuarios = [];
}

// Obtener citas del usuario seleccionado
$citas = [];
if ($usuarioSeleccionado) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM citas WHERE idUser = ? ORDER BY fecha_cita ASC");
        $stmt->execute([$usuarioSeleccionado]);
        $citas = $stmt->fetchAll();
    } catch (PDOException $e) {
        $errores[] = "Error al cargar las citas";
    }
}
?>

<h1>Administración de Citas</h1>

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

<section class="admin-selector">
    <h2>Seleccionar Usuario</h2>
    <form method="GET" action="" class="form-selector">
        <div class="form-group">
            <label for="usuario">Usuario</label>
            <select id="usuario" name="usuario" onchange="this.form.submit()">
                <option value="">Seleccione un usuario...</option>
                <?php foreach ($usuarios as $usuario): ?>
                    <option value="<?php echo $usuario['idUser']; ?>" 
                            <?php echo ($usuarioSeleccionado == $usuario['idUser']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellidos']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</section>

<?php if ($usuarioSeleccionado): ?>
    <?php
    // Obtener nombre del usuario seleccionado
    $stmt = $pdo->prepare("SELECT nombre, apellidos FROM users_data WHERE idUser = ?");
    $stmt->execute([$usuarioSeleccionado]);
    $usuarioInfo = $stmt->fetch();
    ?>
    
    <section class="admin-formulario">
        <h2><?php echo $idCitaEditar ? 'Editar Cita' : 'Crear Nueva Cita'; ?> para <?php echo htmlspecialchars($usuarioInfo['nombre'] . ' ' . $usuarioInfo['apellidos']); ?></h2>
        <form method="POST" action="" class="form-cita-admin">
            <input type="hidden" name="idUser" value="<?php echo $usuarioSeleccionado; ?>">
            <?php if ($idCitaEditar): ?>
                <input type="hidden" name="actualizar_cita" value="1">
                <input type="hidden" name="idCita" value="<?php echo $idCitaEditar; ?>">
            <?php else: ?>
                <input type="hidden" name="crear_cita" value="1">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="fecha_cita">Fecha de la Cita *</label>
                <input type="date" id="fecha_cita" name="fecha_cita" required
                       value="<?php echo $citaEditar ? htmlspecialchars($citaEditar['fecha_cita']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="motivo_cita">Motivo de la Cita *</label>
                <textarea id="motivo_cita" name="motivo_cita" rows="4" required><?php echo $citaEditar ? htmlspecialchars($citaEditar['motivo_cita']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $idCitaEditar ? 'Actualizar Cita' : 'Crear Cita'; ?>
                </button>
                <?php if ($idCitaEditar): ?>
                    <a href="?usuario=<?php echo $usuarioSeleccionado; ?>" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
    
    <section class="admin-lista">
        <h2>Citas de <?php echo htmlspecialchars($usuarioInfo['nombre'] . ' ' . $usuarioInfo['apellidos']); ?></h2>
        <?php if (empty($citas)): ?>
            <p>Este usuario no tiene citas programadas.</p>
        <?php else: ?>
            <table class="tabla-admin">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Motivo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($citas as $cita): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                            <td><?php echo htmlspecialchars($cita['motivo_cita']); ?></td>
                            <td>
                                <a href="?editar=<?php echo $cita['idCita']; ?>&usuario=<?php echo $usuarioSeleccionado; ?>" class="btn btn-small">Editar</a>
                                <a href="?eliminar=<?php echo $cita['idCita']; ?>&usuario=<?php echo $usuarioSeleccionado; ?>" class="btn btn-small btn-danger"
                                   onclick="return confirm('¿Estás seguro de eliminar esta cita?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

