<?php
/**
 * AND FINANCE APP - Ajustar Saldo de Cuenta
 */

require_once __DIR__ . '/../models/CuentaModel.php';
require_once __DIR__ . '/../../transacciones/models/TransaccionModel.php';

$pageTitle = 'Ajustar Saldo';
$pageSubtitle = 'Corrige el saldo de tu cuenta';
$cuentaModel = new CuentaModel();
$transaccionModel = new TransaccionModel();
$userId = getCurrentUserId();

// Obtener cuenta a ajustar
$cuentaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cuenta = $cuentaModel->getById($cuentaId);

if (!$cuenta || $cuenta['usuario_id'] != $userId) {
    setFlashMessage('error', 'Cuenta no encontrada');
    ob_end_clean();
    header('Location: ' . uiModuleUrl('cuentas'));
    exit;
}

$errors = [];
$nuevoSaldo = $cuenta['saldo_actual'];
$descripcion = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevoSaldo = (float)str_replace(['.', ','], ['', '.'], $_POST['nuevo_saldo'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    // Validar que el nuevo saldo sea diferente
    if ($nuevoSaldo == $cuenta['saldo_actual']) {
        $errors[] = 'El nuevo saldo debe ser diferente al saldo actual';
    }
    
    if (empty($errors)) {
        try {
            $transaccionModel->crearAjuste(
                $userId,
                $cuentaId,
                $cuenta['saldo_actual'],
                $nuevoSaldo,
                $descripcion ?: null
            );
            
            $diferencia = $nuevoSaldo - $cuenta['saldo_actual'];
            $mensaje = $diferencia > 0 
                ? 'Saldo ajustado: +' . formatMoney($diferencia) 
                : 'Saldo ajustado: ' . formatMoney($diferencia);
            
            setFlashMessage('success', $mensaje);
            ob_end_clean();
            header('Location: ' . uiModuleUrl('cuentas'));
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error al ajustar saldo: ' . $e->getMessage();
        }
    }
}

$diferencia = $nuevoSaldo - $cuenta['saldo_actual'];
?>

<div class="row justify-content-center">
    <div class="col-lg-5 col-md-8">
        <div class="card fade-in-up">
            <div class="card-header text-center py-3">
                <div class="cuenta-icon-lg mx-auto mb-2" style="background-color: <?= htmlspecialchars($cuenta['color'] ?? '#55A5C8') ?>;">
                    <i class="bi <?= htmlspecialchars($cuenta['icono'] ?? 'bi-wallet2') ?>"></i>
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($cuenta['nombre']) ?></h5>
                <small class="text-muted">Ajustar saldo de cuenta</small>
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
                
                <form method="POST" id="formAjuste">
                    <!-- Saldo actual -->
                    <div class="mb-4">
                        <label class="form-label text-muted small">Saldo actual</label>
                        <div class="saldo-display actual">
                            <span class="monto"><?= formatMoney($cuenta['saldo_actual']) ?></span>
                        </div>
                    </div>
                    
                    <!-- Nuevo saldo -->
                    <div class="mb-4">
                        <label for="nuevo_saldo" class="form-label fw-semibold">Nuevo saldo</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">$</span>
                            <input type="text" 
                                   class="form-control form-control-lg text-end money-input" 
                                   id="nuevo_saldo" 
                                   name="nuevo_saldo" 
                                   value="<?= number_format($nuevoSaldo, 0, ',', '.') ?>"
                                   inputmode="numeric"
                                   autofocus
                                   required>
                        </div>
                    </div>
                    
                    <!-- Diferencia calculada -->
                    <div class="mb-4">
                        <label class="form-label text-muted small">Diferencia a aplicar</label>
                        <div class="diferencia-display" id="diferenciaDisplay">
                            <span class="icono"><i class="bi bi-dash"></i></span>
                            <span class="monto">$0</span>
                        </div>
                        <small class="text-muted d-block mt-1" id="diferenciaTexto">
                            Ingresa un nuevo saldo diferente al actual
                        </small>
                    </div>
                    
                    <hr>
                    
                    <!-- Descripción opcional -->
                    <div class="mb-4">
                        <label for="descripcion" class="form-label">Motivo del ajuste <span class="text-muted">(opcional)</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="descripcion" 
                               name="descripcion" 
                               value="<?= htmlspecialchars($descripcion) ?>"
                               placeholder="Ej: Dinero en efectivo olvidado, Error de registro...">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg" id="btnAjustar" disabled>
                            <i class="bi bi-check-circle me-2"></i>Aplicar ajuste
                        </button>
                        <a href="<?= uiModuleUrl('cuentas') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Nota informativa -->
        <div class="alert alert-info mt-3 fade-in-up" style="animation-delay: 0.1s;">
            <i class="bi bi-info-circle me-2"></i>
            <small>
                Se creará una transacción de <strong>ajuste de saldo</strong> que quedará registrada en tu historial.
            </small>
        </div>
    </div>
</div>

<style>
.cuenta-icon-lg {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
}

.saldo-display {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}
.saldo-display .monto {
    font-size: 1.5rem;
    font-weight: 700;
    color: #666;
}

.diferencia-display {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
}
.diferencia-display .icono {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    background: #e9ecef;
    color: #666;
}
.diferencia-display .monto {
    font-size: 1.5rem;
    font-weight: 700;
    color: #666;
}

.diferencia-display.positivo {
    background: rgba(154, 208, 130, 0.15);
}
.diferencia-display.positivo .icono {
    background: var(--secondary-green);
    color: white;
}
.diferencia-display.positivo .monto {
    color: #5a9a3e;
}

.diferencia-display.negativo {
    background: rgba(255, 107, 107, 0.15);
}
.diferencia-display.negativo .icono {
    background: #FF6B6B;
    color: white;
}
.diferencia-display.negativo .monto {
    color: #ee5a5a;
}
</style>

<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nuevoSaldoInput = document.getElementById('nuevo_saldo');
    const diferenciaDisplay = document.getElementById('diferenciaDisplay');
    const diferenciaTexto = document.getElementById('diferenciaTexto');
    const btnAjustar = document.getElementById('btnAjustar');
    
    const saldoActual = <?= $cuenta['saldo_actual'] ?>;
    
    function actualizarDiferencia() {
        // Obtener valor sin formato
        const nuevoSaldo = parseInt(nuevoSaldoInput.value.replace(/[^\d-]/g, '')) || 0;
        const diferencia = nuevoSaldo - saldoActual;
        
        // Actualizar display
        const iconoEl = diferenciaDisplay.querySelector('.icono i');
        const montoEl = diferenciaDisplay.querySelector('.monto');
        
        diferenciaDisplay.classList.remove('positivo', 'negativo');
        
        if (diferencia > 0) {
            diferenciaDisplay.classList.add('positivo');
            iconoEl.className = 'bi bi-arrow-up';
            montoEl.textContent = '+' + formatMoney(diferencia);
            diferenciaTexto.textContent = 'Se sumará este monto a tu cuenta';
            btnAjustar.disabled = false;
        } else if (diferencia < 0) {
            diferenciaDisplay.classList.add('negativo');
            iconoEl.className = 'bi bi-arrow-down';
            montoEl.textContent = formatMoney(diferencia);
            diferenciaTexto.textContent = 'Se restará este monto de tu cuenta';
            btnAjustar.disabled = false;
        } else {
            iconoEl.className = 'bi bi-dash';
            montoEl.textContent = '$0';
            diferenciaTexto.textContent = 'Ingresa un nuevo saldo diferente al actual';
            btnAjustar.disabled = true;
        }
    }
    
    function formatMoney(value) {
        return '$' + new Intl.NumberFormat('es-CO', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(Math.abs(value));
    }
    
    nuevoSaldoInput.addEventListener('input', actualizarDiferencia);
    
    // Inicializar
    actualizarDiferencia();
});
</script>
<?php $extraScripts = ob_get_clean(); ?>

