<?php
/**
 * AND FINANCE APP - Listado de Gastos Recurrentes
 */

require_once __DIR__ . '/../models/GastoRecurrenteModel.php';

$pageTitle = 'Gastos Recurrentes';
$pageSubtitle = 'Programa tus gastos fijos';
$gastoModel = new GastoRecurrenteModel();
$userId = getCurrentUserId();

// Manejar acciones
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'toggle' && $id > 0) {
    $gasto = $gastoModel->getById($id);
    if ($gasto && $gasto['usuario_id'] == $userId) {
        if ($gastoModel->toggleEstado($id)) {
            setFlashMessage('success', 'Estado del gasto actualizado');
        }
    }
    header('Location: ' . uiModuleUrl('recurrentes'));
    exit;
}

if ($action === 'delete' && $id > 0) {
    $gasto = $gastoModel->getById($id);
    if ($gasto && $gasto['usuario_id'] == $userId) {
        if ($gastoModel->delete($id)) {
            setFlashMessage('success', 'Gasto recurrente eliminado');
        }
    }
    ob_end_clean();
    header('Location: ' . uiModuleUrl('recurrentes'));
    exit;
}

// Verificar transacción existente (AJAX)
if ($action === 'verificar_transaccion' && $id > 0) {
    // Limpiar cualquier output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    
    $gasto = $gastoModel->getById($id);
    if ($gasto && $gasto['usuario_id'] == $userId) {
        $transaccionExistente = $gastoModel->verificarTransaccionExistente($id);
        if ($transaccionExistente) {
            echo json_encode([
                'existe' => true,
                'fecha' => $transaccionExistente['fecha_transaccion'],
                'realizada' => (bool)$transaccionExistente['realizada'],
                'monto' => $transaccionExistente['monto']
            ]);
        } else {
            echo json_encode(['existe' => false]);
        }
    } else {
        echo json_encode(['error' => 'Gasto no encontrado']);
    }
    exit;
}

// Registrar pago manual
if ($action === 'registrar_pago' && $id > 0) {
    $gasto = $gastoModel->getById($id);
    if ($gasto && $gasto['usuario_id'] == $userId) {
        try {
            $programar = isset($_GET['programar']) && $_GET['programar'] == '1';
            $forzar = isset($_GET['forzar']) && $_GET['forzar'] == '1';
            $gastoModel->registrarPagoManual($id, !$programar, $forzar);
            
            $mensaje = $programar 
                ? "Pago de '{$gasto['nombre']}' programado correctamente"
                : "Pago de '{$gasto['nombre']}' registrado correctamente";
            setFlashMessage('success', $mensaje);
        } catch (Exception $e) {
            setFlashMessage('error', 'Error al registrar pago: ' . $e->getMessage());
        }
    }
    ob_end_clean();
    header('Location: ' . uiModuleUrl('recurrentes'));
    exit;
}

// Obtener datos
$gastosRecurrentes = $gastoModel->getAllByUser($userId, false);
$proximosGastos = $gastoModel->getProximos($userId, 30);
// Usar todas las frecuencias para mostrar gastos existentes correctamente
$frecuencias = $gastoModel->getTodasFrecuencias();

// Calcular totales mensuales estimados
$totalMensualEgresos = 0;
$totalMensualIngresos = 0;
$totalProximosEgresos = 0;
$totalProximosIngresos = 0;

foreach ($gastosRecurrentes as $gasto) {
    if ($gasto['estado'] != 1) continue;
    
    $montoMensual = $gasto['monto'];
    switch ($gasto['frecuencia']) {
        case 'diario': $montoMensual *= 30; break;
        case 'semanal': $montoMensual *= 4; break;
        case 'quincenal': $montoMensual *= 2; break;
        case 'bimestral': $montoMensual /= 2; break;
        case 'trimestral': $montoMensual /= 3; break;
        case 'semestral': $montoMensual /= 6; break;
        case 'anual': $montoMensual /= 12; break;
    }
    
    if ($gasto['tipo'] === 'egreso') {
        $totalMensualEgresos += $montoMensual;
    } else {
        $totalMensualIngresos += $montoMensual;
    }
}

// Calcular total de próximos pagos
foreach ($proximosGastos as $gasto) {
    if ($gasto['tipo'] === 'egreso') {
        $totalProximosEgresos += $gasto['monto'];
    } else {
        $totalProximosIngresos += $gasto['monto'];
    }
}
?>

<!-- Resumen -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card bg-danger-subtle border-0 fade-in-up h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-danger text-white me-3 d-none d-sm-flex">
                        <i class="bi bi-arrow-up-circle"></i>
                    </div>
                    <div>
                        <small class="text-danger fw-semibold d-block">Gastos / Mes</small>
                        <h4 class="text-danger mb-0 fw-bold"><?= formatMoney($totalMensualEgresos) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card bg-success-subtle border-0 fade-in-up h-100" style="animation-delay: 0.1s;">
            <div class="card-body py-3">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-success text-white me-3 d-none d-sm-flex">
                        <i class="bi bi-arrow-down-circle"></i>
                    </div>
                    <div>
                        <small class="text-success fw-semibold d-block">Ingresos / Mes</small>
                        <h4 class="text-success mb-0 fw-bold"><?= formatMoney($totalMensualIngresos) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card fade-in-up h-100 <?= count($proximosGastos) > 0 ? 'card-proximos' : '' ?>" style="animation-delay: 0.2s;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-primary text-white me-3">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div>
                            <small class="text-muted fw-semibold d-block">Próximos 30 días</small>
                            <h4 class="text-primary mb-0 fw-bold"><?= count($proximosGastos) ?> pagos</h4>
                        </div>
                    </div>
                    <?php if (count($proximosGastos) > 0): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalProximos">
                        <i class="bi bi-eye me-1"></i>Ver
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Listado de Gastos Recurrentes -->
<div class="card fade-in-up" style="animation-delay: 0.3s;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-repeat me-2"></i>Mis Gastos Programados
            <span class="badge bg-primary ms-2"><?= count($gastosRecurrentes) ?></span>
        </h5>
        <a href="<?= uiModuleUrl('recurrentes', 'crear') ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Nuevo</span>
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($gastosRecurrentes)): ?>
        <div class="text-center py-5">
            <i class="bi bi-calendar-plus display-4 text-muted"></i>
            <h5 class="mt-4 mb-3">No tienes gastos programados</h5>
            <p class="text-muted mb-4">Programa tus gastos fijos para llevar un mejor control.</p>
            <a href="<?= uiModuleUrl('recurrentes', 'crear') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>Programar gasto
            </a>
        </div>
        <?php else: ?>
        <div class="recurrentes-list">
            <?php foreach ($gastosRecurrentes as $gasto): ?>
            <div class="recurrente-item <?= $gasto['estado'] != 1 ? 'pausado' : '' ?>">
                <div class="d-flex align-items-start">
                    <!-- Icono de categoría -->
                    <div class="recurrente-icon me-3 flex-shrink-0" 
                         style="background-color: <?= htmlspecialchars($gasto['categoria_color'] ?? '#B1BCBF') ?>20; 
                                color: <?= htmlspecialchars($gasto['categoria_color'] ?? '#B1BCBF') ?>;">
                        <i class="bi <?= htmlspecialchars($gasto['categoria_icono'] ?? 'bi-tag') ?>"></i>
                    </div>
                    
                    <!-- Info principal -->
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="mb-0 text-truncate pe-2 recurrente-nombre <?= $gasto['estado'] != 1 ? 'text-muted' : '' ?>">
                                <?= htmlspecialchars($gasto['nombre']) ?>
                                <?php if ($gasto['estado'] != 1): ?>
                                <span class="badge bg-warning text-dark ms-1">Pausado</span>
                                <?php endif; ?>
                            </h6>
                            <strong class="<?= $gasto['tipo'] === 'egreso' ? 'text-danger' : 'text-success' ?> flex-shrink-0">
                                <?= formatMoney($gasto['monto']) ?>
                            </strong>
                        </div>
                        
                        <!-- Cuenta con icono -->
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="cuenta-badge">
                                <span class="cuenta-dot" style="background-color: <?= htmlspecialchars($gasto['cuenta_color'] ?? '#55A5C8') ?>;"></span>
                                <?= htmlspecialchars($gasto['cuenta_nombre'] ?? 'Sin cuenta') ?>
                            </span>
                            <span class="frecuencia-badge">
                                <i class="bi <?= $frecuencias[$gasto['frecuencia']]['icono'] ?? 'bi-calendar' ?>"></i>
                                <?= $frecuencias[$gasto['frecuencia']]['nombre'] ?? ucfirst($gasto['frecuencia']) ?>
                            </span>
                        </div>
                        
                        <!-- Próximo pago -->
                        <div class="proximo-pago">
                            <?php if ($gasto['proxima_ejecucion'] && $gasto['estado'] == 1): 
                                $fechaProxima = new DateTime($gasto['proxima_ejecucion']);
                                $hoy = new DateTime();
                                $diff = $hoy->diff($fechaProxima);
                                $diasRestantes = $diff->invert ? 0 : $diff->days;
                            ?>
                            <i class="bi bi-calendar3 me-1"></i>
                            <?php if ($diasRestantes == 0): ?>
                                <span class="text-danger fw-semibold">Hoy</span>
                            <?php elseif ($diasRestantes == 1): ?>
                                <span class="text-warning fw-semibold">Mañana</span>
                            <?php elseif ($diasRestantes <= 7): ?>
                                <span class="text-primary">En <?= $diasRestantes ?> días</span>
                            <?php else: ?>
                                <span><?= formatDate($gasto['proxima_ejecucion']) ?></span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted"><i class="bi bi-pause-circle me-1"></i>Sin próximo pago</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones en fila separada para móviles -->
                <div class="recurrente-actions">
                    <?php if ($gasto['estado'] == 1): ?>
                    <button type="button" class="btn-action btn-action-pay" 
                            onclick="mostrarModalPago(<?= $gasto['id'] ?>, '<?= htmlspecialchars($gasto['nombre']) ?>', <?= $gasto['monto'] ?>)"
                            title="Registrar pago">
                        <i class="bi bi-cash-coin"></i>
                        <span>Pagar</span>
                    </button>
                    <?php endif; ?>
                    <a href="<?= uiModuleUrl('recurrentes', 'editar', ['id' => $gasto['id']]) ?>" 
                       class="btn-action btn-action-edit" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                        <span>Editar</span>
                    </a>
                    <a href="<?= uiModuleUrl('recurrentes') ?>&action=toggle&id=<?= $gasto['id'] ?>" 
                       class="btn-action <?= $gasto['estado'] == 1 ? 'btn-action-pause' : 'btn-action-play' ?>" 
                       title="<?= $gasto['estado'] == 1 ? 'Pausar' : 'Reanudar' ?>">
                        <i class="bi bi-<?= $gasto['estado'] == 1 ? 'pause-fill' : 'play-fill' ?>"></i>
                        <span><?= $gasto['estado'] == 1 ? 'Pausar' : 'Activar' ?></span>
                    </a>
                    <button type="button" class="btn-action btn-action-delete" 
                            onclick="confirmDelete('<?= uiModuleUrl('recurrentes') ?>&action=delete&id=<?= $gasto['id'] ?>', '<?= htmlspecialchars($gasto['nombre']) ?>')"
                            title="Eliminar">
                        <i class="bi bi-trash-fill"></i>
                        <span>Eliminar</span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Próximos Pagos -->
<div class="modal fade" id="modalProximos" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-calendar-week me-2 text-primary"></i>Próximos 30 días
                    </h5>
                    <small class="text-muted"><?= count($proximosGastos) ?> pagos programados</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <!-- Resumen del modal -->
                <div class="d-flex gap-3 mb-4">
                    <div class="flex-fill text-center p-3 rounded-3 bg-danger-subtle">
                        <small class="text-danger d-block">Por pagar</small>
                        <strong class="text-danger fs-5"><?= formatMoney($totalProximosEgresos) ?></strong>
                    </div>
                    <div class="flex-fill text-center p-3 rounded-3 bg-success-subtle">
                        <small class="text-success d-block">Por recibir</small>
                        <strong class="text-success fs-5"><?= formatMoney($totalProximosIngresos) ?></strong>
                    </div>
                </div>
                
                <?php if (empty($proximosGastos)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-calendar-check display-4 text-muted"></i>
                    <p class="text-muted mt-3 mb-0">No hay pagos próximos</p>
                </div>
                <?php else: ?>
                <div class="proximos-list">
                    <?php 
                    $fechaAnterior = '';
                    foreach ($proximosGastos as $gasto): 
                        $fechaActual = date('Y-m-d', strtotime($gasto['proxima_ejecucion']));
                        $mostrarFecha = $fechaActual !== $fechaAnterior;
                        $fechaAnterior = $fechaActual;
                        
                        $fechaProxima = new DateTime($gasto['proxima_ejecucion']);
                        $hoy = new DateTime();
                        $diff = $hoy->diff($fechaProxima);
                        $diasRestantes = $diff->invert ? 0 : $diff->days;
                    ?>
                    <?php if ($mostrarFecha): 
                        // Formatear fecha en español
                        $diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
                        $fechaTs = strtotime($gasto['proxima_ejecucion']);
                        $diaSemana = $diasSemana[date('w', $fechaTs)];
                        $diaMes = date('d', $fechaTs);
                        $mes = $meses[(int)date('n', $fechaTs)];
                        $fechaFormateada = "$diaSemana $diaMes de $mes";
                    ?>
                    <div class="fecha-separador">
                        <?php if ($diasRestantes == 0): ?>
                            <span class="badge bg-danger">Hoy</span>
                        <?php elseif ($diasRestantes == 1): ?>
                            <span class="badge bg-warning text-dark">Mañana</span>
                        <?php else: ?>
                            <span class="badge bg-light text-dark"><?= $fechaFormateada ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="proximo-item">
                        <div class="d-flex align-items-center">
                            <div class="proximo-icon me-3" 
                                 style="background-color: <?= htmlspecialchars($gasto['categoria_color'] ?? '#B1BCBF') ?>20; 
                                        color: <?= htmlspecialchars($gasto['categoria_color'] ?? '#B1BCBF') ?>;">
                                <i class="bi <?= htmlspecialchars($gasto['categoria_icono'] ?? 'bi-tag') ?>"></i>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <h6 class="mb-0 text-truncate"><?= htmlspecialchars($gasto['nombre']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($gasto['cuenta_nombre'] ?? '') ?></small>
                            </div>
                            <strong class="<?= $gasto['tipo'] === 'egreso' ? 'text-danger' : 'text-success' ?> ms-2">
                                <?= $gasto['tipo'] === 'egreso' ? '-' : '+' ?><?= formatMoney($gasto['monto']) ?>
                            </strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.bg-success-subtle { background-color: rgba(154, 208, 130, 0.15) !important; }
.bg-danger-subtle { background-color: rgba(255, 107, 107, 0.15) !important; }
.min-width-0 { min-width: 0; }

.icon-box {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}

.card-proximos {
    border: 1px solid rgba(85, 165, 200, 0.3);
    background: linear-gradient(135deg, rgba(85, 165, 200, 0.05), rgba(53, 113, 158, 0.05));
}

/* Lista de recurrentes */
.recurrentes-list { padding: 0; }

.recurrente-item {
    padding: 16px;
    border-bottom: 1px solid var(--border-light, #f0f0f0);
    transition: background-color 0.2s ease;
}
.recurrente-item:last-child { border-bottom: none; }
.recurrente-item:hover { background-color: var(--bg-hover, #f8f9fa); }
.recurrente-item.pausado { background-color: var(--bg-hover, #fafafa); opacity: 0.8; }

.recurrente-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
}

.recurrente-nombre {
    color: var(--text-primary, #333);
}

.cuenta-badge {
    display: inline-flex; align-items: center;
    font-size: 12px; color: var(--text-secondary, #666);
    background: var(--bg-input, #f5f5f5); padding: 3px 10px;
    border-radius: 20px;
}
.cuenta-dot {
    width: 8px; height: 8px; border-radius: 50%;
    margin-right: 6px; flex-shrink: 0;
}

.frecuencia-badge {
    display: inline-flex; align-items: center;
    font-size: 12px; color: var(--primary-blue);
    background: rgba(85, 165, 200, 0.1);
    padding: 3px 10px; border-radius: 20px;
}
.frecuencia-badge i { margin-right: 4px; font-size: 11px; }

.proximo-pago { font-size: 13px; color: var(--text-secondary, #666); }

.recurrente-actions { 
    display: flex; 
    gap: 6px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border-light, #f0f0f0);
}

.btn-action {
    flex: 1;
    padding: 8px 4px;
    display: flex; 
    flex-direction: column;
    align-items: center; 
    justify-content: center;
    gap: 2px;
    border-radius: 8px; 
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-action i {
    font-size: 14px;
}

.btn-action span {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.2px;
}

.btn-action:hover { transform: scale(1.02); }
.btn-action:active { transform: scale(0.98); }

.btn-action-pay {
    background: rgba(154, 208, 130, 0.25);
    color: #28a745;
}
.btn-action-pay:hover {
    background: #28a745;
    color: white;
}

.btn-action-edit {
    background: rgba(85, 165, 200, 0.15);
    color: var(--primary-blue);
}
.btn-action-edit:hover {
    background: var(--primary-blue);
    color: white;
}

.btn-action-pause {
    background: rgba(247, 220, 111, 0.3);
    color: #b8860b;
}
.btn-action-pause:hover {
    background: #f7dc6f;
    color: #856404;
}

.btn-action-play {
    background: rgba(154, 208, 130, 0.25);
    color: #5a9a3e;
}
.btn-action-play:hover {
    background: var(--secondary-green);
    color: white;
}

.btn-action-delete {
    background: rgba(255, 107, 107, 0.15);
    color: #dc3545;
}
.btn-action-delete:hover {
    background: #FF6B6B;
    color: white;
}

/* Modal próximos pagos */
.proximos-list { }

.fecha-separador {
    padding: 8px 0;
    margin-top: 8px;
    color: var(--text-secondary, #666);
}
.fecha-separador:first-child { margin-top: 0; }

.proximo-item {
    padding: 12px;
    background: var(--bg-input, #f8f9fa);
    border-radius: 12px;
    margin-bottom: 8px;
}

.proximo-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}

/* Mobile optimizations */
@media (max-width: 576px) {
    .recurrente-item { padding: 12px; }
    .recurrente-icon { width: 40px; height: 40px; font-size: 18px; }
    .btn-action { padding: 5px 6px; }
    .btn-action i { font-size: 12px; }
    .btn-action span { font-size: 8px; }
    .icon-box { width: 38px; height: 38px; font-size: 16px; }
}

/* Modal de pago */
.modal-pago-monto {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark-blue);
}
.pago-option {
    padding: 16px;
    border: 2px solid #dee2e6;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}
.pago-option:hover {
    border-color: var(--primary-blue);
    background: rgba(85, 165, 200, 0.05);
}
.pago-option.selected {
    border-color: var(--primary-blue);
    background: rgba(85, 165, 200, 0.1);
}
.pago-option i {
    font-size: 32px;
    margin-bottom: 8px;
}
</style>

<!-- Modal Registrar Pago -->
<div class="modal fade" id="modalPago" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="bi bi-cash-coin text-success me-2"></i>Registrar Pago
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <h6 class="text-muted mb-2" id="pagoNombre">Nombre del gasto</h6>
                <div class="modal-pago-monto mb-4" id="pagoMonto">$0</div>
                
                <!-- Vista normal -->
                <div id="vistaOpciones">
                    <p class="text-muted mb-3">¿Cómo deseas registrar este pago?</p>
                    
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="pago-option" onclick="verificarYRegistrar(false)">
                                <i class="bi bi-check-circle-fill text-success d-block"></i>
                                <strong>Ya lo pagué</strong>
                                <small class="d-block text-muted">Afecta el saldo ahora</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="pago-option" onclick="verificarYRegistrar(true)">
                                <i class="bi bi-clock-fill text-warning d-block"></i>
                                <strong>Programar</strong>
                                <small class="d-block text-muted">Pendiente de pago</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Vista de confirmación por duplicado -->
                <div id="vistaConfirmacion" style="display: none;">
                    <div class="alert alert-warning text-start mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Ya existe una transacción reciente</strong>
                        <p class="mb-0 mt-2 small" id="infoTransaccionExistente"></p>
                    </div>
                    <p class="text-muted mb-3">¿Deseas crear una nueva transacción de todas formas?</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-outline-secondary" onclick="volverOpciones()">
                            <i class="bi bi-arrow-left me-1"></i>Volver
                        </button>
                        <button type="button" class="btn btn-warning" onclick="forzarRegistro()">
                            <i class="bi bi-plus-circle me-1"></i>Sí, crear nueva
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center" id="footerCancelar">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let gastoIdPago = null;
    let modalPago = null;
    let programarPendiente = false;
    
    // Inicializar modal cuando Bootstrap esté disponible
    const modalPagoEl = document.getElementById('modalPago');
    if (modalPagoEl) {
        modalPago = new bootstrap.Modal(modalPagoEl);
    }

    window.mostrarModalPago = function(id, nombre, monto) {
        gastoIdPago = id;
        document.getElementById('pagoNombre').textContent = nombre;
        document.getElementById('pagoMonto').textContent = '$' + new Intl.NumberFormat('es-CO').format(monto);
        // Resetear vista
        document.getElementById('vistaOpciones').style.display = 'block';
        document.getElementById('vistaConfirmacion').style.display = 'none';
        document.getElementById('footerCancelar').style.display = 'flex';
        if (modalPago) modalPago.show();
    };

    window.verificarYRegistrar = async function(programar) {
        if (!gastoIdPago) return;
        programarPendiente = programar;
        
        try {
            const url = '<?= uiModuleUrl('recurrentes') ?>&action=verificar_transaccion&id=' + gastoIdPago;
            console.log('Verificando:', url);
            
            const response = await fetch(url);
            const text = await response.text();
            console.log('Respuesta:', text);
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Error parseando JSON:', e);
                registrarPago(programar, false);
                return;
            }
            
            console.log('Data:', data);
            
            if (data.existe && data.realizada) {
                // Ya existe una transacción realizada reciente, mostrar confirmación
                const fecha = new Date(data.fecha + 'T00:00:00').toLocaleDateString('es-CO', {
                    day: 'numeric', month: 'long', year: 'numeric'
                });
                const estado = data.realizada ? 'pagada' : 'programada';
                document.getElementById('infoTransaccionExistente').innerHTML = 
                    `Tienes una transacción <strong>${estado}</strong> del <strong>${fecha}</strong> por <strong>$${new Intl.NumberFormat('es-CO').format(data.monto)}</strong>`;
                
                document.getElementById('vistaOpciones').style.display = 'none';
                document.getElementById('vistaConfirmacion').style.display = 'block';
                document.getElementById('footerCancelar').style.display = 'none';
            } else {
                // No existe o solo está programada, registrar directamente
                console.log('No existe transacción reciente realizada, procediendo...');
                registrarPago(programar, false);
            }
        } catch (error) {
            console.error('Error verificando:', error);
            // En caso de error, proceder normalmente
            registrarPago(programar, false);
        }
    };
    
    window.volverOpciones = function() {
        document.getElementById('vistaOpciones').style.display = 'block';
        document.getElementById('vistaConfirmacion').style.display = 'none';
        document.getElementById('footerCancelar').style.display = 'flex';
    };
    
    window.forzarRegistro = function() {
        registrarPago(programarPendiente, true);
    };

    window.registrarPago = function(programar, forzar = false) {
        if (!gastoIdPago) return;
        let url = '<?= uiModuleUrl('recurrentes') ?>&action=registrar_pago&id=' + gastoIdPago;
        if (programar) url += '&programar=1';
        if (forzar) url += '&forzar=1';
        window.location.href = url;
    };
});
</script>
