<?php
/**
 * Listado de Gastos Recurrentes con Proyección
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

// Mes actual y siguiente
$mesActual = (int)date('n');
$anioActual = (int)date('Y');
$mesSiguiente = $mesActual == 12 ? 1 : $mesActual + 1;
$anioSiguiente = $mesActual == 12 ? $anioActual + 1 : $anioActual;

$nombresMeses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    $recurringModel = new RecurringExpense($conn);
    $accountModel = new Account($conn);
    $categoryModel = new Category($conn);
    
    $gastos = $recurringModel->getAllByUser($userId);
    $proyeccionActual = $recurringModel->getProjection($userId, $mesActual, $anioActual);
    $proyeccionSiguiente = $recurringModel->getProjection($userId, $mesSiguiente, $anioSiguiente);
    
    // Calcular totales de gastos recurrentes
    $totalActual = array_sum(array_column($proyeccionActual, 'monto'));
    $totalSiguiente = array_sum(array_column($proyeccionSiguiente, 'monto'));
    
    // Calcular saldos proyectados
    $saldoActual = $recurringModel->getCurrentBalance($userId);
    $saldoProyectadoFinMesActual = $recurringModel->getProjectedBalanceEndOfMonth($userId, $mesActual, $anioActual);
    $saldoProyectadoFinMesSiguiente = $recurringModel->getProjectedBalanceEndOfMonth($userId, $mesSiguiente, $anioSiguiente);
    
} catch (Exception $e) {
    $gastos = [];
    $proyeccionActual = [];
    $proyeccionSiguiente = [];
    $totalActual = 0;
    $totalSiguiente = 0;
    $saldoActual = 0;
    $saldoProyectadoFinMesActual = 0;
    $saldoProyectadoFinMesSiguiente = 0;
    $error = 'Error al cargar los gastos recurrentes';
}

require_once dirname(__DIR__, 4) . '/ui/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/ui/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-redo me-2"></i>Gastos Recurrentes</h1>
        <a href="<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/pages/create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Nuevo Gasto Recurrente
        </a>
    </div>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Resumen de Gastos Recurrentes -->
    <h2 class="mb-3"><i class="fas fa-calculator me-2"></i>Resumen de Gastos Recurrentes</h2>
    <div class="row mb-5">
        <div class="col-md-6 mb-3">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?php echo $nombresMeses[$mesActual] . ' ' . $anioActual; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <h3 class="text-danger mb-0">$<?php echo number_format($totalActual, 2, ',', '.'); ?></h3>
                    <small class="text-muted">Total proyectado</small>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-check me-2"></i>
                        <?php echo $nombresMeses[$mesSiguiente] . ' ' . $anioSiguiente; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <h3 class="text-danger mb-0">$<?php echo number_format($totalSiguiente, 2, ',', '.'); ?></h3>
                    <small class="text-muted">Total proyectado</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lista de Gastos Recurrentes Configurados -->
    <h2 class="mb-3"><i class="fas fa-list me-2"></i>Gastos Recurrentes Configurados</h2>
    <div class="card mb-5">
        <div class="card-body">
            <?php if (empty($gastos)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                <p>No tienes gastos recurrentes configurados</p>
                <a href="<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/pages/create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Crear Primer Gasto Recurrente
                </a>
            </div>
            <?php else: ?>
            <!-- Vista Desktop: Tabla -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Día del Mes</th>
                            <th>Frecuencia</th>
                            <th>Categoría</th>
                            <th>Cuenta</th>
                            <th>Monto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gastos as $gasto): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($gasto['nombre']); ?></strong></td>
                            <td><?php echo $gasto['dia_mes']; ?></td>
                            <td>
                                <?php
                                $tipos = [
                                    'mensual' => 'Mensual',
                                    'quincenal' => 'Quincenal',
                                    'semanal' => 'Semanal',
                                    'bimestral' => 'Bimestral',
                                    'trimestral' => 'Trimestral',
                                    'semestral' => 'Semestral',
                                    'anual' => 'Anual'
                                ];
                                echo $tipos[$gasto['tipo']] ?? ucfirst($gasto['tipo']);
                                ?>
                            </td>
                            <td>
                                <?php 
                                $icono = !empty($gasto['categoria_icono']) ? trim($gasto['categoria_icono']) : 'fa-tag';
                                if (!empty($icono) && strpos($icono, 'fa-') === 0 && strpos($icono, 'fas ') !== 0) {
                                    $icono = 'fas ' . $icono;
                                }
                                ?>
                                <i class="<?php echo htmlspecialchars($icono); ?>" 
                                   style="color: <?php echo htmlspecialchars($gasto['categoria_color'] ?? '#000'); ?>;"></i>
                                <?php echo htmlspecialchars($gasto['categoria_nombre']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($gasto['cuenta_nombre']); ?></td>
                            <td class="text-danger"><strong>$<?php echo number_format($gasto['monto'], 2, ',', '.'); ?></strong></td>
                            <td>
                                <a href="<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/pages/edit.php?id=<?php echo $gasto['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteRecurringExpense(<?php echo $gasto['id']; ?>)">
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
                <?php foreach ($gastos as $gasto): ?>
                <?php 
                $icono = !empty($gasto['categoria_icono']) ? trim($gasto['categoria_icono']) : 'fa-tag';
                if (!empty($icono) && strpos($icono, 'fa-') === 0 && strpos($icono, 'fas ') !== 0) {
                    $icono = 'fas ' . $icono;
                }
                $tipos = [
                    'mensual' => 'Mensual',
                    'quincenal' => 'Quincenal',
                    'semanal' => 'Semanal'
                ];
                ?>
                <div class="card mb-3 border-start border-4" style="border-left-color: <?php echo htmlspecialchars($gasto['categoria_color'] ?? '#F1B10B'); ?> !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="mb-2"><?php echo htmlspecialchars($gasto['nombre']); ?></h6>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="badge bg-primary">Día <?php echo $gasto['dia_mes']; ?></span>
                                    <span class="badge bg-secondary"><?php echo $tipos[$gasto['tipo']] ?? ucfirst($gasto['tipo']); ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <div class="d-flex align-items-center justify-content-center" 
                                         style="width: 30px; height: 30px; background-color: <?php echo htmlspecialchars($gasto['categoria_color'] ?? '#F1B10B'); ?>; border-radius: 6px;">
                                        <i class="<?php echo htmlspecialchars($icono); ?> text-white" style="font-size: 0.9rem;"></i>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($gasto['categoria_nombre']); ?></small>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-wallet me-1"></i><?php echo htmlspecialchars($gasto['cuenta_nombre']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="text-danger fw-bold mb-2">$<?php echo number_format($gasto['monto'], 2, ',', '.'); ?></div>
                                <div class="d-flex gap-2">
                                    <a href="<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/pages/edit.php?id=<?php echo $gasto['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteRecurringExpense(<?php echo $gasto['id']; ?>)">
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
    
    <!-- Proyecciones -->
    <h2 class="mb-3"><i class="fas fa-calendar-check me-2"></i>Proyecciones</h2>
    
    <h3 class="mb-3 ms-3"><i class="fas fa-calendar-alt me-2"></i><?php echo $nombresMeses[$mesActual] . ' ' . $anioActual; ?></h3>
    <div class="card mb-5">
        <div class="card-body">
            <?php if (empty($proyeccionActual)): ?>
            <p class="text-muted text-center py-3">No hay gastos recurrentes programados para este mes</p>
            <?php else: ?>
            <!-- Vista Desktop: Tabla -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Gasto</th>
                            <th>Categoría</th>
                            <th>Cuenta</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proyeccionActual as $gasto): ?>
                        <tr>
                            <td><strong><?php echo $gasto['dia_mes']; ?></strong></td>
                            <td><?php echo htmlspecialchars($gasto['nombre']); ?></td>
                            <td>
                                <?php 
                                $icono = !empty($gasto['categoria_icono']) ? trim($gasto['categoria_icono']) : 'fa-tag';
                                if (!empty($icono) && strpos($icono, 'fa-') === 0 && strpos($icono, 'fas ') !== 0) {
                                    $icono = 'fas ' . $icono;
                                }
                                ?>
                                <i class="<?php echo htmlspecialchars($icono); ?>" 
                                   style="color: <?php echo htmlspecialchars($gasto['categoria_color'] ?? '#000'); ?>;"></i>
                                <?php echo htmlspecialchars($gasto['categoria_nombre']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($gasto['cuenta_nombre']); ?></td>
                            <td class="text-danger"><strong>$<?php echo number_format($gasto['monto'], 2, ',', '.'); ?></strong></td>
                            <td>
                                <?php if ($gasto['ejecutado']): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i>Ejecutado
                                </span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($gasto['ejecutado']): ?>
                                <a href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/index.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-eye me-1"></i>Ver
                                </a>
                                <?php else: ?>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-primary" 
                                            onclick="executeExpense(<?php echo $gasto['id']; ?>, <?php echo $mesActual; ?>, <?php echo $anioActual; ?>)">
                                        <i class="fas fa-play me-1"></i>Ejecutar
                                    </button>
                                    <button class="btn btn-sm btn-warning" 
                                            onclick="ignoreExpense(<?php echo $gasto['id']; ?>, <?php echo $mesActual; ?>, <?php echo $anioActual; ?>)">
                                        <i class="fas fa-times me-1"></i>Ignorar
                                    </button>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Vista Móvil: Cards -->
            <div class="d-md-none">
                <?php foreach ($proyeccionActual as $gasto): ?>
                <?php 
                $icono = !empty($gasto['categoria_icono']) ? trim($gasto['categoria_icono']) : 'fa-tag';
                if (!empty($icono) && strpos($icono, 'fa-') === 0 && strpos($icono, 'fas ') !== 0) {
                    $icono = 'fas ' . $icono;
                }
                ?>
                <div class="card mb-3 border-start border-4" style="border-left-color: <?php echo htmlspecialchars($gasto['categoria_color'] ?? '#F1B10B'); ?> !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-primary">Día <?php echo $gasto['dia_mes']; ?></span>
                                    <?php if ($gasto['ejecutado']): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i>Ejecutado
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Pendiente</span>
                                    <?php endif; ?>
                                </div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($gasto['nombre']); ?></h6>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <small class="text-muted">
                                        <i class="<?php echo htmlspecialchars($icono); ?>" 
                                           style="color: <?php echo htmlspecialchars($gasto['categoria_color'] ?? '#000'); ?>;"></i>
                                        <?php echo htmlspecialchars($gasto['categoria_nombre']); ?>
                                    </small>
                                    <span class="text-muted">•</span>
                                    <small class="text-muted"><?php echo htmlspecialchars($gasto['cuenta_nombre']); ?></small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-danger fw-bold mb-2">$<?php echo number_format($gasto['monto'], 2, ',', '.'); ?></div>
                                <?php if ($gasto['ejecutado']): ?>
                                <a href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/index.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php else: ?>
                                <div class="d-flex gap-2 justify-content-end">
                                    <button class="btn btn-sm btn-primary" 
                                            onclick="executeExpense(<?php echo $gasto['id']; ?>, <?php echo $mesActual; ?>, <?php echo $anioActual; ?>)">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" 
                                            onclick="ignoreExpense(<?php echo $gasto['id']; ?>, <?php echo $mesActual; ?>, <?php echo $anioActual; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <h3 class="mb-3 ms-3 mt-4"><i class="fas fa-calendar-check me-2"></i><?php echo $nombresMeses[$mesSiguiente] . ' ' . $anioSiguiente; ?></h3>
    <div class="card mb-5">
        <div class="card-body">
            <?php if (empty($proyeccionSiguiente)): ?>
            <p class="text-muted text-center py-3">No hay gastos recurrentes programados para el próximo mes</p>
            <?php else: ?>
            <!-- Vista Desktop: Tabla -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Gasto</th>
                            <th>Categoría</th>
                            <th>Cuenta</th>
                            <th>Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proyeccionSiguiente as $gasto): ?>
                        <tr>
                            <td><strong><?php echo $gasto['dia_mes']; ?></strong></td>
                            <td><?php echo htmlspecialchars($gasto['nombre']); ?></td>
                            <td>
                                <?php 
                                $icono = !empty($gasto['categoria_icono']) ? trim($gasto['categoria_icono']) : 'fa-tag';
                                if (!empty($icono) && strpos($icono, 'fa-') === 0 && strpos($icono, 'fas ') !== 0) {
                                    $icono = 'fas ' . $icono;
                                }
                                ?>
                                <i class="<?php echo htmlspecialchars($icono); ?>" 
                                   style="color: <?php echo htmlspecialchars($gasto['categoria_color'] ?? '#000'); ?>;"></i>
                                <?php echo htmlspecialchars($gasto['categoria_nombre']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($gasto['cuenta_nombre']); ?></td>
                            <td class="text-danger"><strong>$<?php echo number_format($gasto['monto'], 2, ',', '.'); ?></strong></td>
                            <td>
                                <span class="badge bg-secondary">Pendiente</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Vista Móvil: Cards -->
            <div class="d-md-none">
                <?php foreach ($proyeccionSiguiente as $gasto): ?>
                <?php 
                $icono = !empty($gasto['categoria_icono']) ? trim($gasto['categoria_icono']) : 'fa-tag';
                if (!empty($icono) && strpos($icono, 'fa-') === 0 && strpos($icono, 'fas ') !== 0) {
                    $icono = 'fas ' . $icono;
                }
                ?>
                <div class="card mb-3 border-start border-4" style="border-left-color: <?php echo htmlspecialchars($gasto['categoria_color'] ?? '#F1B10B'); ?> !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-primary">Día <?php echo $gasto['dia_mes']; ?></span>
                                    <span class="badge bg-secondary">Pendiente</span>
                                </div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($gasto['nombre']); ?></h6>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <small class="text-muted">
                                        <i class="<?php echo htmlspecialchars($icono); ?>" 
                                           style="color: <?php echo htmlspecialchars($gasto['categoria_color'] ?? '#000'); ?>;"></i>
                                        <?php echo htmlspecialchars($gasto['categoria_nombre']); ?>
                                    </small>
                                    <span class="text-muted">•</span>
                                    <small class="text-muted"><?php echo htmlspecialchars($gasto['cuenta_nombre']); ?></small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-danger fw-bold">$<?php echo number_format($gasto['monto'], 2, ',', '.'); ?></div>
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
function executeExpense(gastoId, mes, anio) {
    if (confirm('¿Desea ejecutar este gasto recurrente ahora? Se creará una transacción con la fecha correspondiente.')) {
        fetch('<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/api/execute.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                gasto_id: gastoId,
                mes: mes,
                anio: anio
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'No se pudo ejecutar el gasto'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al ejecutar el gasto');
        });
    }
}

function deleteRecurringExpense(id) {
    if (confirm('¿Está seguro de eliminar este gasto recurrente?')) {
        fetch('<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/api/delete.php', {
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
                alert('Error: ' + (data.message || 'No se pudo eliminar el gasto'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar el gasto');
        });
    }
}

function ignoreExpense(gastoId, mes, anio) {
    if (confirm('¿Desea ignorar este gasto recurrente para este mes? No se creará ninguna transacción y no aparecerá en la proyección.')) {
        fetch('<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/api/ignore.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                gasto_id: gastoId,
                mes: mes,
                anio: anio
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'No se pudo ignorar el gasto'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al ignorar el gasto');
        });
    }
}
</script>

<?php require_once dirname(__DIR__, 4) . '/ui/views/layouts/footer.php'; ?>
