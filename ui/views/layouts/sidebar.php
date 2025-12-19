<?php
// paths.php debe ser cargado antes de incluir este sidebar
if (!function_exists('getBaseUrl')) {
    require_once dirname(__DIR__, 2) . '/config/paths.php';
}
$currentUser = $_SESSION['and_finance_user'] ?? null;
$currentPage = $currentPage ?? '';
?>
<!-- Sidebar -->
<div class="sidebar p-3" id="mainSidebar">
    <div class="d-flex justify-content-between align-items-center mb-4 d-md-none">
        <div class="text-center flex-grow-1">
            <a href="<?php echo getBaseUrl(); ?>index.php" class="d-inline-flex align-items-center text-decoration-none text-white">
                <i class="fas fa-wallet fa-2x me-2"></i>
                <h4 class="mb-0">And Finance</h4>
            </a>
        </div>
        <button class="btn btn-link text-white p-0 ms-2" id="closeSidebar" aria-label="Cerrar sidebar">
            <i class="fas fa-times fa-lg"></i>
        </button>
    </div>
    <div class="text-center mb-4 d-none d-md-block">
        <a href="<?php echo getBaseUrl(); ?>index.php" class="d-inline-flex align-items-center text-decoration-none text-white">
            <i class="fas fa-wallet fa-2x me-2"></i>
            <h4 class="mb-0">And Finance</h4>
        </a>
    </div>
    
    <nav class="nav flex-column">
        <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>index.php">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
        <a class="nav-link <?php echo $currentPage === 'cuentas' ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>modules/cuentas/pages/index.php">
            <i class="fas fa-wallet me-2"></i>Cuentas
        </a>
        <a class="nav-link <?php echo $currentPage === 'categorias' ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>modules/categorias/pages/index.php">
            <i class="fas fa-tags me-2"></i>Categorías
        </a>
        <a class="nav-link <?php echo $currentPage === 'gastos_recurrentes' ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>modules/gastos_recurrentes/pages/index.php">
            <i class="fas fa-redo me-2"></i>Gastos Recurrentes
        </a>
        <a class="nav-link <?php echo $currentPage === 'transacciones' ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>modules/transacciones/pages/index.php">
            <i class="fas fa-exchange-alt me-2"></i>Transacciones
        </a>
        <a class="nav-link <?php echo $currentPage === 'reportes' ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>modules/reportes/pages/index.php">
            <i class="fas fa-chart-bar me-2"></i>Reportes
        </a>
        <div class="mt-auto pt-3 border-top border-white border-opacity-25">
            <?php if ($currentUser): ?>
            <div class="text-white-50 small mb-2">
                <i class="fas fa-user me-1"></i>
                <?php echo htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario'); ?>
            </div>
            <a class="nav-link text-danger" href="<?php echo getBaseUrl(); ?>logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
            </a>
            <?php endif; ?>
        </div>
    </nav>
</div>
