<?php
/**
 * Router principal de Admin
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['and_finance_user']) || $_SESSION['and_finance_user']['rol'] !== 'admin') {
    header('Location: ../ui/login.php');
    exit;
}

require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar.php';

$currentPage = 'dashboard';
$currentUser = $_SESSION['and_finance_user'];
?>

<div class="main-content">
    <div class="mb-4">
        <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
        <p class="text-muted">Panel de administración de And Finance App</p>
    </div>
    
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-university fa-3x text-primary mb-3"></i>
                    <h3>Bancos</h3>
                    <p class="text-muted">Gestiona los bancos disponibles</p>
                    <a href="<?php echo getBaseUrl(); ?>modules/bancos/pages/index.php" class="btn btn-primary">
                        <i class="fas fa-arrow-right me-2"></i>Ir a Bancos
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-3x text-secondary mb-3"></i>
                    <h3>Usuarios</h3>
                    <p class="text-muted">Próximamente</p>
                    <button class="btn btn-secondary" disabled>
                        <i class="fas fa-lock me-2"></i>Próximamente
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-chart-bar fa-3x text-primary mb-3"></i>
                    <h3>Estadísticas</h3>
                    <p class="text-muted">Próximamente</p>
                    <button class="btn btn-secondary" disabled>
                        <i class="fas fa-lock me-2"></i>Próximamente
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>
