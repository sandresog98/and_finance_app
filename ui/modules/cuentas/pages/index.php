<?php
/**
 * Listado de Cuentas
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['and_finance_user'])) {
    header('Location: ../../../login.php');
    exit;
}

require_once dirname(__DIR__, 4) . '/ui/config/paths.php';
require_once dirname(__DIR__, 4) . '/utils/Database.php';
require_once dirname(__DIR__, 4) . '/utils/Env.php';
require_once __DIR__ . '/../models/Account.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\Cuentas\Models\Account;

$currentPage = 'cuentas';
$currentUser = $_SESSION['and_finance_user'];
$userId = $currentUser['id'];

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $accountModel = new Account($db->getConnection());
    
    // Recalcular saldos antes de obtener las cuentas
    $accountModel->recalculateAllBalances($userId);
    
    $cuentas = $accountModel->getAllByUser($userId);
    
    // Calcular totales
    $totalSaldo = array_sum(array_column($cuentas, 'saldo_actual'));
    
} catch (Exception $e) {
    $cuentas = [];
    $totalSaldo = 0;
    $error = 'Error al cargar las cuentas';
}

require_once dirname(__DIR__, 4) . '/ui/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/ui/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-wallet me-2"></i>Mis Cuentas</h1>
        <a href="<?php echo getBaseUrl(); ?>modules/cuentas/pages/create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Nueva Cuenta
        </a>
    </div>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Resumen de saldos -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--third-color) 100%); color: white;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 text-white">Saldo Total</h5>
                            <h2 class="mb-0 text-white">$<?php echo number_format($totalSaldo, 2, ',', '.'); ?></h2>
                        </div>
                        <i class="fas fa-wallet fa-3x opacity-50 text-white"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <?php if (empty($cuentas)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No tienes cuentas registradas</h5>
                    <p class="text-muted">Comienza creando tu primera cuenta</p>
                    <a href="<?php echo getBaseUrl(); ?>modules/cuentas/pages/create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Crear Primera Cuenta
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($cuentas as $cuenta): ?>
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <?php if (!empty($cuenta['banco_logo'])): ?>
                                <img src="<?php echo dirname(getBaseUrl(), 1); ?>/file_proxy.php?file=<?php echo urlencode($cuenta['banco_logo']); ?>" 
                                     alt="<?php echo htmlspecialchars($cuenta['banco_nombre'] ?? ''); ?>" 
                                     style="max-width: 50px; max-height: 50px; object-fit: contain;" 
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <i class="fas fa-university fa-2x text-primary" style="display: none;"></i>
                                <?php else: ?>
                                <i class="fas fa-wallet fa-2x text-primary"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($cuenta['nombre']); ?></h5>
                                <?php if (!empty($cuenta['banco_nombre'])): ?>
                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($cuenta['banco_nombre']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm text-white" 
                                    style="background-color: #0dcaf0; border-color: #0dcaf0;"
                                    onclick="viewTransactions(<?php echo $cuenta['id']; ?>, '<?php echo htmlspecialchars($cuenta['nombre'], ENT_QUOTES); ?>')" 
                                    title="Ver últimas transacciones">
                                <i class="fas fa-list"></i>
                            </button>
                            <button type="button" class="btn btn-sm text-white" 
                                    style="background-color: #6c757d; border-color: #6c757d;"
                                    onclick="openAdjustBalance(<?php echo $cuenta['id']; ?>, '<?php echo htmlspecialchars($cuenta['nombre'], ENT_QUOTES); ?>', <?php echo $cuenta['saldo_actual']; ?>)" 
                                    title="Ajustar Saldo">
                                <i class="fas fa-balance-scale"></i>
                            </button>
                            <a href="<?php echo getBaseUrl(); ?>modules/cuentas/pages/edit.php?id=<?php echo $cuenta['id']; ?>" 
                               class="btn btn-sm btn-primary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="deleteAccount(<?php echo $cuenta['id']; ?>)" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($cuenta['tipo'])); ?></span>
                    </div>
                    
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted d-block mb-1">Saldo Actual</small>
                        <h4 class="mb-0 <?php echo $cuenta['saldo_actual'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            $<?php echo number_format($cuenta['saldo_actual'], 2, ',', '.'); ?>
                        </h4>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Ajuste de Saldo -->
<div class="modal fade" id="adjustBalanceModal" tabindex="-1" aria-labelledby="adjustBalanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-custom">
            <div class="modal-header" style="background: var(--primary-color); color: white;">
                <h5 class="modal-title fw-bold" id="adjustBalanceModalLabel">
                    <i class="fas fa-balance-scale me-2"></i>Ajustar Saldo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Cuenta</label>
                    <p class="form-control-plaintext" id="adjustAccountName"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Saldo Actual</label>
                    <p class="form-control-plaintext" id="adjustCurrentBalance"></p>
                </div>
                <div class="mb-3">
                    <label for="adjustNewBalance" class="form-label fw-bold">Nuevo Saldo <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" class="form-control" id="adjustNewBalance" 
                               placeholder="0.00" inputmode="decimal" required>
                        <input type="hidden" id="adjustAccountId">
                        <input type="hidden" id="adjustMontoValue">
                    </div>
                    <small class="text-muted">Ingrese el saldo que desea establecer para esta cuenta</small>
                </div>
                <div class="mb-3">
                    <label for="adjustComment" class="form-label fw-bold">Comentario (opcional)</label>
                    <textarea class="form-control" id="adjustComment" rows="2" 
                              placeholder="Motivo del ajuste..."></textarea>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <span id="adjustDifferenceText"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveAdjustBalance()">
                    <i class="fas fa-save me-2"></i>Guardar Ajuste
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Últimas Transacciones -->
<div class="modal fade" id="transactionsModal" tabindex="-1" aria-labelledby="transactionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-custom">
            <div class="modal-header" style="background: var(--primary-color); color: white;">
                <h5 class="modal-title fw-bold" id="transactionsModalLabel">
                    <i class="fas fa-exchange-alt me-2"></i>Últimas Transacciones
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="transactionsLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Cargando transacciones...</p>
                </div>
                <div id="transactionsContent" style="display: none;">
                    <div id="transactionsList"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Variable global para almacenar el saldo actual
let currentBalanceForAdjust = 0;

// Event listener para formateo de monto (se agrega una sola vez)
document.addEventListener('DOMContentLoaded', function() {
    const newBalanceInput = document.getElementById('adjustNewBalance');
    if (newBalanceInput) {
        newBalanceInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\./g, '').replace(/[^\d,]/g, '');
            
            if (value.includes(',')) {
                const parts = value.split(',');
                value = parts[0] + ',' + (parts[1] || '').substring(0, 2);
            }
            
            if (value) {
                const parts = value.split(',');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                e.target.value = parts.join(',');
                
                const numericValue = value.replace(/\./g, '').replace(',', '.');
                document.getElementById('adjustMontoValue').value = numericValue || '';
                
                // Calcular diferencia
                const nuevoSaldo = parseFloat(numericValue) || 0;
                const diferencia = nuevoSaldo - currentBalanceForAdjust;
                const diferenciaText = document.getElementById('adjustDifferenceText');
                
                if (diferencia > 0) {
                    diferenciaText.innerHTML = `<strong>Ajuste positivo:</strong> +$${Math.abs(diferencia).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    diferenciaText.className = 'alert alert-success';
                } else if (diferencia < 0) {
                    diferenciaText.innerHTML = `<strong>Ajuste negativo:</strong> -$${Math.abs(diferencia).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    diferenciaText.className = 'alert alert-danger';
                } else {
                    diferenciaText.innerHTML = `<strong>Sin cambio:</strong> El saldo permanece igual`;
                    diferenciaText.className = 'alert alert-info';
                }
            } else {
                document.getElementById('adjustMontoValue').value = '';
                document.getElementById('adjustDifferenceText').textContent = '';
            }
        });
    }
});

function openAdjustBalance(cuentaId, cuentaNombre, saldoActual) {
    currentBalanceForAdjust = parseFloat(saldoActual);
    const modal = new bootstrap.Modal(document.getElementById('adjustBalanceModal'));
    document.getElementById('adjustAccountId').value = cuentaId;
    document.getElementById('adjustAccountName').textContent = cuentaNombre;
    document.getElementById('adjustCurrentBalance').textContent = '$' + saldoActual.toLocaleString('es-ES', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    });
    document.getElementById('adjustNewBalance').value = '';
    document.getElementById('adjustMontoValue').value = '';
    document.getElementById('adjustComment').value = '';
    document.getElementById('adjustDifferenceText').textContent = '';
    modal.show();
}

function saveAdjustBalance() {
    const cuentaId = document.getElementById('adjustAccountId').value;
    const montoValueInput = document.getElementById('adjustMontoValue');
    const newBalanceInput = document.getElementById('adjustNewBalance');
    const comentario = document.getElementById('adjustComment').value;
    
    // Obtener el valor numérico
    let nuevoSaldo = montoValueInput.value;
    if (!nuevoSaldo && newBalanceInput.value) {
        // Si no hay valor en el campo oculto, calcularlo desde el campo visible
        const displayValue = newBalanceInput.value.replace(/\./g, '').replace(',', '.');
        nuevoSaldo = displayValue;
    }
    
    nuevoSaldo = parseFloat(nuevoSaldo);
    
    if (isNaN(nuevoSaldo) || nuevoSaldo < 0) {
        alert('Por favor ingrese un saldo válido');
        return;
    }
    
    if (!confirm('¿Está seguro de ajustar el saldo de esta cuenta? Esta acción creará una transacción de ajuste.')) {
        return;
    }
    
    fetch('<?php echo getBaseUrl(); ?>modules/cuentas/api/adjust_balance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            cuenta_id: parseInt(cuentaId),
            nuevo_saldo: nuevoSaldo,
            comentario: comentario
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('adjustBalanceModal'));
            modal.hide();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'No se pudo ajustar el saldo'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al ajustar el saldo');
    });
}

function deleteAccount(id) {
    if (confirm('¿Está seguro de eliminar esta cuenta? Esta acción no se puede deshacer.')) {
        fetch('<?php echo getBaseUrl(); ?>modules/cuentas/api/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'No se pudo eliminar la cuenta'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar la cuenta');
        });
    }
}

function viewTransactions(cuentaId, cuentaNombre) {
    const modal = new bootstrap.Modal(document.getElementById('transactionsModal'));
    const modalTitle = document.getElementById('transactionsModalLabel');
    const loadingDiv = document.getElementById('transactionsLoading');
    const contentDiv = document.getElementById('transactionsContent');
    const transactionsList = document.getElementById('transactionsList');
    
    // Actualizar título del modal
    modalTitle.innerHTML = `<i class="fas fa-exchange-alt me-2"></i>Últimas Transacciones - ${cuentaNombre}`;
    
    // Mostrar loading, ocultar contenido
    loadingDiv.style.display = 'block';
    contentDiv.style.display = 'none';
    transactionsList.innerHTML = '';
    
    // Abrir modal
    modal.show();
    
    // Cargar transacciones
    fetch('<?php echo getBaseUrl(); ?>modules/cuentas/api/get_transactions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ cuenta_id: cuentaId })
    })
    .then(response => response.json())
    .then(data => {
        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';
        
        if (data.success && data.transacciones && data.transacciones.length > 0) {
            let html = '<div class="list-group">';
            data.transacciones.forEach(t => {
                const fecha = new Date(t.fecha).toLocaleDateString('es-ES', { 
                    day: '2-digit', 
                    month: '2-digit', 
                    year: 'numeric' 
                });
                
                let tipoBadge = '';
                let montoClass = '';
                let montoSign = '';
                
                if (t.tipo === 'ingreso') {
                    tipoBadge = '<span class="badge bg-success">Ingreso</span>';
                    montoClass = 'text-success';
                    montoSign = '+$';
                } else if (t.tipo === 'egreso') {
                    tipoBadge = '<span class="badge bg-danger">Egreso</span>';
                    montoClass = 'text-danger';
                    montoSign = '-$';
                } else if (t.tipo === 'ajuste') {
                    tipoBadge = '<span class="badge bg-warning text-dark">Ajuste</span>';
                    montoClass = 'text-warning';
                    montoSign = '±$';
                } else {
                    tipoBadge = '<span class="badge text-white" style="background-color: #0dcaf0;">Transferencia</span>';
                    montoClass = 'text-info';
                    montoSign = '$';
                }
                
                // Para ajustes, usar un icono específico
                let icono = 'fa-balance-scale';
                if (t.tipo !== 'ajuste') {
                    icono = t.categoria_icono || 'fa-tag';
                    if (icono && !icono.includes('fas ') && !icono.includes('far ') && !icono.includes('fab ')) {
                        if (icono.startsWith('fa-')) {
                            icono = 'fas ' + icono;
                        } else {
                            icono = 'fas fa-' + icono;
                        }
                    }
                } else {
                    icono = 'fas fa-balance-scale';
                }
                
                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="d-flex align-items-center gap-3 flex-grow-1">
                                <div class="d-flex align-items-center justify-content-center" 
                                     style="width: 40px; height: 40px; background-color: ${t.tipo === 'ajuste' ? '#ffc107' : (t.categoria_color || '#F1B10B')}; border-radius: 8px;">
                                    <i class="${icono} text-white" style="font-size: 1.2rem;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        ${tipoBadge}
                                        <span class="text-muted small">${fecha}</span>
                                    </div>
                                    <div class="fw-bold">${t.categoria_nombre || 'Ajuste de Saldo'}</div>
                                    ${t.comentario ? `<small class="text-muted">${t.comentario}</small>` : ''}
                                    ${t.tipo === 'transferencia' && t.cuenta_destino_nombre ? `<small class="text-muted d-block"><i class="fas fa-arrow-right me-1"></i>→ ${t.cuenta_destino_nombre}</small>` : ''}
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="${montoClass} fw-bold" style="font-size: 1.1rem;">
                                    ${montoSign}${parseFloat(t.monto).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </div>
                                <a href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/edit.php?id=${t.id}" 
                                   class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            transactionsList.innerHTML = html;
        } else {
            transactionsList.innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No hay transacciones registradas para esta cuenta</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';
        transactionsList.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>Error al cargar las transacciones
            </div>
        `;
    });
}
</script>

<?php require_once dirname(__DIR__, 4) . '/ui/views/layouts/footer.php'; ?>
