<?php
/**
 * Reportes y Dashboard
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
require_once __DIR__ . '/../models/Report.php';
require_once dirname(__DIR__, 4) . '/ui/modules/cuentas/models/Account.php';
require_once dirname(__DIR__, 4) . '/ui/modules/categorias/models/Category.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\Reportes\Models\Report;
use UI\Modules\Cuentas\Models\Account;
use UI\Modules\Categorias\Models\Category;

$currentPage = 'reportes';
$currentUser = $_SESSION['and_finance_user'];
$userId = $currentUser['id'];

// Filtros por defecto
$filtroPeriodo = $_GET['periodo'] ?? 'mes_actual';
$filtroAnio = (int)($_GET['anio'] ?? date('Y'));

// Calcular fechas según período
$fechaDesde = '';
$fechaHasta = '';

switch ($filtroPeriodo) {
    case 'mes_actual':
        $fechaDesde = date('Y-m-01');
        $fechaHasta = date('Y-m-t');
        break;
    case 'mes_anterior':
        $fechaDesde = date('Y-m-01', strtotime('first day of last month'));
        $fechaHasta = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'anio_actual':
        $fechaDesde = date('Y-01-01');
        $fechaHasta = date('Y-12-31');
        break;
    case 'anio_completo':
        $fechaDesde = $filtroAnio . '-01-01';
        $fechaHasta = $filtroAnio . '-12-31';
        break;
    case 'personalizado':
        $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
        $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-t');
        break;
}

$nombresMeses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    $reportModel = new Report($conn);
    $accountModel = new Account($conn);
    $categoryModel = new Category($conn);
    
    $summary = $reportModel->getIncomeExpenseSummary($userId, $fechaDesde, $fechaHasta);
    $expensesByCategory = $reportModel->getExpensesByCategory($userId, $fechaDesde, $fechaHasta);
    $incomeByCategory = $reportModel->getIncomeByCategory($userId, $fechaDesde, $fechaHasta);
    
    $cuentas = $accountModel->getAllByUser($userId);
    $categorias = $categoryModel->getAllByUser($userId);
    
    // Calcular rango de meses: 3 meses anteriores, mes actual y mes siguiente (total 5 meses)
    $mesActual = (int)date('n');
    $anioActual = (int)date('Y');
    
    $mesesParaMostrar = [];
    for ($i = -3; $i <= 1; $i++) {
        $mes = $mesActual + $i;
        $anio = $anioActual;
        
        // Ajustar si el mes es menor a 1 o mayor a 12
        while ($mes < 1) {
            $mes += 12;
            $anio--;
        }
        while ($mes > 12) {
            $mes -= 12;
            $anio++;
        }
        
        $mesesParaMostrar[] = [
            'mes' => $mes,
            'anio' => $anio
        ];
    }
    
    // Obtener datos mensuales para el rango calculado
    $monthlyData = [];
    foreach ($mesesParaMostrar as $item) {
        $data = $reportModel->getMonthlyData($userId, $item['anio'], $item['mes']);
        $monthlyData[$item['mes'] . '_' . $item['anio']] = $data;
    }
    
    // Preparar datos para gráficos
    $chartMonthlyLabels = [];
    $chartMonthlyIncome = [];
    $chartMonthlyExpense = [];
    
    foreach ($mesesParaMostrar as $item) {
        $key = $item['mes'] . '_' . $item['anio'];
        $data = $monthlyData[$key] ?? ['ingreso' => 0, 'egreso' => 0];
        
        // Formato: "Enero 2025" o "Enero" si es el año actual
        $label = $nombresMeses[$item['mes']];
        if ($item['anio'] != $anioActual) {
            $label .= ' ' . $item['anio'];
        }
        
        $chartMonthlyLabels[] = $label;
        $chartMonthlyIncome[] = $data['ingreso'] ?? 0;
        $chartMonthlyExpense[] = $data['egreso'] ?? 0;
    }
    
    $chartCategoryLabels = [];
    $chartCategoryData = [];
    $chartCategoryColors = [];
    
    foreach ($expensesByCategory as $cat) {
        $chartCategoryLabels[] = $cat['categoria'];
        $chartCategoryData[] = (float)$cat['total'];
        $chartCategoryColors[] = $cat['color'] ?? '#F1B10B';
    }
    
} catch (Exception $e) {
    $summary = ['ingreso' => 0, 'egreso' => 0];
    $monthlyData = [];
    $expensesByCategory = [];
    $incomeByCategory = [];
    $chartMonthlyLabels = [];
    $chartMonthlyIncome = [];
    $chartMonthlyExpense = [];
    $chartCategoryLabels = [];
    $chartCategoryData = [];
    $chartCategoryColors = [];
    $error = 'Error al cargar los reportes';
}

require_once dirname(__DIR__, 4) . '/ui/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/ui/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-chart-bar me-2"></i>Reportes y Estadísticas</h1>
    </div>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Período</label>
                    <select class="form-select" name="periodo" id="periodo" onchange="updateFilters()">
                        <option value="mes_actual" <?php echo $filtroPeriodo === 'mes_actual' ? 'selected' : ''; ?>>Mes Actual</option>
                        <option value="mes_anterior" <?php echo $filtroPeriodo === 'mes_anterior' ? 'selected' : ''; ?>>Mes Anterior</option>
                        <option value="anio_actual" <?php echo $filtroPeriodo === 'anio_actual' ? 'selected' : ''; ?>>Año Actual</option>
                        <option value="anio_completo" <?php echo $filtroPeriodo === 'anio_completo' ? 'selected' : ''; ?>>Año Completo</option>
                        <option value="personalizado" <?php echo $filtroPeriodo === 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                    </select>
                </div>
                <div class="col-md-3" id="div_anio" style="display: <?php echo $filtroPeriodo === 'anio_completo' ? 'block' : 'none'; ?>;">
                    <label class="form-label">Año</label>
                    <select class="form-select" name="anio">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $filtroAnio == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3" id="div_fecha_desde" style="display: <?php echo $filtroPeriodo === 'personalizado' ? 'block' : 'none'; ?>;">
                    <label class="form-label">Fecha Desde</label>
                    <input type="date" class="form-control" name="fecha_desde" value="<?php echo $fechaDesde; ?>">
                </div>
                <div class="col-md-3" id="div_fecha_hasta" style="display: <?php echo $filtroPeriodo === 'personalizado' ? 'block' : 'none'; ?>;">
                    <label class="form-label">Fecha Hasta</label>
                    <input type="date" class="form-control" name="fecha_hasta" value="<?php echo $fechaHasta; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resumen -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card text-white" style="background-color: #198754;">
                <div class="card-body">
                    <h6 class="mb-1 text-white">Total Ingresos</h6>
                    <h3 class="mb-0 text-white">$<?php echo number_format($summary['ingreso'], 2, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="mb-1">Total Egresos</h6>
                    <h3 class="mb-0">$<?php echo number_format($summary['egreso'], 2, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-white" style="background: <?php echo ($summary['ingreso'] - $summary['egreso']) >= 0 ? 'linear-gradient(135deg, var(--primary-color) 0%, var(--third-color) 100%)' : '#ffc107'; ?>;">
                <div class="card-body">
                    <h6 class="mb-1 text-white">Balance</h6>
                    <h3 class="mb-0 text-white">$<?php echo number_format($summary['ingreso'] - $summary['egreso'], 2, ',', '.'); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficos -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--third-color) 100%);">
                    <h5 class="mb-0 text-white"><i class="fas fa-chart-line me-2"></i>Ingresos vs. Egresos por Mes</h5>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header text-white" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--third-color) 100%);">
                    <h5 class="mb-0 text-white"><i class="fas fa-chart-pie me-2"></i>Gastos por Categoría</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabla de Gastos por Categoría -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-white" style="background-color: #dc3545;">
                    <h5 class="mb-0 text-white"><i class="fas fa-arrow-up me-2"></i>Top Gastos por Categoría</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($expensesByCategory)): ?>
                    <p class="text-muted text-center py-3">No hay gastos en este período</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expensesByCategory as $cat): ?>
                                <tr>
                                    <td>
                                        <i class="<?php echo htmlspecialchars($cat['icono'] ?? 'fa-tag'); ?>" 
                                           style="color: <?php echo htmlspecialchars($cat['color'] ?? '#000'); ?>;"></i>
                                        <?php echo htmlspecialchars($cat['categoria']); ?>
                                    </td>
                                    <td class="text-end text-danger">
                                        <strong>$<?php echo number_format($cat['total'], 2, ',', '.'); ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header text-white" style="background-color: #198754;">
                    <h5 class="mb-0 text-white"><i class="fas fa-arrow-down me-2"></i>Top Ingresos por Categoría</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($incomeByCategory)): ?>
                    <p class="text-muted text-center py-3">No hay ingresos en este período</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incomeByCategory as $cat): ?>
                                <tr>
                                    <td>
                                        <i class="<?php echo htmlspecialchars($cat['icono'] ?? 'fa-tag'); ?>" 
                                           style="color: <?php echo htmlspecialchars($cat['color'] ?? '#000'); ?>;"></i>
                                        <?php echo htmlspecialchars($cat['categoria']); ?>
                                    </td>
                                    <td class="text-end text-success">
                                        <strong>$<?php echo number_format($cat['total'], 2, ',', '.'); ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function updateFilters() {
    const periodo = document.getElementById('periodo').value;
    document.getElementById('div_anio').style.display = periodo === 'anio_completo' ? 'block' : 'none';
    document.getElementById('div_fecha_desde').style.display = periodo === 'personalizado' ? 'block' : 'none';
    document.getElementById('div_fecha_hasta').style.display = periodo === 'personalizado' ? 'block' : 'none';
}

// Gráfico de Ingresos vs Egresos por Mes
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chartMonthlyLabels); ?>,
        datasets: [{
            label: 'Ingresos',
            data: <?php echo json_encode($chartMonthlyIncome); ?>,
            borderColor: '#39843A',
            backgroundColor: 'rgba(57, 132, 58, 0.1)',
            tension: 0.4
        }, {
            label: 'Egresos',
            data: <?php echo json_encode($chartMonthlyExpense); ?>,
            borderColor: '#F1B10B',
            backgroundColor: 'rgba(241, 177, 11, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Gráfico de Gastos por Categoría
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($chartCategoryLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($chartCategoryData); ?>,
            backgroundColor: <?php echo json_encode($chartCategoryColors); ?>
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
            }
        }
    }
});
</script>

<?php require_once dirname(__DIR__, 4) . '/ui/views/layouts/footer.php'; ?>
