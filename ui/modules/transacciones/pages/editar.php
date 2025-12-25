<?php
/**
 * AND FINANCE APP - Editar Transacción
 */

require_once __DIR__ . '/../models/TransaccionModel.php';
require_once __DIR__ . '/../../cuentas/models/CuentaModel.php';

$pageTitle = 'Editar Transacción';
$pageSubtitle = 'Modifica los datos del movimiento';
$transaccionModel = new TransaccionModel();
$cuentaModel = new CuentaModel();
$userId = getCurrentUserId();
$db = Database::getInstance();

// Obtener transacción a editar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$transaccion = $transaccionModel->getById($id);

if (!$transaccion || $transaccion['usuario_id'] != $userId) {
    setFlashMessage('error', 'Transacción no encontrada');
    header('Location: ' . uiModuleUrl('transacciones'));
    exit;
}

$errors = [];
$data = $transaccion;

// Obtener cuentas del usuario
$cuentas = $cuentaModel->getAllByUser($userId);

// Obtener categorías
$categorias = $db->prepare("
    SELECT id, nombre, tipo, icono, color FROM categorias 
    WHERE usuario_id = :usuario_id AND es_sistema = 0 AND estado = 1
    ORDER BY tipo, orden, nombre
");
$categorias->execute(['usuario_id' => $userId]);
$categorias = $categorias->fetchAll();

$categoriasIngreso = array_filter($categorias, fn($c) => $c['tipo'] === 'ingreso');
$categoriasEgreso = array_filter($categorias, fn($c) => $c['tipo'] === 'egreso');

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['tipo'] = $_POST['tipo'] ?? 'egreso';
    $data['cuenta_id'] = (int)($_POST['cuenta_id'] ?? 0);
    $data['cuenta_destino_id'] = !empty($_POST['cuenta_destino_id']) ? (int)$_POST['cuenta_destino_id'] : null;
    $data['categoria_id'] = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
    $data['monto'] = (float)str_replace(['.', ','], ['', '.'], $_POST['monto'] ?? 0);
    $data['descripcion'] = trim($_POST['descripcion'] ?? '');
    $data['comentario'] = trim($_POST['comentario'] ?? '');
    $data['fecha_transaccion'] = $_POST['fecha_transaccion'] ?? date('Y-m-d');
    
    // Validaciones
    if (empty($data['cuenta_id'])) {
        $errors[] = 'Debes seleccionar una cuenta';
    }
    
    if ($data['monto'] <= 0) {
        $errors[] = 'El monto debe ser mayor a 0';
    }
    
    if ($data['tipo'] === 'transferencia' && empty($data['cuenta_destino_id'])) {
        $errors[] = 'Para una transferencia, debes seleccionar la cuenta destino';
    }
    
    // Guardar si no hay errores
    if (empty($errors)) {
        $data['realizada'] = isset($_POST['programada']) ? 0 : 1;
        
        try {
            $transaccionModel->update($id, $data);
            
            // Procesar nuevos archivos adjuntos
            if (isset($_FILES['archivos']) && !empty($_FILES['archivos']['name'][0])) {
                $uploadDir = UPLOADS_PATH . '/transacciones/' . $userId . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
                
                foreach ($_FILES['archivos']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['archivos']['error'][$key] === UPLOAD_ERR_OK) {
                        $originalName = $_FILES['archivos']['name'][$key];
                        $mimeType = $_FILES['archivos']['type'][$key];
                        $size = $_FILES['archivos']['size'][$key];
                        
                        // Validar tipo
                        if (!in_array($mimeType, $allowedTypes)) {
                            continue;
                        }
                        
                        // Validar tamaño
                        $maxSize = strpos($mimeType, 'image') !== false ? UPLOAD_MAX_IMAGE_SIZE : UPLOAD_MAX_PDF_SIZE;
                        if ($size > $maxSize) {
                            continue;
                        }
                        
                        // Generar nombre único
                        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                        $uniqueKey = uniqid();
                        $fileName = 'trans_' . $id . '_' . $uniqueKey . '.' . $extension;
                        
                        if (move_uploaded_file($tmpName, $uploadDir . $fileName)) {
                            $transaccionModel->guardarArchivo($id, [
                                'nombre_original' => $originalName,
                                'nombre_archivo' => $fileName,
                                'ruta' => 'transacciones/' . $userId . '/' . $fileName,
                                'tipo_archivo' => strpos($mimeType, 'image') !== false ? 'imagen' : 'pdf',
                                'mime_type' => $mimeType,
                                'tamano' => $size
                            ]);
                        }
                    }
                }
            }
            
            setFlashMessage('success', 'Transacción actualizada correctamente');
            ob_end_clean();
            header('Location: ' . uiModuleUrl('transacciones'));
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

// Verificar si la transacción está programada
$esProgramada = isset($transaccion['realizada']) && $transaccion['realizada'] == 0;

// Determinar si la transacción original es transferencia
$esTransferenciaOriginal = $transaccion['tipo'] === 'transferencia';

// Obtener archivos adjuntos existentes
$archivosExistentes = $transaccionModel->getArchivos($id);
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card fade-in-up">
            <div class="card-body p-0">
                <!-- Tabs de tipo -->
                <div class="tipo-tabs d-flex">
                    <?php if ($esTransferenciaOriginal): ?>
                    <!-- Si es transferencia, solo mostrar transferencia -->
                    <button type="button" class="tipo-tab flex-fill disabled" disabled title="No se puede cambiar una transferencia a ingreso">
                        <i class="bi bi-arrow-down-circle me-2"></i>Ingreso
                    </button>
                    <button type="button" class="tipo-tab flex-fill disabled" disabled title="No se puede cambiar una transferencia a gasto">
                        <i class="bi bi-arrow-up-circle me-2"></i>Gasto
                    </button>
                    <button type="button" class="tipo-tab flex-fill active transferencia" data-tipo="transferencia">
                        <i class="bi bi-arrow-left-right me-2"></i>Transferencia
                    </button>
                    <?php else: ?>
                    <!-- Si no es transferencia, solo mostrar ingreso/gasto -->
                    <button type="button" class="tipo-tab flex-fill <?= $data['tipo'] === 'ingreso' ? 'active ingreso' : '' ?>" data-tipo="ingreso">
                        <i class="bi bi-arrow-down-circle me-2"></i>Ingreso
                    </button>
                    <button type="button" class="tipo-tab flex-fill <?= $data['tipo'] === 'egreso' ? 'active egreso' : '' ?>" data-tipo="egreso">
                        <i class="bi bi-arrow-up-circle me-2"></i>Gasto
                    </button>
                    <button type="button" class="tipo-tab flex-fill disabled" disabled title="No se puede convertir a transferencia">
                        <i class="bi bi-arrow-left-right me-2"></i>Transferencia
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="p-4">
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="formTransaccion" enctype="multipart/form-data">
                        <input type="hidden" name="tipo" id="tipoInput" value="<?= htmlspecialchars($data['tipo']) ?>">
                        
                        <!-- Monto -->
                        <div class="monto-input-container mb-4">
                            <label class="form-label">Monto</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control form-control-lg text-end monto-input money-input" 
                                       id="monto" name="monto" 
                                       value="<?= number_format($data['monto'], 0, ',', '.') ?>"
                                       inputmode="numeric"
                                       required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Cuenta Origen -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <span id="labelCuentaOrigen">Cuenta</span> <span class="text-danger">*</span>
                                    </label>
                                    <input type="hidden" name="cuenta_id" id="cuenta_id" value="<?= $data['cuenta_id'] ?>">
                                    <button type="button" class="selector-visual w-100" id="btnSelectCuenta" onclick="abrirModalCuentas('origen')">
                                        <div class="selector-placeholder" id="cuentaOrigenPreview">
                                            <i class="bi bi-wallet2 me-2"></i>Seleccionar cuenta
                                        </div>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Cuenta Destino -->
                            <div class="col-md-6" id="cuentaDestinoWrapper" style="display: <?= $data['tipo'] === 'transferencia' ? 'block' : 'none' ?>;">
                                <div class="mb-3">
                                    <label class="form-label">Cuenta destino <span class="text-danger">*</span></label>
                                    <input type="hidden" name="cuenta_destino_id" id="cuenta_destino_id" value="<?= $data['cuenta_destino_id'] ?>">
                                    <button type="button" class="selector-visual w-100" id="btnSelectCuentaDestino" onclick="abrirModalCuentas('destino')">
                                        <div class="selector-placeholder" id="cuentaDestinoPreview">
                                            <i class="bi bi-wallet2 me-2"></i>Seleccionar cuenta destino
                                        </div>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Categoría -->
                            <div class="col-md-6" id="categoriaWrapper" style="display: <?= $data['tipo'] !== 'transferencia' ? 'block' : 'none' ?>;">
                                <div class="mb-3">
                                    <label class="form-label">Categoría</label>
                                    <input type="hidden" name="categoria_id" id="categoria_id" value="<?= $data['categoria_id'] ?>">
                                    <button type="button" class="selector-visual w-100" id="btnSelectCategoria" onclick="abrirModalCategorias()">
                                        <div class="selector-placeholder" id="categoriaPreview">
                                            <i class="bi bi-tag me-2"></i>Seleccionar categoría
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fecha_transaccion" class="form-label">Fecha <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="fecha_transaccion" name="fecha_transaccion" 
                                           value="<?= htmlspecialchars($data['fecha_transaccion']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="descripcion" class="form-label">Descripción</label>
                                    <input type="text" class="form-control" id="descripcion" name="descripcion" 
                                           value="<?= htmlspecialchars($data['descripcion'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comentario" class="form-label">Notas adicionales</label>
                            <textarea class="form-control" id="comentario" name="comentario" rows="2"><?= htmlspecialchars($data['comentario'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- Archivos adjuntos existentes -->
                        <?php if (!empty($archivosExistentes)): ?>
                        <div class="mb-3">
                            <label class="form-label">Comprobantes actuales (<?= count($archivosExistentes) ?>)</label>
                            <div class="archivos-lista">
                                <?php foreach ($archivosExistentes as $archivo): 
                                    $esImagen = $archivo['tipo_archivo'] === 'imagen';
                                    $urlVer = UI_URL . '/modules/transacciones/api/ver_archivo.php?id=' . $archivo['id'];
                                    $urlDescargar = $urlVer . '&download=1';
                                ?>
                                <div class="archivo-item d-flex align-items-center gap-2 mb-2 p-3 rounded" style="background: var(--bg-input, #f8f9fa);">
                                    <?php if ($esImagen): ?>
                                    <div class="archivo-thumbnail" style="width: 48px; height: 48px; border-radius: 8px; overflow: hidden; flex-shrink: 0;">
                                        <img src="<?= $urlVer ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <?php else: ?>
                                    <div class="archivo-icon" style="width: 48px; height: 48px; border-radius: 8px; background: rgba(220, 53, 69, 0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="bi bi-file-pdf fs-4" style="color: #dc3545;"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="fw-medium text-truncate" style="color: var(--text-primary, #333);"><?= htmlspecialchars($archivo['nombre_original']) ?></div>
                                        <small class="text-muted"><?= round($archivo['tamano'] / 1024, 1) ?> KB</small>
                                    </div>
                                    <div class="archivo-actions d-flex gap-1">
                                        <a href="<?= $urlVer ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= $urlDescargar ?>" class="btn btn-sm btn-outline-secondary" title="Descargar">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Subir nuevos comprobantes -->
                        <div class="mb-4">
                            <label class="form-label">Agregar comprobantes (opcional)</label>
                            <input type="file" 
                                   class="form-control" 
                                   id="archivos" 
                                   name="archivos[]" 
                                   multiple
                                   accept="image/*,application/pdf">
                            <small class="text-muted">Imágenes (máx. 5MB) o PDF (máx. 10MB). Puedes seleccionar varios archivos.</small>
                        </div>
                        
                        <!-- Opción de programar -->
                        <div class="programar-option mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="programada" name="programada" value="1"
                                       <?= $esProgramada ? 'checked' : '' ?>>
                                <label class="form-check-label" for="programada">
                                    <i class="bi bi-clock me-1"></i>
                                    <strong>Transacción programada</strong>
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1 ms-4">
                                <?php if ($esProgramada): ?>
                                Esta transacción está pendiente. Desmarca para aplicarla a tu saldo.
                                <?php else: ?>
                                La transacción está realizada. Marca para revertir el saldo y dejarla pendiente.
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?= uiModuleUrl('transacciones') ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg px-5" id="btnGuardar">
                                <i class="bi bi-check-lg me-2"></i>Actualizar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tipo-tabs { border-bottom: 2px solid var(--border-light, #f0f0f0); }
.tipo-tab {
    padding: 18px; background: none; border: none; font-weight: 600;
    color: var(--text-muted, var(--tertiary-gray)); transition: all 0.3s ease; position: relative;
}
.tipo-tab:hover:not(.disabled) { color: var(--text-primary, var(--dark-blue)); background: var(--bg-hover, #f8f9fa); }
.tipo-tab.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 3px; }
.tipo-tab.active.ingreso { color: #5a9a3e; }
.tipo-tab.active.ingreso::after { background: #9AD082; }
.tipo-tab.active.egreso { color: #ee5a5a; }
.tipo-tab.active.egreso::after { background: #FF6B6B; }
.tipo-tab.active.transferencia { color: var(--dark-blue); }
.tipo-tab.active.transferencia::after { background: var(--primary-blue); }
.monto-input { font-size: 2rem !important; font-weight: 700; }
.monto-input-container .input-group-text { font-size: 1.5rem; font-weight: 600; color: var(--dark-blue); }
.programar-option {
    background: linear-gradient(90deg, rgba(247, 220, 111, 0.15), rgba(247, 220, 111, 0.05));
    border-left: 3px solid #f7dc6f; padding: 16px; border-radius: 0 12px 12px 0;
}
.programar-option .form-check-input:checked { background-color: #f7dc6f; border-color: #f7dc6f; }

/* Selectores visuales */
.selector-visual {
    display: flex; align-items: center; padding: 12px 16px;
    border: 2px dashed var(--border-color, #dee2e6); border-radius: 12px;
    background: var(--bg-input, #f8f9fa); cursor: pointer; transition: all 0.2s ease; text-align: left;
}
.selector-visual:hover { border-color: var(--primary-blue); background: rgba(85, 165, 200, 0.1); }
.selector-visual.selected { border-style: solid; border-color: var(--primary-blue); background: var(--bg-card, white); }
.selector-placeholder { color: var(--text-muted, #6c757d); font-size: 14px; }
.selector-content { display: flex; align-items: center; gap: 12px; width: 100%; }
.selector-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 18px; flex-shrink: 0;
}
.selector-info { flex: 1; min-width: 0; }
.selector-name { font-weight: 600; color: var(--text-primary, var(--dark-blue)); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.selector-detail { font-size: 12px; color: var(--text-muted, #6c757d); }

/* Modal items */
.modal-item {
    display: flex; align-items: center; padding: 12px; border-radius: 12px;
    cursor: pointer; transition: all 0.15s ease; border: 2px solid transparent;
}
.modal-item:hover { background: var(--bg-hover, #f8f9fa); }
.modal-item.selected { background: rgba(85, 165, 200, 0.15); border-color: var(--primary-blue); }
.modal-item-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 20px; margin-right: 12px; flex-shrink: 0;
}
.modal-item-info { flex: 1; }
.modal-item-name { font-weight: 600; color: var(--text-primary, var(--dark-blue)); }
.modal-item-detail { font-size: 13px; color: var(--text-muted, #6c757d); }
.modal-item-check { color: var(--primary-blue); font-size: 20px; opacity: 0; }
.modal-item.selected .modal-item-check { opacity: 1; }

/* Tabs deshabilitados */
.tipo-tab.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
}
</style>

<!-- Modal Selección de Cuenta -->
<div class="modal fade" id="modalCuentas" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="modalCuentasTitle">Seleccionar cuenta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <div id="listaCuentasModal">
                    <?php foreach ($cuentas as $cuenta): ?>
                    <div class="modal-item" 
                         data-id="<?= $cuenta['id'] ?>"
                         data-nombre="<?= htmlspecialchars($cuenta['nombre']) ?>"
                         data-color="<?= htmlspecialchars($cuenta['color'] ?? '#55A5C8') ?>"
                         data-icono="<?= htmlspecialchars($cuenta['icono'] ?? 'bi-wallet2') ?>"
                         data-saldo="<?= $cuenta['saldo_actual'] ?>"
                         data-banco="<?= htmlspecialchars($cuenta['banco_nombre'] ?? '') ?>">
                        <div class="modal-item-icon" style="background-color: <?= $cuenta['color'] ?? '#55A5C8' ?>">
                            <i class="bi <?= $cuenta['icono'] ?? 'bi-wallet2' ?>"></i>
                        </div>
                        <div class="modal-item-info">
                            <div class="modal-item-name"><?= htmlspecialchars($cuenta['nombre']) ?></div>
                            <div class="modal-item-detail">
                                <?= $cuenta['banco_nombre'] ? htmlspecialchars($cuenta['banco_nombre']) . ' · ' : '' ?>
                                <?= formatMoney($cuenta['saldo_actual']) ?>
                            </div>
                        </div>
                        <i class="bi bi-check-circle-fill modal-item-check"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Selección de Categoría -->
<div class="modal fade" id="modalCategorias" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Seleccionar categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <div id="listaCategoriasModal"></div>
                <div class="text-center py-4 text-muted" id="sinCategorias" style="display: none;">
                    <i class="bi bi-tag display-4"></i>
                    <p class="mt-2 mb-0">No tienes categorías creadas</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$categoriasIngresoJson = json_encode(array_values($categoriasIngreso));
$categoriasEgresoJson = json_encode(array_values($categoriasEgreso));
$cuentasJson = json_encode($cuentas);
$categoriaSeleccionada = (int)$data['categoria_id'];
$cuentaSeleccionada = (int)$data['cuenta_id'];
$cuentaDestinoSeleccionadaVal = (int)($data['cuenta_destino_id'] ?? 0);
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoTabs = document.querySelectorAll('.tipo-tab');
    const tipoInput = document.getElementById('tipoInput');
    const cuentaDestinoWrapper = document.getElementById('cuentaDestinoWrapper');
    const categoriaWrapper = document.getElementById('categoriaWrapper');
    const labelCuentaOrigen = document.getElementById('labelCuentaOrigen');
    const btnGuardar = document.getElementById('btnGuardar');
    
    const categoriasIngreso = <?= $categoriasIngresoJson ?>;
    const categoriasEgreso = <?= $categoriasEgresoJson ?>;
    const cuentas = <?= $cuentasJson ?>;
    
    let cuentaSeleccionada = <?= $cuentaSeleccionada ?>;
    let cuentaDestinoSeleccionada = <?= $cuentaDestinoSeleccionadaVal ?>;
    let categoriaSeleccionada = <?= $categoriaSeleccionada ?>;
    let modoSeleccionCuenta = 'origen';
    
    const modalCuentas = new bootstrap.Modal(document.getElementById('modalCuentas'));
    const modalCategorias = new bootstrap.Modal(document.getElementById('modalCategorias'));
    
    function actualizarUI(tipo) {
        tipoTabs.forEach(tab => {
            tab.classList.remove('active', 'ingreso', 'egreso', 'transferencia');
            if (tab.dataset.tipo === tipo) tab.classList.add('active', tipo);
        });
        tipoInput.value = tipo;
        
        if (tipo === 'transferencia') {
            cuentaDestinoWrapper.style.display = 'block';
            categoriaWrapper.style.display = 'none';
            labelCuentaOrigen.textContent = 'Cuenta origen';
        } else {
            cuentaDestinoWrapper.style.display = 'none';
            categoriaWrapper.style.display = 'block';
            labelCuentaOrigen.textContent = 'Cuenta';
        }
        
        btnGuardar.className = 'btn btn-lg px-5 ';
        btnGuardar.className += tipo === 'ingreso' ? 'btn-success' : (tipo === 'egreso' ? 'btn-danger' : 'btn-primary');
    }
    
    // Modal Cuentas
    window.abrirModalCuentas = function(modo) {
        modoSeleccionCuenta = modo;
        document.getElementById('modalCuentasTitle').textContent = modo === 'origen' ? 'Seleccionar cuenta' : 'Seleccionar cuenta destino';
        const idSeleccionado = modo === 'origen' ? cuentaSeleccionada : cuentaDestinoSeleccionada;
        document.querySelectorAll('#listaCuentasModal .modal-item').forEach(item => {
            const itemId = parseInt(item.dataset.id);
            // En modo destino, ocultar la cuenta origen seleccionada
            if (modo === 'destino' && cuentaSeleccionada && itemId === cuentaSeleccionada) {
                item.style.display = 'none';
            } else {
                item.style.display = 'flex';
            }
            item.classList.toggle('selected', itemId == idSeleccionado);
        });
        modalCuentas.show();
    };
    
    document.querySelectorAll('#listaCuentasModal .modal-item').forEach(item => {
        item.addEventListener('click', function() {
            const data = { id: parseInt(this.dataset.id), nombre: this.dataset.nombre, color: this.dataset.color, icono: this.dataset.icono, saldo: parseFloat(this.dataset.saldo), banco: this.dataset.banco };
            if (modoSeleccionCuenta === 'origen') {
                cuentaSeleccionada = data.id;
                document.getElementById('cuenta_id').value = data.id;
                actualizarPreviewCuenta('origen', data);
                // Si la cuenta origen es igual a la destino, limpiar destino
                if (cuentaDestinoSeleccionada === data.id) {
                    cuentaDestinoSeleccionada = null;
                    document.getElementById('cuenta_destino_id').value = '';
                    actualizarPreviewCuenta('destino', null);
                }
            } else {
                cuentaDestinoSeleccionada = data.id;
                document.getElementById('cuenta_destino_id').value = data.id;
                actualizarPreviewCuenta('destino', data);
            }
            modalCuentas.hide();
        });
    });
    
    function actualizarPreviewCuenta(modo, cuenta) {
        const preview = document.getElementById(modo === 'origen' ? 'cuentaOrigenPreview' : 'cuentaDestinoPreview');
        const btn = document.getElementById(modo === 'origen' ? 'btnSelectCuenta' : 'btnSelectCuentaDestino');
        if (cuenta) {
            preview.innerHTML = `<div class="selector-content"><div class="selector-icon" style="background-color: ${cuenta.color}"><i class="bi ${cuenta.icono}"></i></div><div class="selector-info"><div class="selector-name">${cuenta.nombre}</div><div class="selector-detail">${cuenta.banco ? cuenta.banco + ' · ' : ''}${formatMoney(cuenta.saldo)}</div></div><i class="bi bi-chevron-down text-muted"></i></div>`;
            btn.classList.add('selected');
        } else {
            preview.innerHTML = `<i class="bi bi-wallet2 me-2"></i>${modo === 'origen' ? 'Seleccionar cuenta' : 'Seleccionar cuenta destino'}`;
            btn.classList.remove('selected');
        }
    }
    
    // Modal Categorías
    window.abrirModalCategorias = function() {
        const tipo = tipoInput.value;
        const categorias = tipo === 'ingreso' ? categoriasIngreso : categoriasEgreso;
        const container = document.getElementById('listaCategoriasModal');
        const sinCategorias = document.getElementById('sinCategorias');
        
        if (categorias.length === 0) {
            container.style.display = 'none';
            sinCategorias.style.display = 'block';
        } else {
            container.style.display = 'block';
            sinCategorias.style.display = 'none';
            container.innerHTML = categorias.map(cat => `<div class="modal-item ${cat.id == categoriaSeleccionada ? 'selected' : ''}" data-id="${cat.id}" data-nombre="${cat.nombre}" data-color="${cat.color || '#6c757d'}" data-icono="${cat.icono || 'bi-tag'}"><div class="modal-item-icon" style="background-color: ${cat.color || '#6c757d'}"><i class="bi ${cat.icono || 'bi-tag'}"></i></div><div class="modal-item-info"><div class="modal-item-name">${cat.nombre}</div></div><i class="bi bi-check-circle-fill modal-item-check"></i></div>`).join('');
            container.querySelectorAll('.modal-item').forEach(item => {
                item.addEventListener('click', function() {
                    categoriaSeleccionada = parseInt(this.dataset.id);
                    document.getElementById('categoria_id').value = categoriaSeleccionada;
                    actualizarPreviewCategoria({ id: categoriaSeleccionada, nombre: this.dataset.nombre, color: this.dataset.color, icono: this.dataset.icono });
                    modalCategorias.hide();
                });
            });
        }
        modalCategorias.show();
    };
    
    function actualizarPreviewCategoria(cat) {
        const preview = document.getElementById('categoriaPreview');
        const btn = document.getElementById('btnSelectCategoria');
        if (cat) {
            preview.innerHTML = `<div class="selector-content"><div class="selector-icon" style="background-color: ${cat.color}"><i class="bi ${cat.icono}"></i></div><div class="selector-info"><div class="selector-name">${cat.nombre}</div></div><i class="bi bi-chevron-down text-muted"></i></div>`;
            btn.classList.add('selected');
        } else {
            preview.innerHTML = '<i class="bi bi-tag me-2"></i>Seleccionar categoría';
            btn.classList.remove('selected');
        }
    }
    
    // Inicializar
    tipoTabs.forEach(tab => { tab.addEventListener('click', function() { actualizarUI(this.dataset.tipo); }); });
    
    if (cuentaSeleccionada) {
        const c = cuentas.find(x => x.id == cuentaSeleccionada);
        if (c) actualizarPreviewCuenta('origen', { id: c.id, nombre: c.nombre, color: c.color || '#55A5C8', icono: c.icono || 'bi-wallet2', saldo: c.saldo_actual, banco: c.banco_nombre });
    }
    if (cuentaDestinoSeleccionada) {
        const c = cuentas.find(x => x.id == cuentaDestinoSeleccionada);
        if (c) actualizarPreviewCuenta('destino', { id: c.id, nombre: c.nombre, color: c.color || '#55A5C8', icono: c.icono || 'bi-wallet2', saldo: c.saldo_actual, banco: c.banco_nombre });
    }
    if (categoriaSeleccionada) {
        const cats = [...categoriasIngreso, ...categoriasEgreso];
        const cat = cats.find(x => x.id == categoriaSeleccionada);
        if (cat) actualizarPreviewCategoria({ id: cat.id, nombre: cat.nombre, color: cat.color || '#6c757d', icono: cat.icono || 'bi-tag' });
    }
    
    actualizarUI(tipoInput.value);
    
    function formatMoney(v) { return '$' + new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v); }
});
</script>

