<?php
/**
 * AND FINANCE APP - User Dashboard
 * Panel principal con resumen de finanzas
 */

$db = Database::getInstance();
$userId = getCurrentUserId();

// Obtener mes y año actuales
$mesActual = date('m');
$anioActual = date('Y');
$primerDiaMes = date('Y-m-01');
$ultimoDiaMes = date('Y-m-t');

try {
    // Calcular ingresos del mes
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(monto), 0) as total 
        FROM transacciones 
        WHERE usuario_id = :usuario_id 
        AND tipo = 'ingreso' 
        AND estado = 1
        AND fecha_transaccion BETWEEN :inicio AND :fin
    ");
    $stmt->execute([
        'usuario_id' => $userId,
        'inicio' => $primerDiaMes,
        'fin' => $ultimoDiaMes
    ]);
    $ingresosMes = $stmt->fetchColumn();
    
    // Calcular egresos del mes
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(monto), 0) as total 
        FROM transacciones 
        WHERE usuario_id = :usuario_id 
        AND tipo = 'egreso' 
        AND estado = 1
        AND fecha_transaccion BETWEEN :inicio AND :fin
    ");
    $stmt->execute([
        'usuario_id' => $userId,
        'inicio' => $primerDiaMes,
        'fin' => $ultimoDiaMes
    ]);
    $egresosMes = $stmt->fetchColumn();
    
    // Balance del mes
    $balanceMes = $ingresosMes - $egresosMes;
    
    // Saldo total de todas las cuentas
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(saldo_actual), 0) as total 
        FROM cuentas 
        WHERE usuario_id = :usuario_id 
        AND estado = 1 
        AND incluir_en_total = 1
    ");
    $stmt->execute(['usuario_id' => $userId]);
    $saldoTotal = $stmt->fetchColumn();
    
    // Obtener cuentas del usuario
    $stmt = $db->prepare("
        SELECT c.*, b.nombre as banco_nombre, b.logo as banco_logo, b.color_primario as banco_color
        FROM cuentas c
        LEFT JOIN bancos b ON c.banco_id = b.id
        WHERE c.usuario_id = :usuario_id AND c.estado = 1
        ORDER BY c.es_predeterminada DESC, c.nombre ASC
    ");
    $stmt->execute(['usuario_id' => $userId]);
    $cuentas = $stmt->fetchAll();
    
    // Obtener últimas transacciones
    $stmt = $db->prepare("
        SELECT t.*, c.nombre as cuenta_nombre, c.icono as cuenta_icono, c.color as cuenta_color,
               cat.nombre as categoria_nombre, cat.icono as categoria_icono, cat.color as categoria_color
        FROM transacciones t
        LEFT JOIN cuentas c ON t.cuenta_id = c.id
        LEFT JOIN categorias cat ON t.categoria_id = cat.id
        WHERE t.usuario_id = :usuario_id AND t.estado = 1
        ORDER BY t.fecha_transaccion DESC, t.id DESC
        LIMIT 8
    ");
    $stmt->execute(['usuario_id' => $userId]);
    $ultimasTransacciones = $stmt->fetchAll();
    
    // Gastos por categoría (Top 5)
    $stmt = $db->prepare("
        SELECT cat.nombre, cat.color, cat.icono, COALESCE(SUM(t.monto), 0) as total
        FROM transacciones t
        JOIN categorias cat ON t.categoria_id = cat.id
        WHERE t.usuario_id = :usuario_id 
        AND t.tipo = 'egreso' 
        AND t.estado = 1
        AND t.fecha_transaccion BETWEEN :inicio AND :fin
        GROUP BY cat.id
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmt->execute([
        'usuario_id' => $userId,
        'inicio' => $primerDiaMes,
        'fin' => $ultimoDiaMes
    ]);
    $gastosPorCategoria = $stmt->fetchAll();
    
    // Próximos gastos recurrentes (excluyendo los ya pagados)
    $stmt = $db->prepare("
        SELECT gr.*, cat.nombre as categoria_nombre, cat.icono as categoria_icono
        FROM gastos_recurrentes gr
        LEFT JOIN categorias cat ON gr.categoria_id = cat.id
        WHERE gr.usuario_id = :usuario_id 
        AND gr.estado = 1
        AND gr.proxima_ejecucion IS NOT NULL
        AND gr.proxima_ejecucion <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY)
        AND NOT EXISTS (
            SELECT 1 FROM transacciones t 
            WHERE t.gasto_recurrente_id = gr.id 
            AND t.realizada = 1 
            AND t.estado = 1
            AND t.fecha_transaccion >= gr.proxima_ejecucion
        )
        AND (gr.ultima_ejecucion IS NULL OR gr.ultima_ejecucion < gr.proxima_ejecucion)
        ORDER BY gr.proxima_ejecucion ASC
        LIMIT 5
    ");
    $stmt->execute(['usuario_id' => $userId]);
    $proximosRecurrentes = $stmt->fetchAll();
    
    // ========== PROYECCIÓN DE SALDO ==========
    $hoy = date('Y-m-d');
    $finMesActual = date('Y-m-t');
    $inicioMesSiguiente = date('Y-m-01', strtotime('+1 month'));
    $finMesSiguiente = date('Y-m-t', strtotime('+1 month'));
    
    // Transacciones programadas (no realizadas) - Este mes
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos_programados,
            COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END), 0) as egresos_programados
        FROM transacciones 
        WHERE usuario_id = :usuario_id 
        AND realizada = 0 
        AND estado = 1
        AND fecha_transaccion BETWEEN :hoy AND :fin_mes
    ");
    $stmt->execute(['usuario_id' => $userId, 'hoy' => $hoy, 'fin_mes' => $finMesActual]);
    $programadasMesActual = $stmt->fetch();
    
    // Transacciones programadas - Mes siguiente
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos_programados,
            COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END), 0) as egresos_programados
        FROM transacciones 
        WHERE usuario_id = :usuario_id 
        AND realizada = 0 
        AND estado = 1
        AND fecha_transaccion BETWEEN :inicio_mes AND :fin_mes
    ");
    $stmt->execute(['usuario_id' => $userId, 'inicio_mes' => $inicioMesSiguiente, 'fin_mes' => $finMesSiguiente]);
    $programadasMesSiguiente = $stmt->fetch();
    
    // Gastos recurrentes pendientes - Este mes (desde hoy hasta fin de mes)
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos_recurrentes,
            COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END), 0) as egresos_recurrentes
        FROM gastos_recurrentes 
        WHERE usuario_id = :usuario_id 
        AND estado = 1
        AND proxima_ejecucion BETWEEN :hoy AND :fin_mes
    ");
    $stmt->execute(['usuario_id' => $userId, 'hoy' => $hoy, 'fin_mes' => $finMesActual]);
    $recurrentesMesActual = $stmt->fetch();
    
    // Gastos recurrentes - Mes siguiente (estimado basado en frecuencia)
    // Para simplificar, usamos los mismos montos del mes actual como estimación
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos_recurrentes,
            COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END), 0) as egresos_recurrentes
        FROM gastos_recurrentes 
        WHERE usuario_id = :usuario_id 
        AND estado = 1
        AND frecuencia IN ('mensual', 'quincenal', 'semanal', 'diario')
    ");
    $stmt->execute(['usuario_id' => $userId]);
    $recurrentesMesSiguiente = $stmt->fetch();
    
    // Calcular proyecciones
    $ingresosProyectadosMesActual = ($programadasMesActual['ingresos_programados'] ?? 0) + ($recurrentesMesActual['ingresos_recurrentes'] ?? 0);
    $egresosProyectadosMesActual = ($programadasMesActual['egresos_programados'] ?? 0) + ($recurrentesMesActual['egresos_recurrentes'] ?? 0);
    $saldoProyectadoMesActual = $saldoTotal + $ingresosProyectadosMesActual - $egresosProyectadosMesActual;
    
    $ingresosProyectadosMesSiguiente = ($programadasMesSiguiente['ingresos_programados'] ?? 0) + ($recurrentesMesSiguiente['ingresos_recurrentes'] ?? 0);
    $egresosProyectadosMesSiguiente = ($programadasMesSiguiente['egresos_programados'] ?? 0) + ($recurrentesMesSiguiente['egresos_recurrentes'] ?? 0);
    $saldoProyectadoMesSiguiente = $saldoProyectadoMesActual + $ingresosProyectadosMesSiguiente - $egresosProyectadosMesSiguiente;
    
    // Nombres de meses para proyección
    $mesSiguienteNum = (int)date('m') + 1;
    $anioMesSiguiente = (int)date('Y');
    if ($mesSiguienteNum > 12) {
        $mesSiguienteNum = 1;
        $anioMesSiguiente++;
    }
    
} catch (PDOException $e) {
    $ingresosMes = 0;
    $egresosMes = 0;
    $balanceMes = 0;
    $saldoTotal = 0;
    $cuentas = [];
    $ultimasTransacciones = [];
    $gastosPorCategoria = [];
    $proximosRecurrentes = [];
    $saldoProyectadoMesActual = 0;
    $saldoProyectadoMesSiguiente = 0;
    $ingresosProyectadosMesActual = 0;
    $egresosProyectadosMesActual = 0;
    $ingresosProyectadosMesSiguiente = 0;
    $egresosProyectadosMesSiguiente = 0;
    $mesSiguienteNum = (int)date('m') + 1;
    $anioMesSiguiente = (int)date('Y');
}

// Nombres de meses en español
$meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$mesNombre = $meses[(int)$mesActual - 1];
$mesSiguienteNombre = $meses[$mesSiguienteNum - 1];
?>

<!-- Stat Cards - Versión Desktop -->
<div class="row g-4 mb-4 fade-in-up d-none d-md-flex">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card balance">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">Saldo Total</p>
                    <h3 class="stat-value"><?= formatMoney($saldoTotal) ?></h3>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card income">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">Ingresos - <?= $mesNombre ?></p>
                    <h3 class="stat-value"><?= formatMoney($ingresosMes) ?></h3>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-arrow-down-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card expense">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label">Gastos - <?= $mesNombre ?></p>
                    <h3 class="stat-value"><?= formatMoney($egresosMes) ?></h3>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-arrow-up-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card" style="background: <?= $balanceMes >= 0 ? 'linear-gradient(135deg, #9AD082 0%, #7ab85c 100%)' : 'linear-gradient(135deg, #FF6B6B 0%, #ee5a5a 100%)' ?>; padding: 22px;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label" style="color: rgba(255,255,255,0.85);">Balance - <?= $mesNombre ?></p>
                    <h3 class="stat-value" style="color: white;"><?= formatMoney($balanceMes) ?></h3>
                </div>
                <div class="stat-icon" style="background: rgba(255,255,255,0.2); color: white;">
                    <i class="bi bi-<?= $balanceMes >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' ?>"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stat Cards - Versión Mobile Compacta -->
<div class="dash-mobile-stats d-md-none mb-3 fade-in-up">
    <div class="dash-saldo-total">
        <div class="dash-saldo-header">
            <span class="dash-saldo-label">Saldo Total</span>
            <span class="dash-saldo-badge">Actual</span>
        </div>
        <span class="dash-saldo-value <?= $saldoTotal >= 0 ? 'positivo' : 'negativo' ?>"><?= formatMoney($saldoTotal) ?></span>
    </div>
    <div class="dash-periodo-label">
        <i class="bi bi-calendar3 me-1"></i><?= $mesNombre ?> <?= $anioActual ?>
    </div>
    <div class="dash-stats-row">
        <div class="dash-stat-item ingreso">
            <i class="bi bi-arrow-down-circle-fill"></i>
            <span class="dash-stat-value"><?= formatMoney($ingresosMes) ?></span>
            <span class="dash-stat-label">Ingresos</span>
        </div>
        <div class="dash-stat-item egreso">
            <i class="bi bi-arrow-up-circle-fill"></i>
            <span class="dash-stat-value"><?= formatMoney($egresosMes) ?></span>
            <span class="dash-stat-label">Gastos</span>
        </div>
        <div class="dash-stat-item <?= $balanceMes >= 0 ? 'positivo' : 'negativo' ?>">
            <i class="bi bi-<?= $balanceMes >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' ?>"></i>
            <span class="dash-stat-value"><?= formatMoney($balanceMes) ?></span>
            <span class="dash-stat-label">Balance</span>
        </div>
    </div>
</div>

<!-- Proyección de Saldo - Desktop -->
<div class="card mb-4 fade-in-up d-none d-md-block" style="animation-delay: 0.05s;">
    <div class="card-body py-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="proyeccion-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
            <div class="col">
                <h6 class="mb-1"><i class="bi bi-crystal-ball me-2"></i>Proyección de Saldo</h6>
                <small class="text-muted">Basado en transacciones programadas y gastos recurrentes</small>
            </div>
            <div class="col-auto">
                <div class="d-flex gap-4">
                    <div class="text-center">
                        <small class="text-muted d-block">Fin de <?= $mesNombre ?></small>
                        <strong class="fs-5 <?= $saldoProyectadoMesActual >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatMoney($saldoProyectadoMesActual) ?>
                        </strong>
                        <?php 
                        $diferenciaMesActual = $saldoProyectadoMesActual - $saldoTotal;
                        if ($diferenciaMesActual != 0): 
                        ?>
                        <small class="d-block <?= $diferenciaMesActual >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= $diferenciaMesActual >= 0 ? '+' : '' ?><?= formatMoney($diferenciaMesActual) ?>
                        </small>
                        <?php endif; ?>
                    </div>
                    <div class="proyeccion-divider"></div>
                    <div class="text-center">
                        <small class="text-muted d-block">Fin de <?= $mesSiguienteNombre ?></small>
                        <strong class="fs-5 <?= $saldoProyectadoMesSiguiente >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatMoney($saldoProyectadoMesSiguiente) ?>
                        </strong>
                        <?php 
                        $diferenciaMesSiguiente = $saldoProyectadoMesSiguiente - $saldoProyectadoMesActual;
                        if ($diferenciaMesSiguiente != 0): 
                        ?>
                        <small class="d-block <?= $diferenciaMesSiguiente >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= $diferenciaMesSiguiente >= 0 ? '+' : '' ?><?= formatMoney($diferenciaMesSiguiente) ?>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalProyeccion">
                    <i class="bi bi-info-circle"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions - Desktop -->
<div class="row g-3 mb-4 fade-in-up d-none d-md-flex" style="animation-delay: 0.1s;">
    <div class="col-md-3">
        <a href="<?= uiModuleUrl('transacciones', 'crear', ['tipo' => 'ingreso']) ?>" class="quick-action-btn h-100">
            <i class="bi bi-plus-circle text-success"></i>
            <span>Nuevo Ingreso</span>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= uiModuleUrl('transacciones', 'crear', ['tipo' => 'egreso']) ?>" class="quick-action-btn h-100">
            <i class="bi bi-dash-circle text-danger"></i>
            <span>Nuevo Gasto</span>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= uiModuleUrl('transacciones', 'crear', ['tipo' => 'transferencia']) ?>" class="quick-action-btn h-100">
            <i class="bi bi-arrow-left-right text-primary"></i>
            <span>Transferencia</span>
        </a>
    </div>
    <div class="col-md-3">
        <a href="<?= uiModuleUrl('cuentas', 'crear') ?>" class="quick-action-btn h-100">
            <i class="bi bi-wallet-fill" style="color: var(--primary-blue);"></i>
            <span>Nueva Cuenta</span>
        </a>
    </div>
</div>

<!-- Proyección de Saldo - Mobile -->
<div class="proyeccion-mobile d-md-none mb-3 fade-in-up" style="animation-delay: 0.05s;">
    <div class="proyeccion-mobile-header" data-bs-toggle="modal" data-bs-target="#modalProyeccion">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-graph-up-arrow"></i>
            <span>Proyección</span>
        </div>
        <i class="bi bi-info-circle"></i>
    </div>
    <div class="proyeccion-mobile-content">
        <div class="proyeccion-mobile-item">
            <span class="proyeccion-mobile-mes">Fin <?= $mesNombre ?></span>
            <span class="proyeccion-mobile-valor <?= $saldoProyectadoMesActual >= 0 ? 'positivo' : 'negativo' ?>">
                <?= formatMoney($saldoProyectadoMesActual) ?>
            </span>
        </div>
        <div class="proyeccion-mobile-divider">
            <i class="bi bi-arrow-right"></i>
        </div>
        <div class="proyeccion-mobile-item">
            <span class="proyeccion-mobile-mes">Fin <?= $mesSiguienteNombre ?></span>
            <span class="proyeccion-mobile-valor <?= $saldoProyectadoMesSiguiente >= 0 ? 'positivo' : 'negativo' ?>">
                <?= formatMoney($saldoProyectadoMesSiguiente) ?>
            </span>
        </div>
    </div>
</div>

<!-- Quick Actions - Mobile Compacto -->
<div class="dash-quick-actions d-md-none mb-3 fade-in-up" style="animation-delay: 0.1s;">
    <a href="<?= uiModuleUrl('transacciones', 'crear', ['tipo' => 'ingreso']) ?>" class="dash-quick-btn ingreso" title="Ingreso">
        <i class="bi bi-plus-circle-fill"></i>
        <span>Ingreso</span>
    </a>
    <a href="<?= uiModuleUrl('transacciones', 'crear', ['tipo' => 'egreso']) ?>" class="dash-quick-btn egreso" title="Gasto">
        <i class="bi bi-dash-circle-fill"></i>
        <span>Gasto</span>
    </a>
    <a href="<?= uiModuleUrl('transacciones', 'crear', ['tipo' => 'transferencia']) ?>" class="dash-quick-btn transferencia" title="Transferencia">
        <i class="bi bi-arrow-left-right"></i>
        <span>Transferir</span>
    </a>
</div>

<div class="row g-4">
    <!-- Cuentas -->
    <div class="col-lg-4">
        <div class="card fade-in-up" style="animation-delay: 0.2s;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-wallet2 me-2"></i>Mis Cuentas</h5>
                <a href="<?= uiModuleUrl('cuentas') ?>" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($cuentas)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-wallet2 fs-1 text-muted"></i>
                    <p class="text-muted mt-3">No tienes cuentas registradas</p>
                    <a href="<?= uiModuleUrl('cuentas', 'crear') ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus me-1"></i>Crear cuenta
                    </a>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($cuentas as $cuenta): ?>
                    <div class="list-group-item d-flex align-items-center py-3 px-4">
                        <div class="account-icon me-3" style="background-color: <?= htmlspecialchars($cuenta['color'] ?? '#55A5C8') ?>;">
                            <i class="bi <?= htmlspecialchars($cuenta['icono'] ?? 'bi-wallet2') ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0"><?= htmlspecialchars($cuenta['nombre']) ?></h6>
                            <small class="text-muted">
                                <?= $cuenta['banco_nombre'] ?? ucfirst($cuenta['tipo']) ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <strong class="<?= $cuenta['saldo_actual'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= formatMoney($cuenta['saldo_actual']) ?>
                            </strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Próximos Gastos Recurrentes -->
        <?php if (!empty($proximosRecurrentes)): ?>
        <div class="card mt-4 fade-in-up" style="animation-delay: 0.3s;">
            <div class="card-header">
                <h5><i class="bi bi-calendar-check me-2"></i>Próximos Gastos</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($proximosRecurrentes as $recurrente): ?>
                    <div class="list-group-item py-3 px-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0"><?= htmlspecialchars($recurrente['nombre']) ?></h6>
                                <small class="text-muted">
                                    <i class="bi <?= htmlspecialchars($recurrente['categoria_icono'] ?? 'bi-tag') ?> me-1"></i>
                                    <?= htmlspecialchars($recurrente['categoria_nombre'] ?? 'Sin categoría') ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <strong class="text-danger d-block"><?= formatMoney($recurrente['monto']) ?></strong>
                                <small class="text-muted"><?= formatDate($recurrente['proxima_ejecucion']) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Últimas Transacciones -->
    <div class="col-lg-8">
        <div class="card fade-in-up" style="animation-delay: 0.2s;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-clock-history me-2"></i>Últimas Transacciones</h5>
                <a href="<?= uiModuleUrl('transacciones') ?>" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($ultimasTransacciones)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-receipt fs-1 text-muted"></i>
                    <p class="text-muted mt-3">No hay transacciones registradas</p>
                    <a href="<?= uiModuleUrl('transacciones', 'crear') ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus me-1"></i>Agregar transacción
                    </a>
                </div>
                <?php else: ?>
                <div class="dash-trans-list">
                    <?php foreach ($ultimasTransacciones as $trans): ?>
                    <?php
                        // Determinar clase del icono según tipo
                        $iconClass = match($trans['tipo']) {
                            'ingreso' => 'dash-trans-ingreso',
                            'egreso' => 'dash-trans-egreso',
                            'transferencia' => 'dash-trans-transferencia',
                            'ajuste' => 'dash-trans-ajuste',
                            default => 'dash-trans-egreso'
                        };
                        
                        // Limpiar descripción de ajustes
                        $descripcion = $trans['descripcion'] ?: $trans['categoria_nombre'] ?? 'Sin descripción';
                        if ($trans['tipo'] === 'ajuste') {
                            $descripcion = ltrim($descripcion, '+- ');
                        }
                    ?>
                    <div class="dash-trans-item">
                        <div class="dash-trans-icon <?= $iconClass ?>">
                            <?php if ($trans['tipo'] === 'ajuste'): ?>
                            <i class="bi bi-sliders"></i>
                            <?php elseif ($trans['categoria_icono']): ?>
                            <i class="bi <?= htmlspecialchars($trans['categoria_icono']) ?>"></i>
                            <?php else: ?>
                            <i class="bi <?= $trans['tipo'] === 'ingreso' ? 'bi-arrow-down' : ($trans['tipo'] === 'egreso' ? 'bi-arrow-up' : 'bi-arrow-left-right') ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="dash-trans-info">
                            <span class="dash-trans-desc"><?= htmlspecialchars($descripcion) ?></span>
                            <span class="dash-trans-meta">
                                <?= htmlspecialchars($trans['cuenta_nombre'] ?? '') ?>
                                · <?= formatDate($trans['fecha_transaccion']) ?>
                            </span>
                        </div>
                        <div class="dash-trans-monto <?= $trans['tipo'] === 'ingreso' ? 'ingreso' : ($trans['tipo'] === 'egreso' ? 'egreso' : ($trans['tipo'] === 'ajuste' ? 'ajuste' : 'transferencia')) ?>">
                            <?= $trans['tipo'] === 'ingreso' ? '+' : ($trans['tipo'] === 'egreso' ? '-' : '') ?>
                            <?= formatMoney($trans['monto']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Gastos por Categoría -->
        <?php if (!empty($gastosPorCategoria)): ?>
        <div class="card mt-4 fade-in-up" style="animation-delay: 0.3s;">
            <div class="card-header">
                <h5><i class="bi bi-pie-chart me-2"></i>Gastos por Categoría - <?= $mesNombre ?></h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <canvas id="gastosChart" height="200"></canvas>
                    </div>
                    <div class="col-md-6">
                        <div class="category-list">
                            <?php foreach ($gastosPorCategoria as $gasto): ?>
                            <div class="d-flex align-items-center justify-content-between py-2">
                                <div class="d-flex align-items-center">
                                    <span class="category-dot me-2" style="background-color: <?= htmlspecialchars($gasto['color']) ?>;"></span>
                                    <span><?= htmlspecialchars($gasto['nombre']) ?></span>
                                </div>
                                <strong><?= formatMoney($gasto['total']) ?></strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.account-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.category-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.category-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.list-group-item {
    border-left: none;
    border-right: none;
}

.list-group-item:first-child {
    border-top: none;
}

/* ===== MOBILE STATS COMPACTAS ===== */
.dash-mobile-stats {
    background: var(--bg-card, white);
    border-radius: 16px;
    padding: 16px;
    box-shadow: var(--shadow-md, 0 2px 12px rgba(0,0,0,0.06));
}

.dash-saldo-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 10px;
    margin-bottom: 8px;
    border-bottom: 1px solid var(--border-light, #f0f0f0);
}

.dash-saldo-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.dash-saldo-label {
    font-size: 13px;
    color: var(--text-secondary, #666);
    font-weight: 500;
}

.dash-saldo-badge {
    font-size: 9px;
    font-weight: 600;
    background: rgba(85, 165, 200, 0.15);
    color: var(--primary-blue);
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
}

.dash-periodo-label {
    font-size: 11px;
    color: var(--text-muted, #888);
    text-align: center;
    margin-bottom: 10px;
    font-weight: 500;
}

.dash-saldo-value {
    font-size: 20px;
    font-weight: 700;
}

.dash-saldo-value.positivo { color: var(--dark-blue); }
.dash-saldo-value.negativo { color: #FF6B6B; }

.dash-stats-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.dash-stat-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px 6px;
    border-radius: 10px;
    text-align: center;
}

.dash-stat-item i {
    font-size: 16px;
    margin-bottom: 2px;
}

.dash-stat-value {
    font-size: 12px;
    font-weight: 700;
}

.dash-stat-label {
    font-size: 9px;
    font-weight: 500;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.dash-stat-item.ingreso {
    background: rgba(154, 208, 130, 0.15);
    color: #5a9a3e;
}

.dash-stat-item.egreso {
    background: rgba(255, 107, 107, 0.15);
    color: #ee5a5a;
}

.dash-stat-item.positivo {
    background: rgba(85, 165, 200, 0.15);
    color: var(--dark-blue);
}

.dash-stat-item.negativo {
    background: rgba(255, 107, 107, 0.15);
    color: #ee5a5a;
}

/* ===== QUICK ACTIONS MOBILE ===== */
.dash-quick-actions {
    display: flex;
    gap: 8px;
    background: var(--bg-card, white);
    border-radius: 14px;
    padding: 10px;
    box-shadow: var(--shadow-sm, 0 2px 10px rgba(0,0,0,0.05));
}

.dash-quick-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 10px 4px;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s ease;
    gap: 4px;
}

.dash-quick-btn i {
    font-size: 20px;
}

.dash-quick-btn span {
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
}

.dash-quick-btn.ingreso {
    background: rgba(154, 208, 130, 0.15);
    color: #5a9a3e;
}

.dash-quick-btn.egreso {
    background: rgba(255, 107, 107, 0.15);
    color: #ee5a5a;
}

.dash-quick-btn.transferencia {
    background: rgba(85, 165, 200, 0.15);
    color: var(--primary-blue);
}

.dash-quick-btn.cuenta {
    background: rgba(53, 113, 158, 0.15);
    color: var(--dark-blue);
}

.dash-quick-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* ===== LISTA TRANSACCIONES DASHBOARD ===== */
.dash-trans-list {
    display: flex;
    flex-direction: column;
}

.dash-trans-item {
    display: flex;
    align-items: center;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-light, #f5f5f5);
    gap: 12px;
}

.dash-trans-item:last-child {
    border-bottom: none;
}

.dash-trans-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.dash-trans-ingreso { background: rgba(154, 208, 130, 0.2); color: #5a9a3e; }
.dash-trans-egreso { background: rgba(255, 107, 107, 0.2); color: #ee5a5a; }
.dash-trans-transferencia { background: rgba(85, 165, 200, 0.2); color: var(--dark-blue); }
.dash-trans-ajuste { background: rgba(156, 39, 176, 0.15); color: #9c27b0; }

.dash-trans-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.dash-trans-desc {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary, #333);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dash-trans-meta {
    font-size: 12px;
    color: var(--text-muted, #999);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dash-trans-monto {
    font-size: 14px;
    font-weight: 600;
    text-align: right;
    flex-shrink: 0;
}

.dash-trans-monto.ingreso { color: #5a9a3e; }
.dash-trans-monto.egreso { color: #ee5a5a; }
.dash-trans-monto.transferencia { color: var(--dark-blue); }
.dash-trans-monto.ajuste { color: #9c27b0; }

/* ===== PROYECCIÓN DE SALDO ===== */
.proyeccion-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
}

.proyeccion-divider {
    width: 1px;
    height: 50px;
    background: var(--border-color, #e0e0e0);
}

/* Proyección Mobile */
.proyeccion-mobile {
    background: var(--bg-card, white);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: var(--shadow-sm, 0 2px 10px rgba(0,0,0,0.05));
}

.proyeccion-mobile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.proyeccion-mobile-header i:first-child {
    font-size: 14px;
}

.proyeccion-mobile-content {
    display: flex;
    align-items: center;
    padding: 12px;
}

.proyeccion-mobile-item {
    flex: 1;
    text-align: center;
}

.proyeccion-mobile-mes {
    display: block;
    font-size: 10px;
    color: var(--text-muted, #888);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.proyeccion-mobile-valor {
    display: block;
    font-size: 15px;
    font-weight: 700;
    margin-top: 2px;
}

.proyeccion-mobile-valor.positivo { color: #5a9a3e; }
.proyeccion-mobile-valor.negativo { color: #ee5a5a; }

.proyeccion-mobile-divider {
    padding: 0 8px;
    color: var(--text-muted, #ccc);
    font-size: 12px;
}

/* Modal Proyección */
.proyeccion-detail-card {
    background: var(--bg-input, #f8f9fa);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
}

.proyeccion-detail-card h6 {
    font-size: 13px;
    color: var(--text-secondary, #666);
    margin-bottom: 12px;
    font-weight: 600;
}

.proyeccion-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 14px;
}

.proyeccion-detail-row.total {
    border-top: 2px solid var(--border-color, #dee2e6);
    margin-top: 8px;
    padding-top: 10px;
    font-weight: 700;
}

.proyeccion-detail-row .label {
    color: var(--text-secondary, #555);
}

.proyeccion-detail-row .value.ingreso { color: #5a9a3e; }
.proyeccion-detail-row .value.egreso { color: #ee5a5a; }
.proyeccion-detail-row .value.positivo { color: #5a9a3e; }
.proyeccion-detail-row .value.negativo { color: #ee5a5a; }

@media (max-width: 576px) {
    .dash-trans-icon {
        width: 36px;
        height: 36px;
        font-size: 14px;
    }
    
    .dash-trans-desc {
        font-size: 13px;
    }
    
    .dash-trans-monto {
        font-size: 13px;
    }
    
    .account-icon {
        width: 38px;
        height: 38px;
        font-size: 16px;
    }
}
</style>

<!-- Modal Proyección de Saldo -->
<div class="modal fade" id="modalProyeccion" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">
                    <i class="bi bi-graph-up-arrow me-2"></i>Proyección de Saldo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Esta proyección se calcula sumando tus transacciones programadas y gastos recurrentes pendientes.
                </p>
                
                <!-- Saldo Actual -->
                <div class="proyeccion-detail-card" style="background: linear-gradient(135deg, rgba(85, 165, 200, 0.1) 0%, rgba(53, 113, 158, 0.1) 100%);">
                    <h6><i class="bi bi-wallet2 me-2"></i>Saldo Actual</h6>
                    <div class="text-center">
                        <strong class="fs-4 <?= $saldoTotal >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatMoney($saldoTotal) ?>
                        </strong>
                    </div>
                </div>
                
                <!-- Proyección Mes Actual -->
                <div class="proyeccion-detail-card">
                    <h6><i class="bi bi-calendar me-2"></i>Fin de <?= $mesNombre ?> <?= $anioActual ?></h6>
                    
                    <div class="proyeccion-detail-row">
                        <span class="label"><i class="bi bi-arrow-down-circle text-success me-2"></i>Ingresos esperados</span>
                        <span class="value ingreso">+<?= formatMoney($ingresosProyectadosMesActual) ?></span>
                    </div>
                    <div class="proyeccion-detail-row">
                        <span class="label"><i class="bi bi-arrow-up-circle text-danger me-2"></i>Egresos esperados</span>
                        <span class="value egreso">-<?= formatMoney($egresosProyectadosMesActual) ?></span>
                    </div>
                    <div class="proyeccion-detail-row total">
                        <span class="label">Saldo proyectado</span>
                        <span class="value <?= $saldoProyectadoMesActual >= 0 ? 'positivo' : 'negativo' ?>">
                            <?= formatMoney($saldoProyectadoMesActual) ?>
                        </span>
                    </div>
                </div>
                
                <!-- Proyección Mes Siguiente -->
                <div class="proyeccion-detail-card">
                    <h6><i class="bi bi-calendar-plus me-2"></i>Fin de <?= $mesSiguienteNombre ?> <?= $anioMesSiguiente ?></h6>
                    
                    <div class="proyeccion-detail-row">
                        <span class="label"><i class="bi bi-arrow-down-circle text-success me-2"></i>Ingresos esperados</span>
                        <span class="value ingreso">+<?= formatMoney($ingresosProyectadosMesSiguiente) ?></span>
                    </div>
                    <div class="proyeccion-detail-row">
                        <span class="label"><i class="bi bi-arrow-up-circle text-danger me-2"></i>Egresos esperados</span>
                        <span class="value egreso">-<?= formatMoney($egresosProyectadosMesSiguiente) ?></span>
                    </div>
                    <div class="proyeccion-detail-row total">
                        <span class="label">Saldo proyectado</span>
                        <span class="value <?= $saldoProyectadoMesSiguiente >= 0 ? 'positivo' : 'negativo' ?>">
                            <?= formatMoney($saldoProyectadoMesSiguiente) ?>
                        </span>
                    </div>
                </div>
                
                <div class="alert alert-light small mb-0">
                    <strong>💡 Tip:</strong> Para mejorar la precisión, registra tus gastos recurrentes y programa tus transacciones futuras.
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($gastosPorCategoria)): ?>
<?php
$chartLabels = array_map(fn($g) => $g['nombre'], $gastosPorCategoria);
$chartData = array_map(fn($g) => $g['total'], $gastosPorCategoria);
$chartColors = array_map(fn($g) => $g['color'], $gastosPorCategoria);
?>
<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('gastosChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                data: <?= json_encode($chartData) ?>,
                backgroundColor: <?= json_encode($chartColors) ?>,
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + formatMoney(context.raw);
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
});
</script>
<?php $extraScripts = ob_get_clean(); ?>
<?php endif; ?>

