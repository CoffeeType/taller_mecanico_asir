<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
iniciarSesion();

// Verificar que sea administrador
if (!verificarRol('admin')) {
    header("Location: index.php");
    exit();
}

$tituloPagina = "Administración de Noticias";
require_once __DIR__ . '/includes/header.php';

$errores = [];
$mensajeExito = '';
$idNoticiaEditar = null;
$noticiaEditar = null;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = sanitizarDatos($_POST);
    
    // Crear nueva noticia
    if (isset($datos['crear_noticia'])) {
        $camposObligatorios = ['titulo', 'texto', 'fecha'];
        $errores = validarCamposObligatorios($datos, $camposObligatorios);
        
        // Validar imagen
        if (empty($_FILES['imagen']['name'])) {
            $errores[] = "La imagen es obligatoria";
        } else {
            $erroresImagen = validarArchivoImagen($_FILES['imagen']);
            $errores = array_merge($errores, $erroresImagen);
        }
        
        // Validar título único
        if (empty($errores) && !empty($datos['titulo'])) {
            try {
                $stmt = $pdo->prepare("SELECT idNoticia FROM noticias WHERE titulo = ?");
                $stmt->execute([$datos['titulo']]);
                if ($stmt->fetch()) {
                    $errores[] = "Ya existe una noticia con ese título";
                }
            } catch (PDOException $e) {
                $errores[] = "Error al verificar el título";
            }
        }
        
        if (empty($errores)) {
            try {
                // Subir imagen con nombre sanitizado
                $imagen = $_FILES['imagen'];
                $nombreImagen = sanitizarNombreArchivo($imagen['name']);
                $rutaImagen = 'assets/images/' . $nombreImagen;
                
                // Ensure directory exists
                $uploadDir = dirname($rutaImagen);
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                if (!move_uploaded_file($imagen['tmp_name'], $rutaImagen)) {
                    throw new Exception("Error al subir la imagen");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO noticias (titulo, imagen, texto, fecha, idUser)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $datos['titulo'],
                    $rutaImagen,
                    $datos['texto'],
                    $datos['fecha'],
                    $_SESSION['idUser']
                ]);
                
                $mensajeExito = "Noticia creada correctamente";
            } catch (Exception $e) {
                $errores[] = "Error al crear la noticia: " . $e->getMessage();
            }
        }
    }
    
    // Actualizar noticia
    if (isset($datos['actualizar_noticia'])) {
        $idNoticia = intval($datos['idNoticia']);
        $camposObligatorios = ['titulo', 'texto', 'fecha'];
        $errores = validarCamposObligatorios($datos, $camposObligatorios);
        
        // Validar título único (excepto la noticia actual)
        if (empty($errores) && !empty($datos['titulo'])) {
            try {
                $stmt = $pdo->prepare("SELECT idNoticia FROM noticias WHERE titulo = ? AND idNoticia != ?");
                $stmt->execute([$datos['titulo'], $idNoticia]);
                if ($stmt->fetch()) {
                    $errores[] = "Ya existe otra noticia con ese título";
                }
            } catch (PDOException $e) {
                $errores[] = "Error al verificar el título";
            }
        }
        
        if (empty($errores)) {
            try {
                $rutaImagen = null;
                
                // Si se sube una nueva imagen
                if (!empty($_FILES['imagen']['name'])) {
                    $erroresImagen = validarArchivoImagen($_FILES['imagen']);
                    if (empty($erroresImagen)) {
                        $imagen = $_FILES['imagen'];
                        // Obtener imagen anterior para eliminarla
                        $stmt = $pdo->prepare("SELECT imagen FROM noticias WHERE idNoticia = ?");
                        $stmt->execute([$idNoticia]);
                        $noticiaAnterior = $stmt->fetch();
                        
                        if ($noticiaAnterior && file_exists($noticiaAnterior['imagen'])) {
                            unlink($noticiaAnterior['imagen']);
                        }
                        
                        // Subir nueva imagen con nombre sanitizado
                        $nombreImagen = sanitizarNombreArchivo($imagen['name']);
                        $rutaImagen = 'assets/images/' . $nombreImagen;
                        
                        // Ensure directory exists
                        $uploadDir = dirname($rutaImagen);
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        if (!move_uploaded_file($imagen['tmp_name'], $rutaImagen)) {
                            throw new Exception("Error al subir la imagen");
                        }
                    } else {
                        $errores = array_merge($errores, $erroresImagen);
                    }
                }
                
                // Update database
                if (empty($errores)) {
                    if ($rutaImagen !== null) {
                        // Update with new image
                        $stmt = $pdo->prepare("
                            UPDATE noticias 
                            SET titulo = ?, imagen = ?, texto = ?, fecha = ?
                            WHERE idNoticia = ?
                        ");
                        $stmt->execute([
                            $datos['titulo'],
                            $rutaImagen,
                            $datos['texto'],
                            $datos['fecha'],
                            $idNoticia
                        ]);
                    } else {
                        // No se cambia la imagen
                        $stmt = $pdo->prepare("
                            UPDATE noticias 
                            SET titulo = ?, texto = ?, fecha = ?
                            WHERE idNoticia = ?
                        ");
                        $stmt->execute([
                            $datos['titulo'],
                            $datos['texto'],
                            $datos['fecha'],
                            $idNoticia
                        ]);
                    }
                    
                    $mensajeExito = "Noticia actualizada correctamente";
                    $idNoticiaEditar = null;
                    $noticiaEditar = null;
                }
            } catch (Exception $e) {
                $errores[] = "Error al actualizar la noticia: " . $e->getMessage();
            }
        }
    }
}

// Eliminar noticia
if (isset($_GET['eliminar'])) {
    $idNoticia = intval($_GET['eliminar']);
    
    try {
        // Obtener ruta de la imagen para eliminarla
        $stmt = $pdo->prepare("SELECT imagen FROM noticias WHERE idNoticia = ?");
        $stmt->execute([$idNoticia]);
        $noticia = $stmt->fetch();
        
        if ($noticia) {
            // Eliminar archivo de imagen
            if (file_exists($noticia['imagen'])) {
                unlink($noticia['imagen']);
            }
            
            // Eliminar noticia de BD
            $stmt = $pdo->prepare("DELETE FROM noticias WHERE idNoticia = ?");
            $stmt->execute([$idNoticia]);
            
            $mensajeExito = "Noticia eliminada correctamente";
        } else {
            $errores[] = "Noticia no encontrada";
        }
    } catch (PDOException $e) {
        $errores[] = "Error al eliminar la noticia";
    }
}

// Obtener noticia para editar
if (isset($_GET['editar'])) {
    $idNoticiaEditar = intval($_GET['editar']);
    try {
        $stmt = $pdo->prepare("SELECT * FROM noticias WHERE idNoticia = ?");
        $stmt->execute([$idNoticiaEditar]);
        $noticiaEditar = $stmt->fetch();
        
        if (!$noticiaEditar) {
            $errores[] = "Noticia no encontrada";
            $idNoticiaEditar = null;
        }
    } catch (PDOException $e) {
        $errores[] = "Error al obtener la noticia";
    }
}

// Obtener todas las noticias
try {
    $stmt = $pdo->query("
        SELECT n.*, CONCAT(ud.nombre, ' ', ud.apellidos) AS autor_nombre
        FROM noticias n
        INNER JOIN users_data ud ON n.idUser = ud.idUser
        ORDER BY n.fecha DESC, n.idNoticia DESC
    ");
    $noticias = $stmt->fetchAll();
} catch (PDOException $e) {
    $noticias = [];
    $errores[] = "Error al cargar las noticias";
}
?>

<h1>Administración de Noticias</h1>

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
    <h2><?php echo $idNoticiaEditar ? 'Editar Noticia' : 'Crear Nueva Noticia'; ?></h2>
    <form method="POST" action="" enctype="multipart/form-data" class="form-noticia-admin">
        <?php if ($idNoticiaEditar): ?>
            <input type="hidden" name="actualizar_noticia" value="1">
            <input type="hidden" name="idNoticia" value="<?php echo $idNoticiaEditar; ?>">
        <?php else: ?>
            <input type="hidden" name="crear_noticia" value="1">
        <?php endif; ?>
        
        <div class="form-group">
            <label for="titulo">Título *</label>
            <input type="text" id="titulo" name="titulo" required
                   value="<?php echo $noticiaEditar ? htmlspecialchars($noticiaEditar['titulo']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="imagen">Imagen <?php echo $idNoticiaEditar ? '(dejar vacío para mantener la actual)' : '*'; ?></label>
            <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/jpg,image/png,image/gif" <?php echo $idNoticiaEditar ? '' : 'required'; ?>>
            <?php if ($idNoticiaEditar && !empty($noticiaEditar['imagen'])): ?>
                <div class="imagen-actual">
                    <p>Imagen actual:</p>
                    <img src="<?php echo htmlspecialchars($noticiaEditar['imagen']); ?>" alt="Imagen actual" style="max-width: 200px;">
                </div>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <label for="texto">Texto *</label>
            <textarea id="texto" name="texto" rows="10" required><?php echo $noticiaEditar ? htmlspecialchars($noticiaEditar['texto']) : ''; ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="fecha">Fecha *</label>
            <input type="date" id="fecha" name="fecha" required
                   value="<?php echo $noticiaEditar ? htmlspecialchars($noticiaEditar['fecha']) : date('Y-m-d'); ?>">
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <?php echo $idNoticiaEditar ? 'Actualizar Noticia' : 'Crear Noticia'; ?>
            </button>
            <?php if ($idNoticiaEditar): ?>
                <a href="noticias-administracion.php" class="btn btn-secondary">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="admin-lista">
    <h2>Lista de Noticias</h2>
    <?php if (empty($noticias)): ?>
        <p>No hay noticias creadas.</p>
    <?php else: ?>
        <table class="tabla-admin">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Fecha</th>
                    <th>Autor</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($noticias as $noticia): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($noticia['idNoticia']); ?></td>
                        <td><?php echo htmlspecialchars($noticia['titulo']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($noticia['fecha'])); ?></td>
                        <td><?php echo htmlspecialchars($noticia['autor_nombre']); ?></td>
                        <td>
                            <a href="?editar=<?php echo $noticia['idNoticia']; ?>" class="btn btn-small">Editar</a>
                            <a href="?eliminar=<?php echo $noticia['idNoticia']; ?>" class="btn btn-small btn-danger"
                               onclick="return confirm('¿Estás seguro de eliminar esta noticia?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

