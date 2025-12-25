<?php
/**
 * AND FINANCE APP - Listado de Cuentas
 */

require_once __DIR__ . '/../models/CuentaModel.php';

$pageTitle = 'Mis Cuentas';
$pageSubtitle = 'Gestiona tus cuentas bancarias y efectivo';
$cuentaModel = new CuentaModel();
$userId = getCurrentUserId();

// Manejar acciones
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'predeterminada' && $id > 0) {
    if ($cuentaModel->setPredeterminada($id, $userId)) {
        setFlashMessage('success', 'Cuenta establecida como predeterminada');
    } else {
        setFlashMessage('error', 'No se pudo actualizar la cuenta');
    }
    ob_end_clean();
    header('Location: ' . uiModuleUrl('cuentas'));
    exit;
}

if ($action === 'delete' && $id > 0) {
    $cuenta = $cuentaModel->getById($id);
    if ($cuenta && $cuenta['usuario_id'] == $userId) {
        $resultado = $cuentaModel->delete($id);
        if ($resultado['success']) {
            setFlashMessage('success', $resultado['message']);
        } else {
            setFlashMessage('error', $resultado['message']);
        }
    }
    ob_end_clean();
    header('Location: ' . uiModuleUrl('cuentas'));
    exit;
}

// Obtener cuentas del usuario
$cuentas = $cuentaModel->getAllByUser($userId);
$saldoTotal = $cuentaModel->getSaldoTotal($userId);
$tiposCuenta = $cuentaModel->getTiposCuenta();
?>

<!-- Header con Saldo Total - Desktop -->
<div class="card fade-in-up mb-4 d-none d-md-block" style="background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));">
    <div class="card-body py-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <p class="text-white-50 mb-1">Saldo Total de Cuentas</p>
                <h2 class="text-white fw-bold mb-0" style="font-size: 2.5rem;">
                    <?= formatMoney($saldoTotal) ?>
                </h2>
                <small class="text-white-50">
                    <i class="bi bi-wallet2 me-1"></i>
                    <?= count($cuentas) ?> cuenta<?= count($cuentas) !== 1 ? 's' : '' ?> activa<?= count($cuentas) !== 1 ? 's' : '' ?>
                </small>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?= uiModuleUrl('cuentas', 'crear') ?>" class="btn btn-light">
                    <i class="bi bi-plus-lg me-2"></i>Nueva Cuenta
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Header con Saldo Total - Mobile Compacto -->
<div class="cuentas-header-mobile d-md-none fade-in-up mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <span class="cuentas-header-label">Saldo Total</span>
            <span class="cuentas-header-value <?= $saldoTotal >= 0 ? '' : 'negativo' ?>"><?= formatMoney($saldoTotal) ?></span>
        </div>
        <a href="<?= uiModuleUrl('cuentas', 'crear') ?>" class="btn-nueva-cuenta-mobile">
            <i class="bi bi-plus-lg"></i>
        </a>
    </div>
    <span class="cuentas-header-count"><i class="bi bi-wallet2 me-1"></i><?= count($cuentas) ?> cuenta<?= count($cuentas) !== 1 ? 's' : '' ?></span>
</div>

<!-- Listado de Cuentas - Desktop -->
<div class="row g-4 d-none d-md-flex">
    <?php if (empty($cuentas)): ?>
    <div class="col-12">
        <div class="card fade-in-up">
            <div class="card-body text-center py-5">
                <i class="bi bi-wallet2 display-1 text-muted"></i>
                <h5 class="mt-4 mb-3">No tienes cuentas registradas</h5>
                <p class="text-muted mb-4">Comienza creando tu primera cuenta para empezar a registrar tus transacciones.</p>
                <a href="<?= uiModuleUrl('cuentas', 'crear') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Crear mi primera cuenta
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>
        <?php foreach ($cuentas as $index => $cuenta): ?>
        <div class="col-lg-6 col-xl-4">
            <div class="card h-100 cuenta-card fade-in-up" style="animation-delay: <?= ($index * 0.05) ?>s;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="cuenta-icon" style="background-color: <?= htmlspecialchars($cuenta['color'] ?? '#55A5C8') ?>;">
                            <i class="bi <?= htmlspecialchars($cuenta['icono'] ?? 'bi-wallet2') ?>"></i>
                        </div>
                        <div class="cuenta-actions">
                            <a href="<?= uiModuleUrl('cuentas', 'editar', ['id' => $cuenta['id']]) ?>" 
                               class="btn-cuenta-action btn-cuenta-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <button type="button" 
                                    class="btn-cuenta-action btn-cuenta-delete" 
                                    title="Eliminar"
                                    onclick="mostrarModalEliminar(<?= $cuenta['id'] ?>)">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </div>
                    </div>
                    
                    <h5 class="cuenta-nombre mb-1">
                        <?= htmlspecialchars($cuenta['nombre']) ?>
                        <?php if ($cuenta['es_predeterminada']): ?>
                        <i class="bi bi-star-fill text-warning ms-1" title="Cuenta predeterminada" style="font-size: 14px;"></i>
                        <?php endif; ?>
                    </h5>
                    
                    <p class="text-muted mb-3">
                        <?php if ($cuenta['banco_nombre']): ?>
                            <?php if ($cuenta['banco_personalizado']): ?>
                                <i class="bi bi-building me-1" style="color: #9AD082;"></i>
                            <?php else: ?>
                                <i class="bi bi-bank me-1"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($cuenta['banco_nombre']) ?>
                        <?php else: ?>
                            <i class="bi <?= $tiposCuenta[$cuenta['tipo']]['icono'] ?? 'bi-wallet2' ?> me-1"></i>
                            <?= $tiposCuenta[$cuenta['tipo']]['nombre'] ?? ucfirst($cuenta['tipo']) ?>
                        <?php endif; ?>
                    </p>
                    
                    <div class="cuenta-saldo">
                        <small class="text-muted">Saldo actual</small>
                        <h3 class="mb-0 <?= $cuenta['saldo_actual'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatMoney($cuenta['saldo_actual']) ?>
                        </h3>
                        <a href="<?= uiModuleUrl('cuentas', 'ajustar', ['id' => $cuenta['id']]) ?>" 
                           class="btn-ajustar-saldo mt-2">
                            <i class="bi bi-sliders me-1"></i>Ajustar Saldo
                        </a>
                    </div>
                    
                    <?php if (!$cuenta['incluir_en_total']): ?>
                    <div class="mt-2">
                        <span class="badge bg-secondary">
                            <i class="bi bi-eye-slash me-1"></i>Excluida del total
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent border-0 pt-0">
                    <a href="<?= uiModuleUrl('transacciones') ?>&cuenta_id=<?= $cuenta['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-list-ul me-1"></i>Ver Transacciones
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Listado de Cuentas - Mobile Compacto -->
<div class="cuentas-list-mobile d-md-none">
    <?php if (empty($cuentas)): ?>
    <div class="card fade-in-up">
        <div class="card-body text-center py-4">
            <i class="bi bi-wallet2 fs-1 text-muted"></i>
            <p class="text-muted mt-3 mb-3">No tienes cuentas registradas</p>
            <a href="<?= uiModuleUrl('cuentas', 'crear') ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Crear cuenta
            </a>
        </div>
    </div>
    <?php else: ?>
        <?php foreach ($cuentas as $index => $cuenta): ?>
        <div class="cuenta-item-mobile fade-in-up" style="animation-delay: <?= ($index * 0.03) ?>s;">
            <div class="cuenta-item-main">
                <div class="cuenta-icon-mobile" style="background-color: <?= htmlspecialchars($cuenta['color'] ?? '#55A5C8') ?>;">
                    <i class="bi <?= htmlspecialchars($cuenta['icono'] ?? 'bi-wallet2') ?>"></i>
                </div>
                <div class="cuenta-info-mobile">
                    <div class="cuenta-nombre-mobile">
                        <?= htmlspecialchars($cuenta['nombre']) ?>
                        <?php if ($cuenta['es_predeterminada']): ?>
                        <i class="bi bi-star-fill text-warning"></i>
                        <?php endif; ?>
                    </div>
                    <div class="cuenta-banco-mobile">
                        <?php if ($cuenta['banco_nombre']): ?>
                            <?= htmlspecialchars($cuenta['banco_nombre']) ?>
                        <?php else: ?>
                            <?= $tiposCuenta[$cuenta['tipo']]['nombre'] ?? ucfirst($cuenta['tipo']) ?>
                        <?php endif; ?>
                        <?php if (!$cuenta['incluir_en_total']): ?>
                        <span class="badge-excluida"><i class="bi bi-eye-slash"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cuenta-saldo-mobile <?= $cuenta['saldo_actual'] >= 0 ? 'positivo' : 'negativo' ?>">
                    <?= formatMoney($cuenta['saldo_actual']) ?>
                </div>
            </div>
            <div class="cuenta-actions-mobile">
                <a href="<?= uiModuleUrl('transacciones') ?>&cuenta_id=<?= $cuenta['id'] ?>" class="cuenta-action-btn ver">
                    <i class="bi bi-list-ul"></i>
                    <span>Movimientos</span>
                </a>
                <a href="<?= uiModuleUrl('cuentas', 'ajustar', ['id' => $cuenta['id']]) ?>" class="cuenta-action-btn ajustar">
                    <i class="bi bi-sliders"></i>
                    <span>Ajustar</span>
                </a>
                <a href="<?= uiModuleUrl('cuentas', 'editar', ['id' => $cuenta['id']]) ?>" class="cuenta-action-btn editar">
                    <i class="bi bi-pencil"></i>
                    <span>Editar</span>
                </a>
                <button type="button" class="cuenta-action-btn eliminar" onclick="mostrarModalEliminar(<?= $cuenta['id'] ?>)">
                    <i class="bi bi-trash"></i>
                    <span>Eliminar</span>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
/* ===== MOBILE HEADER ===== */
.cuentas-header-mobile {
    background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
    border-radius: 16px;
    padding: 16px;
    color: white;
}

.cuentas-header-label {
    font-size: 12px;
    opacity: 0.8;
    display: block;
}

.cuentas-header-value {
    font-size: 24px;
    font-weight: 700;
    display: block;
}

.cuentas-header-value.negativo {
    color: #ffcdd2;
}

.cuentas-header-count {
    font-size: 11px;
    opacity: 0.7;
}

.btn-nueva-cuenta-mobile {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255,255,255,0.2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-nueva-cuenta-mobile:hover {
    background: rgba(255,255,255,0.3);
    color: white;
    transform: scale(1.05);
}

/* ===== MOBILE CUENTAS LIST ===== */
.cuentas-list-mobile {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.cuenta-item-mobile {
    background: var(--bg-card, white);
    border-radius: 14px;
    padding: 14px;
    box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.05));
}

.cuenta-item-main {
    display: flex;
    align-items: center;
    gap: 12px;
}

.cuenta-icon-mobile {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    flex-shrink: 0;
}

.cuenta-info-mobile {
    flex: 1;
    min-width: 0;
}

.cuenta-nombre-mobile {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary, var(--dark-blue));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.cuenta-nombre-mobile i {
    font-size: 10px;
    margin-left: 4px;
}

.cuenta-banco-mobile {
    font-size: 11px;
    color: var(--text-muted, #888);
    display: flex;
    align-items: center;
    gap: 6px;
}

.badge-excluida {
    font-size: 10px;
    color: var(--text-muted, #999);
}

.cuenta-saldo-mobile {
    font-size: 14px;
    font-weight: 700;
    text-align: right;
    flex-shrink: 0;
}

.cuenta-saldo-mobile.positivo { color: #5a9a3e; }
.cuenta-saldo-mobile.negativo { color: #ee5a5a; }

.cuenta-actions-mobile {
    display: flex;
    gap: 6px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--border-light, #f0f0f0);
}

.cuenta-action-btn {
    flex: 1;
    padding: 8px 4px;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.cuenta-action-btn i {
    font-size: 14px;
}

.cuenta-action-btn span {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.2px;
}

.cuenta-action-btn.ver {
    background: rgba(85, 165, 200, 0.12);
    color: var(--primary-blue);
}

.cuenta-action-btn.ajustar {
    background: rgba(255, 152, 0, 0.12);
    color: #f57c00;
}

.cuenta-action-btn.editar {
    background: rgba(154, 208, 130, 0.15);
    color: #5a9a3e;
}

.cuenta-action-btn.eliminar {
    background: rgba(255, 107, 107, 0.12);
    color: #dc3545;
}

.cuenta-action-btn:active {
    transform: scale(0.95);
}

/* ===== DESKTOP STYLES ===== */
.cuenta-card {
    transition: all 0.3s ease;
}

.cuenta-card:hover {
    transform: translateY(-5px);
}

.cuenta-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.cuenta-nombre {
    font-weight: 700;
    color: var(--dark-blue);
}

.cuenta-saldo h3 {
    font-weight: 800;
}

.btn-ajustar-saldo {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 20px;
    background: rgba(255, 152, 0, 0.12);
    color: #f57c00;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 152, 0, 0.2);
}
.btn-ajustar-saldo:hover {
    background: #ff9800;
    color: white;
    border-color: #ff9800;
    transform: scale(1.02);
}
.btn-ajustar-saldo i {
    font-size: 11px;
}

/* Botones de acción de cuenta */
.cuenta-actions {
    display: flex;
    gap: 6px;
}

.btn-cuenta-action {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: none;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-cuenta-edit {
    background: rgba(85, 165, 200, 0.12);
    color: var(--primary-blue);
}
.btn-cuenta-edit:hover {
    background: var(--primary-blue);
    color: white;
    transform: scale(1.1);
}

.btn-cuenta-delete {
    background: rgba(255, 107, 107, 0.12);
    color: #dc3545;
}
.btn-cuenta-delete:hover {
    background: #FF6B6B;
    color: white;
    transform: scale(1.1);
}

/* Modal de eliminación */
.delete-modal-icon {
    width: 70px;
    height: 70px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
    margin: 0 auto 16px;
}
.delete-info-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
}
.delete-info-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}
.delete-info-row:last-child {
    border-bottom: none;
}
.delete-warning {
    background: rgba(255, 193, 7, 0.15);
    border-left: 4px solid #ffc107;
    padding: 12px 16px;
    border-radius: 0 8px 8px 0;
    margin-bottom: 16px;
}
.delete-danger {
    background: rgba(220, 53, 69, 0.1);
    border-left: 4px solid #dc3545;
    padding: 12px 16px;
    border-radius: 0 8px 8px 0;
}
.step-indicator {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 20px;
}
.step-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #dee2e6;
}
.step-dot.active {
    background: #dc3545;
}
</style>

<!-- Modal Paso 1: Información -->
<div class="modal fade" id="modalEliminarInfo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="step-indicator w-100">
                    <div class="step-dot active"></div>
                    <div class="step-dot"></div>
                </div>
            </div>
            <div class="modal-body text-center pt-2">
                <div class="delete-modal-icon" id="deleteIconPreview">
                    <i class="bi bi-wallet2"></i>
                </div>
                <h5 class="mb-1" id="deleteNombreCuenta">Nombre de cuenta</h5>
                <p class="text-muted mb-4">¿Estás seguro de eliminar esta cuenta?</p>
                
                <div class="delete-info-card text-start">
                    <div class="delete-info-row">
                        <span class="text-muted"><i class="bi bi-calendar3 me-2"></i>Antigüedad</span>
                        <strong id="deleteAntiguedad">-</strong>
                    </div>
                    <div class="delete-info-row">
                        <span class="text-muted"><i class="bi bi-cash-stack me-2"></i>Saldo actual</span>
                        <strong id="deleteSaldo">-</strong>
                    </div>
                    <div class="delete-info-row">
                        <span class="text-muted"><i class="bi bi-receipt me-2"></i>Transacciones</span>
                        <strong id="deleteTransacciones">-</strong>
                    </div>
                    <div class="delete-info-row" id="deleteTransferenciasRow" style="display: none;">
                        <span class="text-muted"><i class="bi bi-arrow-left-right me-2"></i>Transferencias</span>
                        <strong id="deleteTransferencias">-</strong>
                    </div>
                </div>
                
                <div class="delete-warning text-start" id="deleteWarning" style="display: none;">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                    <small id="deleteWarningText"></small>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-2">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger px-4" onclick="mostrarPaso2()">
                    <i class="bi bi-arrow-right me-2"></i>Continuar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Paso 2: Confirmación final -->
<div class="modal fade" id="modalEliminarConfirm" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="step-indicator w-100">
                    <div class="step-dot active"></div>
                    <div class="step-dot active"></div>
                </div>
            </div>
            <div class="modal-body text-center pt-2">
                <div class="mb-4">
                    <i class="bi bi-exclamation-octagon-fill text-danger" style="font-size: 64px;"></i>
                </div>
                <h5 class="text-danger mb-3">⚠️ Acción irreversible</h5>
                
                <div class="delete-danger text-start mb-4">
                    <strong>Esta acción eliminará permanentemente:</strong>
                    <ul class="mb-0 mt-2">
                        <li>La cuenta "<span id="confirmNombreCuenta"></span>"</li>
                        <li id="confirmTransaccionesLi" style="display: none;"><span id="confirmTransacciones"></span> transacción(es) asociada(s)</li>
                    </ul>
                    <p class="mb-0 mt-2" id="confirmTransferenciasNote" style="display: none;">
                        <i class="bi bi-info-circle me-1"></i>
                        <small>Las transferencias serán conservadas.</small>
                    </p>
                </div>
                
                <p class="text-muted small">
                    Escribe <strong class="text-danger">ELIMINAR</strong> para confirmar:
                </p>
                <input type="text" class="form-control text-center mb-3" id="confirmInput" 
                       placeholder="ELIMINAR" autocomplete="off">
            </div>
            <div class="modal-footer border-0 justify-content-center gap-2">
                <button type="button" class="btn btn-outline-secondary px-4" onclick="volverPaso1()">
                    <i class="bi bi-arrow-left me-2"></i>Volver
                </button>
                <button type="button" class="btn btn-danger px-4" id="btnEliminarFinal" disabled onclick="eliminarCuenta()">
                    <i class="bi bi-trash-fill me-2"></i>Eliminar definitivamente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let cuentaIdEliminar = null;
    let infoEliminar = null;
    
    const modalInfoEl = document.getElementById('modalEliminarInfo');
    const modalConfirmEl = document.getElementById('modalEliminarConfirm');
    const modalInfo = new bootstrap.Modal(modalInfoEl);
    const modalConfirm = new bootstrap.Modal(modalConfirmEl);
    
    // Exponer función globalmente
    window.mostrarModalEliminar = async function(id) {
        cuentaIdEliminar = id;
        
        // Obtener información de la cuenta
        try {
            const response = await fetch(`<?= UI_URL ?>/modules/cuentas/api/info_eliminar.php?id=${id}`);
            infoEliminar = await response.json();
            
            if (infoEliminar.error) {
                Swal.fire('Error', infoEliminar.error, 'error');
                return;
            }
            
            // Llenar modal con información
            document.getElementById('deleteIconPreview').style.backgroundColor = infoEliminar.color;
            document.getElementById('deleteIconPreview').innerHTML = `<i class="bi ${infoEliminar.icono}"></i>`;
            document.getElementById('deleteNombreCuenta').textContent = infoEliminar.nombre;
            document.getElementById('deleteAntiguedad').textContent = infoEliminar.antiguedad;
            document.getElementById('deleteSaldo').textContent = formatMoneyLocal(infoEliminar.saldo);
            document.getElementById('deleteTransacciones').textContent = infoEliminar.transacciones_normales;
            
            // Mostrar transferencias si hay
            if (infoEliminar.transferencias > 0) {
                document.getElementById('deleteTransferenciasRow').style.display = 'flex';
                document.getElementById('deleteTransferencias').textContent = infoEliminar.transferencias;
            } else {
                document.getElementById('deleteTransferenciasRow').style.display = 'none';
            }
            
            // Mostrar advertencia si hay transacciones
            const warningEl = document.getElementById('deleteWarning');
            if (infoEliminar.transacciones_normales > 0) {
                warningEl.style.display = 'block';
                document.getElementById('deleteWarningText').innerHTML = 
                    `Se eliminarán <strong>${infoEliminar.transacciones_normales}</strong> transacción(es) permanentemente.` +
                    (infoEliminar.transferencias > 0 ? ' Las transferencias serán conservadas.' : '');
            } else {
                warningEl.style.display = 'none';
            }
            
            modalInfo.show();
            
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'Error al obtener información de la cuenta', 'error');
        }
    };
    
    window.mostrarPaso2 = function() {
        modalInfo.hide();
        
        // Llenar info del paso 2
        document.getElementById('confirmNombreCuenta').textContent = infoEliminar.nombre;
        
        if (infoEliminar.transacciones_normales > 0) {
            document.getElementById('confirmTransaccionesLi').style.display = 'list-item';
            document.getElementById('confirmTransacciones').textContent = infoEliminar.transacciones_normales;
        } else {
            document.getElementById('confirmTransaccionesLi').style.display = 'none';
        }
        
        if (infoEliminar.transferencias > 0) {
            document.getElementById('confirmTransferenciasNote').style.display = 'block';
        } else {
            document.getElementById('confirmTransferenciasNote').style.display = 'none';
        }
        
        // Resetear input
        document.getElementById('confirmInput').value = '';
        document.getElementById('btnEliminarFinal').disabled = true;
        
        setTimeout(() => modalConfirm.show(), 200);
    };
    
    window.volverPaso1 = function() {
        modalConfirm.hide();
        setTimeout(() => modalInfo.show(), 200);
    };
    
    window.eliminarCuenta = function() {
        if (document.getElementById('confirmInput').value !== 'ELIMINAR') return;
        window.location.href = `<?= uiModuleUrl('cuentas') ?>&action=delete&id=${cuentaIdEliminar}`;
    };
    
    // Validar input de confirmación
    document.getElementById('confirmInput').addEventListener('input', function() {
        document.getElementById('btnEliminarFinal').disabled = this.value !== 'ELIMINAR';
    });
    
    function formatMoneyLocal(value) {
        return '$' + new Intl.NumberFormat('es-CO', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value);
    }
});
</script>

