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
            <small class="text-white-50 d-block">Panel de Administración</small>
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
        <small class="text-white-50">Panel de Administración</small>
    </div>
    
    <nav class="nav flex-column">
        <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>index.php">
            <i class="fas fa-home me-2"></i>Inicio
        </a>
        <a class="nav-link <?php echo $currentPage === 'bancos' ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>modules/bancos/pages/index.php">
            <i class="fas fa-university me-2"></i>Bancos
        </a>
        <div class="mt-auto pt-3 border-top border-white border-opacity-25">
            <?php if ($currentUser): ?>
            <div class="text-white-50 small mb-2">
                <i class="fas fa-user me-1"></i>
                <?php echo htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario'); ?>
            </div>
            <a class="nav-link text-danger" href="<?php echo getBaseUrl(); ?>../ui/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
            </a>
            <?php endif; ?>
        </div>
    </nav>
</div>
