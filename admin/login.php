<?php
/**
 * Página de Login para Administradores
 */

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Env.php';
require_once __DIR__ . '/../utils/Auth.php';

use Utils\Database;
use Utils\Env;
use Utils\Auth;

session_start();

// Si ya está autenticado como admin, redirigir
if (isset($_SESSION['and_finance_user']) && $_SESSION['and_finance_user']['rol'] === 'admin') {
    header('Location: index.php');
    exit;
}

$errorParam = $_GET['error'] ?? '';
$error = '';
if ($errorParam === 'no_admin') {
    $error = 'No tienes permisos de administrador';
}

$info = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $env = new Env(__DIR__ . '/../.env');
        $db = new Database($env);
        $auth = new Auth($db->getConnection());
        
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Email y contraseña son requeridos';
        } else {
            $result = $auth->login($email, $password);
            
            if ($result['success']) {
                // Verificar que sea admin
                if ($result['user']['rol'] === 'admin') {
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Este usuario no tiene permisos de administrador';
                    // Cerrar sesión si no es admin
                    $auth->logout();
                }
            } else {
                $error = $result['message'];
            }
        }
    } catch (Exception $e) {
        $error = 'Error al procesar la solicitud';
        error_log('Admin login error: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/config/paths.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrador - And Finance App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo getAssetUrl('css/common.css'); ?>" rel="stylesheet">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--third-color) 100%);
        }
        .login-card {
            max-width: 400px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="card shadow-lg">
                <div class="card-header text-center py-4">
                    <i class="fas fa-shield-alt fa-3x mb-3"></i>
                    <h3 class="mb-0">Panel de Administración</h3>
                    <small class="text-white-50">And Finance App</small>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($info): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($info); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required autofocus>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="../ui/login.php" class="text-muted text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i>Volver al login de usuarios
                        </a>
                    </div>
                    
                    <div class="alert alert-warning mt-3 mb-0" role="alert">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Nota:</strong> Solo usuarios con rol de administrador pueden acceder.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
