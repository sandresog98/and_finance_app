<?php
/**
 * Listado de Transacciones
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

// Filtros
$filters = [
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'tipo' => $_GET['tipo'] ?? '',
    'categoria_id' => $_GET['categoria_id'] ?? '',
    'cuenta_id' => $_GET['cuenta_id'] ?? ''
];

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    $transactionModel = new Transaction($conn);
    $accountModel = new Account($conn);
    $categoryModel = new Category($conn);
    
    $transacciones = $transactionModel->getAllByUser($userId, array_filter($filters));
    $cuentas = $accountModel->getAllByUser($userId);
    $categorias = $categoryModel->getAllByUser($userId);
    
    // Calcular totales
    $totalIngresos = 0;
    $totalEgresos = 0;
    foreach ($transacciones as $t) {
        if ($t['tipo'] === 'ingreso') {
            $totalIngresos += $t['monto'];
        } elseif ($t['tipo'] === 'egreso') {
            $totalEgresos += $t['monto'];
        }
    }
    
} catch (Exception $e) {
    $transacciones = [];
    $cuentas = [];
    $categorias = [];
    $totalIngresos = 0;
    $totalEgresos = 0;
    $error = 'Error al cargar las transacciones';
}

require_once dirname(__DIR__, 4) . '/ui/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/ui/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-exchange-alt me-2"></i>Mis Transacciones</h1>
        <a href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Nueva Transacción
        </a>
    </div>
    
    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>Transacción guardada con éxito
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Resumen -->
    <div class="row mb-4">
        <div class="col-6 col-md-6 mb-3">
            <div class="card text-white" style="background-color: #198754;">
                <div class="card-body">
                    <h6 class="mb-1 text-white">Total Ingresos</h6>
                    <h3 class="mb-0 text-white">$<?php echo number_format($totalIngresos, 2, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-6 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="mb-1">Total Egresos</h6>
                    <h3 class="mb-0">$<?php echo number_format($totalEgresos, 2, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Botón de Filtros -->
    <div class="mb-3">
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="offcanvas" data-bs-target="#filtersOffcanvas" aria-controls="filtersOffcanvas">
            <i class="fas fa-filter me-2"></i>Filtros
        </button>
    </div>
    
    <!-- Offcanvas de Filtros -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="filtersOffcanvas" aria-labelledby="filtersOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="filtersOffcanvasLabel">
                <i class="fas fa-filter me-2"></i>Filtros
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <form method="GET" id="filtersForm">
                <div class="mb-3">
                    <label class="form-label">Fecha Desde</label>
                    <input type="date" class="form-control" name="fecha_desde" value="<?php echo htmlspecialchars($filters['fecha_desde']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha Hasta</label>
                    <input type="date" class="form-control" name="fecha_hasta" value="<?php echo htmlspecialchars($filters['fecha_hasta']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="tipo">
                        <option value="">Todos</option>
                        <option value="ingreso" <?php echo $filters['tipo'] === 'ingreso' ? 'selected' : ''; ?>>Ingreso</option>
                        <option value="egreso" <?php echo $filters['tipo'] === 'egreso' ? 'selected' : ''; ?>>Egreso</option>
                        <option value="transferencia" <?php echo $filters['tipo'] === 'transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                        <option value="ajuste" <?php echo $filters['tipo'] === 'ajuste' ? 'selected' : ''; ?>>Ajuste</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Cuenta</label>
                    <select class="form-select" name="cuenta_id">
                        <option value="">Todas</option>
                        <?php foreach ($cuentas as $cuenta): ?>
                        <option value="<?php echo $cuenta['id']; ?>" <?php echo $filters['cuenta_id'] == $cuenta['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cuenta['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" name="categoria_id">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $filters['categoria_id'] == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Aplicar Filtros
                    </button>
                    <a href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Lista de transacciones -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($transacciones)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                <p>No hay transacciones registradas</p>
                <a href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Crear Primera Transacción
                </a>
            </div>
            <?php else: ?>
            <!-- Vista Desktop: Tabla -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th>Cuenta</th>
                            <th>Monto</th>
                            <th>Comentario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transacciones as $t): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($t['fecha'])); ?></td>
                            <td>
                                <?php if ($t['tipo'] === 'ingreso'): ?>
                                <span class="badge bg-success">Ingreso</span>
                                <?php elseif ($t['tipo'] === 'egreso'): ?>
                                <span class="badge bg-danger">Egreso</span>
                                <?php else: ?>
                                <span class="badge bg-info">Transferencia</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="<?php echo htmlspecialchars($t['categoria_icono'] ?? 'fa-tag'); ?>" 
                                   style="color: <?php echo htmlspecialchars($t['categoria_color'] ?? '#000'); ?>;"></i>
                                <?php echo htmlspecialchars($t['categoria_nombre']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($t['cuenta_nombre']); ?></td>
                            <td class="<?php echo $t['tipo'] === 'ingreso' ? 'text-success' : ($t['tipo'] === 'egreso' ? 'text-danger' : ($t['tipo'] === 'ajuste' ? 'text-warning' : 'text-info')); ?>">
                                <?php if ($t['tipo'] === 'ingreso'): ?>
                                +$<?php echo number_format($t['monto'], 2, ',', '.'); ?>
                                <?php elseif ($t['tipo'] === 'egreso'): ?>
                                -$<?php echo number_format($t['monto'], 2, ',', '.'); ?>
                                <?php elseif ($t['tipo'] === 'ajuste'): ?>
                                ±$<?php echo number_format($t['monto'], 2, ',', '.'); ?>
                                <?php else: ?>
                                $<?php echo number_format($t['monto'], 2, ',', '.'); ?>
                                <?php endif; ?>
                                <?php if ($t['tipo'] === 'transferencia' && !empty($t['cuenta_destino_nombre'])): ?>
                                <br><small class="text-muted">→ <?php echo htmlspecialchars($t['cuenta_destino_nombre']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($t['comentario'] ?? '-'); ?></td>
                            <td>
                                <a href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/edit.php?id=<?php echo $t['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteTransaction(<?php echo $t['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Vista Móvil: Cards -->
            <div class="d-md-none">
                <?php foreach ($transacciones as $t): ?>
                <?php 
                $icono = !empty($t['categoria_icono']) ? trim($t['categoria_icono']) : 'fa-tag';
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
                $colorBorde = $t['tipo'] === 'ingreso' ? '#39843A' : ($t['tipo'] === 'egreso' ? '#dc3545' : ($t['tipo'] === 'ajuste' ? '#ffc107' : '#0dcaf0'));
                $colorTexto = $t['tipo'] === 'ingreso' ? 'text-success' : ($t['tipo'] === 'egreso' ? 'text-danger' : ($t['tipo'] === 'ajuste' ? 'text-warning' : 'text-info'));
                ?>
                <div class="card mb-3 border-start border-4 shadow-sm" style="border-left-color: <?php echo $colorBorde; ?> !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge <?php echo $t['tipo'] === 'ingreso' ? 'bg-success' : ($t['tipo'] === 'egreso' ? 'bg-danger' : ($t['tipo'] === 'ajuste' ? 'bg-warning text-dark' : 'bg-info')); ?>">
                                        <?php 
                                        if ($t['tipo'] === 'ingreso') echo 'Ingreso';
                                        elseif ($t['tipo'] === 'egreso') echo 'Egreso';
                                        elseif ($t['tipo'] === 'ajuste') echo 'Ajuste';
                                        else echo 'Transferencia';
                                        ?>
                                    </span>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y', strtotime($t['fecha'])); ?>
                                    </small>
                                </div>
                                
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="d-flex align-items-center justify-content-center" 
                                         style="width: 35px; height: 35px; background-color: <?php echo htmlspecialchars($t['categoria_color'] ?? '#F1B10B'); ?>; border-radius: 8px;">
                                        <i class="<?php echo htmlspecialchars($icono); ?> text-white" style="font-size: 1rem;"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($t['categoria_nombre']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-wallet me-1"></i><?php echo htmlspecialchars($t['cuenta_nombre']); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <?php if ($t['tipo'] === 'transferencia' && !empty($t['cuenta_destino_nombre'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-arrow-right me-1"></i>→ <?php echo htmlspecialchars($t['cuenta_destino_nombre']); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($t['comentario'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-comment me-1"></i><?php echo htmlspecialchars($t['comentario']); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-end ms-3">
                                <div class="<?php echo $colorTexto; ?> fw-bold mb-2" style="font-size: 1.1rem;">
                                    <?php if ($t['tipo'] === 'ingreso'): ?>
                                    +$<?php echo number_format($t['monto'], 2, ',', '.'); ?>
                                    <?php elseif ($t['tipo'] === 'egreso'): ?>
                                    -$<?php echo number_format($t['monto'], 2, ',', '.'); ?>
                                    <?php elseif ($t['tipo'] === 'ajuste'): ?>
                                    ±$<?php echo number_format($t['monto'], 2, ',', '.'); ?>
                                    <?php else: ?>
                                    $<?php echo number_format($t['monto'], 2, ',', '.'); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/edit.php?id=<?php echo $t['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteTransaction(<?php echo $t['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteTransaction(id) {
    if (confirm('¿Está seguro de eliminar esta transacción? Esta acción revertirá los saldos de las cuentas.')) {
        fetch('<?php echo getBaseUrl(); ?>modules/transacciones/api/delete.php', {
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
                alert('Error: ' + (data.message || 'No se pudo eliminar la transacción'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar la transacción');
        });
    }
}
</script>

<?php require_once dirname(__DIR__, 4) . '/ui/views/layouts/footer.php'; ?>
