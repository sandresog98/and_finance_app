<?php
/**
 * AND FINANCE APP - User Login/Register Page
 * Con verificación de email y recuperación de contraseña
 */

ob_start();
require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/controllers/AuthController.php';

// Si ya está logueado, redirigir al dashboard
if (isUserAuthenticated()) {
    ob_end_clean();
    header('Location: index.php');
    exit;
}

$auth = new AuthController();

// Modos: login, register, verify, forgot, reset
$mode = $_GET['mode'] ?? 'login';
$error = '';
$success = '';
$errors = [];
$formData = [
    'nombre' => '',
    'email' => $_GET['email'] ?? ''
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    
    switch ($action) {
        case 'login':
            $formData['email'] = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $result = $auth->login($formData['email'], $password);
            
            if ($result['success']) {
                ob_end_clean();
                header('Location: index.php');
                exit;
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'register':
            $formData['nombre'] = trim($_POST['nombre'] ?? '');
            $formData['email'] = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            $result = $auth->iniciarRegistro($formData['nombre'], $formData['email'], $password, $confirmPassword);
            
            if ($result['success']) {
                // Redirigir a verificación
                ob_end_clean();
                header('Location: login.php?mode=verify&email=' . urlencode($formData['email']) . '&type=registro');
                exit;
            } else {
                $errors = $result['errors'] ?? [];
                $error = $result['message'];
                $mode = 'register';
            }
            break;
            
        case 'verify':
            $email = trim($_POST['email'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $tipo = $_POST['type'] ?? 'registro';
            
            if ($tipo === 'registro') {
                $result = $auth->verificarYRegistrar($email, $codigo);
                
                if ($result['success']) {
                    ob_end_clean();
                    header('Location: index.php');
                    exit;
                } else {
                    $error = $result['message'];
                    $mode = 'verify';
                    $formData['email'] = $email;
                }
            } else { // recuperacion
                $result = $auth->verificarCodigoRecuperacion($email, $codigo);
                
                if ($result['success']) {
                    // Redirigir a cambio de contraseña
                    ob_end_clean();
                    header('Location: login.php?mode=reset&email=' . urlencode($email) . '&code=' . urlencode($codigo));
                    exit;
                } else {
                    $error = $result['message'];
                    $mode = 'verify';
                    $formData['email'] = $email;
                }
            }
            break;
            
        case 'forgot':
            $formData['email'] = trim($_POST['email'] ?? '');
            
            $result = $auth->solicitarRecuperacion($formData['email']);
            
            if ($result['success']) {
                ob_end_clean();
                header('Location: login.php?mode=verify&email=' . urlencode($formData['email']) . '&type=recuperacion');
                exit;
            } else {
                $error = $result['message'];
                $mode = 'forgot';
            }
            break;
            
        case 'reset':
            $email = trim($_POST['email'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            $result = $auth->cambiarPassword($email, $codigo, $newPassword, $confirmPassword);
            
            if ($result['success']) {
                $success = '¡Contraseña actualizada! Ya puedes iniciar sesión.';
                $mode = 'login';
            } else {
                $error = $result['message'];
                $mode = 'reset';
                $formData['email'] = $email;
            }
            break;
            
        case 'resend':
            $email = trim($_POST['email'] ?? '');
            $tipo = $_POST['type'] ?? 'registro';
            
            $result = $auth->reenviarCodigo($email, $tipo);
            
            if ($result['success']) {
                $success = 'Código reenviado correctamente';
            } else {
                $error = $result['message'];
            }
            $mode = 'verify';
            $formData['email'] = $email;
            break;
    }
}

// Obtener parámetros para verify y reset
$verifyType = $_GET['type'] ?? 'registro';
$resetCode = $_GET['code'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
        switch($mode) {
            case 'register': echo 'Crear Cuenta'; break;
            case 'verify': echo 'Verificar Email'; break;
            case 'forgot': echo 'Recuperar Contraseña'; break;
            case 'reset': echo 'Nueva Contraseña'; break;
            default: echo 'Iniciar Sesión';
        }
    ?> - <?= APP_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= assetUrl('favicons/favicon.ico') ?>">
    
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #55A5C8;
            --secondary-green: #9AD082;
            --tertiary-gray: #B1BCBF;
            --dark-blue: #35719E;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e9f4f8 100%);
        }
        
        .auth-container {
            min-height: 100vh;
            display: flex;
        }
        
        .auth-sidebar {
            width: 45%;
            background: linear-gradient(135deg, var(--dark-blue) 0%, var(--primary-blue) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .auth-sidebar::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }
        
        .auth-sidebar::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
        }
        
        .auth-sidebar-content {
            position: relative;
            z-index: 1;
            text-align: center;
            color: white;
            max-width: 400px;
        }
        
        .auth-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .auth-logo i {
            font-size: 40px;
            color: var(--dark-blue);
        }
        
        .auth-sidebar h1 {
            font-weight: 800;
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .auth-sidebar p {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.7;
        }
        
        .features-list {
            text-align: left;
            margin-top: 40px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .feature-icon {
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .feature-text {
            font-size: 14px;
            font-weight: 500;
        }
        
        .auth-form-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        
        .auth-form-container {
            width: 100%;
            max-width: 440px;
        }
        
        .auth-form-header {
            margin-bottom: 35px;
        }
        
        .auth-form-header h2 {
            font-weight: 800;
            font-size: 28px;
            color: var(--dark-blue);
            margin-bottom: 8px;
        }
        
        .auth-form-header p {
            color: var(--tertiary-gray);
            font-size: 15px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(85, 165, 200, 0.15);
        }
        
        .input-group-text {
            background: transparent;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: var(--tertiary-gray);
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: var(--primary-blue);
        }
        
        .btn-auth {
            background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(53, 113, 158, 0.35);
            color: white;
        }
        
        .btn-google {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            font-size: 15px;
            color: #333;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-google:hover {
            background: #f8f9fa;
            border-color: #dee2e6;
        }
        
        .btn-google img {
            width: 20px;
            height: 20px;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e9ecef;
        }
        
        .divider span {
            padding: 0 15px;
            color: var(--tertiary-gray);
            font-size: 13px;
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 30px;
        }
        
        .auth-footer a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
        }
        
        .auth-footer a:hover {
            color: var(--dark-blue);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--tertiary-gray);
            z-index: 10;
        }
        
        .alert {
            border: none;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .alert ul {
            margin: 0;
            padding-left: 20px;
        }
        
        /* Estilos para código de verificación */
        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 25px 0;
        }
        
        .code-input {
            width: 50px;
            height: 60px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--dark-blue);
            transition: all 0.3s ease;
        }
        
        .code-input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(85, 165, 200, 0.15);
            outline: none;
        }
        
        .resend-link {
            color: var(--tertiary-gray);
            font-size: 14px;
        }
        
        .resend-link a {
            color: var(--primary-blue);
            font-weight: 600;
            cursor: pointer;
        }
        
        .resend-link a:hover {
            color: var(--dark-blue);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--tertiary-gray);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            color: var(--primary-blue);
        }
        
        .verify-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-blue)20, var(--secondary-green)20);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .verify-icon i {
            font-size: 36px;
            color: var(--dark-blue);
        }
        
        @media (max-width: 992px) {
            .auth-sidebar {
                display: none;
            }
            
            .auth-form-section {
                padding: 30px 20px;
            }
        }
        
        @media (max-width: 576px) {
            .auth-form-header h2 {
                font-size: 24px;
            }
            
            .mobile-logo {
                display: flex;
                flex-direction: column;
                align-items: center;
                margin-bottom: 30px;
            }
            
            .mobile-logo .auth-logo {
                width: 60px;
                height: 60px;
                margin-bottom: 15px;
            }
            
            .mobile-logo .auth-logo i {
                font-size: 28px;
            }
            
            .mobile-logo h1 {
                font-weight: 800;
                font-size: 24px;
                color: var(--dark-blue);
            }
            
            .code-input {
                width: 45px;
                height: 55px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <!-- Sidebar -->
        <div class="auth-sidebar">
            <div class="auth-sidebar-content">
                <div class="auth-logo">
                    <i class="bi bi-wallet2"></i>
                </div>
                <h1><?= APP_NAME ?></h1>
                <p>Tu compañero inteligente para gestionar tus finanzas personales de forma simple y efectiva.</p>
                
                <div class="features-list">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <span class="feature-text">Control total de tus ingresos y gastos</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <span class="feature-text">Programa gastos recurrentes</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-pie-chart"></i>
                        </div>
                        <span class="feature-text">Reportes y estadísticas detalladas</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="bi bi-bank"></i>
                        </div>
                        <span class="feature-text">Múltiples cuentas y bancos</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Section -->
        <div class="auth-form-section">
            <div class="auth-form-container">
                <!-- Mobile Logo -->
                <div class="mobile-logo d-lg-none">
                    <div class="auth-logo">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <h1><?= APP_NAME ?></h1>
                </div>
                
                <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <div>
                        <?= htmlspecialchars($error) ?>
                        <?php if (!empty($errors)): ?>
                        <ul class="mt-2 mb-0">
                            <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($mode === 'verify'): ?>
                <!-- ============ VERIFICAR CÓDIGO ============ -->
                <a href="?mode=<?= $verifyType === 'registro' ? 'register' : 'login' ?>" class="back-link">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                
                <div class="text-center">
                    <div class="verify-icon">
                        <i class="bi bi-envelope-check"></i>
                    </div>
                </div>
                
                <div class="auth-form-header text-center">
                    <h2>Verificar correo</h2>
                    <p>Ingresa el código de 6 dígitos que enviamos a<br><strong><?= htmlspecialchars($formData['email']) ?></strong></p>
                </div>
                
                <form method="POST" id="verifyForm">
                    <input type="hidden" name="action" value="verify">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($formData['email']) ?>">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($verifyType) ?>">
                    <input type="hidden" name="codigo" id="codigoCompleto">
                    
                    <div class="code-inputs">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autofocus>
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    </div>
                    
                    <button type="submit" class="btn btn-auth">
                        <i class="bi bi-check-lg me-2"></i>
                        Verificar
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p class="resend-link">
                        ¿No recibiste el código? 
                        <a href="#" id="resendBtn">Reenviar código</a>
                    </p>
                </div>
                
                <!-- Form oculto para reenviar -->
                <form method="POST" id="resendForm" style="display:none;">
                    <input type="hidden" name="action" value="resend">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($formData['email']) ?>">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($verifyType) ?>">
                </form>
                
                <?php elseif ($mode === 'forgot'): ?>
                <!-- ============ OLVIDÉ MI CONTRASEÑA ============ -->
                <a href="?mode=login" class="back-link">
                    <i class="bi bi-arrow-left"></i> Volver al login
                </a>
                
                <div class="text-center">
                    <div class="verify-icon">
                        <i class="bi bi-key"></i>
                    </div>
                </div>
                
                <div class="auth-form-header text-center">
                    <h2>Recuperar contraseña</h2>
                    <p>Ingresa tu correo y te enviaremos un código para restablecer tu contraseña</p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="forgot">
                    
                    <div class="mb-4">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($formData['email']) ?>"
                                   placeholder="correo@ejemplo.com"
                                   required 
                                   autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-auth">
                        <i class="bi bi-send me-2"></i>
                        Enviar código
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p class="text-muted mb-0">
                        ¿Recordaste tu contraseña? 
                        <a href="?mode=login">Iniciar sesión</a>
                    </p>
                </div>
                
                <?php elseif ($mode === 'reset'): ?>
                <!-- ============ NUEVA CONTRASEÑA ============ -->
                <div class="text-center">
                    <div class="verify-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                </div>
                
                <div class="auth-form-header text-center">
                    <h2>Nueva contraseña</h2>
                    <p>Crea una nueva contraseña segura para tu cuenta</p>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($formData['email']) ?>">
                    <input type="hidden" name="codigo" value="<?= htmlspecialchars($resetCode) ?>">
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva contraseña</label>
                        <div class="input-group position-relative">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   id="new_password" 
                                   name="new_password" 
                                   placeholder="Mínimo 6 caracteres"
                                   required
                                   minlength="6"
                                   autofocus>
                            <span class="password-toggle" onclick="togglePassword('new_password')">
                                <i class="bi bi-eye" id="toggleIcon-new_password"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                        <div class="input-group position-relative">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Repite tu contraseña"
                                   required>
                            <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="bi bi-eye" id="toggleIcon-confirm_password"></i>
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-auth">
                        <i class="bi bi-check-lg me-2"></i>
                        Guardar contraseña
                    </button>
                </form>
                
                <?php elseif ($mode === 'register'): ?>
                <!-- ============ REGISTRO ============ -->
                <div class="auth-form-header">
                    <h2>Crear Cuenta</h2>
                    <p>Completa tus datos para comenzar</p>
                </div>
                
                <!-- Google Login Button -->
                <button type="button" class="btn-google" id="googleLogin">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continuar con Google
                </button>
                
                <div class="divider">
                    <span>o</span>
                </div>
                
                <form method="POST" action="?mode=register" autocomplete="off">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre completo</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-person"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="nombre" 
                                   name="nombre" 
                                   value="<?= htmlspecialchars($formData['nombre']) ?>"
                                   placeholder="Tu nombre"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($formData['email']) ?>"
                                   placeholder="correo@ejemplo.com"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group position-relative">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Mínimo 6 caracteres"
                                   required
                                   minlength="6">
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i class="bi bi-eye" id="toggleIcon-password"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                        <div class="input-group position-relative">
                            <span class="input-group-text">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Repite tu contraseña"
                                   required>
                            <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="bi bi-eye" id="toggleIcon-confirm_password"></i>
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-auth">
                        <i class="bi bi-person-plus me-2"></i>
                        Crear mi cuenta
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p class="text-muted mb-0">
                        ¿Ya tienes cuenta? 
                        <a href="?mode=login">Inicia sesión</a>
                    </p>
                </div>
                
                <?php else: ?>
                <!-- ============ LOGIN ============ -->
                <div class="auth-form-header">
                    <h2>Bienvenido de nuevo</h2>
                    <p>Ingresa a tu cuenta para continuar</p>
                </div>
                
                <!-- Google Login Button -->
                <button type="button" class="btn-google" id="googleLogin">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continuar con Google
                </button>
                
                <div class="divider">
                    <span>o</span>
                </div>
                
                <form method="POST" action="" autocomplete="off">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($formData['email']) ?>"
                                   placeholder="correo@ejemplo.com"
                                   required 
                                   autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group position-relative">
                            <span class="input-group-text">
                                <i class="bi bi-lock"></i>
                            </span>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Tu contraseña"
                                   required>
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i class="bi bi-eye" id="toggleIcon-password"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4 text-end">
                        <a href="?mode=forgot" class="text-decoration-none" style="color: var(--primary-blue); font-size: 14px; font-weight: 500;">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn btn-auth">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Iniciar Sesión
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p class="text-muted mb-0">
                        ¿No tienes cuenta? 
                        <a href="?mode=register">Regístrate gratis</a>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById('toggleIcon-' + inputId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }
        
        // Manejar inputs de código de verificación
        document.querySelectorAll('.code-input').forEach((input, index, inputs) => {
            input.addEventListener('input', function(e) {
                // Solo permitir números
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Auto-avanzar al siguiente input
                if (this.value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
                
                // Actualizar código completo
                updateFullCode();
            });
            
            input.addEventListener('keydown', function(e) {
                // Retroceder con backspace
                if (e.key === 'Backspace' && !this.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
            
            // Permitir pegar código completo
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                
                pastedData.split('').forEach((char, i) => {
                    if (inputs[i]) {
                        inputs[i].value = char;
                    }
                });
                
                if (pastedData.length > 0) {
                    inputs[Math.min(pastedData.length, inputs.length - 1)].focus();
                }
                
                updateFullCode();
            });
        });
        
        function updateFullCode() {
            const inputs = document.querySelectorAll('.code-input');
            const code = Array.from(inputs).map(i => i.value).join('');
            const codigoInput = document.getElementById('codigoCompleto');
            if (codigoInput) {
                codigoInput.value = code;
            }
        }
        
        // Reenviar código
        const resendBtn = document.getElementById('resendBtn');
        const resendForm = document.getElementById('resendForm');
        
        if (resendBtn && resendForm) {
            resendBtn.addEventListener('click', function(e) {
                e.preventDefault();
                resendForm.submit();
            });
        }
        
        // Google Login placeholder
        document.querySelectorAll('#googleLogin').forEach(btn => {
            btn.addEventListener('click', function() {
                alert('La autenticación con Google requiere configurar las credenciales de Google Cloud Console en el archivo .env');
            });
        });
    </script>
</body>
</html>
