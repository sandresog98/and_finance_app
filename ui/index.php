<?php
/**
 * Router principal de UI - Dashboard
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['and_finance_user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/paths.php';
require_once dirname(__DIR__) . '/utils/Database.php';
require_once dirname(__DIR__) . '/utils/Env.php';
require_once __DIR__ . '/modules/gastos_recurrentes/models/RecurringExpense.php';
require_once __DIR__ . '/modules/cuentas/models/Account.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\GastosRecurrentes\Models\RecurringExpense;
use UI\Modules\Cuentas\Models\Account;

$currentPage = 'dashboard';
$currentUser = $_SESSION['and_finance_user'];
$userId = $currentUser['id'];

// Mes actual y siguiente
$mesActual = (int)date('n');
$anioActual = (int)date('Y');
$mesSiguiente = $mesActual == 12 ? 1 : $mesActual + 1;
$anioSiguiente = $mesActual == 12 ? $anioActual + 1 : $anioActual;

$nombresMeses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

try {
    $env = new Env(dirname(__DIR__) . '/.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    $accountModel = new Account($conn);
    
    // Recalcular saldos antes de obtenerlos
    $accountModel->recalculateAllBalances($userId);
    
    $recurringModel = new RecurringExpense($conn);
    
    // Calcular saldos proyectados
    $saldoActual = $recurringModel->getCurrentBalance($userId);
    $saldoProyectadoFinMesActual = $recurringModel->getProjectedBalanceEndOfMonth($userId, $mesActual, $anioActual);
    $saldoProyectadoFinMesSiguiente = $recurringModel->getProjectedBalanceEndOfMonth($userId, $mesSiguiente, $anioSiguiente);
    
} catch (Exception $e) {
    $saldoActual = 0;
    $saldoProyectadoFinMesActual = 0;
    $saldoProyectadoFinMesSiguiente = 0;
    $error = 'Error al cargar los saldos';
}

require_once __DIR__ . '/views/layouts/header.php';
require_once __DIR__ . '/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="mb-4">
        <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
        <p class="text-muted">Bienvenido, <?php echo htmlspecialchars($currentUser['nombre_completo']); ?></p>
    </div>
    
    <!-- Resumen de Saldos -->
    <h2 class="mb-3"><i class="fas fa-chart-line me-2"></i>Resumen de Saldos</h2>
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-primary">
                <div class="card-header text-white" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--third-color) 100%);">
                    <h5 class="mb-0 text-white">
                        <i class="fas fa-wallet me-2"></i>Saldo Actual
                    </h5>
                </div>
                <div class="card-body">
                    <h3 class="<?php echo $saldoActual < 0 ? 'text-danger' : 'text-success'; ?> mb-0">
                        $<?php echo number_format($saldoActual, 2, ',', '.'); ?>
                    </h3>
                    <small class="text-muted">Suma de todas tus cuentas</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Proyección: <?php echo $nombresMeses[$mesActual] . ' ' . $anioActual; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <h3 class="<?php echo $saldoProyectadoFinMesActual < 0 ? 'text-danger' : 'text-success'; ?> mb-0">
                        $<?php echo number_format($saldoProyectadoFinMesActual, 2, ',', '.'); ?>
                    </h3>
                    <small class="text-muted">Saldo proyectado a fin de mes</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-check me-2"></i>
                        Proyección: <?php echo $nombresMeses[$mesSiguiente] . ' ' . $anioSiguiente; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <h3 class="<?php echo $saldoProyectadoFinMesSiguiente < 0 ? 'text-danger' : 'text-success'; ?> mb-0">
                        $<?php echo number_format($saldoProyectadoFinMesSiguiente, 2, ',', '.'); ?>
                    </h3>
                    <small class="text-muted">Saldo proyectado a fin del próximo mes</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/views/layouts/footer.php'; ?>
