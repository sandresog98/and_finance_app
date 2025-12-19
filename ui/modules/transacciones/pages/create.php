<?php
/**
 * Crear Transacción
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
require_once dirname(__DIR__, 4) . '/utils/FileUploadManager.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once dirname(__DIR__, 4) . '/ui/modules/cuentas/models/Account.php';
require_once dirname(__DIR__, 4) . '/ui/modules/categorias/models/Category.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\Transacciones\Models\Transaction;
use UI\Modules\Cuentas\Models\Account;
use UI\Modules\Categorias\Models\Category;

$currentPage = 'transacciones';
$currentUser = $_SESSION['and_finance_user'];
$userId = $currentUser['id'];
$error = '';

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    $transactionModel = new Transaction($conn);
    $accountModel = new Account($conn);
    $categoryModel = new Category($conn);
    
    $cuentas = $accountModel->getAllByUser($userId);
    $categoriasIngresos = $categoryModel->getByType($userId, 'ingreso');
    $categoriasEgresos = $categoryModel->getByType($userId, 'egreso');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tipo = $_POST['tipo'] ?? '';
        $cuentaId = (int)($_POST['cuenta_id'] ?? 0);
        $categoriaId = (int)($_POST['categoria_id'] ?? 0);
        $monto = (float)($_POST['monto'] ?? 0);
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $comentario = trim($_POST['comentario'] ?? '');
        $cuentaDestinoId = !empty($_POST['cuenta_destino_id']) ? (int)$_POST['cuenta_destino_id'] : null;
        $esProgramada = isset($_POST['es_programada']) ? (bool)$_POST['es_programada'] : false;
        
        // Validación: categoría no requerida para transferencias
        if (empty($tipo) || $cuentaId <= 0 || $monto <= 0) {
            $error = 'Todos los campos requeridos deben estar completos';
        } elseif ($tipo !== 'transferencia' && $categoriaId <= 0) {
            $error = 'Debe seleccionar una categoría';
        } else {
            $data = [
                'usuario_id' => $userId,
                'cuenta_id' => $cuentaId,
                'tipo' => $tipo,
                'monto' => $monto,
                'fecha' => $fecha,
                'comentario' => $comentario,
                'es_programada' => $esProgramada
            ];
            
            // Categoría solo para ingresos y egresos, no para transferencias
            if ($tipo !== 'transferencia' && $categoriaId > 0) {
                $data['categoria_id'] = $categoriaId;
            }
            
            if ($tipo === 'transferencia') {
                if (!$cuentaDestinoId || $cuentaDestinoId == $cuentaId) {
                    $error = 'Debe seleccionar una cuenta destino diferente';
                } else {
                    $data['cuenta_destino_id'] = $cuentaDestinoId;
                }
            }
            
            if (empty($error)) {
                $result = $transactionModel->create($data);
                
                if ($result['success']) {
                    $transactionId = $result['id'];
                    
                    // Procesar archivos adjuntos si hay
                    if (isset($_FILES['archivos']) && !empty($_FILES['archivos']['name'][0])) {
                        $uploadDir = dirname(__DIR__, 4) . '/uploads/transacciones/';
                        $webPath = '/and_finance_app/uploads/transacciones';
                        
                        foreach ($_FILES['archivos']['name'] as $key => $name) {
                            if ($_FILES['archivos']['error'][$key] === UPLOAD_ERR_OK) {
                                $file = [
                                    'name' => $_FILES['archivos']['name'][$key],
                                    'type' => $_FILES['archivos']['type'][$key],
                                    'tmp_name' => $_FILES['archivos']['tmp_name'][$key],
                                    'error' => $_FILES['archivos']['error'][$key],
                                    'size' => $_FILES['archivos']['size'][$key]
                                ];
                                
                                try {
                                    $uploadResult = FileUploadManager::saveUploadedFile(
                                        $file,
                                        $uploadDir,
                                        [
                                            'maxSize' => 10 * 1024 * 1024, // 10MB
                                            'allowedExtensions' => ['jpg', 'jpeg', 'png', 'pdf'],
                                            'prefix' => 'transaccion',
                                            'userId' => (string)$userId,
                                            'createSubdirs' => true,
                                            'webPath' => $webPath
                                        ]
                                    );
                                    
                                    // Guardar referencia en BD
                                    $stmt = $conn->prepare("
                                        INSERT INTO transacciones_archivos 
                                        (transaccion_id, nombre_original, nombre_archivo, ruta, tipo_mime, tamano)
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ");
                                    $stmt->execute([
                                        $transactionId,
                                        $uploadResult['original_name'],
                                        $uploadResult['file_name'],
                                        $uploadResult['web_path'],
                                        $uploadResult['mime_type'],
                                        $uploadResult['size']
                                    ]);
                                } catch (Exception $e) {
                                    error_log('File upload error: ' . $e->getMessage());
                                    // Continuar aunque falle un archivo
                                }
                            }
                        }
                    }
                    
                    header('Location: index.php?success=1');
                    exit;
                } else {
                    $error = $result['message'] ?? 'Error al crear la transacción';
                }
            }
        }
    }
} catch (Exception $e) {
    $error = 'Error al procesar la solicitud';
    error_log('Create transaction error: ' . $e->getMessage());
    $cuentas = [];
    $categoriasIngresos = [];
    $categoriasEgresos = [];
}

require_once dirname(__DIR__, 4) . '/ui/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/ui/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-plus-circle me-2"></i>Nueva Transacción</h1>
        <a href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/index.php" class="btn btn-secondary">
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
            <form method="POST" enctype="multipart/form-data" id="transactionForm">
                <!-- Tipo: Toggle Button -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block mb-3">Tipo de Transacción <span class="text-danger">*</span></label>
                    <input type="hidden" id="tipo" name="tipo" value="egreso" required>
                    <div class="d-flex gap-3">
                        <button type="button" class="btn tipo-toggle-btn flex-fill p-4 rounded-custom shadow-sm" 
                                data-tipo="ingreso" 
                                id="btn-ingreso"
                                style="font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease;">
                            <i class="fas fa-arrow-down me-2"></i>Ingreso
                        </button>
                        <button type="button" class="btn tipo-toggle-btn flex-fill p-4 rounded-custom shadow-sm active" 
                                data-tipo="egreso" 
                                id="btn-egreso"
                                style="font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease;">
                            <i class="fas fa-arrow-up me-2"></i>Egreso
                        </button>
                        <button type="button" class="btn tipo-toggle-btn flex-fill p-4 rounded-custom shadow-sm" 
                                data-tipo="transferencia" 
                                id="btn-transferencia"
                                style="font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease;">
                            <i class="fas fa-exchange-alt me-2"></i>Transferencia
                        </button>
                    </div>
                </div>
                
                <!-- Fecha -->
                <div class="mb-4">
                    <label for="fecha" class="form-label fw-bold">Fecha <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-lg" id="fecha" name="fecha" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                    <div id="programada_info" class="mt-2" style="display: none;">
                        <small class="text-info">
                            <i class="fas fa-info-circle me-1"></i>
                            <span id="programada_text"></span>
                        </small>
                    </div>
                </div>
                
                <!-- Cuenta: Botón con Modal -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block mb-3" id="label_cuenta">Cuenta <span class="text-danger">*</span></label>
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
                
                <!-- Cuenta Destino: Botón con Modal (solo para transferencias) -->
                <div class="mb-4" id="div_cuenta_destino" style="display: none;">
                    <label class="form-label fw-bold d-block mb-3">Cuenta Destino <span class="text-danger">*</span></label>
                    <input type="hidden" id="cuenta_destino_id" name="cuenta_destino_id" value="">
                    <button type="button" class="btn btn-outline-primary btn-lg w-100 p-4 rounded-custom shadow-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#cuentaDestinoModal"
                            style="border: 2px dashed var(--primary-color); background: #f8f9fa;">
                        <div class="d-flex align-items-center justify-content-center gap-3">
                            <div id="cuentaDestinoPreview" class="d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px; background-color: #e9ecef; border-radius: 12px;">
                                <i class="fas fa-wallet text-muted" style="font-size: 1.8rem;"></i>
                            </div>
                            <div class="text-start flex-grow-1">
                                <div class="fw-bold" id="cuentaDestinoText">Seleccionar cuenta destino</div>
                                <small class="text-muted">Haz clic para elegir una cuenta</small>
                            </div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </button>
                </div>
                
                <!-- Categoría: Botón con Modal (oculto para transferencias) -->
                <div class="mb-4" id="div_categoria">
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
                
                <!-- Comentario -->
                <div class="mb-4">
                    <label for="comentario" class="form-label fw-bold">Comentario</label>
                    <textarea class="form-control form-control-lg" id="comentario" name="comentario" rows="3" 
                              placeholder="Descripción adicional (opcional)"></textarea>
                </div>
                
                <!-- Comprobantes -->
                <div class="mb-4">
                    <label for="archivos" class="form-label fw-bold">Comprobantes (opcional)</label>
                    <input type="file" class="form-control form-control-lg" id="archivos" name="archivos[]" 
                           accept="image/jpeg,image/png,image/jpg,application/pdf" multiple>
                    <small class="text-muted">Puedes subir múltiples archivos. Máximo 10MB por archivo. Formatos: JPG, PNG, PDF</small>
                </div>
                
                <!-- Botones de acción -->
                <div class="mt-4 pt-3 border-top d-flex gap-3">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-save me-2"></i>Guardar
                    </button>
                    <a href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/index.php" class="btn btn-secondary btn-lg px-4">
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

<!-- Modal de Selección de Cuenta Destino -->
<div class="modal fade" id="cuentaDestinoModal" tabindex="-1" aria-labelledby="cuentaDestinoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-custom">
            <div class="modal-header" style="background: var(--primary-color); color: white;">
                <h5 class="modal-title fw-bold" id="cuentaDestinoModalLabel">
                    <i class="fas fa-wallet me-2"></i>Seleccionar Cuenta Destino
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
                        <button type="button" class="cuenta-destino-select-btn w-100 p-3 rounded-custom border-0 bg-light shadow-sm" 
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
                <div id="categoriaModalContent">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <p>Primero selecciona el tipo de transacción</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tipo-toggle-btn {
    background: #e9ecef;
    color: #495057;
    border: 2px solid transparent;
}

.tipo-toggle-btn.active {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15) !important;
}

.tipo-toggle-btn[data-tipo="ingreso"].active {
    background: linear-gradient(135deg, #39843A 0%, #2d6a2e 100%);
    color: white;
    border-color: #39843A;
}

.tipo-toggle-btn[data-tipo="egreso"].active {
    background: linear-gradient(135deg, #F1B10B 0%, #d19e0a 100%);
    color: #1F4738;
    border-color: #F1B10B;
}

.tipo-toggle-btn[data-tipo="transferencia"].active {
    background: linear-gradient(135deg, #31424B 0%, #1F4738 100%);
    color: white;
    border-color: #31424B;
}

.cuenta-select-btn:hover,
.cuenta-destino-select-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    background: var(--primary-color) !important;
    color: white !important;
}

.cuenta-select-btn:hover small,
.cuenta-select-btn:hover .badge,
.cuenta-destino-select-btn:hover small,
.cuenta-destino-select-btn:hover .badge {
    color: white !important;
}

.cuenta-select-btn.selected,
.cuenta-destino-select-btn.selected {
    background: var(--primary-color) !important;
    border: 2px solid var(--third-color) !important;
    transform: scale(1.02);
    color: white !important;
}

.cuenta-select-btn.selected small,
.cuenta-select-btn.selected .badge,
.cuenta-destino-select-btn.selected small,
.cuenta-destino-select-btn.selected .badge {
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
const categoriasIngresos = <?php echo json_encode($categoriasIngresos); ?>;
const categoriasEgresos = <?php echo json_encode($categoriasEgresos); ?>;
const baseProjectUrl = '<?php 
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $marker = '/and_finance_app/';
    $pos = strpos($scriptName, $marker);
    echo $pos !== false ? substr($scriptName, 0, $pos + strlen($marker)) : '/and_finance_app/';
?>';

document.addEventListener('DOMContentLoaded', function() {
    const tipoInput = document.getElementById('tipo');
    const btnIngreso = document.getElementById('btn-ingreso');
    const btnEgreso = document.getElementById('btn-egreso');
    const btnTransferencia = document.getElementById('btn-transferencia');
    const cuentaDestinoDiv = document.getElementById('div_cuenta_destino');
    const categoriaDiv = document.getElementById('div_categoria');
    const categoriaIdInput = document.getElementById('categoria_id');
    const labelCuenta = document.getElementById('label_cuenta');
    
    // Toggle tipo
    [btnIngreso, btnEgreso, btnTransferencia].forEach(btn => {
        btn.addEventListener('click', function() {
            const tipo = this.getAttribute('data-tipo');
            tipoInput.value = tipo;
            
            // Actualizar botones
            btnIngreso.classList.remove('active');
            btnEgreso.classList.remove('active');
            btnTransferencia.classList.remove('active');
            this.classList.add('active');
            
            // Manejar cuenta destino
            if (tipo === 'transferencia') {
                cuentaDestinoDiv.style.display = 'block';
                document.getElementById('cuenta_destino_id').required = true;
                labelCuenta.textContent = 'Cuenta Origen *';
                
                // Ocultar categoría para transferencias
                categoriaDiv.style.display = 'none';
                categoriaIdInput.required = false;
                categoriaIdInput.value = '';
            } else {
                cuentaDestinoDiv.style.display = 'none';
                document.getElementById('cuenta_destino_id').required = false;
                labelCuenta.textContent = 'Cuenta *';
                
                // Mostrar categoría para ingresos y egresos
                categoriaDiv.style.display = 'block';
                categoriaIdInput.required = true;
            }
            
            // Actualizar categorías disponibles
            if (tipo !== 'transferencia') {
                updateCategoriasModal(tipo);
            }
        });
    });
    
    // Función para actualizar el modal de categorías
    function updateCategoriasModal(tipo) {
        const modalContent = document.getElementById('categoriaModalContent');
        let categorias = [];
        
        if (tipo === 'ingreso') {
            categorias = categoriasIngresos;
        } else if (tipo === 'egreso' || tipo === 'transferencia') {
            categorias = categoriasEgresos;
        }
        
        if (categorias.length === 0) {
            modalContent.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No tienes categorías de ${tipo === 'ingreso' ? 'ingreso' : 'egreso'} registradas</p>
                    <a href="<?php echo getBaseUrl(); ?>modules/categorias/pages/create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Crear Categoría
                    </a>
                </div>
            `;
        } else {
            let html = '<div class="categoria-grid" style="max-height: 400px; overflow-y: auto;"><div class="row g-3">';
            categorias.forEach(cat => {
                let icono = cat.icono || 'fa-tag';
                if (icono && !icono.includes('fas ') && !icono.includes('far ') && !icono.includes('fab ')) {
                    if (icono.startsWith('fa-')) {
                        icono = 'fas ' + icono;
                    } else {
                        icono = 'fas fa-' + icono;
                    }
                }
                const color = cat.color || (tipo === 'ingreso' ? '#39843A' : '#F1B10B');
                html += `
                    <div class="col-md-4 col-6">
                        <button type="button" class="categoria-select-btn w-100 p-3 rounded-custom border-0 bg-light shadow-sm" 
                                data-categoria-id="${cat.id}"
                                data-categoria-nombre="${cat.nombre}"
                                data-categoria-icono="${icono}"
                                data-categoria-color="${color}"
                                style="transition: all 0.2s ease; min-height: 100px;"
                                title="${cat.nombre}">
                            <div class="d-flex flex-column align-items-center justify-content-center">
                                <div class="d-flex align-items-center justify-content-center mb-2" 
                                     style="width: 50px; height: 50px; background-color: ${color}; border-radius: 10px;">
                                    <i class="${icono} text-white" style="font-size: 1.5rem;"></i>
                                </div>
                                <small class="text-center">${cat.nombre}</small>
                            </div>
                        </button>
                    </div>
                `;
            });
            html += '</div></div>';
            modalContent.innerHTML = html;
            
            // Agregar event listeners a los nuevos botones
            document.querySelectorAll('.categoria-select-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const categoriaId = this.getAttribute('data-categoria-id');
                    const categoriaNombre = this.getAttribute('data-categoria-nombre');
                    const categoriaIcono = this.getAttribute('data-categoria-icono');
                    const categoriaColor = this.getAttribute('data-categoria-color');
                    
                    document.getElementById('categoria_id').value = categoriaId;
                    
                    // Actualizar botones
                    document.querySelectorAll('.categoria-select-btn').forEach(b => b.classList.remove('selected'));
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
        }
    }
    
    // Selección de cuenta
    const cuentaInput = document.getElementById('cuenta_id');
    const cuentaButtons = document.querySelectorAll('.cuenta-select-btn');
    const cuentaPreview = document.getElementById('cuentaPreview');
    const cuentaText = document.getElementById('cuentaText');
    
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
            updateCuentaPreview(cuentaNombre, cuentaBanco, cuentaLogo, cuentaTipo, cuentaPreview, cuentaText);
            
            // Cerrar modal
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('cuentaModal'));
                if (modal) {
                    modal.hide();
                }
            }, 300);
        });
    });
    
    // Selección de cuenta destino
    const cuentaDestinoInput = document.getElementById('cuenta_destino_id');
    const cuentaDestinoButtons = document.querySelectorAll('.cuenta-destino-select-btn');
    const cuentaDestinoPreview = document.getElementById('cuentaDestinoPreview');
    const cuentaDestinoText = document.getElementById('cuentaDestinoText');
    
    cuentaDestinoButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const cuentaId = this.getAttribute('data-cuenta-id');
            const cuentaNombre = this.getAttribute('data-cuenta-nombre');
            const cuentaBanco = this.getAttribute('data-cuenta-banco');
            const cuentaLogo = this.getAttribute('data-cuenta-logo');
            const cuentaTipo = this.getAttribute('data-cuenta-tipo');
            
            cuentaDestinoInput.value = cuentaId;
            
            // Actualizar botones
            cuentaDestinoButtons.forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            
            // Actualizar preview
            updateCuentaPreview(cuentaNombre, cuentaBanco, cuentaLogo, cuentaTipo, cuentaDestinoPreview, cuentaDestinoText);
            
            // Cerrar modal
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('cuentaDestinoModal'));
                if (modal) {
                    modal.hide();
                }
            }, 300);
        });
    });
    
    function updateCuentaPreview(nombre, banco, logo, tipo, previewEl, textEl) {
        textEl.textContent = nombre;
        if (logo) {
            previewEl.innerHTML = `<img src="${baseProjectUrl}${logo}" 
                                       alt="${banco}" 
                                       style="max-width: 50px; max-height: 40px; object-fit: contain;"
                                       onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <i class="fas fa-university text-muted" style="font-size: 1.8rem; display: none;"></i>`;
        } else {
            previewEl.innerHTML = '<i class="fas fa-wallet text-muted" style="font-size: 1.8rem;"></i>';
            previewEl.style.backgroundColor = '#e9ecef';
        }
    }
    
    function updateCategoriaPreview(nombre, icono, color) {
        document.getElementById('categoriaText').textContent = nombre;
        const preview = document.getElementById('categoriaPreview');
        preview.style.backgroundColor = color;
        preview.innerHTML = `<i class="${icono} text-white" style="font-size: 1.8rem;"></i>`;
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
        document.getElementById('transactionForm').addEventListener('submit', function(e) {
            const displayValue = montoInput.value.replace(/\./g, '').replace(',', '.');
            montoValueInput.value = displayValue || '0';
        });
    }
    
    // Verificar si la transacción será programada según la fecha
    const fechaInput = document.getElementById('fecha');
    const programadaInfo = document.getElementById('programada_info');
    const programadaText = document.getElementById('programada_text');
    
    function checkFechaProgramada() {
        if (!fechaInput.value) return;
        
        const fechaSeleccionada = new Date(fechaInput.value);
        const fechaActual = new Date();
        fechaActual.setHours(0, 0, 0, 0);
        fechaSeleccionada.setHours(0, 0, 0, 0);
        
        if (fechaSeleccionada > fechaActual) {
            programadaText.textContent = 'Esta transacción será programada. El saldo no se actualizará hasta la fecha seleccionada.';
            programadaInfo.style.display = 'block';
        } else {
            programadaText.textContent = 'Esta transacción se registrará inmediatamente y actualizará el saldo de la cuenta.';
            programadaInfo.style.display = 'block';
        }
    }
    
    fechaInput.addEventListener('change', checkFechaProgramada);
    checkFechaProgramada(); // Verificar al cargar la página
    
    // Inicializar categorías según el tipo por defecto (egreso)
    updateCategoriasModal('egreso');
});
</script>

<?php require_once dirname(__DIR__, 4) . '/ui/views/layouts/footer.php'; ?>
