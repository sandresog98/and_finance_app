<?php
/**
 * AND FINANCE APP - Listado de Transacciones
 */

require_once __DIR__ . '/../models/TransaccionModel.php';
require_once __DIR__ . '/../../cuentas/models/CuentaModel.php';
require_once __DIR__ . '/../../recurrentes/models/GastoRecurrenteModel.php';

$pageTitle = 'Transacciones';
$pageSubtitle = 'Historial de movimientos';
$transaccionModel = new TransaccionModel();
$cuentaModel = new CuentaModel();
$gastoRecurrenteModel = new GastoRecurrenteModel();
$userId = getCurrentUserId();

// Recalcular próximas ejecuciones (por si hay fechas incorrectas)
$gastoRecurrenteModel->recalcularProximasEjecuciones($userId);

// Procesar gastos recurrentes pendientes (crear transacciones programadas)
$resultadoProceso = $gastoRecurrenteModel->procesarPendientes($userId);
if ($resultadoProceso['creadas'] > 0) {
    setFlashMessage('info', "Se crearon {$resultadoProceso['creadas']} transacción(es) programada(s) de tus gastos recurrentes");
}

// Obtener filtros
$filtros = [
    'tipo' => $_GET['tipo'] ?? '',
    'cuenta_id' => $_GET['cuenta_id'] ?? '',
    'categoria_id' => $_GET['categoria_id'] ?? '',
    'fecha_inicio' => $_GET['fecha_inicio'] ?? date('Y-m-01'),
    'fecha_fin' => $_GET['fecha_fin'] ?? date('Y-m-t')
];

// Manejar acciones
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'delete' && $id > 0) {
    $transaccion = $transaccionModel->getById($id);
    if ($transaccion && $transaccion['usuario_id'] == $userId) {
        if ($transaccionModel->delete($id)) {
            setFlashMessage('success', 'Transacción eliminada');
        }
    }
    ob_end_clean();
    header('Location: ' . uiModuleUrl('transacciones'));
    exit;
}

if ($action === 'realizar' && $id > 0) {
    $transaccion = $transaccionModel->getById($id);
    if ($transaccion && $transaccion['usuario_id'] == $userId) {
        if ($transaccionModel->marcarRealizada($id, true)) {
            setFlashMessage('success', '¡Transacción marcada como realizada!');
        }
    }
    ob_end_clean();
    header('Location: ' . uiModuleUrl('transacciones'));
    exit;
}

if ($action === 'programar' && $id > 0) {
    $transaccion = $transaccionModel->getById($id);
    if ($transaccion && $transaccion['usuario_id'] == $userId) {
        if ($transaccionModel->marcarRealizada($id, false)) {
            setFlashMessage('success', 'Transacción marcada como programada');
        }
    }
    ob_end_clean();
    header('Location: ' . uiModuleUrl('transacciones'));
    exit;
}

// Obtener datos
$transacciones = $transaccionModel->getByUser($userId, $filtros);
$cuentas = $cuentaModel->getAllByUser($userId);
$totales = $transaccionModel->getTotalesPorPeriodo($userId, $filtros['fecha_inicio'], $filtros['fecha_fin']);
$programadas = $transaccionModel->contarProgramadas($userId);
$balance = $totales['ingreso'] - $totales['egreso'];

// Obtener categorías
$db = Database::getInstance();
$categorias = $db->prepare("
    SELECT id, nombre, tipo, icono, color FROM categorias 
    WHERE usuario_id = :usuario_id AND es_sistema = 0 AND estado = 1
    ORDER BY tipo, nombre
");
$categorias->execute(['usuario_id' => $userId]);
$categorias = $categorias->fetchAll();

// Contar filtros activos
$filtrosActivos = 0;
if (!empty($filtros['tipo'])) $filtrosActivos++;
if (!empty($filtros['cuenta_id'])) $filtrosActivos++;
if (!empty($filtros['categoria_id'])) $filtrosActivos++;

// Formatear fechas para mostrar
$mesActual = strftime('%B %Y', strtotime($filtros['fecha_inicio']));
$meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 
          'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
          'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
$mesActual = str_replace(array_keys($meses), array_values($meses), date('F Y', strtotime($filtros['fecha_inicio'])));
?>

<!-- Header compacto con resumen -->
<div class="resumen-header mb-4 fade-in-up">
    <div class="resumen-periodo">
        <span class="text-muted small">Período:</span>
        <strong><?= $mesActual ?></strong>
    </div>
    <div class="resumen-numeros">
        <div class="resumen-item ingreso">
            <i class="bi bi-arrow-down-circle-fill"></i>
            <span><?= formatMoney($totales['ingreso']) ?></span>
        </div>
        <div class="resumen-item egreso">
            <i class="bi bi-arrow-up-circle-fill"></i>
            <span><?= formatMoney($totales['egreso']) ?></span>
        </div>
        <div class="resumen-item balance <?= $balance >= 0 ? 'positivo' : 'negativo' ?>">
            <i class="bi bi-wallet2"></i>
            <span><?= formatMoney($balance) ?></span>
        </div>
    </div>
</div>

<!-- Barra de acciones -->
<div class="acciones-bar mb-4 fade-in-up" style="animation-delay: 0.1s;">
    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#filtrosDrawer">
        <i class="bi bi-funnel me-1"></i>Filtros
        <?php if ($filtrosActivos > 0): ?>
        <span class="badge bg-primary ms-1"><?= $filtrosActivos ?></span>
        <?php endif; ?>
    </button>
    
    <?php if ($programadas > 0): ?>
    <span class="badge-programadas">
        <i class="bi bi-clock-history"></i>
        <?= $programadas ?> pendiente<?= $programadas > 1 ? 's' : '' ?>
    </span>
    <?php endif; ?>
    
    <a href="<?= uiModuleUrl('transacciones', 'crear') ?>" class="btn btn-primary ms-auto d-none d-sm-inline-flex">
        <i class="bi bi-plus-lg me-1"></i>Nueva
    </a>
</div>

<!-- Listado de Transacciones -->
<div class="card fade-in-up" style="animation-delay: 0.2s;">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <h6 class="mb-0">
            <i class="bi bi-list-ul me-2"></i>Movimientos
            <span class="badge bg-secondary ms-1"><?= count($transacciones) ?></span>
        </h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($transacciones)): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox display-1 text-muted"></i>
            <h5 class="mt-4 mb-3">No hay transacciones</h5>
            <p class="text-muted mb-4">No se encontraron movimientos con los filtros seleccionados.</p>
            <a href="<?= uiModuleUrl('transacciones', 'crear') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>Registrar transacción
            </a>
        </div>
        <?php else: ?>
        <div class="transacciones-list">
            <?php 
            $lastDate = '';
            foreach ($transacciones as $trans): 
                $currentDate = $trans['fecha_transaccion'];
                $esProgramada = isset($trans['realizada']) && $trans['realizada'] == 0;
                
                // Formatear fecha en español
                $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                $mesesArr = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                $fechaTs = strtotime($currentDate);
                $fechaFormateada = $diasSemana[date('w', $fechaTs)] . ' ' . date('d', $fechaTs) . ' de ' . $mesesArr[(int)date('n', $fechaTs)];
            ?>
            <?php if ($currentDate !== $lastDate): ?>
            <div class="fecha-separador">
                <span><?= $fechaFormateada ?></span>
            </div>
            <?php $lastDate = $currentDate; endif; ?>
            
            <div class="transaccion-item <?= $esProgramada ? 'programada' : '' ?>">
                <?php if ($esProgramada): ?>
                <div class="programada-badge">
                    <i class="bi bi-clock"></i> Programada
                </div>
                <?php endif; ?>
                
                <div class="d-flex align-items-start">
                    <!-- Icono de tipo/categoría -->
                    <?php 
                    $iconClass = 'trans-' . $trans['tipo'];
                    if ($trans['tipo'] === 'ajuste') {
                        $iconClass = str_starts_with($trans['descripcion'] ?? '', '+') ? 'trans-ingreso' : 'trans-egreso';
                    }
                    ?>
                    <div class="trans-icon me-3 <?= $iconClass ?> flex-shrink-0">
                        <?php if ($trans['tipo'] === 'ajuste'): ?>
                        <i class="bi bi-sliders"></i>
                        <?php elseif ($trans['categoria_icono']): ?>
                        <i class="bi <?= htmlspecialchars($trans['categoria_icono']) ?>"></i>
                        <?php else: ?>
                        <i class="bi <?= $trans['tipo'] === 'ingreso' ? 'bi-arrow-down' : ($trans['tipo'] === 'egreso' ? 'bi-arrow-up' : 'bi-arrow-left-right') ?>"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info principal -->
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <?php 
                            // Limpiar descripción de ajustes (quitar +/- del inicio)
                            $descripcionMostrar = $trans['descripcion'] ?: $trans['categoria_nombre'] ?? 'Sin descripción';
                            if ($trans['tipo'] === 'ajuste' && preg_match('/^[+-]\s*/', $descripcionMostrar)) {
                                $descripcionMostrar = preg_replace('/^[+-]\s*/', '', $descripcionMostrar);
                            }
                            ?>
                            <h6 class="mb-0 text-truncate pe-2 trans-descripcion">
                                <?= htmlspecialchars($descripcionMostrar) ?>
                            </h6>
                            <?php 
                            // Para ajustes, el signo está al inicio de la descripción
                            $esAjustePositivo = $trans['tipo'] === 'ajuste' && str_starts_with($trans['descripcion'] ?? '', '+');
                            $esAjusteNegativo = $trans['tipo'] === 'ajuste' && str_starts_with($trans['descripcion'] ?? '', '-');
                            
                            $montoClass = match(true) {
                                $trans['tipo'] === 'ingreso' || $esAjustePositivo => 'text-success',
                                $trans['tipo'] === 'egreso' || $esAjusteNegativo => 'text-danger',
                                $trans['tipo'] === 'ajuste' => 'text-purple',
                                default => 'text-primary'
                            };
                            $montoPrefix = match(true) {
                                $trans['tipo'] === 'ingreso' || $esAjustePositivo => '+',
                                $trans['tipo'] === 'egreso' || $esAjusteNegativo => '-',
                                default => ''
                            };
                            ?>
                            <strong class="trans-monto <?= $montoClass ?> flex-shrink-0">
                                <?= $montoPrefix ?><?= formatMoney($trans['monto']) ?>
                            </strong>
                        </div>
                        
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <!-- Cuenta -->
                            <span class="cuenta-badge">
                                <span class="cuenta-dot" style="background-color: <?= htmlspecialchars($trans['cuenta_color'] ?? '#55A5C8') ?>;"></span>
                                <?= htmlspecialchars($trans['cuenta_nombre']) ?>
                                <?php if ($trans['tipo'] === 'transferencia' && $trans['cuenta_destino_nombre']): ?>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <?= htmlspecialchars($trans['cuenta_destino_nombre']) ?>
                                <?php endif; ?>
                            </span>
                            
                            <!-- Categoría -->
                            <?php if ($trans['categoria_nombre']): ?>
                            <span class="categoria-badge" style="background-color: <?= htmlspecialchars($trans['categoria_color'] ?? '#B1BCBF') ?>20; color: <?= htmlspecialchars($trans['categoria_color'] ?? '#B1BCBF') ?>;">
                                <?= htmlspecialchars($trans['categoria_nombre']) ?>
                            </span>
                            <?php endif; ?>
                            
                            <!-- Indicador de comprobantes -->
                            <?php if (!empty($trans['num_archivos']) && $trans['num_archivos'] > 0): ?>
                            <button type="button" class="comprobante-badge" 
                                    onclick="verComprobantes(<?= $trans['id'] ?>)" 
                                    title="Ver comprobantes">
                                <i class="bi bi-paperclip"></i>
                                <?= $trans['num_archivos'] ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($trans['comentario']): ?>
                        <small class="text-muted d-block text-truncate"><?= htmlspecialchars($trans['comentario']) ?></small>
                        <?php endif; ?>
                        
                        <!-- Acciones -->
                        <div class="d-flex justify-content-end gap-2 mt-2">
                            <?php if ($esProgramada): ?>
                            <a href="<?= uiModuleUrl('transacciones') ?>&action=realizar&id=<?= $trans['id'] ?>" 
                               class="btn-action btn-action-check" title="Marcar como realizada">
                                <i class="bi bi-check-lg"></i>
                                <span class="d-none d-sm-inline ms-1">Realizada</span>
                            </a>
                            <?php endif; ?>
                            <a href="<?= uiModuleUrl('transacciones', 'editar', ['id' => $trans['id']]) ?>" 
                               class="btn-action btn-action-edit" title="Editar">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <button type="button" class="btn-action btn-action-delete" 
                                    onclick="confirmDelete('<?= uiModuleUrl('transacciones') ?>&action=delete&id=<?= $trans['id'] ?>', 'esta transacción')"
                                    title="Eliminar">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Offcanvas Filtros -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="filtrosDrawer">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title"><i class="bi bi-funnel me-2"></i>Filtros</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form method="GET" action="" id="formFiltros">
            <input type="hidden" name="module" value="transacciones">
            <input type="hidden" name="tipo" id="filtroTipo" value="<?= htmlspecialchars($filtros['tipo']) ?>">
            <input type="hidden" name="cuenta_id" id="filtroCuenta" value="<?= htmlspecialchars($filtros['cuenta_id']) ?>">
            <input type="hidden" name="categoria_id" id="filtroCategoria" value="<?= htmlspecialchars($filtros['categoria_id']) ?>">
            
            <!-- Tipo de movimiento -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Tipo de movimiento</label>
                <button type="button" class="filtro-selector w-100" id="btnFiltroTipo" onclick="abrirModalFiltroTipo()">
                    <div id="filtroTipoPreview">
                        <?php if ($filtros['tipo']): ?>
                        <?php 
                        $tiposInfo = [
                            'ingreso' => ['nombre' => 'Ingresos', 'icono' => 'bi-arrow-down-circle-fill', 'color' => '#9AD082'],
                            'egreso' => ['nombre' => 'Egresos', 'icono' => 'bi-arrow-up-circle-fill', 'color' => '#FF6B6B'],
                            'transferencia' => ['nombre' => 'Transferencias', 'icono' => 'bi-arrow-left-right', 'color' => '#55A5C8'],
                            'ajuste' => ['nombre' => 'Ajustes', 'icono' => 'bi-sliders', 'color' => '#9c27b0']
                        ];
                        $t = $tiposInfo[$filtros['tipo']] ?? null;
                        ?>
                        <?php if ($t): ?>
                        <div class="filtro-selected">
                            <span class="filtro-icon" style="background: <?= $t['color'] ?>"><i class="bi <?= $t['icono'] ?>"></i></span>
                            <span class="filtro-name"><?= $t['nombre'] ?></span>
                            <i class="bi bi-chevron-down ms-auto text-muted"></i>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-muted"><i class="bi bi-layers me-2"></i>Todos los tipos</span>
                        <?php endif; ?>
                    </div>
                </button>
            </div>
            
            <!-- Cuenta -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Cuenta</label>
                <button type="button" class="filtro-selector w-100" id="btnFiltroCuenta" onclick="abrirModalFiltroCuenta()">
                    <div id="filtroCuentaPreview">
                        <?php 
                        $cuentaFiltro = null;
                        if ($filtros['cuenta_id']) {
                            foreach ($cuentas as $c) {
                                if ($c['id'] == $filtros['cuenta_id']) {
                                    $cuentaFiltro = $c;
                                    break;
                                }
                            }
                        }
                        ?>
                        <?php if ($cuentaFiltro): ?>
                        <div class="filtro-selected">
                            <span class="filtro-icon" style="background: <?= $cuentaFiltro['color'] ?? '#55A5C8' ?>">
                                <i class="bi <?= $cuentaFiltro['icono'] ?? 'bi-wallet2' ?>"></i>
                            </span>
                            <span class="filtro-name"><?= htmlspecialchars($cuentaFiltro['nombre']) ?></span>
                            <i class="bi bi-chevron-down ms-auto text-muted"></i>
                        </div>
                        <?php else: ?>
                        <span class="text-muted"><i class="bi bi-wallet2 me-2"></i>Todas las cuentas</span>
                        <?php endif; ?>
                    </div>
                </button>
            </div>
            
            <!-- Categoría -->
            <div class="mb-4">
                <label class="form-label fw-semibold">Categoría</label>
                <button type="button" class="filtro-selector w-100" id="btnFiltroCategoria" onclick="abrirModalFiltroCategoria()">
                    <div id="filtroCategoriaPreview">
                        <?php 
                        $catFiltro = null;
                        if ($filtros['categoria_id']) {
                            foreach ($categorias as $c) {
                                if ($c['id'] == $filtros['categoria_id']) {
                                    $catFiltro = $c;
                                    break;
                                }
                            }
                        }
                        ?>
                        <?php if ($catFiltro): ?>
                        <div class="filtro-selected">
                            <span class="filtro-icon" style="background: <?= $catFiltro['color'] ?? '#6c757d' ?>">
                                <i class="bi <?= $catFiltro['icono'] ?? 'bi-tag' ?>"></i>
                            </span>
                            <span class="filtro-name"><?= htmlspecialchars($catFiltro['nombre']) ?></span>
                            <i class="bi bi-chevron-down ms-auto text-muted"></i>
                        </div>
                        <?php else: ?>
                        <span class="text-muted"><i class="bi bi-tag me-2"></i>Todas las categorías</span>
                        <?php endif; ?>
                    </div>
                </button>
            </div>
            
            <hr>
            
            <div class="mb-4">
                <label class="form-label fw-semibold">Período</label>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-muted">Desde</label>
                        <input type="date" class="form-control" name="fecha_inicio" value="<?= $filtros['fecha_inicio'] ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted">Hasta</label>
                        <input type="date" class="form-control" name="fecha_fin" value="<?= $filtros['fecha_fin'] ?>">
                    </div>
                </div>
            </div>
            
            <!-- Atajos de período -->
            <div class="mb-4">
                <label class="form-label small text-muted">Atajos rápidos</label>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary periodo-btn" data-periodo="hoy">Hoy</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary periodo-btn" data-periodo="semana">Esta semana</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary periodo-btn" data-periodo="mes">Este mes</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary periodo-btn" data-periodo="anio">Este año</button>
                </div>
            </div>
            
            <hr>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-2"></i>Aplicar filtros
                </button>
                <a href="<?= uiModuleUrl('transacciones') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-2"></i>Limpiar filtros
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Modal Filtro Tipo -->
<div class="modal fade" id="modalFiltroTipo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title">Tipo de movimiento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="filtro-option <?= $filtros['tipo'] === '' ? 'selected' : '' ?>" data-value="">
                    <span class="filtro-icon" style="background: #6c757d"><i class="bi bi-layers"></i></span>
                    <span>Todos</span>
                    <i class="bi bi-check-circle-fill check-icon"></i>
                </div>
                <div class="filtro-option <?= $filtros['tipo'] === 'ingreso' ? 'selected' : '' ?>" data-value="ingreso">
                    <span class="filtro-icon" style="background: #9AD082"><i class="bi bi-arrow-down-circle-fill"></i></span>
                    <span>Ingresos</span>
                    <i class="bi bi-check-circle-fill check-icon"></i>
                </div>
                <div class="filtro-option <?= $filtros['tipo'] === 'egreso' ? 'selected' : '' ?>" data-value="egreso">
                    <span class="filtro-icon" style="background: #FF6B6B"><i class="bi bi-arrow-up-circle-fill"></i></span>
                    <span>Egresos</span>
                    <i class="bi bi-check-circle-fill check-icon"></i>
                </div>
                <div class="filtro-option <?= $filtros['tipo'] === 'transferencia' ? 'selected' : '' ?>" data-value="transferencia">
                    <span class="filtro-icon" style="background: #55A5C8"><i class="bi bi-arrow-left-right"></i></span>
                    <span>Transferencias</span>
                    <i class="bi bi-check-circle-fill check-icon"></i>
                </div>
                <div class="filtro-option <?= $filtros['tipo'] === 'ajuste' ? 'selected' : '' ?>" data-value="ajuste">
                    <span class="filtro-icon" style="background: #9c27b0"><i class="bi bi-sliders"></i></span>
                    <span>Ajustes</span>
                    <i class="bi bi-check-circle-fill check-icon"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Filtro Cuenta -->
<div class="modal fade" id="modalFiltroCuenta" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title">Seleccionar cuenta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="filtro-option <?= $filtros['cuenta_id'] === '' ? 'selected' : '' ?>" data-value="" data-nombre="Todas las cuentas" data-color="#6c757d" data-icono="bi-wallet2">
                    <span class="filtro-icon" style="background: #6c757d"><i class="bi bi-wallet2"></i></span>
                    <span>Todas las cuentas</span>
                    <i class="bi bi-check-circle-fill check-icon"></i>
                </div>
                <?php foreach ($cuentas as $cuenta): ?>
                <div class="filtro-option <?= $filtros['cuenta_id'] == $cuenta['id'] ? 'selected' : '' ?>" 
                     data-value="<?= $cuenta['id'] ?>"
                     data-nombre="<?= htmlspecialchars($cuenta['nombre']) ?>"
                     data-color="<?= $cuenta['color'] ?? '#55A5C8' ?>"
                     data-icono="<?= $cuenta['icono'] ?? 'bi-wallet2' ?>">
                    <span class="filtro-icon" style="background: <?= $cuenta['color'] ?? '#55A5C8' ?>">
                        <i class="bi <?= $cuenta['icono'] ?? 'bi-wallet2' ?>"></i>
                    </span>
                    <span><?= htmlspecialchars($cuenta['nombre']) ?></span>
                    <i class="bi bi-check-circle-fill check-icon"></i>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Filtro Categoría -->
<div class="modal fade" id="modalFiltroCategoria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title">Seleccionar categoría</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="filtro-option <?= $filtros['categoria_id'] === '' ? 'selected' : '' ?>" data-value="" data-nombre="Todas las categorías" data-color="#6c757d" data-icono="bi-tag">
                    <span class="filtro-icon" style="background: #6c757d"><i class="bi bi-tag"></i></span>
                    <span>Todas las categorías</span>
                    <i class="bi bi-check-circle-fill check-icon"></i>
                </div>
                <?php foreach ($categorias as $cat): ?>
                <div class="filtro-option <?= $filtros['categoria_id'] == $cat['id'] ? 'selected' : '' ?>" 
                     data-value="<?= $cat['id'] ?>"
                     data-nombre="<?= htmlspecialchars($cat['nombre']) ?>"
                     data-color="<?= $cat['color'] ?? '#6c757d' ?>"
                     data-icono="<?= $cat['icono'] ?? 'bi-tag' ?>">
                    <span class="filtro-icon" style="background: <?= $cat['color'] ?? '#6c757d' ?>">
                        <i class="bi <?= $cat['icono'] ?? 'bi-tag' ?>"></i>
                    </span>
                    <span><?= htmlspecialchars($cat['nombre']) ?></span>
                    <i class="bi bi-check-circle-fill check-icon"></i>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Botón flotante móvil (siempre visible en móvil) -->
<a href="<?= uiModuleUrl('transacciones', 'crear') ?>" class="btn-float-mobile">
    <i class="bi bi-plus-lg"></i>
</a>

<style>
.min-width-0 { min-width: 0; }

/* Resumen header compacto */
.resumen-header {
    background: var(--bg-card, white);
    border-radius: 16px;
    padding: 16px;
    box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.06));
}
.resumen-periodo {
    text-align: center;
    margin-bottom: 12px;
    color: var(--text-primary, #333);
}
.resumen-numeros {
    display: flex;
    justify-content: space-around;
    gap: 8px;
}
.resumen-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 12px;
    border-radius: 12px;
    flex: 1;
}
.resumen-item i { font-size: 18px; margin-bottom: 4px; }
.resumen-item span { font-weight: 700; font-size: 14px; }
.resumen-item.ingreso { background: rgba(154, 208, 130, 0.15); color: #5a9a3e; }
.resumen-item.egreso { background: rgba(255, 107, 107, 0.15); color: #ee5a5a; }
.resumen-item.balance.positivo { background: rgba(85, 165, 200, 0.15); color: var(--dark-blue); }
.resumen-item.balance.negativo { background: rgba(255, 107, 107, 0.15); color: #ee5a5a; }

@media (min-width: 576px) {
    .resumen-header { display: flex; align-items: center; justify-content: space-between; }
    .resumen-periodo { margin-bottom: 0; text-align: left; }
    .resumen-numeros { gap: 16px; }
    .resumen-item { flex-direction: row; gap: 8px; padding: 10px 16px; }
    .resumen-item i { margin-bottom: 0; }
    .resumen-item span { font-size: 16px; }
}

/* Barra de acciones */
.acciones-bar {
    display: flex;
    align-items: center;
    gap: 12px;
}
.badge-programadas {
    background: rgba(247, 220, 111, 0.3);
    color: #856404;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

/* Lista de transacciones */
.transacciones-list { padding: 0; }

.fecha-separador {
    background: var(--bg-input, #f8f9fa);
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary, #666);
    text-transform: capitalize;
    border-bottom: 1px solid var(--border-color, #eee);
}

.transaccion-item {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-light, #f0f0f0);
    position: relative;
    transition: background-color 0.2s ease;
}
.transaccion-item:last-child { border-bottom: none; }
.transaccion-item:hover { background-color: var(--bg-hover, #fafafa); }

.transaccion-item.programada {
    background: linear-gradient(90deg, rgba(247, 220, 111, 0.1), transparent);
    border-left: 3px solid #f7dc6f;
}
.programada-badge {
    position: absolute;
    top: 8px;
    right: 12px;
    background: #f7dc6f;
    color: #856404;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
}

.trans-icon {
    width: 42px; height: 42px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
}
.trans-ingreso { background: rgba(154, 208, 130, 0.2); color: #5a9a3e; }
.trans-egreso { background: rgba(255, 107, 107, 0.2); color: #ee5a5a; }
.trans-transferencia { background: rgba(85, 165, 200, 0.2); color: var(--dark-blue); }
.trans-ajuste { background: rgba(156, 39, 176, 0.15); color: #9c27b0; }

.text-purple { color: #9c27b0 !important; }

.trans-monto { font-size: 15px; }

.trans-descripcion {
    color: var(--text-primary, #333);
}

.cuenta-badge {
    display: inline-flex; align-items: center;
    font-size: 11px; color: var(--text-secondary, #666);
    background: var(--bg-input, #f5f5f5); padding: 3px 8px;
    border-radius: 20px;
}
.cuenta-dot {
    width: 8px; height: 8px; border-radius: 50%;
    margin-right: 5px; flex-shrink: 0;
}
.categoria-badge {
    font-size: 11px; padding: 3px 8px; border-radius: 20px;
    color: var(--text-primary, #333);
}

/* Badge de comprobantes */
.comprobante-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 20px;
    background: linear-gradient(135deg, #55A5C8, #35719E);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(85, 165, 200, 0.3);
}
.comprobante-badge i {
    font-size: 14px;
}
.comprobante-badge:hover {
    background: linear-gradient(135deg, #35719E, #2a5a7e);
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(85, 165, 200, 0.4);
}
.comprobante-badge:active {
    transform: scale(0.98);
}

/* Botones de acción */
.btn-action {
    width: 32px; height: 32px; 
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 8px; border: none;
    font-size: 13px; cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}
.btn-action:hover { transform: scale(1.1); }

.btn-action-edit { background: rgba(85, 165, 200, 0.15); color: var(--primary-blue); }
.btn-action-edit:hover { background: var(--primary-blue); color: white; }

.btn-action-delete { background: rgba(255, 107, 107, 0.15); color: #dc3545; }
.btn-action-delete:hover { background: #FF6B6B; color: white; }

.btn-action-check {
    background: rgba(154, 208, 130, 0.25); color: #5a9a3e;
    width: auto; padding: 0 12px;
}
.btn-action-check:hover { background: var(--secondary-green); color: white; }

/* Botón flotante móvil */
.btn-float-mobile {
    display: none;
}

@media (max-width: 575.98px) {
    .btn-float-mobile {
        display: flex;
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
        color: white;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        box-shadow: 0 6px 24px rgba(85, 165, 200, 0.5);
        z-index: 1030; /* Menor que offcanvas-backdrop (1040) para quedar detrás */
        text-decoration: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }
    
    /* Ocultar FAB cuando el drawer está abierto */
    .offcanvas.show ~ .btn-float-mobile,
    body.offcanvas-open .btn-float-mobile {
        opacity: 0;
        pointer-events: none;
    }
    .btn-float-mobile:hover,
    .btn-float-mobile:active {
        transform: scale(1.1);
        box-shadow: 0 8px 30px rgba(85, 165, 200, 0.6);
        color: white;
    }
}

/* Mobile optimizations */
@media (max-width: 576px) {
    .resumen-item span { font-size: 12px; }
    .trans-icon { width: 38px; height: 38px; font-size: 16px; }
    .trans-monto { font-size: 14px; }
    .btn-action { width: 30px; height: 30px; font-size: 12px; }
    .btn-action-check { padding: 0 10px; }
}

/* Filtro selectores */
.filtro-selector {
    display: flex;
    align-items: center;
    padding: 10px 14px;
    border: 2px solid var(--border-color, #e9ecef);
    border-radius: 10px;
    background: var(--bg-card, white);
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
}
.filtro-selector:hover {
    border-color: var(--primary-blue);
}
.filtro-selected {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}
.filtro-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
    flex-shrink: 0;
}
.filtro-name {
    font-weight: 500;
    color: var(--text-primary, var(--dark-blue));
    flex: 1;
}

/* Filtro opciones en modal */
.filtro-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s ease;
    margin-bottom: 4px;
    color: var(--text-primary, #333);
}
.filtro-option:hover {
    background: var(--bg-hover, #f8f9fa);
}
.filtro-option.selected {
    background: rgba(85, 165, 200, 0.1);
}
.filtro-option .check-icon {
    margin-left: auto;
    color: var(--primary-blue);
    opacity: 0;
}
.filtro-option.selected .check-icon {
    opacity: 1;
}

/* Estilos del modal de comprobantes */
.comprobante-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg-input, #f8f9fa);
    border-radius: 12px;
    margin-bottom: 8px;
}
.comprobante-thumbnail {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}
.comprobante-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.comprobante-pdf-icon {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    background: rgba(220, 53, 69, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.comprobante-info {
    flex: 1;
    min-width: 0;
}
.comprobante-nombre {
    font-weight: 500;
    color: var(--text-primary, #333);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<!-- Modal Ver Comprobantes -->
<div class="modal fade" id="modalComprobantes" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-paperclip me-2"></i>Comprobantes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="listaComprobantes">
                    <div class="text-center py-4 text-muted">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        Cargando comprobantes...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Atajos de período
    document.querySelectorAll('.periodo-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const periodo = this.dataset.periodo;
            const hoy = new Date();
            let inicio, fin;
            
            switch(periodo) {
                case 'hoy':
                    inicio = fin = hoy.toISOString().split('T')[0];
                    break;
                case 'semana':
                    const diaSemana = hoy.getDay();
                    const inicioSemana = new Date(hoy);
                    inicioSemana.setDate(hoy.getDate() - diaSemana + (diaSemana === 0 ? -6 : 1));
                    inicio = inicioSemana.toISOString().split('T')[0];
                    fin = hoy.toISOString().split('T')[0];
                    break;
                case 'mes':
                    inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().split('T')[0];
                    fin = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0).toISOString().split('T')[0];
                    break;
                case 'anio':
                    inicio = new Date(hoy.getFullYear(), 0, 1).toISOString().split('T')[0];
                    fin = new Date(hoy.getFullYear(), 11, 31).toISOString().split('T')[0];
                    break;
            }
            
            document.querySelector('input[name="fecha_inicio"]').value = inicio;
            document.querySelector('input[name="fecha_fin"]').value = fin;
        });
    });
    
    // === MODALES DE FILTROS ===
    const modalFiltroTipo = new bootstrap.Modal(document.getElementById('modalFiltroTipo'));
    const modalFiltroCuenta = new bootstrap.Modal(document.getElementById('modalFiltroCuenta'));
    const modalFiltroCategoria = new bootstrap.Modal(document.getElementById('modalFiltroCategoria'));
    
    const tiposInfo = {
        '': { nombre: 'Todos los tipos', icono: 'bi-layers', color: '#6c757d' },
        'ingreso': { nombre: 'Ingresos', icono: 'bi-arrow-down-circle-fill', color: '#9AD082' },
        'egreso': { nombre: 'Egresos', icono: 'bi-arrow-up-circle-fill', color: '#FF6B6B' },
        'transferencia': { nombre: 'Transferencias', icono: 'bi-arrow-left-right', color: '#55A5C8' },
        'ajuste': { nombre: 'Ajustes', icono: 'bi-sliders', color: '#9c27b0' }
    };
    
    // Abrir modales
    window.abrirModalFiltroTipo = function() { modalFiltroTipo.show(); };
    window.abrirModalFiltroCuenta = function() { modalFiltroCuenta.show(); };
    window.abrirModalFiltroCategoria = function() { modalFiltroCategoria.show(); };
    
    // Selección de tipo
    document.querySelectorAll('#modalFiltroTipo .filtro-option').forEach(opt => {
        opt.addEventListener('click', function() {
            const value = this.dataset.value;
            document.getElementById('filtroTipo').value = value;
            
            document.querySelectorAll('#modalFiltroTipo .filtro-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            
            // Actualizar preview
            const preview = document.getElementById('filtroTipoPreview');
            const info = tiposInfo[value];
            if (value) {
                preview.innerHTML = `<div class="filtro-selected"><span class="filtro-icon" style="background: ${info.color}"><i class="bi ${info.icono}"></i></span><span class="filtro-name">${info.nombre}</span><i class="bi bi-chevron-down ms-auto text-muted"></i></div>`;
            } else {
                preview.innerHTML = '<span class="text-muted"><i class="bi bi-layers me-2"></i>Todos los tipos</span>';
            }
            
            modalFiltroTipo.hide();
        });
    });
    
    // Selección de cuenta
    document.querySelectorAll('#modalFiltroCuenta .filtro-option').forEach(opt => {
        opt.addEventListener('click', function() {
            const value = this.dataset.value;
            const nombre = this.dataset.nombre;
            const color = this.dataset.color;
            const icono = this.dataset.icono;
            
            document.getElementById('filtroCuenta').value = value;
            
            document.querySelectorAll('#modalFiltroCuenta .filtro-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            
            const preview = document.getElementById('filtroCuentaPreview');
            if (value) {
                preview.innerHTML = `<div class="filtro-selected"><span class="filtro-icon" style="background: ${color}"><i class="bi ${icono}"></i></span><span class="filtro-name">${nombre}</span><i class="bi bi-chevron-down ms-auto text-muted"></i></div>`;
            } else {
                preview.innerHTML = '<span class="text-muted"><i class="bi bi-wallet2 me-2"></i>Todas las cuentas</span>';
            }
            
            modalFiltroCuenta.hide();
        });
    });
    
    // Selección de categoría
    document.querySelectorAll('#modalFiltroCategoria .filtro-option').forEach(opt => {
        opt.addEventListener('click', function() {
            const value = this.dataset.value;
            const nombre = this.dataset.nombre;
            const color = this.dataset.color;
            const icono = this.dataset.icono;
            
            document.getElementById('filtroCategoria').value = value;
            
            document.querySelectorAll('#modalFiltroCategoria .filtro-option').forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            
            const preview = document.getElementById('filtroCategoriaPreview');
            if (value) {
                preview.innerHTML = `<div class="filtro-selected"><span class="filtro-icon" style="background: ${color}"><i class="bi ${icono}"></i></span><span class="filtro-name">${nombre}</span><i class="bi bi-chevron-down ms-auto text-muted"></i></div>`;
            } else {
                preview.innerHTML = '<span class="text-muted"><i class="bi bi-tag me-2"></i>Todas las categorías</span>';
            }
            
            modalFiltroCategoria.hide();
        });
    });
    
    // === OCULTAR FAB CUANDO DRAWER ESTÁ ABIERTO ===
    const filtrosDrawer = document.getElementById('filtrosDrawer');
    const fabButton = document.querySelector('.btn-float-mobile');
    
    if (filtrosDrawer && fabButton) {
        filtrosDrawer.addEventListener('show.bs.offcanvas', function() {
            fabButton.style.opacity = '0';
            fabButton.style.pointerEvents = 'none';
        });
        
        filtrosDrawer.addEventListener('hidden.bs.offcanvas', function() {
            fabButton.style.opacity = '1';
            fabButton.style.pointerEvents = 'auto';
        });
    }
    
    // === MODAL DE COMPROBANTES ===
    const modalComprobantes = new bootstrap.Modal(document.getElementById('modalComprobantes'));
    
    window.verComprobantes = async function(transaccionId) {
        const container = document.getElementById('listaComprobantes');
        
        // Mostrar loading
        container.innerHTML = `
            <div class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div>
                Cargando comprobantes...
            </div>
        `;
        
        modalComprobantes.show();
        
        try {
            const response = await fetch(`<?= UI_URL ?>/modules/transacciones/api/get_archivos.php?transaccion_id=${transaccionId}`);
            const data = await response.json();
            
            if (data.error) {
                container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            if (!data.archivos || data.archivos.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-paperclip display-4"></i>
                        <p class="mt-2 mb-0">No hay comprobantes</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = data.archivos.map(archivo => {
                const esImagen = archivo.tipo_archivo === 'imagen';
                return `
                    <div class="comprobante-item">
                        ${esImagen ? `
                            <div class="comprobante-thumbnail">
                                <img src="${archivo.url_ver}" alt="Preview">
                            </div>
                        ` : `
                            <div class="comprobante-pdf-icon">
                                <i class="bi bi-file-pdf fs-3" style="color: #dc3545;"></i>
                            </div>
                        `}
                        <div class="comprobante-info">
                            <div class="comprobante-nombre">${archivo.nombre_original}</div>
                            <small class="text-muted">${archivo.tamano_kb} KB</small>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="${archivo.url_ver}" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="${archivo.url_descargar}" class="btn btn-sm btn-outline-secondary" title="Descargar">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                    </div>
                `;
            }).join('');
            
        } catch (error) {
            container.innerHTML = `<div class="alert alert-danger">Error al cargar comprobantes</div>`;
            console.error('Error:', error);
        }
    };
});
</script>
