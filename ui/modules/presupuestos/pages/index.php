<?php
/**
 * AND FINANCE APP - Lista de Presupuestos
 */

ob_start();

require_once __DIR__ . '/../models/PresupuestoModel.php';

$pageTitle = 'Presupuestos';
$pageSubtitle = 'Control de gastos por categoría';

$presupuestoModel = new PresupuestoModel();
$userId = getCurrentUserId();

// Obtener mes y año (por defecto el actual)
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Acciones
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Eliminar presupuesto
if ($action === 'eliminar' && $id > 0) {
    $presupuesto = $presupuestoModel->getById($id);
    if ($presupuesto && $presupuesto['usuario_id'] == $userId) {
        if ($presupuestoModel->delete($id)) {
            setFlashMessage('success', 'Presupuesto eliminado correctamente');
        } else {
            setFlashMessage('error', 'Error al eliminar el presupuesto');
        }
    }
    ob_end_clean();
    header('Location: ' . uiModuleUrl('presupuestos') . '&mes=' . $mes . '&anio=' . $anio);
    exit;
}

// Copiar presupuestos del mes anterior
if ($action === 'copiar_anterior') {
    $mesAnterior = $mes - 1;
    $anioAnterior = $anio;
    if ($mesAnterior < 1) {
        $mesAnterior = 12;
        $anioAnterior--;
    }
    
    $copiados = $presupuestoModel->copiarPresupuestos($userId, $mesAnterior, $anioAnterior, $mes, $anio);
    if ($copiados > 0) {
        setFlashMessage('success', "Se copiaron $copiados presupuesto(s) del mes anterior");
    } else {
        setFlashMessage('info', 'No hay presupuestos para copiar o ya existen en este mes');
    }
    ob_end_clean();
    header('Location: ' . uiModuleUrl('presupuestos') . '&mes=' . $mes . '&anio=' . $anio);
    exit;
}

// Obtener resumen
$resumen = $presupuestoModel->getResumenMes($userId, $mes, $anio);

// Nombres de meses
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Calcular mes anterior y siguiente para navegación
$mesAnterior = $mes - 1;
$anioMesAnterior = $anio;
if ($mesAnterior < 1) {
    $mesAnterior = 12;
    $anioMesAnterior--;
}

$mesSiguiente = $mes + 1;
$anioMesSiguiente = $anio;
if ($mesSiguiente > 12) {
    $mesSiguiente = 1;
    $anioMesSiguiente++;
}

$esMesActual = ($mes == date('m') && $anio == date('Y'));
?>

<style>
/* Resumen Mobile */
.presupuesto-resumen-mobile {
    background: linear-gradient(135deg, #35719E 0%, #55A5C8 100%);
    border-radius: 16px;
    padding: 16px;
    color: white;
    margin-bottom: 1rem;
}

.presupuesto-periodo {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.presupuesto-periodo-nav {
    display: flex;
    align-items: center;
    gap: 8px;
}

.presupuesto-periodo-nav a {
    color: white;
    opacity: 0.8;
    text-decoration: none;
    padding: 4px 8px;
    border-radius: 8px;
    transition: all 0.2s;
}

.presupuesto-periodo-nav a:hover {
    opacity: 1;
    background: rgba(255,255,255,0.2);
}

.presupuesto-periodo-label {
    font-weight: 600;
    font-size: 15px;
}

.presupuesto-progress-container {
    margin-bottom: 12px;
}

.presupuesto-progress-info {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 6px;
}

.presupuesto-progress-bar {
    height: 8px;
    background: rgba(255,255,255,0.2);
    border-radius: 4px;
    overflow: hidden;
}

.presupuesto-progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
}

.presupuesto-progress-fill.safe { background: #9AD082; }
.presupuesto-progress-fill.warning { background: #F7DC6F; }
.presupuesto-progress-fill.danger { background: #FF6B6B; }

.presupuesto-stats {
    display: flex;
    gap: 8px;
}

.presupuesto-stat {
    flex: 1;
    background: rgba(255,255,255,0.15);
    border-radius: 10px;
    padding: 10px;
    text-align: center;
}

.presupuesto-stat-value {
    font-size: 14px;
    font-weight: 700;
    display: block;
}

.presupuesto-stat-label {
    font-size: 10px;
    opacity: 0.8;
}

/* Cards de presupuesto */
.presupuesto-card {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: transform 0.2s;
}

.presupuesto-card:hover {
    transform: translateY(-2px);
}

.presupuesto-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.presupuesto-categoria {
    display: flex;
    align-items: center;
    gap: 12px;
}

.presupuesto-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white;
}

.presupuesto-info h6 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
}

.presupuesto-info small {
    color: #6c757d;
    font-size: 12px;
}

.presupuesto-monto {
    text-align: right;
}

.presupuesto-monto-gastado {
    font-size: 16px;
    font-weight: 700;
}

.presupuesto-monto-limite {
    font-size: 12px;
    color: #6c757d;
}

.presupuesto-progress {
    margin-bottom: 12px;
}

.presupuesto-progress .progress {
    height: 6px;
    border-radius: 3px;
}

.presupuesto-card-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #f0f0f0;
}

.presupuesto-porcentaje {
    font-size: 13px;
    font-weight: 600;
}

.presupuesto-btns {
    display: flex;
    gap: 8px;
}

.presupuesto-btns .btn {
    padding: 4px 10px;
    font-size: 13px;
}

/* Empty state */
.empty-presupuestos {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-presupuestos i {
    font-size: 4rem;
    color: #B1BCBF;
    margin-bottom: 1rem;
}

/* Alertas */
.alertas-presupuesto {
    margin-bottom: 1rem;
}

.alerta-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 10px;
    margin-bottom: 8px;
    font-size: 13px;
}

.alerta-item.warning {
    background: rgba(247, 220, 111, 0.2);
    border-left: 3px solid #F7DC6F;
}

.alerta-item.danger {
    background: rgba(255, 107, 107, 0.2);
    border-left: 3px solid #FF6B6B;
}

/* Desktop adjustments */
@media (min-width: 768px) {
    .presupuesto-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .presupuesto-card {
        margin-bottom: 0;
    }
}

@media (min-width: 992px) {
    .presupuesto-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>

<!-- Resumen Mobile -->
<div class="presupuesto-resumen-mobile d-md-none fade-in-up">
    <div class="presupuesto-periodo">
        <div class="presupuesto-periodo-nav">
            <a href="<?= uiModuleUrl('presupuestos') ?>&mes=<?= $mesAnterior ?>&anio=<?= $anioMesAnterior ?>">
                <i class="bi bi-chevron-left"></i>
            </a>
        </div>
        <span class="presupuesto-periodo-label">
            <i class="bi bi-calendar3 me-1"></i><?= $meses[$mes] ?> <?= $anio ?>
        </span>
        <div class="presupuesto-periodo-nav">
            <a href="<?= uiModuleUrl('presupuestos') ?>&mes=<?= $mesSiguiente ?>&anio=<?= $anioMesSiguiente ?>">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>
    
    <?php if ($resumen['cantidad'] > 0): ?>
    <div class="presupuesto-progress-container">
        <div class="presupuesto-progress-info">
            <span>Usado: <?= $resumen['porcentaje_usado'] ?>%</span>
            <span>Disponible: <?= formatMoney($resumen['disponible']) ?></span>
        </div>
        <div class="presupuesto-progress-bar">
            <?php 
            $progressClass = 'safe';
            if ($resumen['porcentaje_usado'] >= 100) $progressClass = 'danger';
            elseif ($resumen['porcentaje_usado'] >= 80) $progressClass = 'warning';
            ?>
            <div class="presupuesto-progress-fill <?= $progressClass ?>" 
                 style="width: <?= min($resumen['porcentaje_usado'], 100) ?>%"></div>
        </div>
    </div>
    
    <div class="presupuesto-stats">
        <div class="presupuesto-stat">
            <span class="presupuesto-stat-value"><?= formatMoney($resumen['total_presupuestado']) ?></span>
            <span class="presupuesto-stat-label">Presupuesto</span>
        </div>
        <div class="presupuesto-stat">
            <span class="presupuesto-stat-value"><?= formatMoney($resumen['total_gastado']) ?></span>
            <span class="presupuesto-stat-label">Gastado</span>
        </div>
    </div>
    <?php else: ?>
    <div class="text-center py-2">
        <p class="mb-0 opacity-75">No tienes presupuestos para este mes</p>
    </div>
    <?php endif; ?>
</div>

<!-- Header Desktop con navegación de período -->
<div class="card mb-4 d-none d-md-block fade-in-up">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="<?= uiModuleUrl('presupuestos') ?>&mes=<?= $mesAnterior ?>&anio=<?= $anioMesAnterior ?>" 
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <h5 class="mb-0">
                        <i class="bi bi-calendar3 me-2"></i><?= $meses[$mes] ?> <?= $anio ?>
                    </h5>
                    <a href="<?= uiModuleUrl('presupuestos') ?>&mes=<?= $mesSiguiente ?>&anio=<?= $anioMesSiguiente ?>" 
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <?php if ($resumen['cantidad'] > 0): ?>
                <div class="d-flex justify-content-center gap-4">
                    <div>
                        <small class="text-muted d-block">Presupuestado</small>
                        <strong><?= formatMoney($resumen['total_presupuestado']) ?></strong>
                    </div>
                    <div>
                        <small class="text-muted d-block">Gastado</small>
                        <strong class="text-danger"><?= formatMoney($resumen['total_gastado']) ?></strong>
                    </div>
                    <div>
                        <small class="text-muted d-block">Disponible</small>
                        <strong class="<?= $resumen['disponible'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatMoney($resumen['disponible']) ?>
                        </strong>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-end">
                <a href="<?= uiModuleUrl('presupuestos', 'crear') ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" 
                   class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Nuevo Presupuesto
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Alertas -->
<?php if (!empty($resumen['alertas'])): ?>
<div class="alertas-presupuesto fade-in-up" style="animation-delay: 0.1s;">
    <?php foreach ($resumen['alertas'] as $alerta): ?>
    <div class="alerta-item <?= $alerta['tipo'] ?>">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>
            <strong><?= htmlspecialchars($alerta['categoria']) ?>:</strong> 
            <?= $alerta['mensaje'] ?> (<?= $alerta['porcentaje'] ?>%)
        </span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Lista de Presupuestos -->
<?php if (empty($resumen['presupuestos'])): ?>
<div class="card fade-in-up" style="animation-delay: 0.2s;">
    <div class="card-body empty-presupuestos">
        <i class="bi bi-pie-chart"></i>
        <h5>Sin presupuestos para <?= $meses[$mes] ?></h5>
        <p class="text-muted">Crea presupuestos para controlar tus gastos por categoría</p>
        <div class="d-flex flex-column flex-md-row gap-2 justify-content-center mt-3">
            <a href="<?= uiModuleUrl('presupuestos', 'crear') ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" 
               class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Crear Presupuesto
            </a>
            <?php if ($mesAnterior >= 1 || $anioMesAnterior < $anio): ?>
            <a href="<?= uiModuleUrl('presupuestos') ?>&action=copiar_anterior&mes=<?= $mes ?>&anio=<?= $anio ?>" 
               class="btn btn-outline-secondary">
                <i class="bi bi-copy me-1"></i>Copiar del mes anterior
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Botón crear (mobile) -->
<div class="d-md-none mb-3">
    <div class="d-flex gap-2">
        <a href="<?= uiModuleUrl('presupuestos', 'crear') ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" 
           class="btn btn-primary flex-grow-1">
            <i class="bi bi-plus-lg me-1"></i>Nuevo Presupuesto
        </a>
        <a href="<?= uiModuleUrl('presupuestos') ?>&action=copiar_anterior&mes=<?= $mes ?>&anio=<?= $anio ?>" 
           class="btn btn-outline-secondary" title="Copiar del mes anterior">
            <i class="bi bi-copy"></i>
        </a>
    </div>
</div>

<div class="presupuesto-grid">
    <?php foreach ($resumen['presupuestos'] as $index => $p): ?>
    <?php 
    $porcentaje = $p['monto_limite'] > 0 ? ($p['monto_gastado'] / $p['monto_limite']) * 100 : 0;
    $progressColor = '#9AD082';
    $textClass = 'text-success';
    if ($porcentaje >= 100) {
        $progressColor = '#FF6B6B';
        $textClass = 'text-danger';
    } elseif ($porcentaje >= $p['alertar_al']) {
        $progressColor = '#F7DC6F';
        $textClass = 'text-warning';
    }
    ?>
    <div class="presupuesto-card fade-in-up" style="animation-delay: <?= (0.1 + $index * 0.05) ?>s;">
        <div class="presupuesto-card-header">
            <div class="presupuesto-categoria">
                <div class="presupuesto-icon" style="background-color: <?= htmlspecialchars($p['categoria_color'] ?? '#55A5C8') ?>;">
                    <i class="bi <?= htmlspecialchars($p['categoria_icono'] ?? 'bi-tag') ?>"></i>
                </div>
                <div class="presupuesto-info">
                    <h6><?= htmlspecialchars($p['categoria_nombre']) ?></h6>
                    <small>Alerta al <?= $p['alertar_al'] ?>%</small>
                </div>
            </div>
            <div class="presupuesto-monto">
                <span class="presupuesto-monto-gastado <?= $textClass ?>"><?= formatMoney($p['monto_gastado']) ?></span>
                <div class="presupuesto-monto-limite">de <?= formatMoney($p['monto_limite']) ?></div>
            </div>
        </div>
        
        <div class="presupuesto-progress">
            <div class="progress">
                <div class="progress-bar" 
                     role="progressbar" 
                     style="width: <?= min($porcentaje, 100) ?>%; background-color: <?= $progressColor ?>;"
                     aria-valuenow="<?= $porcentaje ?>" 
                     aria-valuemin="0" 
                     aria-valuemax="100"></div>
            </div>
        </div>
        
        <div class="presupuesto-card-actions">
            <span class="presupuesto-porcentaje <?= $textClass ?>">
                <?php if ($porcentaje >= 100): ?>
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <?php endif; ?>
                <?= round($porcentaje, 1) ?>% usado
            </span>
            <div class="presupuesto-btns">
                <a href="<?= uiModuleUrl('presupuestos', 'editar', ['id' => $p['id']]) ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" 
                   class="btn btn-sm btn-outline-primary" title="Editar">
                    <i class="bi bi-pencil"></i>
                </a>
                <a href="<?= uiModuleUrl('presupuestos') ?>&action=eliminar&id=<?= $p['id'] ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" 
                   class="btn btn-sm btn-outline-danger" 
                   title="Eliminar"
                   onclick="return confirm('¿Eliminar este presupuesto?');">
                    <i class="bi bi-trash"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Botón copiar al siguiente mes (Desktop) -->
<div class="d-none d-md-block mt-4 text-center">
    <a href="<?= uiModuleUrl('presupuestos') ?>&action=copiar_anterior&mes=<?= $mes ?>&anio=<?= $anio ?>" 
       class="btn btn-outline-secondary">
        <i class="bi bi-copy me-1"></i>Copiar presupuestos del mes anterior
    </a>
</div>

<?php endif; ?>

<?php ob_start(); ?>
<script>
// Animación de barras de progreso al cargar
document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach((bar, index) => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.transition = 'width 0.8s ease-out';
            bar.style.width = width;
        }, 100 + (index * 100));
    });
});
</script>
<?php $extraScripts = ob_get_clean(); ?>

