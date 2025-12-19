<?php
/**
 * Página de Perfil - Cambiar Contraseña
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['and_finance_user'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/Env.php';
require_once __DIR__ . '/../../utils/Auth.php';

use Utils\Database;
use Utils\Env;
use Utils\Auth;

$currentPage = 'perfil';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    try {
        $env = new Env(__DIR__ . '/../../.env');
        $db = new Database($env);
        $auth = new Auth($db->getConnection());
        
        $userId = $_SESSION['and_finance_user']['id'];
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Todos los campos son requeridos';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Las contraseñas nuevas no coinciden';
        } else {
            $result = $auth->changePassword($userId, $currentPassword, $newPassword);
            
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    } catch (Exception $e) {
        $error = 'Error al procesar la solicitud';
        error_log('Change password error: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../views/layouts/header.php';
require_once __DIR__ . '/../views/layouts/sidebar.php';

$currentUser = $_SESSION['and_finance_user'];
?>

<div class="main-content">
    <div class="mb-4">
        <h1><i class="fas fa-user-circle me-2"></i>Mi Perfil</h1>
        <p class="text-muted">Gestiona tu información personal y seguridad</p>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información Personal</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted">Nombre Completo</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($currentUser['nombre_completo']); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Email</label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Rol</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-primary"><?php echo htmlspecialchars(ucfirst($currentUser['rol'])); ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Cambiar Contraseña</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="changePasswordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Contraseña Actual</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nueva Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                            </div>
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Cambiar Contraseña
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Las contraseñas nuevas no coinciden');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('La contraseña debe tener al menos 6 caracteres');
        return false;
    }
});
</script>

<?php require_once __DIR__ . '/../views/layouts/footer.php'; ?>
