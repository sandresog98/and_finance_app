<?php
/**
 * Crear Gasto Recurrente
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
require_once __DIR__ . '/../models/RecurringExpense.php';
require_once dirname(__DIR__, 4) . '/ui/modules/cuentas/models/Account.php';
require_once dirname(__DIR__, 4) . '/ui/modules/categorias/models/Category.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\GastosRecurrentes\Models\RecurringExpense;
use UI\Modules\Cuentas\Models\Account;
use UI\Modules\Categorias\Models\Category;

$currentPage = 'gastos_recurrentes';
$currentUser = $_SESSION['and_finance_user'];
$userId = $currentUser['id'];
$error = '';

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    $recurringModel = new RecurringExpense($conn);
    $accountModel = new Account($conn);
    $categoryModel = new Category($conn);
    
    $cuentas = $accountModel->getAllByUser($userId);
    $categorias = $categoryModel->getByType($userId, 'egreso');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $cuentaId = (int)($_POST['cuenta_id'] ?? 0);
        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $monto = (float)($_POST['monto'] ?? 0);
        $diaMes = (int)($_POST['dia_mes'] ?? 1);
        $tipo = $_POST['tipo'] ?? 'mensual';
        
        if (empty($nombre) || $cuentaId <= 0 || $categoriaId <= 0 || $monto <= 0) {
            $error = 'Todos los campos son requeridos';
        } else {
            $result = $recurringModel->create([
                'usuario_id' => $userId,
                'cuenta_id' => $cuentaId,
                'categoria_id' => $categoriaId,
                'nombre' => $nombre,
                'monto' => $monto,
                'dia_mes' => $diaMes,
                'tipo' => $tipo
            ]);
            
            if ($result['success']) {
                header('Location: index.php?success=1');
                exit;
            } else {
                $error = $result['message'] ?? 'Error al crear el gasto recurrente';
            }
        }
    }
} catch (Exception $e) {
    $error = 'Error al procesar la solicitud';
    error_log('Create recurring expense error: ' . $e->getMessage());
    $cuentas = [];
    $categorias = [];
}

require_once dirname(__DIR__, 4) . '/ui/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/ui/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-plus-circle me-2"></i>Nuevo Gasto Recurrente</h1>
        <a href="<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/pages/index.php" class="btn btn-secondary">
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
            <form method="POST" id="recurringForm">
                <!-- Nombre -->
                <div class="mb-4">
                    <label for="nombre" class="form-label fw-bold">Nombre del Gasto <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg" id="nombre" name="nombre" required
                           placeholder="Ej: Arriendo, Mercado, etc.">
                </div>
                
                <!-- Monto -->
                <div class="mb-4">
                    <label for="monto" class="form-label fw-bold">Monto <span class="text-danger">*</span></label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">$</span>
                        <input type="text" class="form-control" id="monto" 
                               required placeholder="0.00" inputmode="decimal">
                        <input type="hidden" id="monto_value" name="monto">
                    </div>
                </div>
                
                <!-- Cuenta: Botón con Modal -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block mb-3">Cuenta <span class="text-danger">*</span></label>
                    <input type="hidden" id="cuenta_id" name="cuenta_id" value="" required>
                    <button type="button" class="btn btn-outline-primary btn-lg w-100 p-4 rounded-custom shadow-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#cuentaModal"
                            style="border: 2px dashed var(--primary-color); background: #f8f9fa;">
                        <div class="d-flex align-items-center justify-content-center gap-3">
                            <div id="cuentaPreview" class="d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px; background-color: #e9ecef; border-radius: 12px;">
                                <i class="fas fa-wallet text-muted" style="font-size: 1.8rem;"></i>
                            </div>
                            <div class="text-start flex-grow-1">
                                <div class="fw-bold" id="cuentaText">Seleccionar cuenta</div>
                                <small class="text-muted">Haz clic para elegir una cuenta</small>
                            </div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </button>
                </div>
                
                <!-- Categoría: Botón con Modal -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block mb-3">Categoría <span class="text-danger">*</span></label>
                    <input type="hidden" id="categoria_id" name="categoria_id" value="" required>
                    <button type="button" class="btn btn-outline-primary btn-lg w-100 p-4 rounded-custom shadow-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#categoriaModal"
                            style="border: 2px dashed var(--primary-color); background: #f8f9fa;">
                        <div class="d-flex align-items-center justify-content-center gap-3">
                            <div id="categoriaPreview" class="d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px; background-color: #e9ecef; border-radius: 12px;">
                                <i class="fas fa-tag text-muted" style="font-size: 1.8rem;"></i>
                            </div>
                            <div class="text-start flex-grow-1">
                                <div class="fw-bold" id="categoriaText">Seleccionar categoría</div>
                                <small class="text-muted">Haz clic para elegir una categoría</small>
                            </div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </button>
                </div>
                
                <!-- Día del Mes: Botón con Modal -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block mb-3">Día del Mes <span class="text-danger">*</span></label>
                    <input type="hidden" id="dia_mes" name="dia_mes" value="1" required>
                    <button type="button" class="btn btn-outline-primary btn-lg w-100 p-4 rounded-custom shadow-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#diaModal"
                            style="border: 2px dashed var(--primary-color); background: #f8f9fa;">
                        <div class="d-flex align-items-center justify-content-center gap-3">
                            <div id="diaPreview" class="d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px; background-color: var(--primary-color); border-radius: 12px;">
                                <span class="text-white fw-bold" style="font-size: 1.5rem;">1</span>
                            </div>
                            <div class="text-start flex-grow-1">
                                <div class="fw-bold" id="diaText">Día 1</div>
                                <small class="text-muted">Haz clic para seleccionar el día</small>
                            </div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </button>
                </div>
                
                <!-- Frecuencia -->
                <div class="mb-4">
                    <label for="tipo" class="form-label fw-bold">Frecuencia <span class="text-danger">*</span></label>
                    <select class="form-select form-select-lg" id="tipo" name="tipo" required>
                        <option value="mensual" selected>Mensual (cada mes)</option>
                        <option value="quincenal">Quincenal (día 15 y último día del mes)</option>
                        <option value="semanal">Semanal (cada semana)</option>
                        <option value="bimestral">Bimestral (cada 2 meses)</option>
                        <option value="trimestral">Trimestral (cada 3 meses)</option>
                        <option value="semestral">Semestral (cada 6 meses)</option>
                        <option value="anual">Anual (una vez al año)</option>
                    </select>
                    <small class="text-muted mt-2 d-block">Con qué frecuencia se ejecuta este gasto</small>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Nota:</strong> Este gasto aparecerá en la proyección del mes correspondiente. 
                    Podrás ejecutarlo manualmente cuando desees crear la transacción.
                </div>
                
                <!-- Botones de acción -->
                <div class="mt-4 pt-3 border-top d-flex gap-3">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-save me-2"></i>Guardar
                    </button>
                    <a href="<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/pages/index.php" class="btn btn-secondary btn-lg px-4">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Selección de Cuenta -->
<div class="modal fade" id="cuentaModal" tabindex="-1" aria-labelledby="cuentaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-custom">
            <div class="modal-header" style="background: var(--primary-color); color: white;">
                <h5 class="modal-title fw-bold" id="cuentaModalLabel">
                    <i class="fas fa-wallet me-2"></i>Seleccionar Cuenta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <?php if (empty($cuentas)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No tienes cuentas registradas</p>
                    <a href="<?php echo getBaseUrl(); ?>modules/cuentas/pages/create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Crear Cuenta
                    </a>
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($cuentas as $cuenta): ?>
                    <div class="col-md-6">
                        <button type="button" class="cuenta-select-btn w-100 p-3 rounded-custom border-0 bg-light shadow-sm" 
                                data-cuenta-id="<?php echo $cuenta['id']; ?>"
                                data-cuenta-nombre="<?php echo htmlspecialchars($cuenta['nombre']); ?>"
                                data-cuenta-banco="<?php echo htmlspecialchars($cuenta['banco_nombre'] ?? ''); ?>"
                                data-cuenta-logo="<?php echo htmlspecialchars($cuenta['banco_logo'] ?? ''); ?>"
                                data-cuenta-tipo="<?php echo htmlspecialchars($cuenta['tipo']); ?>"
                                style="transition: all 0.2s ease; text-align: left;"
                                title="<?php echo htmlspecialchars($cuenta['nombre']); ?>">
                            <div class="d-flex align-items-center gap-3">
                                <div>
                                    <?php if (!empty($cuenta['banco_logo'])): ?>
                                    <img src="<?php echo htmlspecialchars(getFileUrl($cuenta['banco_logo'])); ?>" 
                                         alt="<?php echo htmlspecialchars($cuenta['banco_nombre'] ?? ''); ?>"
                                         style="max-width: 50px; max-height: 50px; object-fit: contain;"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <i class="fas fa-university text-primary" style="font-size: 2rem; display: none;"></i>
                                    <?php else: ?>
                                    <i class="fas fa-wallet text-primary" style="font-size: 2rem;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo htmlspecialchars($cuenta['nombre']); ?></div>
                                    <?php if (!empty($cuenta['banco_nombre'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($cuenta['banco_nombre']); ?></small>
                                    <?php endif; ?>
                                    <div class="mt-1">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($cuenta['tipo'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Selección de Categoría -->
<div class="modal fade" id="categoriaModal" tabindex="-1" aria-labelledby="categoriaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-custom">
            <div class="modal-header" style="background: var(--primary-color); color: white;">
                <h5 class="modal-title fw-bold" id="categoriaModalLabel">
                    <i class="fas fa-tags me-2"></i>Seleccionar Categoría
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <?php if (empty($categorias)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No tienes categorías de egreso registradas</p>
                    <a href="<?php echo getBaseUrl(); ?>modules/categorias/pages/create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Crear Categoría
                    </a>
                </div>
                <?php else: ?>
                <div class="categoria-grid" style="max-height: 400px; overflow-y: auto;">
                    <div class="row g-3">
                        <?php foreach ($categorias as $cat): ?>
                        <?php 
                        $icono = !empty($cat['icono']) ? trim($cat['icono']) : 'fa-tag';
                        if (!empty($icono)) {
                            if (strpos($icono, 'fas ') === 0 || strpos($icono, 'far ') === 0 || strpos($icono, 'fab ') === 0) {
                                // Ya tiene prefijo
                            } elseif (strpos($icono, 'fa-') === 0) {
                                $icono = 'fas ' . $icono;
                            } else {
                                $icono = 'fas fa-' . $icono;
                            }
                        } else {
                            $icono = 'fas fa-tag';
                        }
                        ?>
                        <div class="col-md-4 col-6">
                            <button type="button" class="categoria-select-btn w-100 p-3 rounded-custom border-0 bg-light shadow-sm" 
                                    data-categoria-id="<?php echo $cat['id']; ?>"
                                    data-categoria-nombre="<?php echo htmlspecialchars($cat['nombre']); ?>"
                                    data-categoria-icono="<?php echo htmlspecialchars($icono); ?>"
                                    data-categoria-color="<?php echo htmlspecialchars($cat['color'] ?? '#F1B10B'); ?>"
                                    style="transition: all 0.2s ease; min-height: 100px;"
                                    title="<?php echo htmlspecialchars($cat['nombre']); ?>">
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    <div class="d-flex align-items-center justify-content-center mb-2" 
                                         style="width: 50px; height: 50px; background-color: <?php echo htmlspecialchars($cat['color'] ?? '#F1B10B'); ?>; border-radius: 10px;">
                                        <i class="<?php echo htmlspecialchars($icono); ?> text-white" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <small class="text-center"><?php echo htmlspecialchars($cat['nombre']); ?></small>
                                </div>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Selección de Día del Mes -->
<div class="modal fade" id="diaModal" tabindex="-1" aria-labelledby="diaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-custom">
            <div class="modal-header" style="background: var(--primary-color); color: white;">
                <h5 class="modal-title fw-bold" id="diaModalLabel">
                    <i class="fas fa-calendar-day me-2"></i>Seleccionar Día del Mes
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="dia-list" style="max-height: 400px; overflow-y: auto;">
                    <div class="d-flex flex-column gap-2">
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                        <button type="button" class="dia-select-btn w-100 p-3 rounded-custom border-0 bg-light shadow-sm <?php echo $i == 1 ? 'selected' : ''; ?>" 
                                data-dia="<?php echo $i; ?>"
                                style="transition: all 0.2s ease; text-align: left;"
                                title="Día <?php echo $i; ?>">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center justify-content-center" 
                                         style="width: 50px; height: 50px; background-color: var(--primary-color); border-radius: 10px;">
                                        <span class="text-white fw-bold" style="font-size: 1.3rem;"><?php echo $i; ?></span>
                                    </div>
                                    <span class="fw-bold fs-5">Día <?php echo $i; ?></span>
                                </div>
                                <i class="fas fa-check text-primary" style="display: none;"></i>
                            </div>
                        </button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dia-select-btn:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    background: rgba(57, 132, 58, 0.1) !important;
    border-left: 4px solid var(--primary-color) !important;
}

.dia-select-btn.selected {
    background: rgba(57, 132, 58, 0.15) !important;
    border-left: 4px solid var(--primary-color) !important;
    transform: translateX(5px);
}

.dia-select-btn.selected .fa-check {
    display: block !important;
}

.dia-select-btn.selected .d-flex.align-items-center.justify-content-center {
    background-color: var(--primary-color) !important;
}

.dia-list::-webkit-scrollbar {
    width: 8px;
}

.dia-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.dia-list::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 10px;
}

.dia-list::-webkit-scrollbar-thumb:hover {
    background: var(--third-color);
}

.cuenta-select-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    background: var(--primary-color) !important;
    color: white !important;
}

.cuenta-select-btn:hover small,
.cuenta-select-btn:hover .badge {
    color: white !important;
}

.cuenta-select-btn.selected {
    background: var(--primary-color) !important;
    border: 2px solid var(--third-color) !important;
    transform: scale(1.02);
    color: white !important;
}

.cuenta-select-btn.selected small,
.cuenta-select-btn.selected .badge {
    color: white !important;
}

.categoria-select-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
}

.categoria-select-btn.selected {
    border: 2px solid var(--primary-color) !important;
    transform: scale(1.02);
}

.categoria-grid::-webkit-scrollbar {
    width: 8px;
}

.categoria-grid::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.categoria-grid::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 10px;
}

.categoria-grid::-webkit-scrollbar-thumb:hover {
    background: var(--third-color);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cuentaInput = document.getElementById('cuenta_id');
    const cuentaButtons = document.querySelectorAll('.cuenta-select-btn');
    const cuentaPreview = document.getElementById('cuentaPreview');
    const cuentaText = document.getElementById('cuentaText');
    
    const categoriaInput = document.getElementById('categoria_id');
    const categoriaButtons = document.querySelectorAll('.categoria-select-btn');
    const categoriaPreview = document.getElementById('categoriaPreview');
    const categoriaText = document.getElementById('categoriaText');
    
    const fileProxyUrl = '<?php 
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $marker = '/and_finance_app/';
        $pos = strpos($scriptName, $marker);
        $baseProjectUrl = $pos !== false ? substr($scriptName, 0, $pos + strlen($marker)) : '/and_finance_app/';
        echo $baseProjectUrl . 'file_proxy.php';
    ?>';
    
    // Selección de cuenta
    cuentaButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const cuentaId = this.getAttribute('data-cuenta-id');
            const cuentaNombre = this.getAttribute('data-cuenta-nombre');
            const cuentaBanco = this.getAttribute('data-cuenta-banco');
            const cuentaLogo = this.getAttribute('data-cuenta-logo');
            const cuentaTipo = this.getAttribute('data-cuenta-tipo');
            
            cuentaInput.value = cuentaId;
            
            // Actualizar botones
            cuentaButtons.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            
            // Actualizar preview
            updateCuentaPreview(cuentaNombre, cuentaBanco, cuentaLogo, cuentaTipo);
            
            // Cerrar modal
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('cuentaModal'));
                if (modal) {
                    modal.hide();
                }
            }, 300);
        });
    });
    
    function updateCuentaPreview(nombre, banco, logo, tipo) {
        cuentaText.textContent = nombre;
        if (logo) {
            cuentaPreview.innerHTML = `<img src="${fileProxyUrl}?file=${encodeURIComponent(logo)}" 
                                           alt="${banco}" 
                                           style="max-width: 50px; max-height: 40px; object-fit: contain;"
                                           onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <i class="fas fa-university text-muted" style="font-size: 1.8rem; display: none;"></i>`;
        } else {
            cuentaPreview.innerHTML = '<i class="fas fa-wallet text-muted" style="font-size: 1.8rem;"></i>';
            cuentaPreview.style.backgroundColor = '#e9ecef';
        }
    }
    
    // Selección de categoría
    categoriaButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const categoriaId = this.getAttribute('data-categoria-id');
            const categoriaNombre = this.getAttribute('data-categoria-nombre');
            const categoriaIcono = this.getAttribute('data-categoria-icono');
            const categoriaColor = this.getAttribute('data-categoria-color');
            
            categoriaInput.value = categoriaId;
            
            // Actualizar botones
            categoriaButtons.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            
            // Actualizar preview
            updateCategoriaPreview(categoriaNombre, categoriaIcono, categoriaColor);
            
            // Cerrar modal
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('categoriaModal'));
                if (modal) {
                    modal.hide();
                }
            }, 300);
        });
    });
    
    function updateCategoriaPreview(nombre, icono, color) {
        categoriaText.textContent = nombre;
        categoriaPreview.style.backgroundColor = color;
        categoriaPreview.innerHTML = `<i class="${icono} text-white" style="font-size: 1.8rem;"></i>`;
    }
    
    // Selección de día del mes
    const diaInput = document.getElementById('dia_mes');
    const diaButtons = document.querySelectorAll('.dia-select-btn');
    const diaPreview = document.getElementById('diaPreview');
    const diaText = document.getElementById('diaText');
    
    if (diaInput && diaButtons.length > 0) {
        diaButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const dia = this.getAttribute('data-dia');
                diaInput.value = dia;
                
                // Actualizar botones
                diaButtons.forEach(b => {
                    b.classList.remove('selected');
                    b.querySelector('.fa-check').style.display = 'none';
                });
                this.classList.add('selected');
                this.querySelector('.fa-check').style.display = 'block';
                
                // Actualizar preview
                diaPreview.innerHTML = `<span class="text-white fw-bold" style="font-size: 1.5rem;">${dia}</span>`;
                diaText.textContent = `Día ${dia}`;
                
                // Cerrar modal
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('diaModal'));
                    if (modal) {
                        modal.hide();
                    }
                }, 300);
            });
        });
    }
    
    // Formateo de monto con puntos de miles
    const montoInput = document.getElementById('monto');
    const montoValueInput = document.getElementById('monto_value');
    
    if (montoInput) {
        montoInput.addEventListener('input', function(e) {
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
                montoValueInput.value = numericValue || '';
            } else {
                montoValueInput.value = '';
            }
        });
        
        // Antes de enviar, asegurar que el valor esté en el campo oculto
        document.getElementById('recurringForm').addEventListener('submit', function(e) {
            const displayValue = montoInput.value.replace(/\./g, '').replace(',', '.');
            montoValueInput.value = displayValue || '0';
        });
    }
});
</script>

<?php require_once dirname(__DIR__, 4) . '/ui/views/layouts/footer.php'; ?>
