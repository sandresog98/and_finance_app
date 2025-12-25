<?php
/**
 * AND FINANCE APP - Editar Presupuesto
 */

ob_start();

require_once __DIR__ . '/../models/PresupuestoModel.php';
require_once __DIR__ . '/../../categorias/models/CategoriaModel.php';

$presupuestoModel = new PresupuestoModel();
$categoriaModel = new CategoriaModel();
$userId = getCurrentUserId();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Obtener presupuesto
$presupuesto = $presupuestoModel->getById($id);

if (!$presupuesto || $presupuesto['usuario_id'] != $userId) {
    setFlashMessage('error', 'Presupuesto no encontrado');
    ob_end_clean();
    header('Location: ' . uiModuleUrl('presupuestos'));
    exit;
}

$pageTitle = 'Editar Presupuesto';
$pageSubtitle = $presupuesto['categoria_nombre'];

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Categorías disponibles + la actual
$categoriasDisponibles = $presupuestoModel->getCategoriasDisponibles($userId, $presupuesto['mes'], $presupuesto['anio']);
// Agregar la categoría actual si no está
$categoriaActual = [
    'id' => $presupuesto['categoria_id'],
    'nombre' => $presupuesto['categoria_nombre'],
    'icono' => $presupuesto['categoria_icono'],
    'color' => $presupuesto['categoria_color']
];
$found = false;
foreach ($categoriasDisponibles as $cat) {
    if ($cat['id'] == $categoriaActual['id']) {
        $found = true;
        break;
    }
}
if (!$found) {
    array_unshift($categoriasDisponibles, $categoriaActual);
}

$errors = [];
$data = [
    'categoria_id' => $presupuesto['categoria_id'],
    'monto_limite' => $presupuesto['monto_limite'],
    'alertar_al' => $presupuesto['alertar_al']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'categoria_id' => (int)($_POST['categoria_id'] ?? 0),
        'monto_limite' => (float)str_replace(['.', ','], ['', '.'], $_POST['monto_limite'] ?? 0),
        'alertar_al' => (int)($_POST['alertar_al'] ?? 80)
    ];

    // Validaciones
    if (!$data['categoria_id']) {
        $errors[] = 'Selecciona una categoría';
    } elseif ($presupuestoModel->existePresupuesto($userId, $data['categoria_id'], $presupuesto['mes'], $presupuesto['anio'], $id)) {
        $errors[] = 'Ya existe un presupuesto para esta categoría en este mes';
    }

    if ($data['monto_limite'] <= 0) {
        $errors[] = 'El monto debe ser mayor a 0';
    }

    if ($data['alertar_al'] < 1 || $data['alertar_al'] > 100) {
        $errors[] = 'El porcentaje de alerta debe estar entre 1 y 100';
    }

    if (empty($errors)) {
        try {
            $presupuestoModel->update($id, $data);

            setFlashMessage('success', 'Presupuesto actualizado correctamente');
            ob_end_clean();
            header('Location: ' . uiModuleUrl('presupuestos') . '&mes=' . $mes . '&anio=' . $anio);
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error al actualizar el presupuesto: ' . $e->getMessage();
        }
    }
}

$categoriaSeleccionada = (int)$data['categoria_id'];
$categoriasJson = json_encode(array_values($categoriasDisponibles));
?>

<style>
.selector-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: 2px solid #dee2e6;
    border-radius: 12px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;
    text-align: left;
}

.selector-btn:hover {
    border-color: #55A5C8;
}

.selector-btn.selected {
    border-color: #55A5C8;
    background: rgba(85, 165, 200, 0.1);
}

.selector-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
}

.selector-info {
    flex-grow: 1;
}

.selector-info span {
    font-weight: 600;
    display: block;
}

.selector-info small {
    color: #6c757d;
}

.categoria-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.categoria-option:hover {
    background: rgba(85, 165, 200, 0.1);
}

.categoria-option.selected {
    background: rgba(85, 165, 200, 0.2);
    border: 2px solid #55A5C8;
}

.alert-slider {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 12px;
}

.alert-slider input[type="range"] {
    width: 100%;
    accent-color: #55A5C8;
}

.alert-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #55A5C8;
}

.info-gasto {
    background: linear-gradient(135deg, rgba(255, 107, 107, 0.1) 0%, rgba(255, 107, 107, 0.05) 100%);
    border-radius: 12px;
    padding: 16px;
    border-left: 4px solid #FF6B6B;
}
</style>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card fade-in-up">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-pencil me-2"></i>
                    Editar Presupuesto - <?= $meses[$presupuesto['mes']] ?> <?= $presupuesto['anio'] ?>
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

                <!-- Info de gasto actual -->
                <?php 
                $gastado = 0;
                $presupuestoActual = $presupuestoModel->getResumenMes($userId, $presupuesto['mes'], $presupuesto['anio']);
                foreach ($presupuestoActual['presupuestos'] as $p) {
                    if ($p['id'] == $id) {
                        $gastado = $p['monto_gastado'];
                        break;
                    }
                }
                $porcentajeGastado = $data['monto_limite'] > 0 ? round(($gastado / $data['monto_limite']) * 100, 1) : 0;
                ?>
                <div class="info-gasto mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">Gastado este mes</small>
                            <h4 class="mb-0 text-danger"><?= formatMoney($gastado) ?></h4>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">del presupuesto</small>
                            <h4 class="mb-0"><?= $porcentajeGastado ?>%</h4>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <!-- Categoría -->
                    <div class="mb-4">
                        <label class="form-label">Categoría <span class="text-danger">*</span></label>
                        <button type="button" class="selector-btn selected" 
                                data-bs-toggle="modal" data-bs-target="#modalCategoria">
                            <div class="selector-icon" id="categoriaIcon" 
                                 style="background-color: <?= htmlspecialchars($presupuesto['categoria_color'] ?? '#55A5C8') ?>;">
                                <i class="bi <?= htmlspecialchars($presupuesto['categoria_icono'] ?? 'bi-tag') ?>"></i>
                            </div>
                            <div class="selector-info">
                                <span id="categoriaNombre"><?= htmlspecialchars($presupuesto['categoria_nombre']) ?></span>
                                <small>Categoría de gasto</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </button>
                        <input type="hidden" name="categoria_id" id="categoria_id" value="<?= $categoriaSeleccionada ?>">
                    </div>

                    <!-- Monto límite -->
                    <div class="mb-4">
                        <label class="form-label">Monto Límite <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">$</span>
                            <input type="text" 
                                   class="form-control" 
                                   name="monto_limite" 
                                   id="monto_limite"
                                   value="<?= number_format($data['monto_limite'], 0, ',', '.') ?>"
                                   placeholder="0"
                                   required>
                        </div>
                        <small class="text-muted">Monto máximo que planeas gastar en esta categoría</small>
                    </div>

                    <!-- Alertar al -->
                    <div class="mb-4">
                        <label class="form-label">Alertar cuando alcance</label>
                        <div class="alert-slider">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Porcentaje de alerta:</span>
                                <span class="alert-value" id="alertaValor"><?= $data['alertar_al'] ?>%</span>
                            </div>
                            <input type="range" 
                                   name="alertar_al" 
                                   id="alertar_al"
                                   min="50" 
                                   max="100" 
                                   step="5"
                                   value="<?= $data['alertar_al'] ?>">
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">50%</small>
                                <small class="text-muted">100%</small>
                            </div>
                        </div>
                        <small class="text-muted">Recibirás una alerta cuando alcances este porcentaje del presupuesto</small>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="<?= uiModuleUrl('presupuestos') ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" 
                           class="btn btn-outline-secondary flex-grow-1">
                            <i class="bi bi-arrow-left me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Categorías -->
<div class="modal fade" id="modalCategoria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-tag me-2"></i>Seleccionar Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="categoriasLista">
                    <?php foreach ($categoriasDisponibles as $cat): ?>
                    <div class="categoria-option <?= $categoriaSeleccionada == $cat['id'] ? 'selected' : '' ?>"
                         data-id="<?= $cat['id'] ?>"
                         data-nombre="<?= htmlspecialchars($cat['nombre']) ?>"
                         data-icono="<?= htmlspecialchars($cat['icono']) ?>"
                         data-color="<?= htmlspecialchars($cat['color']) ?>">
                        <div class="selector-icon" style="background-color: <?= htmlspecialchars($cat['color']) ?>;">
                            <i class="bi <?= htmlspecialchars($cat['icono']) ?>"></i>
                        </div>
                        <span><?= htmlspecialchars($cat['nombre']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalCategoria = new bootstrap.Modal(document.getElementById('modalCategoria'));
    
    // Formatear monto
    const montoInput = document.getElementById('monto_limite');
    montoInput.addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value) {
            this.value = new Intl.NumberFormat('es-CO').format(value);
        }
    });
    
    // Slider de alerta
    const alertaSlider = document.getElementById('alertar_al');
    const alertaValor = document.getElementById('alertaValor');
    alertaSlider.addEventListener('input', function() {
        alertaValor.textContent = this.value + '%';
    });
    
    // Selección de categoría
    document.querySelectorAll('.categoria-option').forEach(option => {
        option.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            const icono = this.dataset.icono;
            const color = this.dataset.color;
            
            document.getElementById('categoria_id').value = id;
            document.getElementById('categoriaNombre').textContent = nombre;
            document.getElementById('categoriaIcon').style.backgroundColor = color;
            document.getElementById('categoriaIcon').innerHTML = `<i class="bi ${icono}"></i>`;
            
            document.querySelectorAll('.categoria-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            
            modalCategoria.hide();
        });
    });
});
</script>
<?php $extraScripts = ob_get_clean(); ?>

