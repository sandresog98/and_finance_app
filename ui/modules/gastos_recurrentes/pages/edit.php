<?php
/**
 * Editar Gasto Recurrente
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
$gasto = null;

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    $recurringModel = new RecurringExpense($conn);
    $accountModel = new Account($conn);
    $categoryModel = new Category($conn);
    
    $gasto = $recurringModel->getById((int)$id, $userId);
    
    if (!$gasto) {
        header('Location: index.php');
        exit;
    }
    
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
            $result = $recurringModel->update((int)$id, $userId, [
                'nombre' => $nombre,
                'cuenta_id' => $cuentaId,
                'categoria_id' => $categoriaId,
                'monto' => $monto,
                'dia_mes' => $diaMes,
                'tipo' => $tipo
            ]);
            
            if ($result['success']) {
                header('Location: index.php?success=1');
                exit;
            } else {
                $error = $result['message'] ?? 'Error al actualizar el gasto recurrente';
            }
        }
    }
} catch (Exception $e) {
    $error = 'Error al procesar la solicitud';
    error_log('Edit recurring expense error: ' . $e->getMessage());
    $cuentas = [];
    $categorias = [];
}

require_once dirname(__DIR__, 4) . '/ui/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/ui/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-edit me-2"></i>Editar Gasto Recurrente</h1>
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
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nombre" class="form-label">Nombre del Gasto <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required
                               value="<?php echo htmlspecialchars($gasto['nombre']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="monto" class="form-label">Monto <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="monto" name="monto" 
                                   step="0.01" min="0.01" value="<?php echo $gasto['monto']; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="cuenta_id" class="form-label">Cuenta <span class="text-danger">*</span></label>
                        <select class="form-select" id="cuenta_id" name="cuenta_id" required>
                            <option value="">Seleccionar cuenta</option>
                            <?php foreach ($cuentas as $cuenta): ?>
                            <option value="<?php echo $cuenta['id']; ?>" 
                                    <?php echo $gasto['cuenta_id'] == $cuenta['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cuenta['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="categoria_id" class="form-label">Categoría <span class="text-danger">*</span></label>
                        <select class="form-select" id="categoria_id" name="categoria_id" required>
                            <option value="">Seleccionar categoría</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo $gasto['categoria_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="dia_mes" class="form-label">Día del Mes <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="dia_mes" name="dia_mes" 
                               min="1" max="31" value="<?php echo $gasto['dia_mes']; ?>" required>
                        <small class="text-muted">Día en que se ejecuta el gasto (1-31)</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="tipo" class="form-label">Frecuencia <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="mensual" <?php echo $gasto['tipo'] === 'mensual' ? 'selected' : ''; ?>>Mensual (cada mes)</option>
                            <option value="quincenal" <?php echo $gasto['tipo'] === 'quincenal' ? 'selected' : ''; ?>>Quincenal (día 15 y último día del mes)</option>
                            <option value="semanal" <?php echo $gasto['tipo'] === 'semanal' ? 'selected' : ''; ?>>Semanal (cada semana)</option>
                            <option value="bimestral" <?php echo $gasto['tipo'] === 'bimestral' ? 'selected' : ''; ?>>Bimestral (cada 2 meses)</option>
                            <option value="trimestral" <?php echo $gasto['tipo'] === 'trimestral' ? 'selected' : ''; ?>>Trimestral (cada 3 meses)</option>
                            <option value="semestral" <?php echo $gasto['tipo'] === 'semestral' ? 'selected' : ''; ?>>Semestral (cada 6 meses)</option>
                            <option value="anual" <?php echo $gasto['tipo'] === 'anual' ? 'selected' : ''; ?>>Anual (una vez al año)</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Guardar Cambios
                    </button>
                    <a href="<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/pages/index.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 4) . '/ui/views/layouts/footer.php'; ?>
