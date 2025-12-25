<?php
/**
 * AND FINANCE APP - Admin Dashboard
 * Panel principal de administración
 */

$db = Database::getInstance();

// Obtener estadísticas generales
try {
    // Total usuarios
    $totalUsuarios = $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario'")->fetchColumn();
    
    // Usuarios activos (último mes)
    $usuariosActivos = $db->query("
        SELECT COUNT(*) FROM usuarios 
        WHERE rol = 'usuario' 
        AND ultimo_acceso >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetchColumn();
    
    // Total transacciones del mes
    $transaccionesMes = $db->query("
        SELECT COUNT(*) FROM transacciones 
        WHERE MONTH(fecha_transaccion) = MONTH(CURRENT_DATE())
        AND YEAR(fecha_transaccion) = YEAR(CURRENT_DATE())
    ")->fetchColumn();
    
    // Total bancos activos
    $totalBancos = $db->query("SELECT COUNT(*) FROM bancos WHERE estado = 1")->fetchColumn();
    
    // Últimos usuarios registrados
    $ultimosUsuarios = $db->query("
        SELECT id, nombre, email, fecha_creacion, ultimo_acceso 
        FROM usuarios 
        WHERE rol = 'usuario'
        ORDER BY fecha_creacion DESC 
        LIMIT 5
    ")->fetchAll();
    
} catch (PDOException $e) {
    $totalUsuarios = 0;
    $usuariosActivos = 0;
    $transaccionesMes = 0;
    $totalBancos = 0;
    $ultimosUsuarios = [];
}
?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-primary-soft">
                            <i class="bi bi-people text-primary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Total Usuarios</h6>
                        <h3 class="mb-0"><?= number_format($totalUsuarios) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-success-soft">
                            <i class="bi bi-person-check text-success"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Usuarios Activos</h6>
                        <h3 class="mb-0"><?= number_format($usuariosActivos) ?></h3>
                        <small class="text-muted">Último mes</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-info-soft">
                            <i class="bi bi-receipt text-info"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Transacciones</h6>
                        <h3 class="mb-0"><?= number_format($transaccionesMes) ?></h3>
                        <small class="text-muted">Este mes</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="stats-icon bg-warning-soft">
                            <i class="bi bi-bank text-warning"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Bancos</h6>
                        <h3 class="mb-0"><?= number_format($totalBancos) ?></h3>
                        <small class="text-muted">Activos</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-clock-history me-2"></i>Últimos Usuarios Registrados</h5>
                <a href="<?= moduleUrl('usuarios') ?>" class="btn btn-sm btn-outline-primary">
                    Ver todos
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Registro</th>
                                <th>Último acceso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ultimosUsuarios)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No hay usuarios registrados aún
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($ultimosUsuarios as $usuario): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar-sm me-2">
                                                <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                                            </div>
                                            <?= htmlspecialchars($usuario['nombre']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($usuario['email']) ?></td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($usuario['ultimo_acceso']): ?>
                                            <small><?= date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Nunca</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-lightning me-2"></i>Accesos Rápidos</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-3">
                    <a href="<?= moduleUrl('bancos', 'crear') ?>" class="btn btn-outline-primary text-start">
                        <i class="bi bi-plus-circle me-2"></i>
                        Agregar Nuevo Banco
                    </a>
                    <a href="<?= moduleUrl('categorias', 'crear') ?>" class="btn btn-outline-primary text-start">
                        <i class="bi bi-tags me-2"></i>
                        Nueva Categoría del Sistema
                    </a>
                    <a href="<?= moduleUrl('usuarios') ?>" class="btn btn-outline-primary text-start">
                        <i class="bi bi-people me-2"></i>
                        Gestionar Usuarios
                    </a>
                    <a href="<?= moduleUrl('reportes') ?>" class="btn btn-outline-primary text-start">
                        <i class="bi bi-graph-up me-2"></i>
                        Ver Reportes Globales
                    </a>
                </div>
            </div>
        </div>
        
        <!-- System Info Card -->
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="bi bi-info-circle me-2"></i>Información del Sistema</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Versión</span>
                        <strong>1.0.0</strong>
                    </li>
                    <li class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">PHP</span>
                        <strong><?= phpversion() ?></strong>
                    </li>
                    <li class="d-flex justify-content-between py-2">
                        <span class="text-muted">Entorno</span>
                        <span class="badge bg-success">Desarrollo</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.stats-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.bg-primary-soft {
    background: rgba(85, 165, 200, 0.15);
}

.bg-success-soft {
    background: rgba(154, 208, 130, 0.15);
}

.bg-info-soft {
    background: rgba(13, 202, 240, 0.15);
}

.bg-warning-soft {
    background: rgba(255, 193, 7, 0.15);
}

.user-avatar-sm {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 12px;
}
</style>

