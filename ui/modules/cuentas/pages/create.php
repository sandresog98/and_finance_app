<?php
/**
 * Crear Cuenta
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
$error = '';

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $accountModel = new Account($db->getConnection());
    
    $bancos = $accountModel->getBanks();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $bancoId = !empty($_POST['banco_id']) ? (int)$_POST['banco_id'] : null;
        $tipo = $_POST['tipo'] ?? 'bancaria';
        $saldoInicial = (float)($_POST['saldo_inicial'] ?? 0);
        
        if (empty($nombre)) {
            $error = 'El nombre es requerido';
        } else {
            $result = $accountModel->create([
                'usuario_id' => $userId,
                'nombre' => $nombre,
                'banco_id' => $bancoId,
                'tipo' => $tipo,
                'saldo_inicial' => $saldoInicial
            ]);
            
            if ($result['success']) {
                header('Location: index.php?success=1');
                exit;
            } else {
                $error = $result['message'] ?? 'Error al crear la cuenta';
            }
        }
    }
} catch (Exception $e) {
    $error = 'Error al procesar la solicitud';
    error_log('Create account error: ' . $e->getMessage());
    $bancos = [];
}

require_once dirname(__DIR__, 4) . '/ui/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/ui/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-plus-circle me-2"></i>Nueva Cuenta</h1>
        <a href="<?php echo getBaseUrl(); ?>modules/cuentas/pages/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver
        </a>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" id="accountForm">
                <!-- Nombre -->
                <div class="mb-4">
                    <label for="nombre" class="form-label fw-bold">Nombre de la Cuenta <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg" id="nombre" name="nombre" required
                           placeholder="Ej: Cuenta Principal, Ahorros, etc.">
                </div>
                
                <!-- Tipo: Botón con Modal -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block mb-3">Tipo de Cuenta <span class="text-danger">*</span></label>
                    <input type="hidden" id="tipo" name="tipo" value="bancaria" required>
                    <button type="button" class="btn btn-outline-primary btn-lg w-100 p-4 rounded-custom shadow-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#tipoModal"
                            style="border: 2px dashed var(--primary-color); background: #f8f9fa;">
                        <div class="d-flex align-items-center justify-content-center gap-3">
                            <div id="tipoPreview" class="d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px; background-color: var(--primary-color); border-radius: 12px;">
                                <i class="fas fa-university text-white" style="font-size: 1.8rem;"></i>
                            </div>
                            <div class="text-start flex-grow-1">
                                <div class="fw-bold" id="tipoText">Bancaria</div>
                                <small class="text-muted">Haz clic para seleccionar el tipo</small>
                            </div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </button>
                </div>
                
                <!-- Banco: Botón con Modal -->
                <div class="mb-4" id="bancoSection">
                    <label class="form-label fw-bold d-block mb-3">Banco</label>
                    <input type="hidden" id="banco_id" name="banco_id" value="">
                    <button type="button" class="btn btn-outline-primary btn-lg w-100 p-4 rounded-custom shadow-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#bancoModal"
                            style="border: 2px dashed var(--primary-color); background: #f8f9fa;">
                        <div class="d-flex align-items-center justify-content-center gap-3">
                            <div id="bancoPreview" class="d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px; background-color: #e9ecef; border-radius: 12px;">
                                <i class="fas fa-building text-muted" style="font-size: 1.8rem;"></i>
                            </div>
                            <div class="text-start flex-grow-1">
                                <div class="fw-bold" id="bancoText">Seleccionar banco</div>
                                <small class="text-muted">Haz clic para elegir un banco</small>
                            </div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </button>
                    <small class="text-muted mt-2 d-block">Opcional - Dejar vacío para cuentas de efectivo</small>
                </div>
                
                <!-- Saldo Inicial -->
                <div class="mb-4">
                    <label for="saldo_inicial" class="form-label fw-bold">Saldo Inicial</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">$</span>
                        <input type="text" class="form-control" id="saldo_inicial" 
                               value="0" placeholder="0.00" inputmode="decimal">
                        <input type="hidden" id="saldo_inicial_value" name="saldo_inicial">
                    </div>
                    <small class="text-muted mt-2 d-block">Saldo con el que inicia la cuenta</small>
                </div>
                
                <!-- Botones de acción -->
                <div class="mt-4 pt-3 border-top d-flex gap-3">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-save me-2"></i>Guardar
                    </button>
                    <a href="<?php echo getBaseUrl(); ?>modules/cuentas/pages/index.php" class="btn btn-secondary btn-lg px-4">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Selección de Tipo -->
<div class="modal fade" id="tipoModal" tabindex="-1" aria-labelledby="tipoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-custom">
            <div class="modal-header" style="background: var(--primary-color); color: white;">
                <h5 class="modal-title fw-bold" id="tipoModalLabel">
                    <i class="fas fa-list me-2"></i>Seleccionar Tipo de Cuenta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-column gap-3">
                    <button type="button" class="tipo-select-btn btn p-4 rounded-custom shadow-sm selected" 
                            data-tipo="bancaria"
                            style="border: 2px solid var(--primary-color); background: #f8f9fa; transition: all 0.2s ease;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px; background-color: var(--primary-color); border-radius: 12px;">
                                <i class="fas fa-university text-white" style="font-size: 1.8rem;"></i>
                            </div>
                            <div class="text-start">
                                <div class="fw-bold fs-5">Bancaria</div>
                                <small class="text-muted">Cuenta bancaria tradicional</small>
                            </div>
                        </div>
                    </button>
                    
                    <button type="button" class="tipo-select-btn btn p-4 rounded-custom shadow-sm" 
                            data-tipo="efectivo"
                            style="border: 2px solid #dee2e6; background: #f8f9fa; transition: all 0.2s ease;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px; background-color: var(--secondary-color); border-radius: 12px;">
                                <i class="fas fa-wallet text-white" style="font-size: 1.8rem;"></i>
                            </div>
                            <div class="text-start">
                                <div class="fw-bold fs-5">Efectivo</div>
                                <small class="text-muted">Dinero en efectivo</small>
                            </div>
                        </div>
                    </button>
                    
                    <button type="button" class="tipo-select-btn btn p-4 rounded-custom shadow-sm" 
                            data-tipo="inversion"
                            style="border: 2px solid #dee2e6; background: #f8f9fa; transition: all 0.2s ease;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px; background-color: var(--third-color); border-radius: 12px;">
                                <i class="fas fa-chart-line text-white" style="font-size: 1.8rem;"></i>
                            </div>
                            <div class="text-start">
                                <div class="fw-bold fs-5">Inversión</div>
                                <small class="text-muted">Cuenta de inversión</small>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Selección de Banco -->
<div class="modal fade" id="bancoModal" tabindex="-1" aria-labelledby="bancoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-custom">
            <div class="modal-header" style="background: var(--primary-color); color: white;">
                <h5 class="modal-title fw-bold" id="bancoModalLabel">
                    <i class="fas fa-university me-2"></i>Seleccionar Banco
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="banco-grid" style="max-height: 400px; overflow-y: auto;">
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <button type="button" class="banco-select-btn w-100 p-3 rounded-custom border-0 bg-light shadow-sm selected" 
                                    data-banco-id=""
                                    style="transition: all 0.2s ease; min-height: 100px;"
                                    title="Sin banco">
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    <i class="fas fa-times text-muted mb-2" style="font-size: 2rem;"></i>
                                    <small class="text-muted">Sin banco</small>
                                </div>
                            </button>
                        </div>
                        <?php foreach ($bancos as $banco): ?>
                        <div class="col-6 col-md-4">
                            <button type="button" class="banco-select-btn w-100 p-3 rounded-custom border-0 bg-light shadow-sm" 
                                    data-banco-id="<?php echo $banco['id']; ?>"
                                    data-banco-nombre="<?php echo htmlspecialchars($banco['nombre']); ?>"
                                    data-banco-logo="<?php echo htmlspecialchars($banco['logo_url'] ?? ''); ?>"
                                    style="transition: all 0.2s ease; min-height: 100px;"
                                    title="<?php echo htmlspecialchars($banco['nombre']); ?>">
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    <?php if (!empty($banco['logo_url'])): ?>
                                    <img src="<?php echo htmlspecialchars(getFileUrl($banco['logo_url'])); ?>" 
                                         alt="<?php echo htmlspecialchars($banco['nombre']); ?>"
                                         style="max-width: 80px; max-height: 50px; object-fit: contain; margin-bottom: 8px;"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <i class="fas fa-university text-muted mb-2" style="font-size: 2rem; display: none;"></i>
                                    <?php else: ?>
                                    <i class="fas fa-university text-muted mb-2" style="font-size: 2rem;"></i>
                                    <?php endif; ?>
                                    <small class="text-center"><?php echo htmlspecialchars($banco['nombre']); ?></small>
                                </div>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tipo-select-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15) !important;
}

.tipo-select-btn.selected {
    border-color: var(--primary-color) !important;
    background: rgba(57, 132, 58, 0.1) !important;
}

.banco-select-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    background: var(--primary-color) !important;
    color: white !important;
}

.banco-select-btn:hover small,
.banco-select-btn:hover i {
    color: white !important;
}

.banco-select-btn.selected {
    background: var(--primary-color) !important;
    border: 2px solid var(--third-color) !important;
    transform: scale(1.02);
}

.banco-select-btn.selected small,
.banco-select-btn.selected i {
    color: white !important;
}

.banco-grid::-webkit-scrollbar {
    width: 8px;
}

.banco-grid::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.banco-grid::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 10px;
}

.banco-grid::-webkit-scrollbar-thumb:hover {
    background: var(--third-color);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoInput = document.getElementById('tipo');
    const tipoButtons = document.querySelectorAll('.tipo-select-btn');
    const tipoPreview = document.getElementById('tipoPreview');
    const tipoText = document.getElementById('tipoText');
    const bancoSection = document.getElementById('bancoSection');
    const bancoInput = document.getElementById('banco_id');
    const bancoButtons = document.querySelectorAll('.banco-select-btn');
    const bancoPreview = document.getElementById('bancoPreview');
    const bancoText = document.getElementById('bancoText');
    
    // Configuración de tipos
    const tipoConfig = {
        'bancaria': { icon: 'fa-university', color: 'var(--primary-color)', text: 'Bancaria' },
        'efectivo': { icon: 'fa-wallet', color: 'var(--secondary-color)', text: 'Efectivo' },
        'inversion': { icon: 'fa-chart-line', color: 'var(--third-color)', text: 'Inversión' }
    };
    
    // Selección de tipo
    tipoButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const tipo = this.getAttribute('data-tipo');
            tipoInput.value = tipo;
            
            // Actualizar botones
            tipoButtons.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            
            // Actualizar preview
            const config = tipoConfig[tipo];
            tipoPreview.style.backgroundColor = config.color;
            tipoPreview.querySelector('i').className = 'fas ' + config.icon + ' text-white';
            tipoText.textContent = config.text;
            
            // Mostrar/ocultar sección de banco
            if (tipo === 'efectivo') {
                bancoSection.style.display = 'none';
                bancoInput.value = '';
                updateBancoPreview();
            } else {
                bancoSection.style.display = 'block';
            }
            
            // Cerrar modal
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('tipoModal'));
                if (modal) {
                    modal.hide();
                }
            }, 300);
        });
    });
    
    // Selección de banco
    bancoButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const bancoId = this.getAttribute('data-banco-id');
            const bancoNombre = this.getAttribute('data-banco-nombre') || 'Sin banco';
            const bancoLogo = this.getAttribute('data-banco-logo') || '';
            
            bancoInput.value = bancoId;
            
            // Actualizar botones
            bancoButtons.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            
            // Actualizar preview
            updateBancoPreview(bancoNombre, bancoLogo);
            
            // Cerrar modal
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('bancoModal'));
                if (modal) {
                    modal.hide();
                }
            }, 300);
        });
    });
    
    const baseProjectUrl = '<?php 
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $marker = '/and_finance_app/';
        $pos = strpos($scriptName, $marker);
        echo $pos !== false ? substr($scriptName, 0, $pos + strlen($marker)) : '/and_finance_app/';
    ?>';
    
    function updateBancoPreview(nombre = 'Seleccionar banco', logo = '') {
        bancoText.textContent = nombre;
        if (logo) {
            bancoPreview.innerHTML = `<img src="${baseProjectUrl}${logo}" 
                                           alt="${nombre}" 
                                           style="max-width: 50px; max-height: 40px; object-fit: contain;"
                                           onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <i class="fas fa-building text-muted" style="font-size: 1.8rem; display: none;"></i>`;
        } else {
            bancoPreview.innerHTML = '<i class="fas fa-building text-muted" style="font-size: 1.8rem;"></i>';
            bancoPreview.style.backgroundColor = '#e9ecef';
        }
    }
    
    // Formateo de saldo inicial con puntos de miles
    const saldoInput = document.getElementById('saldo_inicial');
    const saldoValueInput = document.getElementById('saldo_inicial_value');
    
    if (saldoInput) {
        saldoInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\./g, '').replace(/[^\d,]/g, '');
            
            // Manejar coma decimal
            if (value.includes(',')) {
                const parts = value.split(',');
                value = parts[0] + ',' + (parts[1] || '').substring(0, 2);
            }
            
            // Formatear con puntos de miles
            if (value) {
                const parts = value.split(',');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                e.target.value = parts.join(',');
                
                // Guardar valor numérico sin formato
                const numericValue = value.replace(/\./g, '').replace(',', '.');
                saldoValueInput.value = numericValue || '0';
            } else {
                saldoValueInput.value = '0';
            }
        });
        
        // Inicializar valor
        saldoValueInput.value = '0';
        
        // Antes de enviar, asegurar que el valor esté en el campo oculto
        document.getElementById('accountForm').addEventListener('submit', function(e) {
            const displayValue = saldoInput.value.replace(/\./g, '').replace(',', '.');
            saldoValueInput.value = displayValue || '0';
        });
    }
});
</script>

<?php require_once dirname(__DIR__, 4) . '/ui/views/layouts/footer.php'; ?>
