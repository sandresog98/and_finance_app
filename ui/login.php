<?php
/**
 * Página de Login - And Finance App
 */

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Env.php';
require_once __DIR__ . '/../utils/Auth.php';

use Utils\Database;
use Utils\Env;
use Utils\Auth;

session_start();

// Si ya está autenticado, redirigir
if (isset($_SESSION['and_finance_user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $env = new Env(__DIR__ . '/../.env');
        $db = new Database($env);
        $auth = new Auth($db->getConnection());
        
        if ($_POST['action'] === 'login') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            $result = $auth->login($email, $password);
            
            if ($result['success']) {
                header('Location: index.php');
                exit;
            } else {
                $error = $result['message'];
            }
        } elseif ($_POST['action'] === 'register') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $nombreCompleto = $_POST['nombre_completo'] ?? '';
            
            if (empty($email) || empty($password) || empty($nombreCompleto)) {
                $error = 'Todos los campos son requeridos';
            } else {
                $result = $auth->register($email, $password, $nombreCompleto);
                
                if ($result['success']) {
                    header('Location: index.php');
                    exit;
                } else {
                    $error = $result['message'];
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Error al procesar la solicitud';
        error_log('Login error: ' . $e->getMessage());
    }
}

// Procesar callback de Google OAuth
if (isset($_GET['code'])) {
    try {
        $env = new Env(__DIR__ . '/../.env');
        $db = new Database($env);
        $auth = new Auth($db->getConnection());
        
        // Intercambiar código por token (simplificado - en producción usar librería oficial)
        $clientId = $env->get('GOOGLE_CLIENT_ID');
        $clientSecret = $env->get('GOOGLE_CLIENT_SECRET');
        $redirectUri = $env->get('GOOGLE_REDIRECT_URI');
        
        // Aquí deberías usar la librería oficial de Google OAuth
        // Por ahora, esto es un placeholder
        $error = 'Autenticación con Google en desarrollo';
        
    } catch (Exception $e) {
        $error = 'Error al autenticar con Google';
        error_log('Google OAuth error: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/config/paths.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - And Finance App</title>
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
                    <i class="fas fa-wallet fa-3x mb-3"></i>
                    <h3 class="mb-0">And Finance App</h3>
                </div>
                <div class="card-body p-4">
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
                    
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">
                                <i class="fas fa-sign-in-alt me-1"></i>Iniciar Sesión
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">
                                <i class="fas fa-user-plus me-1"></i>Registrarse
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <!-- Login Tab -->
                        <div class="tab-pane fade show active" id="login" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="login">
                                
                                <div class="mb-3">
                                    <label for="login_email" class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="login_email" name="email" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="login_password" class="form-label">Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="login_password" name="password" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                </button>
                            </form>
                            
                            <div class="text-center">
                                <p class="text-muted mb-2">O continúa con</p>
                                <a href="#" class="btn btn-outline-danger w-100" onclick="alert('Funcionalidad en desarrollo'); return false;">
                                    <i class="fab fa-google me-2"></i>Google
                                </a>
                            </div>
                        </div>
                        
                        <!-- Register Tab -->
                        <div class="tab-pane fade" id="register" role="tabpanel">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="register">
                                
                                <div class="mb-3">
                                    <label for="register_nombre" class="form-label">Nombre Completo</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="register_nombre" name="nombre_completo" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="register_email" class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="register_email" name="email" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="register_password" class="form-label">Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="register_password" name="password" required minlength="6">
                                    </div>
                                    <small class="text-muted">Mínimo 6 caracteres</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-user-plus me-2"></i>Registrarse
                                </button>
                            </form>
                            
                            <div class="text-center">
                                <p class="text-muted mb-2">O regístrate con</p>
                                <a href="#" class="btn btn-outline-danger w-100" onclick="alert('Funcionalidad en desarrollo'); return false;">
                                    <i class="fab fa-google me-2"></i>Google
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
