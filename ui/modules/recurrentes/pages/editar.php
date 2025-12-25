<?php
/**
 * AND FINANCE APP - Editar Gasto Recurrente
 */

require_once __DIR__ . '/../models/GastoRecurrenteModel.php';
require_once __DIR__ . '/../../cuentas/models/CuentaModel.php';

$pageTitle = 'Editar Gasto Recurrente';
$pageSubtitle = 'Modifica los datos del gasto programado';
$gastoModel = new GastoRecurrenteModel();
$cuentaModel = new CuentaModel();
$userId = getCurrentUserId();
$db = Database::getInstance();

// Obtener gasto a editar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$gasto = $gastoModel->getById($id);

if (!$gasto || $gasto['usuario_id'] != $userId) {
    setFlashMessage('error', 'Gasto recurrente no encontrado');
    ob_end_clean();
    header('Location: ' . uiModuleUrl('recurrentes'));
    exit;
}

$errors = [];
$data = $gasto;

// Obtener datos para el formulario
$cuentas = $cuentaModel->getAllByUser($userId);
$frecuencias = $gastoModel->getFrecuencias();
$diasSemana = $gastoModel->getDiasSemana();

// Obtener categorías (solo las del usuario, sin las del sistema)
$categorias = $db->prepare("
    SELECT id, nombre, tipo, icono, color FROM categorias 
    WHERE usuario_id = :usuario_id AND es_sistema = 0 AND estado = 1
    ORDER BY tipo, orden, nombre
");
$categorias->execute(['usuario_id' => $userId]);
$categorias = $categorias->fetchAll();

$categoriasIngreso = array_values(array_filter($categorias, fn($c) => $c['tipo'] === 'ingreso'));
$categoriasEgreso = array_values(array_filter($categorias, fn($c) => $c['tipo'] === 'egreso'));

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['nombre'] = trim($_POST['nombre'] ?? '');
    $data['tipo'] = $_POST['tipo'] ?? 'egreso';
    $data['cuenta_id'] = (int)($_POST['cuenta_id'] ?? 0);
    $data['categoria_id'] = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
    $data['monto'] = (float)str_replace(['.', ','], ['', '.'], $_POST['monto'] ?? 0);
    $data['frecuencia'] = $_POST['frecuencia'] ?? 'mensual';
    $data['dia_ejecucion'] = !empty($_POST['dia_ejecucion']) ? (int)$_POST['dia_ejecucion'] : null;
    $data['dias_ejecucion'] = trim($_POST['dias_ejecucion'] ?? '');
    $data['fecha_inicio'] = $_POST['fecha_inicio'] ?? date('Y-m-d');
    $data['fecha_fin'] = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
    $data['notificar'] = isset($_POST['notificar']) ? 1 : 0;
    $data['dias_anticipacion'] = (int)($_POST['dias_anticipacion'] ?? 1);
    $data['auto_registrar'] = isset($_POST['auto_registrar']) ? 1 : 0;
    
    // Validaciones
    if (empty($data['nombre'])) {
        $errors[] = 'El nombre es obligatorio';
    }
    if (empty($data['cuenta_id'])) {
        $errors[] = 'Debes seleccionar una cuenta';
    }
    if ($data['monto'] <= 0) {
        $errors[] = 'El monto debe ser mayor a 0';
    }
    
    if (empty($errors)) {
        try {
            $gastoModel->update($id, $data);
            setFlashMessage('success', 'Gasto recurrente actualizado correctamente');
            ob_end_clean();
            header('Location: ' . uiModuleUrl('recurrentes'));
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

// Obtener cuenta y categoría seleccionadas para mostrar
$cuentaSeleccionada = null;
$categoriaSeleccionadaData = null;
foreach ($cuentas as $c) {
    if ($c['id'] == $data['cuenta_id']) {
        $cuentaSeleccionada = $c;
        break;
    }
}
foreach ($categorias as $c) {
    if ($c['id'] == $data['categoria_id']) {
        $categoriaSeleccionadaData = $c;
        break;
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card fade-in-up">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-pencil me-2"></i>
                    Editar: <?= htmlspecialchars($gasto['nombre']) ?>
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
                
                <form method="POST" id="formRecurrente">
                    <!-- Tipo -->
                    <div class="mb-4">
                        <label class="form-label">Tipo</label>
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
                    
                    <!-- Nombre -->
                    <div class="mb-4">
                        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg" id="nombre" name="nombre" 
                               value="<?= htmlspecialchars($data['nombre']) ?>" required>
                    </div>
                    
                    <!-- Monto -->
                    <div class="mb-4">
                        <label for="monto" class="form-label">Monto <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">$</span>
                            <input type="text" class="form-control money-input text-end" id="monto" name="monto" 
                                   value="<?= number_format($data['monto'], 0, ',', '.') ?>"
                                   inputmode="numeric" required>
                        </div>
                    </div>
                    
                    <!-- Cuenta (Modal) -->
                    <div class="mb-4">
                        <label class="form-label">Cuenta <span class="text-danger">*</span></label>
                        <input type="hidden" name="cuenta_id" id="cuenta_id" value="<?= $data['cuenta_id'] ?>">
                        <button type="button" class="btn btn-outline-secondary w-100 selector-btn py-3" 
                                data-bs-toggle="modal" data-bs-target="#modalCuentas">
                            <div class="d-flex align-items-center" id="cuentaSeleccionada">
                                <?php if ($cuentaSeleccionada): ?>
                                <div class="selector-icon me-3" style="background-color: <?= htmlspecialchars($cuentaSeleccionada['color']) ?>;">
                                    <i class="bi <?= htmlspecialchars($cuentaSeleccionada['icono']) ?>"></i>
                                </div>
                                <span class="fw-semibold"><?= htmlspecialchars($cuentaSeleccionada['nombre']) ?></span>
                                <?php else: ?>
                                <div class="selector-icon me-3 bg-secondary"><i class="bi bi-wallet2"></i></div>
                                <span class="text-muted">Seleccionar cuenta</span>
                                <?php endif; ?>
                            </div>
                            <i class="bi bi-chevron-right ms-auto"></i>
                        </button>
                    </div>
                    
                    <!-- Categoría (Modal) -->
                    <div class="mb-4">
                        <label class="form-label">Categoría</label>
                        <input type="hidden" name="categoria_id" id="categoria_id" value="<?= $data['categoria_id'] ?>">
                        <button type="button" class="btn btn-outline-secondary w-100 selector-btn py-3" 
                                data-bs-toggle="modal" data-bs-target="#modalCategorias">
                            <div class="d-flex align-items-center" id="categoriaSeleccionada">
                                <?php if ($categoriaSeleccionadaData): ?>
                                <div class="selector-icon me-3" style="background-color: <?= htmlspecialchars($categoriaSeleccionadaData['color']) ?>20; color: <?= htmlspecialchars($categoriaSeleccionadaData['color']) ?>;">
                                    <i class="bi <?= htmlspecialchars($categoriaSeleccionadaData['icono']) ?>"></i>
                                </div>
                                <span class="fw-semibold"><?= htmlspecialchars($categoriaSeleccionadaData['nombre']) ?></span>
                                <?php else: ?>
                                <div class="selector-icon me-3 bg-secondary"><i class="bi bi-tag"></i></div>
                                <span class="text-muted">Seleccionar categoría</span>
                                <?php endif; ?>
                            </div>
                            <i class="bi bi-chevron-right ms-auto"></i>
                        </button>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="text-muted mb-3"><i class="bi bi-repeat me-2"></i>Frecuencia de Pago</h6>
                    
                    <!-- Frecuencia (Modal) -->
                    <div class="mb-4">
                        <label class="form-label">Repetir cada</label>
                        <input type="hidden" name="frecuencia" id="frecuencia" value="<?= $data['frecuencia'] ?>">
                        <button type="button" class="btn btn-outline-secondary w-100 selector-btn py-3" 
                                data-bs-toggle="modal" data-bs-target="#modalFrecuencias">
                            <div class="d-flex align-items-center" id="frecuenciaSeleccionada">
                                <div class="selector-icon me-3" style="background-color: var(--primary-blue);">
                                    <i class="bi <?= $frecuencias[$data['frecuencia']]['icono'] ?? 'bi-calendar' ?>"></i>
                                </div>
                                <div>
                                    <span class="fw-semibold"><?= $frecuencias[$data['frecuencia']]['nombre'] ?? 'Mensual' ?></span>
                                    <small class="d-block text-muted"><?= $frecuencias[$data['frecuencia']]['descripcion'] ?? '' ?></small>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right ms-auto"></i>
                        </button>
                    </div>
                    
                    <!-- Día del mes (Modal) -->
                    <div class="mb-4" id="diaEjecucionWrapper">
                        <label class="form-label">Día del mes</label>
                        <input type="hidden" name="dia_ejecucion" id="dia_ejecucion" value="<?= $data['dia_ejecucion'] ?? 1 ?>">
                        <button type="button" class="btn btn-outline-secondary w-100 selector-btn py-3" 
                                data-bs-toggle="modal" data-bs-target="#modalDias">
                            <div class="d-flex align-items-center" id="diaSeleccionado">
                                <div class="selector-icon me-3" style="background-color: var(--secondary-green);">
                                    <span class="fw-bold"><?= $data['dia_ejecucion'] ?? 1 ?></span>
                                </div>
                                <div>
                                    <span class="fw-semibold">Día <?= $data['dia_ejecucion'] ?? 1 ?> de cada mes</span>
                                    <small class="d-block text-muted">
                                        <?= ($data['dia_ejecucion'] ?? 1) > 28 ? 'En meses cortos se ajustará al último día disponible' : '' ?>
                                    </small>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right ms-auto"></i>
                        </button>
                    </div>
                    
                    <!-- Día de la semana (Modal) -->
                    <div class="mb-4" id="diaSemanaWrapper" style="display: none;">
                        <label class="form-label">Día de la semana</label>
                        <input type="hidden" name="dia_semana" id="dia_semana" value="<?= $data['dia_ejecucion'] ?? 1 ?>">
                        <button type="button" class="btn btn-outline-secondary w-100 selector-btn py-3" 
                                data-bs-toggle="modal" data-bs-target="#modalDiasSemana">
                            <div class="d-flex align-items-center" id="diaSemanaSeleccionado">
                                <div class="selector-icon me-3" style="background-color: var(--secondary-green);">
                                    <i class="bi bi-calendar-week"></i>
                                </div>
                                <span class="fw-semibold"><?= $diasSemana[$data['dia_ejecucion']] ?? 'Lunes' ?></span>
                            </div>
                            <i class="bi bi-chevron-right ms-auto"></i>
                        </button>
                    </div>
                    
                    <!-- Días quincenales -->
                    <div class="mb-4" id="diasQuincenalesWrapper" style="display: none;">
                        <label class="form-label">Días del mes (separados por coma)</label>
                        <input type="text" class="form-control" id="dias_ejecucion" name="dias_ejecucion" 
                               value="<?= htmlspecialchars($data['dias_ejecucion'] ?? '15,30') ?>"
                               placeholder="Ej: 15, 30">
                        <small class="text-muted">Los días mayores a 28 se ajustarán en meses cortos</small>
                    </div>
                    
                    <!-- Fechas -->
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-4">
                                <label for="fecha_inicio" class="form-label">Desde</label>
                                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                       value="<?= htmlspecialchars($data['fecha_inicio']) ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-4">
                                <label for="fecha_fin" class="form-label">Hasta (opcional)</label>
                                <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                                       value="<?= htmlspecialchars($data['fecha_fin'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="text-muted mb-3"><i class="bi bi-bell me-2"></i>Notificaciones</h6>
                    
                    <div class="row align-items-center">
                        <div class="col-7">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="notificar" name="notificar"
                                       <?= $data['notificar'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notificar">
                                    Notificarme antes
                                </label>
                            </div>
                        </div>
                        <div class="col-5">
                            <select class="form-select form-select-sm" id="dias_anticipacion" name="dias_anticipacion">
                                <option value="1" <?= $data['dias_anticipacion'] == 1 ? 'selected' : '' ?>>1 día antes</option>
                                <option value="2" <?= $data['dias_anticipacion'] == 2 ? 'selected' : '' ?>>2 días antes</option>
                                <option value="3" <?= $data['dias_anticipacion'] == 3 ? 'selected' : '' ?>>3 días antes</option>
                                <option value="5" <?= $data['dias_anticipacion'] == 5 ? 'selected' : '' ?>>5 días antes</option>
                                <option value="7" <?= $data['dias_anticipacion'] == 7 ? 'selected' : '' ?>>1 semana antes</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= uiModuleUrl('recurrentes') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cuentas -->
<div class="modal fade" id="modalCuentas" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i>Seleccionar Cuenta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($cuentas as $cuenta): ?>
                    <button type="button" class="list-group-item list-group-item-action cuenta-option py-3 <?= $data['cuenta_id'] == $cuenta['id'] ? 'active' : '' ?>"
                            data-id="<?= $cuenta['id'] ?>"
                            data-nombre="<?= htmlspecialchars($cuenta['nombre']) ?>"
                            data-icono="<?= htmlspecialchars($cuenta['icono']) ?>"
                            data-color="<?= htmlspecialchars($cuenta['color']) ?>">
                        <div class="d-flex align-items-center">
                            <div class="selector-icon me-3" style="background-color: <?= htmlspecialchars($cuenta['color']) ?>;">
                                <i class="bi <?= htmlspecialchars($cuenta['icono']) ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <span class="fw-semibold"><?= htmlspecialchars($cuenta['nombre']) ?></span>
                                <small class="d-block text-muted"><?= formatMoney($cuenta['saldo_actual']) ?></small>
                            </div>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Categorías -->
<div class="modal fade" id="modalCategorias" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-tag me-2"></i>Seleccionar Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group list-group-flush" id="listaCategorias">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Frecuencias -->
<div class="modal fade" id="modalFrecuencias" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-repeat me-2"></i>Frecuencia de Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($frecuencias as $key => $freq): ?>
                    <button type="button" class="list-group-item list-group-item-action frecuencia-option py-3 <?= $data['frecuencia'] === $key ? 'active' : '' ?>"
                            data-value="<?= $key ?>"
                            data-nombre="<?= htmlspecialchars($freq['nombre']) ?>"
                            data-icono="<?= htmlspecialchars($freq['icono']) ?>"
                            data-descripcion="<?= htmlspecialchars($freq['descripcion']) ?>">
                        <div class="d-flex align-items-center">
                            <div class="selector-icon me-3" style="background-color: var(--primary-blue);">
                                <i class="bi <?= htmlspecialchars($freq['icono']) ?>"></i>
                            </div>
                            <div>
                                <span class="fw-semibold"><?= htmlspecialchars($freq['nombre']) ?></span>
                                <small class="d-block text-muted"><?= htmlspecialchars($freq['descripcion']) ?></small>
                            </div>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Días del Mes -->
<div class="modal fade" id="modalDias" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar3 me-2"></i>Día del Mes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Si seleccionas un día mayor al último del mes (ej: 31 en febrero), 
                    el cobro se realizará el <strong>último día disponible</strong> de ese mes.
                </div>
                <div class="row g-2">
                    <?php for ($i = 1; $i <= 31; $i++): ?>
                    <div class="col-auto">
                        <button type="button" class="btn dia-option <?= ($data['dia_ejecucion'] ?? 1) == $i ? 'btn-primary' : 'btn-outline-secondary' ?>"
                                data-dia="<?= $i ?>"><?= $i ?></button>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Días de la Semana -->
<div class="modal fade" id="modalDiasSemana" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-week me-2"></i>Día de la Semana</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($diasSemana as $num => $nombre): ?>
                    <button type="button" class="list-group-item list-group-item-action dia-semana-option py-3"
                            data-dia="<?= $num ?>" data-nombre="<?= $nombre ?>">
                        <span class="fw-semibold"><?= $nombre ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
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

.selector-btn {
    display: flex; align-items: center; justify-content: space-between;
    border: 1px solid var(--border-color, #dee2e6); border-radius: 12px; background: var(--bg-card, white);
    text-align: left; transition: all 0.2s ease; color: var(--text-primary, #333);
}
.selector-btn:hover { border-color: var(--primary-blue); background: rgba(85, 165, 200, 0.1); }

.selector-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 18px; flex-shrink: 0;
}

.dia-option {
    width: 44px; height: 44px; border-radius: 10px; font-weight: 600;
}

.list-group-item { background: var(--bg-card, white); color: var(--text-primary, #333); border-color: var(--border-color, #dee2e6); }
.list-group-item.active { background-color: rgba(85, 165, 200, 0.15); border-color: var(--primary-blue); }
.list-group-item:hover { background-color: var(--bg-hover, #f8f9fa); }
</style>

<?php
$categoriasIngresoJson = json_encode($categoriasIngreso);
$categoriasEgresoJson = json_encode($categoriasEgreso);
$categoriaSeleccionadaId = (int)$data['categoria_id'];
?>

<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cuentaIdInput = document.getElementById('cuenta_id');
    const categoriaIdInput = document.getElementById('categoria_id');
    const frecuenciaInput = document.getElementById('frecuencia');
    const diaEjecucionInput = document.getElementById('dia_ejecucion');
    const diaSemanaInput = document.getElementById('dia_semana');
    
    const cuentaSeleccionada = document.getElementById('cuentaSeleccionada');
    const categoriaSeleccionada = document.getElementById('categoriaSeleccionada');
    const frecuenciaSeleccionada = document.getElementById('frecuenciaSeleccionada');
    const diaSeleccionado = document.getElementById('diaSeleccionado');
    const diaSemanaSeleccionado = document.getElementById('diaSemanaSeleccionado');
    
    const diaWrapper = document.getElementById('diaEjecucionWrapper');
    const diaSemanaWrapper = document.getElementById('diaSemanaWrapper');
    const diasQuincenalesWrapper = document.getElementById('diasQuincenalesWrapper');
    
    const listaCategorias = document.getElementById('listaCategorias');
    const categoriasIngreso = <?= $categoriasIngresoJson ?>;
    const categoriasEgreso = <?= $categoriasEgresoJson ?>;
    let categoriaSeleccionadaId = <?= $categoriaSeleccionadaId ?>;
    
    // Actualizar categorías según tipo
    function actualizarCategorias() {
        const tipo = document.querySelector('input[name="tipo"]:checked').value;
        const categorias = tipo === 'ingreso' ? categoriasIngreso : categoriasEgreso;
        
        listaCategorias.innerHTML = categorias.map(cat => `
            <button type="button" class="list-group-item list-group-item-action categoria-option py-3 ${cat.id == categoriaSeleccionadaId ? 'active' : ''}"
                    data-id="${cat.id}" data-nombre="${cat.nombre}" 
                    data-icono="${cat.icono}" data-color="${cat.color}">
                <div class="d-flex align-items-center">
                    <div class="selector-icon me-3" style="background-color: ${cat.color}20; color: ${cat.color};">
                        <i class="bi ${cat.icono}"></i>
                    </div>
                    <span class="fw-semibold">${cat.nombre}</span>
                </div>
            </button>
        `).join('');
        
        // Rebind events
        document.querySelectorAll('.categoria-option').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const nombre = this.dataset.nombre;
                const icono = this.dataset.icono;
                const color = this.dataset.color;
                
                categoriaIdInput.value = id;
                categoriaSeleccionadaId = id;
                categoriaSeleccionada.innerHTML = `
                    <div class="selector-icon me-3" style="background-color: ${color}20; color: ${color};">
                        <i class="bi ${icono}"></i>
                    </div>
                    <span class="fw-semibold">${nombre}</span>
                `;
                bootstrap.Modal.getInstance(document.getElementById('modalCategorias')).hide();
            });
        });
    }
    
    // Actualizar campos según frecuencia
    function actualizarCamposFrecuencia() {
        const frecuencia = frecuenciaInput.value;
        const frecuenciasConDia = ['mensual', 'bimestral', 'trimestral', 'semestral', 'anual'];
        
        diaWrapper.style.display = frecuenciasConDia.includes(frecuencia) ? 'block' : 'none';
        diaSemanaWrapper.style.display = frecuencia === 'semanal' ? 'block' : 'none';
        diasQuincenalesWrapper.style.display = frecuencia === 'quincenal' ? 'block' : 'none';
    }
    
    // Tipo change
    document.querySelectorAll('.tipo-option').forEach(option => {
        option.querySelector('input').addEventListener('change', function() {
            document.querySelectorAll('.tipo-option').forEach(o => o.classList.remove('active', 'ingreso', 'egreso'));
            option.classList.add('active', this.value);
            actualizarCategorias();
        });
    });
    
    // Cuenta selection
    document.querySelectorAll('.cuenta-option').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            const icono = this.dataset.icono;
            const color = this.dataset.color;
            
            cuentaIdInput.value = id;
            document.querySelectorAll('.cuenta-option').forEach(o => o.classList.remove('active'));
            this.classList.add('active');
            
            cuentaSeleccionada.innerHTML = `
                <div class="selector-icon me-3" style="background-color: ${color};">
                    <i class="bi ${icono}"></i>
                </div>
                <span class="fw-semibold">${nombre}</span>
            `;
            bootstrap.Modal.getInstance(document.getElementById('modalCuentas')).hide();
        });
    });
    
    // Frecuencia selection
    document.querySelectorAll('.frecuencia-option').forEach(btn => {
        btn.addEventListener('click', function() {
            const value = this.dataset.value;
            const nombre = this.dataset.nombre;
            const icono = this.dataset.icono;
            const descripcion = this.dataset.descripcion;
            
            frecuenciaInput.value = value;
            frecuenciaSeleccionada.innerHTML = `
                <div class="selector-icon me-3" style="background-color: var(--primary-blue);">
                    <i class="bi ${icono}"></i>
                </div>
                <div>
                    <span class="fw-semibold">${nombre}</span>
                    <small class="d-block text-muted">${descripcion}</small>
                </div>
            `;
            
            document.querySelectorAll('.frecuencia-option').forEach(o => o.classList.remove('active'));
            this.classList.add('active');
            
            actualizarCamposFrecuencia();
            bootstrap.Modal.getInstance(document.getElementById('modalFrecuencias')).hide();
        });
    });
    
    // Día del mes selection
    document.querySelectorAll('.dia-option').forEach(btn => {
        btn.addEventListener('click', function() {
            const dia = this.dataset.dia;
            
            diaEjecucionInput.value = dia;
            diaSeleccionado.innerHTML = `
                <div class="selector-icon me-3" style="background-color: var(--secondary-green);">
                    <span class="fw-bold">${dia}</span>
                </div>
                <div>
                    <span class="fw-semibold">Día ${dia} de cada mes</span>
                    <small class="d-block text-muted">${dia > 28 ? 'En meses cortos se ajustará al último día disponible' : ''}</small>
                </div>
            `;
            
            document.querySelectorAll('.dia-option').forEach(o => {
                o.classList.remove('btn-primary');
                o.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-primary');
            
            bootstrap.Modal.getInstance(document.getElementById('modalDias')).hide();
        });
    });
    
    // Día de la semana selection
    document.querySelectorAll('.dia-semana-option').forEach(btn => {
        btn.addEventListener('click', function() {
            const dia = this.dataset.dia;
            const nombre = this.dataset.nombre;
            
            diaSemanaInput.value = dia;
            diaEjecucionInput.value = dia;
            diaSemanaSeleccionado.innerHTML = `
                <div class="selector-icon me-3" style="background-color: var(--secondary-green);">
                    <i class="bi bi-calendar-week"></i>
                </div>
                <span class="fw-semibold">${nombre}</span>
            `;
            bootstrap.Modal.getInstance(document.getElementById('modalDiasSemana')).hide();
        });
    });
    
    // Inicializar
    actualizarCategorias();
    actualizarCamposFrecuencia();
});
</script>
<?php $extraScripts = ob_get_clean(); ?>
