<?php
/**
 * AND FINANCE APP - Editar Categoría
 */

require_once __DIR__ . '/../models/CategoriaModel.php';

$pageTitle = 'Editar Categoría';
$pageSubtitle = 'Modifica la categoría';
$categoriaModel = new CategoriaModel();
$userId = getCurrentUserId();

// Obtener categoría a editar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$categoria = $categoriaModel->getById($id);

// Verificar permisos
if (!$categoria || $categoria['usuario_id'] != $userId) {
    setFlashMessage('error', 'Categoría no encontrada');
    ob_end_clean();
    header('Location: ' . uiModuleUrl('categorias'));
    exit;
}

$errors = [];
$data = $categoria;

// Colores disponibles
$colores = [
    '#FF6B6B' => 'Rojo',
    '#9AD082' => 'Verde',
    '#55A5C8' => 'Azul',
    '#F7DC6F' => 'Amarillo',
    '#BB8FCE' => 'Púrpura',
    '#5DADE2' => 'Celeste',
    '#48C9B0' => 'Turquesa',
    '#F5B041' => 'Naranja',
    '#85929E' => 'Gris',
    '#E74C3C' => 'Rojo Intenso',
    '#3498DB' => 'Azul Cielo',
    '#2ECC71' => 'Verde Esmeralda'
];

// Iconos disponibles
$iconos = $categoriaModel->getIconosDisponibles();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['nombre'] = trim($_POST['nombre'] ?? '');
    $data['tipo'] = $_POST['tipo'] ?? 'egreso';
    $data['icono'] = $_POST['icono'] ?? 'bi-tag';
    $data['color'] = $_POST['color'] ?? '#55A5C8';
    
    if (empty($data['nombre'])) {
        $errors[] = 'El nombre de la categoría es obligatorio';
    }
    
    if (empty($errors)) {
        try {
            $categoriaModel->update($id, $data);
            setFlashMessage('success', 'Categoría actualizada correctamente');
            ob_end_clean();
            header('Location: ' . uiModuleUrl('categorias'));
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card fade-in-up">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-pencil me-2"></i>
                    Editar: <?= htmlspecialchars($categoria['nombre']) ?>
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
                
                <form method="POST" id="formCategoria">
                    <!-- Nombre -->
                    <div class="mb-4">
                        <label for="nombre" class="form-label">
                            Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control form-control-lg" 
                               id="nombre" 
                               name="nombre" 
                               value="<?= htmlspecialchars($data['nombre']) ?>"
                               required>
                    </div>
                    
                    <!-- Tipo de categoría -->
                    <div class="mb-4">
                        <label class="form-label">Tipo de categoría</label>
                        <div class="d-flex gap-3">
                            <label class="tipo-option flex-fill <?= $data['tipo'] === 'egreso' ? 'active egreso' : '' ?>">
                                <input type="radio" name="tipo" value="egreso" <?= $data['tipo'] === 'egreso' ? 'checked' : '' ?>>
                                <div class="tipo-content">
                                    <i class="bi bi-arrow-up-circle me-2"></i>Gasto
                                </div>
                            </label>
                            <label class="tipo-option flex-fill <?= $data['tipo'] === 'ingreso' ? 'active ingreso' : '' ?>">
                                <input type="radio" name="tipo" value="ingreso" <?= $data['tipo'] === 'ingreso' ? 'checked' : '' ?>>
                                <div class="tipo-content">
                                    <i class="bi bi-arrow-down-circle me-2"></i>Ingreso
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Color e Icono con modales -->
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-4">
                                <label class="form-label">Color</label>
                                <input type="hidden" name="color" id="color_input" value="<?= htmlspecialchars($data['color']) ?>">
                                
                                <button type="button" class="btn btn-outline-secondary w-100 selector-btn" 
                                        data-bs-toggle="modal" data-bs-target="#modalColores">
                                    <div class="d-flex align-items-center" id="colorSeleccionado">
                                        <div class="color-preview" style="background-color: <?= htmlspecialchars($data['color']) ?>;"></div>
                                        <span><?= $colores[$data['color']] ?? 'Color' ?></span>
                                    </div>
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-4">
                                <label class="form-label">Icono</label>
                                <input type="hidden" name="icono" id="icono_input" value="<?= htmlspecialchars($data['icono']) ?>">
                                
                                <button type="button" class="btn btn-outline-secondary w-100 selector-btn" 
                                        data-bs-toggle="modal" data-bs-target="#modalIconos">
                                    <div class="d-flex align-items-center" id="iconoSeleccionado">
                                        <div class="icon-preview">
                                            <i class="bi <?= htmlspecialchars($data['icono']) ?>"></i>
                                        </div>
                                        <span><?= $iconos[$data['icono']] ?? 'Icono' ?></span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vista previa -->
                    <div class="mb-4">
                        <label class="form-label">Vista previa</label>
                        <div class="preview-categoria">
                            <div class="categoria-icon-preview" id="previewIcon" 
                                 style="background-color: <?= htmlspecialchars($data['color']) ?>20; color: <?= htmlspecialchars($data['color']) ?>;">
                                <i class="bi <?= htmlspecialchars($data['icono']) ?>"></i>
                            </div>
                            <span class="fw-semibold" id="previewNombre"><?= htmlspecialchars($data['nombre']) ?></span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= uiModuleUrl('categorias') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Selección de Color -->
<div class="modal fade" id="modalColores" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-palette me-2"></i>Seleccionar Color
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <?php foreach ($colores as $hex => $nombre): ?>
                    <div class="col-4 col-md-3">
                        <div class="color-card <?= $data['color'] === $hex ? 'selected' : '' ?>" 
                             data-color="<?= $hex ?>" data-nombre="<?= $nombre ?>">
                            <div class="color-circle" style="background-color: <?= $hex ?>;"></div>
                            <span><?= $nombre ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Selección de Icono -->
<div class="modal fade" id="modalIconos" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-emoji-smile me-2"></i>Seleccionar Icono
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <?php foreach ($iconos as $icono => $nombre): ?>
                    <div class="col-4 col-md-3">
                        <div class="icon-card <?= $data['icono'] === $icono ? 'selected' : '' ?>" 
                             data-icono="<?= $icono ?>" data-nombre="<?= $nombre ?>">
                            <div class="icon-circle"><i class="bi <?= $icono ?>"></i></div>
                            <span><?= $nombre ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Tipo de categoría */
.tipo-option { cursor: pointer; }
.tipo-option input { display: none; }
.tipo-content {
    padding: 15px; border: 2px solid var(--border-color, #e9ecef); border-radius: 12px;
    text-align: center; font-weight: 600; transition: all 0.3s ease; color: var(--text-muted, var(--tertiary-gray));
    background: var(--bg-card, white);
}
.tipo-option:hover .tipo-content { border-color: var(--primary-blue); }
.tipo-option.active.egreso .tipo-content { border-color: #FF6B6B; background: rgba(255, 107, 107, 0.1); color: #FF6B6B; }
.tipo-option.active.ingreso .tipo-content { border-color: #9AD082; background: rgba(154, 208, 130, 0.1); color: #5a9a3e; }

/* Selector Buttons */
.selector-btn {
    padding: 12px 16px; border-radius: 8px;
    border: 1px solid var(--border-color, #dee2e6); background: var(--bg-card, white);
    transition: all 0.2s ease; text-align: left; color: var(--text-primary, #333);
}
.selector-btn:hover { border-color: var(--primary-blue); background: rgba(85, 165, 200, 0.1); }
.color-preview {
    width: 28px; height: 28px; border-radius: 50%;
    border: 2px solid rgba(0,0,0,0.1); margin-right: 12px; flex-shrink: 0;
}
.icon-preview {
    width: 32px; height: 32px; border-radius: 8px;
    background: var(--primary-blue); color: white;
    display: flex; align-items: center; justify-content: center;
    margin-right: 12px; flex-shrink: 0;
}

/* Preview */
.preview-categoria {
    display: flex; align-items: center; padding: 16px;
    background: var(--bg-input, #f8f9fa); border-radius: 12px; border: 1px solid var(--border-color, #e9ecef);
}
.categoria-icon-preview {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; margin-right: 12px; flex-shrink: 0;
    transition: all 0.3s ease;
}

/* Color Cards */
.color-card {
    display: flex; flex-direction: column; align-items: center;
    padding: 12px; border-radius: 12px;
    border: 2px solid var(--border-color, #e9ecef); cursor: pointer; transition: all 0.2s ease;
    background: var(--bg-card, white);
}
.color-card:hover { border-color: var(--primary-blue); transform: scale(1.05); }
.color-card.selected { border-color: var(--primary-blue); background: rgba(85, 165, 200, 0.15); }
.color-circle {
    width: 36px; height: 36px; border-radius: 50%;
    margin-bottom: 8px; border: 3px solid var(--bg-card, white);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.color-card span { font-size: 11px; font-weight: 600; color: var(--text-primary, var(--dark-blue)); }

/* Icon Cards */
.icon-card {
    display: flex; flex-direction: column; align-items: center;
    padding: 12px; border-radius: 12px;
    border: 2px solid var(--border-color, #e9ecef); cursor: pointer; transition: all 0.2s ease;
    background: var(--bg-card, white);
}
.icon-card:hover { border-color: var(--primary-blue); transform: scale(1.05); }
.icon-card.selected { border-color: var(--primary-blue); background: rgba(85, 165, 200, 0.15); }
.icon-circle {
    width: 40px; height: 40px; border-radius: 10px;
    background: var(--primary-blue); color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: 8px;
}
.icon-card span { font-size: 11px; font-weight: 600; color: var(--text-primary, var(--dark-blue)); }
</style>

<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nombreInput = document.getElementById('nombre');
    const colorInput = document.getElementById('color_input');
    const iconoInput = document.getElementById('icono_input');
    const colorSeleccionado = document.getElementById('colorSeleccionado');
    const iconoSeleccionado = document.getElementById('iconoSeleccionado');
    const previewIcon = document.getElementById('previewIcon');
    const previewNombre = document.getElementById('previewNombre');
    
    const colores = <?= json_encode($colores) ?>;
    const iconos = <?= json_encode($iconos) ?>;
    
    nombreInput.addEventListener('input', () => {
        previewNombre.textContent = nombreInput.value || 'Nombre de categoría';
    });
    
    document.querySelectorAll('.tipo-option').forEach(option => {
        option.querySelector('input').addEventListener('change', function() {
            document.querySelectorAll('.tipo-option').forEach(o => o.classList.remove('active', 'ingreso', 'egreso'));
            option.classList.add('active', this.value);
        });
    });
    
    // Selección de color
    document.querySelectorAll('.color-card').forEach(card => {
        card.addEventListener('click', function() {
            const color = this.dataset.color;
            const nombre = this.dataset.nombre;
            
            document.querySelectorAll('.color-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            colorInput.value = color;
            
            colorSeleccionado.innerHTML = '<div class="color-preview" style="background-color: ' + color + ';"></div><span>' + nombre + '</span>';
            previewIcon.style.backgroundColor = color + '20';
            previewIcon.style.color = color;
            
            bootstrap.Modal.getInstance(document.getElementById('modalColores')).hide();
        });
    });
    
    // Selección de icono
    document.querySelectorAll('.icon-card').forEach(card => {
        card.addEventListener('click', function() {
            const icono = this.dataset.icono;
            const nombre = this.dataset.nombre;
            
            document.querySelectorAll('.icon-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            iconoInput.value = icono;
            
            iconoSeleccionado.innerHTML = '<div class="icon-preview"><i class="bi ' + icono + '"></i></div><span>' + nombre + '</span>';
            previewIcon.innerHTML = '<i class="bi ' + icono + '"></i>';
            
            bootstrap.Modal.getInstance(document.getElementById('modalIconos')).hide();
        });
    });
});
</script>
<?php $extraScripts = ob_get_clean(); ?>
