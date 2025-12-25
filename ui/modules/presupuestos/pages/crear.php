<?php
/**
 * AND FINANCE APP - Crear Presupuesto
 */

ob_start();

require_once __DIR__ . '/../models/PresupuestoModel.php';
require_once __DIR__ . '/../../categorias/models/CategoriaModel.php';

$pageTitle = 'Nuevo Presupuesto';
$pageSubtitle = 'Establece un límite de gasto';

$presupuestoModel = new PresupuestoModel();
$categoriaModel = new CategoriaModel();
$userId = getCurrentUserId();

// Obtener mes y año del parámetro o usar el actual
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Categorías disponibles (sin presupuesto asignado)
$categoriasDisponibles = $presupuestoModel->getCategoriasDisponibles($userId, $mes, $anio);

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$errors = [];
$data = [
    'categoria_id' => '',
    'monto_limite' => '',
    'alertar_al' => 80
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
    } elseif ($presupuestoModel->existePresupuesto($userId, $data['categoria_id'], $mes, $anio)) {
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
            $presupuestoModel->create([
                'usuario_id' => $userId,
                'categoria_id' => $data['categoria_id'],
                'monto_limite' => $data['monto_limite'],
                'mes' => $mes,
                'anio' => $anio,
                'alertar_al' => $data['alertar_al']
            ]);

            setFlashMessage('success', 'Presupuesto creado correctamente');
            ob_end_clean();
            header('Location: ' . uiModuleUrl('presupuestos') . '&mes=' . $mes . '&anio=' . $anio);
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error al crear el presupuesto: ' . $e->getMessage();
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

/* Modal lista */
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
</style>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card fade-in-up">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-pie-chart me-2"></i>
                    Presupuesto para <?= $meses[$mes] ?> <?= $anio ?>
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

                <?php if (empty($categoriasDisponibles)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Todas las categorías de egreso ya tienen presupuesto asignado para este mes.
                    <a href="<?= uiModuleUrl('categorias', 'crear') ?>">Crear nueva categoría</a>
                </div>
                <?php else: ?>

                <form method="POST">
                    <!-- Categoría -->
                    <div class="mb-4">
                        <label class="form-label">Categoría <span class="text-danger">*</span></label>
                        <button type="button" class="selector-btn <?= $categoriaSeleccionada ? 'selected' : '' ?>" 
                                data-bs-toggle="modal" data-bs-target="#modalCategoria">
                            <div class="selector-icon" id="categoriaIcon" style="background-color: #B1BCBF;">
                                <i class="bi bi-tag"></i>
                            </div>
                            <div class="selector-info">
                                <span id="categoriaNombre">Seleccionar categoría</span>
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
                                   value="<?= $data['monto_limite'] ? number_format($data['monto_limite'], 0, ',', '.') : '' ?>"
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
                            <i class="bi bi-check-lg me-1"></i>Crear Presupuesto
                        </button>
                    </div>
                </form>
                <?php endif; ?>
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
    const categorias = <?= $categoriasJson ?>;
    let categoriaSeleccionadaId = <?= $categoriaSeleccionada ?: 0 ?>;
    
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
            document.querySelector('.selector-btn').classList.add('selected');
            
            // Marcar seleccionado
            document.querySelectorAll('.categoria-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            
            modalCategoria.hide();
        });
    });
    
    // Si hay categoría seleccionada, actualizar UI
    if (categoriaSeleccionadaId) {
        const cat = categorias.find(c => c.id == categoriaSeleccionadaId);
        if (cat) {
            document.getElementById('categoriaNombre').textContent = cat.nombre;
            document.getElementById('categoriaIcon').style.backgroundColor = cat.color;
            document.getElementById('categoriaIcon').innerHTML = `<i class="bi ${cat.icono}"></i>`;
        }
    }
});
</script>
<?php $extraScripts = ob_get_clean(); ?>

