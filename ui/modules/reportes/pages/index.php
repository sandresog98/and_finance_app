<?php
/**
 * AND FINANCE APP - Módulo de Reportes
 */

require_once __DIR__ . '/../../transacciones/models/TransaccionModel.php';
require_once __DIR__ . '/../../cuentas/models/CuentaModel.php';

$pageTitle = 'Reportes';
$pageSubtitle = 'Análisis de tus finanzas';
$transaccionModel = new TransaccionModel();
$cuentaModel = new CuentaModel();
$userId = getCurrentUserId();
$db = Database::getInstance();

// Obtener período seleccionado
$periodo = $_GET['periodo'] ?? 'mes_actual';
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');

// Calcular fechas según período
switch ($periodo) {
    case 'mes_actual':
        $fechaInicio = date('Y-m-01');
        $fechaFin = date('Y-m-t');
        $tituloperiodo = 'Este mes';
        break;
    case 'mes_anterior':
        $fechaInicio = date('Y-m-01', strtotime('-1 month'));
        $fechaFin = date('Y-m-t', strtotime('-1 month'));
        $tituloperiodo = 'Mes anterior';
        break;
    case 'anio':
        $fechaInicio = "$anio-01-01";
        $fechaFin = "$anio-12-31";
        $tituloperiodo = "Año $anio";
        break;
    case 'personalizado':
        $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
        $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-t');
        $tituloperiodo = 'Período personalizado';
        break;
    default:
        $fechaInicio = date('Y-m-01');
        $fechaFin = date('Y-m-t');
        $tituloperiodo = 'Este mes';
}

// Obtener totales del período
$totales = $transaccionModel->getTotalesPorPeriodo($userId, $fechaInicio, $fechaFin);
$balance = $totales['ingreso'] - $totales['egreso'];

// Gastos por categoría
$gastosPorCategoria = $db->prepare("
    SELECT cat.nombre, cat.color, cat.icono, COALESCE(SUM(t.monto), 0) as total
    FROM transacciones t
    JOIN categorias cat ON t.categoria_id = cat.id
    WHERE t.usuario_id = :usuario_id 
    AND t.tipo = 'egreso' 
    AND t.estado = 1
    AND t.fecha_transaccion BETWEEN :inicio AND :fin
    GROUP BY cat.id
    ORDER BY total DESC
");
$gastosPorCategoria->execute([
    'usuario_id' => $userId,
    'inicio' => $fechaInicio,
    'fin' => $fechaFin
]);
$gastosPorCategoria = $gastosPorCategoria->fetchAll();

// Ingresos por categoría
$ingresosPorCategoria = $db->prepare("
    SELECT cat.nombre, cat.color, cat.icono, COALESCE(SUM(t.monto), 0) as total
    FROM transacciones t
    JOIN categorias cat ON t.categoria_id = cat.id
    WHERE t.usuario_id = :usuario_id 
    AND t.tipo = 'ingreso' 
    AND t.estado = 1
    AND t.fecha_transaccion BETWEEN :inicio AND :fin
    GROUP BY cat.id
    ORDER BY total DESC
");
$ingresosPorCategoria->execute([
    'usuario_id' => $userId,
    'inicio' => $fechaInicio,
    'fin' => $fechaFin
]);
$ingresosPorCategoria = $ingresosPorCategoria->fetchAll();

// Evolución diaria del mes (para gráfico de líneas)
$evolucionDiaria = $db->prepare("
    SELECT 
        fecha_transaccion as fecha,
        SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
        SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as egresos
    FROM transacciones
    WHERE usuario_id = :usuario_id 
    AND estado = 1
    AND fecha_transaccion BETWEEN :inicio AND :fin
    GROUP BY fecha_transaccion
    ORDER BY fecha_transaccion
");
$evolucionDiaria->execute([
    'usuario_id' => $userId,
    'inicio' => $fechaInicio,
    'fin' => $fechaFin
]);
$evolucionDiaria = $evolucionDiaria->fetchAll();

// Saldo por cuenta
$saldosPorCuenta = $cuentaModel->getAllByUser($userId);

// Meses disponibles
$meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
?>

<!-- Filtros -->
<div class="card mb-4 fade-in-up">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="module" value="reportes">
            
            <div class="col-md-3">
                <label class="form-label small">Período</label>
                <select class="form-select form-select-sm" name="periodo" id="periodoSelect">
                    <option value="mes_actual" <?= $periodo === 'mes_actual' ? 'selected' : '' ?>>Mes actual</option>
                    <option value="mes_anterior" <?= $periodo === 'mes_anterior' ? 'selected' : '' ?>>Mes anterior</option>
                    <option value="anio" <?= $periodo === 'anio' ? 'selected' : '' ?>>Año completo</option>
                    <option value="personalizado" <?= $periodo === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                </select>
            </div>
            
            <div class="col-md-2" id="anioWrapper" style="<?= $periodo === 'anio' ? '' : 'display:none' ?>">
                <label class="form-label small">Año</label>
                <select class="form-select form-select-sm" name="anio">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $anio === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-2" id="fechaInicioWrapper" style="<?= $periodo === 'personalizado' ? '' : 'display:none' ?>">
                <label class="form-label small">Desde</label>
                <input type="date" class="form-control form-control-sm" name="fecha_inicio" value="<?= $fechaInicio ?>">
            </div>
            
            <div class="col-md-2" id="fechaFinWrapper" style="<?= $periodo === 'personalizado' ? '' : 'display:none' ?>">
                <label class="form-label small">Hasta</label>
                <input type="date" class="form-control form-control-sm" name="fecha_fin" value="<?= $fechaFin ?>">
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resumen del Período - Versión Mobile Compacta -->
<div class="reportes-mobile-stats d-md-none mb-4 fade-in-up">
    <div class="reportes-periodo-label">
        <i class="bi bi-calendar3 me-2"></i><?= $tituloperiodo ?>
    </div>
    <div class="reportes-stats-row">
        <div class="reportes-stat-item ingreso">
            <i class="bi bi-arrow-down-circle-fill"></i>
            <span class="reportes-stat-value"><?= formatMoney($totales['ingreso']) ?></span>
            <span class="reportes-stat-label">Ingresos</span>
        </div>
        <div class="reportes-stat-item egreso">
            <i class="bi bi-arrow-up-circle-fill"></i>
            <span class="reportes-stat-value"><?= formatMoney($totales['egreso']) ?></span>
            <span class="reportes-stat-label">Gastos</span>
        </div>
        <div class="reportes-stat-item <?= $balance >= 0 ? 'positivo' : 'negativo' ?>">
            <i class="bi bi-<?= $balance >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' ?>"></i>
            <span class="reportes-stat-value"><?= formatMoney($balance) ?></span>
            <span class="reportes-stat-label">Balance</span>
        </div>
    </div>
</div>

<!-- Resumen del Período - Versión Desktop -->
<div class="row g-4 mb-4 d-none d-md-flex">
    <div class="col-lg-4">
        <div class="card stat-card income fade-in-up" style="animation-delay: 0.1s;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">Total Ingresos</p>
                    <h3 class="stat-value"><?= formatMoney($totales['ingreso']) ?></h3>
                    <small class="text-white-50"><?= $tituloperiodo ?></small>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-arrow-down-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card stat-card expense fade-in-up" style="animation-delay: 0.2s;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">Total Gastos</p>
                    <h3 class="stat-value"><?= formatMoney($totales['egreso']) ?></h3>
                    <small class="text-white-50"><?= $tituloperiodo ?></small>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-arrow-up-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card stat-card <?= $balance >= 0 ? 'income' : 'expense' ?> fade-in-up" style="animation-delay: 0.3s;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">Balance</p>
                    <h3 class="stat-value"><?= formatMoney($balance) ?></h3>
                    <small class="text-white-50"><?= $balance >= 0 ? '¡Ahorraste!' : 'Gastaste más de lo que ingresaste' ?></small>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-<?= $balance >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' ?>"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos Mobile -->
<div class="d-md-none">
    <!-- Gastos por Categoría Mobile -->
    <div class="card mb-3 fade-in-up" style="animation-delay: 0.4s;">
        <div class="card-header py-2">
            <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Gastos por Categoría</h6>
        </div>
        <div class="card-body py-3">
            <?php if (empty($gastosPorCategoria)): ?>
            <div class="text-center py-3">
                <i class="bi bi-pie-chart fs-1 text-muted"></i>
                <p class="text-muted mt-2 mb-0 small">Sin datos en este período</p>
            </div>
            <?php else: ?>
            <div class="chart-container-mobile">
                <canvas id="gastosChartMobile"></canvas>
            </div>
            <div class="mt-3">
                <?php foreach (array_slice($gastosPorCategoria, 0, 5) as $cat): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div class="d-flex align-items-center">
                        <span class="color-dot me-2" style="background-color: <?= htmlspecialchars($cat['color']) ?>;"></span>
                        <small><?= htmlspecialchars($cat['nombre']) ?></small>
                    </div>
                    <strong class="small"><?= formatMoney($cat['total']) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Evolución Mobile -->
    <div class="card mb-3 fade-in-up" style="animation-delay: 0.5s;">
        <div class="card-header py-2">
            <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Evolución</h6>
        </div>
        <div class="card-body py-3">
            <?php if (empty($evolucionDiaria)): ?>
            <div class="text-center py-3">
                <i class="bi bi-graph-up fs-1 text-muted"></i>
                <p class="text-muted mt-2 mb-0 small">Sin datos en este período</p>
            </div>
            <?php else: ?>
            <div class="chart-container-mobile-line">
                <canvas id="evolucionChartMobile"></canvas>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Gráficos Desktop -->
<div class="row g-4 d-none d-md-flex">
    <!-- Gráfico de Evolución -->
    <div class="col-lg-8">
        <div class="card fade-in-up" style="animation-delay: 0.4s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Evolución en el Período</h5>
            </div>
            <div class="card-body">
                <div class="chart-container-desktop">
                    <canvas id="evolucionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Distribución de Gastos -->
    <div class="col-lg-4">
        <div class="card fade-in-up" style="animation-delay: 0.5s;">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Gastos por Categoría</h5>
            </div>
            <div class="card-body">
                <?php if (empty($gastosPorCategoria)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-pie-chart display-4 text-muted"></i>
                    <p class="text-muted mt-3">Sin datos en este período</p>
                </div>
                <?php else: ?>
                <div class="chart-container-doughnut">
                    <canvas id="gastosChart"></canvas>
                </div>
                <div class="mt-3">
                    <?php foreach (array_slice($gastosPorCategoria, 0, 5) as $cat): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div class="d-flex align-items-center">
                            <span class="color-dot me-2" style="background-color: <?= htmlspecialchars($cat['color']) ?>;"></span>
                            <small><?= htmlspecialchars($cat['nombre']) ?></small>
                        </div>
                        <strong class="small"><?= formatMoney($cat['total']) ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <!-- Ingresos por Categoría -->
    <div class="col-lg-6">
        <div class="card fade-in-up" style="animation-delay: 0.6s;">
            <div class="card-header bg-success-subtle py-2 py-md-3">
                <h6 class="mb-0 text-success d-md-none"><i class="bi bi-arrow-down-circle me-2"></i>Ingresos</h6>
                <h5 class="mb-0 text-success d-none d-md-block"><i class="bi bi-arrow-down-circle me-2"></i>Ingresos por Categoría</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($ingresosPorCategoria)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-inbox text-muted fs-3"></i>
                    <p class="text-muted small mt-2 mb-0">Sin ingresos en este período</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($ingresosPorCategoria as $cat): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center min-width-0">
                            <div class="cat-icon me-2 me-md-3" style="background-color: <?= htmlspecialchars($cat['color']) ?>20; color: <?= htmlspecialchars($cat['color']) ?>;">
                                <i class="bi <?= htmlspecialchars($cat['icono']) ?>"></i>
                            </div>
                            <span class="text-truncate"><?= htmlspecialchars($cat['nombre']) ?></span>
                        </div>
                        <strong class="text-success text-nowrap ms-2"><?= formatMoney($cat['total']) ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Saldo por Cuenta -->
    <div class="col-lg-6">
        <div class="card fade-in-up" style="animation-delay: 0.7s;">
            <div class="card-header py-2 py-md-3">
                <h6 class="mb-0 d-md-none"><i class="bi bi-wallet2 me-2"></i>Mis Cuentas</h6>
                <h5 class="mb-0 d-none d-md-block"><i class="bi bi-wallet2 me-2"></i>Saldo por Cuenta</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($saldosPorCuenta)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-wallet2 text-muted fs-3"></i>
                    <p class="text-muted small mt-2 mb-0">Sin cuentas registradas</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($saldosPorCuenta as $cuenta): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center min-width-0">
                            <div class="cuenta-icon me-2 me-md-3" style="background-color: <?= htmlspecialchars($cuenta['color'] ?? '#55A5C8') ?>;">
                                <i class="bi <?= htmlspecialchars($cuenta['icono'] ?? 'bi-wallet2') ?>"></i>
                            </div>
                            <div class="min-width-0">
                                <span class="d-block text-truncate"><?= htmlspecialchars($cuenta['nombre']) ?></span>
                                <small class="text-muted d-none d-md-block"><?= $cuenta['banco_nombre'] ?? ucfirst($cuenta['tipo']) ?></small>
                            </div>
                        </div>
                        <strong class="<?= $cuenta['saldo_actual'] >= 0 ? 'text-success' : 'text-danger' ?> text-nowrap ms-2">
                            <?= formatMoney($cuenta['saldo_actual']) ?>
                        </strong>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* ========== Estilos Mobile para Reportes ========== */
.reportes-mobile-stats {
    background: linear-gradient(135deg, #35719E 0%, #55A5C8 100%);
    border-radius: 16px;
    padding: 16px;
    color: white;
}

.reportes-periodo-label {
    text-align: center;
    font-size: 12px;
    opacity: 0.9;
    margin-bottom: 12px;
    font-weight: 500;
}

.reportes-stats-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.reportes-stat-item {
    flex: 1;
    text-align: center;
    padding: 10px 4px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.15);
}

.reportes-stat-item i {
    font-size: 16px;
    margin-bottom: 4px;
    display: block;
}

.reportes-stat-item.ingreso i { color: #9AD082; }
.reportes-stat-item.egreso i { color: #FF6B6B; }
.reportes-stat-item.positivo i { color: #9AD082; }
.reportes-stat-item.negativo i { color: #FF6B6B; }

.reportes-stat-value {
    display: block;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.reportes-stat-label {
    display: block;
    font-size: 10px;
    opacity: 0.8;
    margin-top: 2px;
}

/* ========== Contenedores de Gráficos con altura fija ========== */
.chart-container-mobile {
    position: relative;
    height: 180px;
    width: 100%;
}

.chart-container-mobile-line {
    position: relative;
    height: 200px;
    width: 100%;
}

.chart-container-desktop {
    position: relative;
    height: 280px;
    width: 100%;
}

.chart-container-doughnut {
    position: relative;
    height: 200px;
    width: 100%;
}

/* ========== Stat Cards Desktop ========== */
.stat-card {
    border-radius: 16px;
    padding: 22px;
}

.stat-card.income {
    background: linear-gradient(135deg, #9AD082 0%, #7ab85c 100%);
}

.stat-card.expense {
    background: linear-gradient(135deg, #FF6B6B 0%, #ee5a5a 100%);
}

.stat-card .stat-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: white;
}

.stat-card .stat-label {
    color: rgba(255, 255, 255, 0.85);
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 5px;
}

.stat-card .stat-value {
    color: white;
    font-size: 26px;
    font-weight: 800;
    margin: 0;
}

/* ========== Otros estilos ========== */
.bg-success-subtle {
    background-color: rgba(154, 208, 130, 0.15) !important;
}

.color-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.cat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.cuenta-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    flex-shrink: 0;
}

.list-group-item {
    border-left: none;
    border-right: none;
}

.list-group-item:first-child {
    border-top: none;
}

/* Utility */
.min-width-0 {
    min-width: 0;
}

/* Mobile adjustments for list items */
@media (max-width: 767.98px) {
    .cat-icon, .cuenta-icon {
        width: 32px;
        height: 32px;
        font-size: 14px;
    }
    
    .list-group-item {
        padding: 10px 12px;
    }
    
    .list-group-item .d-flex {
        flex-wrap: nowrap;
    }
    
    .list-group-item span {
        font-size: 13px;
    }
    
    .list-group-item strong {
        font-size: 13px;
    }
    
    /* Filtros más compactos en mobile */
    .card-body form.row {
        gap: 8px !important;
    }
    
    .card-body form .form-label {
        margin-bottom: 4px;
    }
}
</style>

<?php
// Preparar datos para gráficos
$evolucionLabels = array_map(fn($e) => date('d/m', strtotime($e['fecha'])), $evolucionDiaria);
$evolucionIngresos = array_map(fn($e) => (float)$e['ingresos'], $evolucionDiaria);
$evolucionEgresos = array_map(fn($e) => (float)$e['egresos'], $evolucionDiaria);

$gastosLabels = array_map(fn($g) => $g['nombre'], array_slice($gastosPorCategoria, 0, 6));
$gastosData = array_map(fn($g) => (float)$g['total'], array_slice($gastosPorCategoria, 0, 6));
$gastosColors = array_map(fn($g) => $g['color'], array_slice($gastosPorCategoria, 0, 6));
?>

<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/ocultar campos según período
    const periodoSelect = document.getElementById('periodoSelect');
    const anioWrapper = document.getElementById('anioWrapper');
    const fechaInicioWrapper = document.getElementById('fechaInicioWrapper');
    const fechaFinWrapper = document.getElementById('fechaFinWrapper');
    
    periodoSelect.addEventListener('change', function() {
        anioWrapper.style.display = this.value === 'anio' ? 'block' : 'none';
        fechaInicioWrapper.style.display = this.value === 'personalizado' ? 'block' : 'none';
        fechaFinWrapper.style.display = this.value === 'personalizado' ? 'block' : 'none';
    });
    
    // Configuración común para tooltips
    const tooltipConfig = {
        callbacks: {
            label: function(context) {
                return context.dataset?.label 
                    ? context.dataset.label + ': $' + new Intl.NumberFormat('es-CO').format(context.raw)
                    : context.label + ': $' + new Intl.NumberFormat('es-CO').format(context.raw);
            }
        }
    };
    
    // Detectar si es móvil
    const isMobile = window.innerWidth < 768;
    
    // ========== GRÁFICOS MOBILE ==========
    <?php if (!empty($gastosPorCategoria)): ?>
    // Gráfico de gastos - Mobile
    const gastosCtxMobile = document.getElementById('gastosChartMobile');
    if (gastosCtxMobile) {
        new Chart(gastosCtxMobile.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($gastosLabels) ?>,
                datasets: [{
                    data: <?= json_encode($gastosData) ?>,
                    backgroundColor: <?= json_encode($gastosColors) ?>,
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: tooltipConfig
                },
                cutout: '60%'
            }
        });
    }
    <?php endif; ?>
    
    <?php if (!empty($evolucionDiaria)): ?>
    // Gráfico de evolución - Mobile (Barras verticales delgadas)
    const evolucionCtxMobile = document.getElementById('evolucionChartMobile');
    if (evolucionCtxMobile) {
        new Chart(evolucionCtxMobile.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($evolucionLabels) ?>,
                datasets: [
                    {
                        label: 'Ingresos',
                        data: <?= json_encode($evolucionIngresos) ?>,
                        backgroundColor: 'rgba(154, 208, 130, 0.8)',
                        borderColor: '#9AD082',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.4,
                        categoryPercentage: 0.8
                    },
                    {
                        label: 'Gastos',
                        data: <?= json_encode($evolucionEgresos) ?>,
                        backgroundColor: 'rgba(255, 107, 107, 0.8)',
                        borderColor: '#FF6B6B',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.4,
                        categoryPercentage: 0.8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 10, font: { size: 11 } }
                    },
                    tooltip: tooltipConfig
                },
                scales: {
                    x: {
                        ticks: { maxTicksLimit: 8, font: { size: 10 } },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 5,
                            font: { size: 10 },
                            callback: function(value) {
                                if (value >= 1000000) return '$' + (value / 1000000).toFixed(1) + 'M';
                                if (value >= 1000) return '$' + (value / 1000).toFixed(0) + 'k';
                                return '$' + value;
                            }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    // ========== GRÁFICOS DESKTOP ==========
    <?php if (!empty($evolucionDiaria)): ?>
    // Gráfico de evolución - Desktop (Barras verticales delgadas)
    const evolucionCtx = document.getElementById('evolucionChart');
    if (evolucionCtx) {
        new Chart(evolucionCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($evolucionLabels) ?>,
                datasets: [
                    {
                        label: 'Ingresos',
                        data: <?= json_encode($evolucionIngresos) ?>,
                        backgroundColor: 'rgba(154, 208, 130, 0.8)',
                        borderColor: '#9AD082',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.35,
                        categoryPercentage: 0.8
                    },
                    {
                        label: 'Gastos',
                        data: <?= json_encode($evolucionEgresos) ?>,
                        backgroundColor: 'rgba(255, 107, 107, 0.8)',
                        borderColor: '#FF6B6B',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.35,
                        categoryPercentage: 0.8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: tooltipConfig
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000) return '$' + (value / 1000000).toFixed(1) + 'M';
                                if (value >= 1000) return '$' + (value / 1000).toFixed(0) + 'k';
                                return '$' + value;
                            }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    <?php if (!empty($gastosPorCategoria)): ?>
    // Gráfico de gastos - Desktop
    const gastosCtx = document.getElementById('gastosChart');
    if (gastosCtx) {
        new Chart(gastosCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($gastosLabels) ?>,
                datasets: [{
                    data: <?= json_encode($gastosData) ?>,
                    backgroundColor: <?= json_encode($gastosColors) ?>,
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: tooltipConfig
                },
                cutout: '65%'
            }
        });
    }
    <?php endif; ?>
});
</script>
<?php $extraScripts = ob_get_clean(); ?>

