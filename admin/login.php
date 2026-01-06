<?php
/**
 * AND FINANCE APP - Admin Login Page
 */

require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/controllers/AuthController.php';

// Si ya está logueado, redirigir al dashboard
if (isAdminAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $auth = new AuthController();
    $result = $auth->login($email, $password);
    
    if ($result['success']) {
        header('Location: index.php');
        exit;
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?= APP_NAME ?></title>
    
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
            --gradient-start: #35719E;
            --gradient-end: #55A5C8;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo-container {
            display: inline-block;
            background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
            padding: 15px 25px;
            border-radius: 16px;
            margin-bottom: 15px;
            box-shadow: 0 8px 25px rgba(53, 113, 158, 0.3);
        }
        
        .login-logo img {
            max-width: 180px;
            height: auto;
        }
        
        .login-logo i {
            font-size: 48px;
            color: var(--primary-blue);
            background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-logo h1 {
            font-weight: 800;
            font-size: 28px;
            color: var(--dark-blue);
            margin: 10px 0 5px;
        }
        
        .login-logo span {
            display: inline-block;
            background: linear-gradient(135deg, var(--secondary-green), var(--primary-blue));
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 8px;
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
            border-radius: 0;
        }
        
        .input-group .form-control:not(:last-child) {
            border-right: none;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: var(--primary-blue);
        }
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(53, 113, 158, 0.4);
            color: white;
        }
        
        .btn-login:active {
            transform: translateY(0);
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
        
        .password-toggle {
            border: 2px solid #e9ecef;
            border-left: none;
            border-radius: 0 12px 12px 0 !important;
            color: var(--tertiary-gray);
            background: white;
            padding: 0 15px;
        }
        
        .password-toggle:hover {
            color: var(--dark-blue);
            background: #f8f9fa;
            border-color: #e9ecef;
        }
        
        .password-toggle:focus {
            box-shadow: none;
            border-color: var(--primary-blue);
        }
        
        .input-group:focus-within .password-toggle {
            border-color: var(--primary-blue);
        }
        
        .copyright {
            text-align: center;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
        }
        
        @media (max-width: 576px) {
            .login-card {
                padding: 30px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <div class="login-logo-container">
                    <img src="<?= assetUrl('img/logo-horizontal-white.png') ?>" alt="<?= APP_NAME ?>">
                </div>
                <br>
                <span>Panel Administrativo</span>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" autocomplete="off">
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope me-1"></i> Correo electrónico
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-at"></i>
                        </span>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?= htmlspecialchars($email) ?>"
                               placeholder="admin@ejemplo.com"
                               required 
                               autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock me-1"></i> Contraseña
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-key"></i>
                        </span>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="••••••••"
                               required>
                        <button type="button" class="btn btn-outline-secondary password-toggle" onclick="togglePassword()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Iniciar Sesión
                </button>
            </form>
        </div>
        
        <p class="copyright">
            <i class="bi bi-shield-lock me-1"></i>
            <?= APP_NAME ?> &copy; <?= date('Y') ?>
        </p>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
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
    </script>
</body>
</html>

