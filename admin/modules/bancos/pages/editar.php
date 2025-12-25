<?php
/**
 * AND FINANCE APP - Editar Banco
 */

require_once __DIR__ . '/../models/BancoModel.php';

$pageTitle = 'Editar Banco';
$bancoModel = new BancoModel();
$errors = [];

// Obtener banco a editar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$banco = $bancoModel->getById($id);

if (!$banco) {
    setFlashMessage('error', 'El banco no existe');
    ob_end_clean();
    header('Location: ' . moduleUrl('bancos'));
    exit;
}

$data = $banco;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['nombre'] = trim($_POST['nombre'] ?? '');
    $data['codigo'] = trim($_POST['codigo'] ?? '');
    $data['color_primario'] = $_POST['color_primario'] ?? '#55A5C8';
    $data['orden'] = (int)($_POST['orden'] ?? 0);
    $data['estado'] = isset($_POST['estado']) ? 1 : 0;
    
    // Validaciones
    if (empty($data['nombre'])) {
        $errors[] = 'El nombre del banco es obligatorio';
    } elseif ($bancoModel->existeNombre($data['nombre'], $id)) {
        $errors[] = 'Ya existe otro banco con ese nombre';
    }
    
    // Procesar nuevo logo
    $logoFileName = $banco['logo']; // Mantener logo actual por defecto
    
    // Eliminar logo si se solicitó
    if (isset($_POST['eliminar_logo']) && $_POST['eliminar_logo'] == '1') {
        if ($banco['logo'] && file_exists(UPLOADS_PATH . '/bancos/' . $banco['logo'])) {
            unlink(UPLOADS_PATH . '/bancos/' . $banco['logo']);
        }
        $logoFileName = null;
    }
    
    // Subir nuevo logo si se proporcionó
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = 'El logo debe ser una imagen (JPG, PNG, GIF, WEBP o SVG)';
        } elseif ($file['size'] > UPLOAD_MAX_IMAGE_SIZE) {
            $errors[] = 'El logo no puede superar los 5MB';
        } else {
            // Eliminar logo anterior si existe
            if ($banco['logo'] && file_exists(UPLOADS_PATH . '/bancos/' . $banco['logo'])) {
                unlink(UPLOADS_PATH . '/bancos/' . $banco['logo']);
            }
            
            // Generar nombre único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $uniqueKey = uniqid();
            $logoFileName = 'banco_' . preg_replace('/[^a-z0-9]/', '', strtolower($data['nombre'])) . '_' . $uniqueKey . '.' . $extension;
            
            // Crear directorio si no existe
            $uploadDir = UPLOADS_PATH . '/bancos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Mover archivo
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $logoFileName)) {
                $errors[] = 'Error al subir el logo';
                $logoFileName = $banco['logo']; // Mantener el anterior
            }
        }
    }
    
    // Guardar si no hay errores
    if (empty($errors)) {
        $data['logo'] = $logoFileName;
        
        try {
            $bancoModel->update($id, $data);
            setFlashMessage('success', 'Banco actualizado correctamente');
            ob_end_clean();
            header('Location: ' . moduleUrl('bancos'));
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error al actualizar el banco: ' . $e->getMessage();
        }
    }
}
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="<?= moduleUrl('bancos') ?>">Bancos</a></li>
            <li class="breadcrumb-item active">Editar Banco</li>
        </ol>
    </nav>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-pencil me-2"></i>
                    Editar Banco: <?= htmlspecialchars($banco['nombre']) ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">
                                    Nombre del Banco <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="nombre" 
                                       name="nombre" 
                                       value="<?= htmlspecialchars($data['nombre']) ?>"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="codigo" class="form-label">Código</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="codigo" 
                                       name="codigo" 
                                       value="<?= htmlspecialchars($data['codigo'] ?? '') ?>"
                                       maxlength="20"
                                       placeholder="Ej: BCOL">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="logo" class="form-label">Logo del Banco</label>
                                
                                <?php if ($banco['logo']): ?>
                                <div class="current-logo mb-3 p-3 bg-light rounded">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= UPLOADS_URL . '/bancos/' . htmlspecialchars($banco['logo']) ?>" 
                                             alt="Logo actual"
                                             class="img-thumbnail"
                                             style="max-height: 60px;"
                                             onerror="this.style.display='none'">
                                        <div>
                                            <small class="text-muted d-block">Logo actual</small>
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       id="eliminar_logo" 
                                                       name="eliminar_logo" 
                                                       value="1">
                                                <label class="form-check-label text-danger" for="eliminar_logo">
                                                    <small>Eliminar logo</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <input type="file" 
                                       class="form-control" 
                                       id="logo" 
                                       name="logo" 
                                       accept="image/*">
                                <small class="text-muted">
                                    <?= $banco['logo'] ? 'Subir nueva imagen para reemplazar' : 'Formatos: JPG, PNG, GIF, WEBP, SVG. Máx. 5MB' ?>
                                </small>
                            </div>
                            <div id="logoPreview" class="mb-3" style="display: none;">
                                <img id="logoPreviewImg" src="" alt="Preview" class="img-thumbnail" style="max-height: 100px;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="color_primario" class="form-label">Color Primario</label>
                                <div class="input-group">
                                    <input type="color" 
                                           class="form-control form-control-color" 
                                           id="color_primario" 
                                           name="color_primario" 
                                           value="<?= htmlspecialchars($data['color_primario'] ?? '#55A5C8') ?>">
                                    <input type="text" 
                                           class="form-control" 
                                           id="color_hex" 
                                           value="<?= htmlspecialchars($data['color_primario'] ?? '#55A5C8') ?>"
                                           readonly>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="orden" class="form-label">Orden</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="orden" 
                                       name="orden" 
                                       value="<?= $data['orden'] ?>"
                                       min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="estado" 
                                   name="estado"
                                   <?= $data['estado'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="estado">
                                Banco activo
                            </label>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= moduleUrl('bancos') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Actualizar Banco
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<SCRIPT
<script>
// Preview de logo
document.getElementById('logo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logoPreviewImg').src = e.target.result;
            document.getElementById('logoPreview').style.display = 'block';
        }
        reader.readAsDataURL(file);
    } else {
        document.getElementById('logoPreview').style.display = 'none';
    }
});

// Sincronizar color
document.getElementById('color_primario').addEventListener('input', function(e) {
    document.getElementById('color_hex').value = e.target.value;
});

// Si se marca eliminar logo, limpiar el input file
document.getElementById('eliminar_logo')?.addEventListener('change', function(e) {
    if (e.target.checked) {
        document.getElementById('logo').value = '';
        document.getElementById('logoPreview').style.display = 'none';
    }
});
</script>
SCRIPT;
?>

