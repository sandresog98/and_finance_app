<?php
/**
 * AND FINANCE APP - Session Management
 * Manejo de sesiones para la interfaz de administración
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_name('and_finance_admin');
    session_start();
}

/**
 * Verificar si el usuario está autenticado como admin
 */
function isAdminAuthenticated(): bool {
    return isset($_SESSION['admin_user']) && 
           isset($_SESSION['admin_user']['id']) && 
           $_SESSION['admin_user']['rol'] === 'admin';
}

/**
 * Requerir autenticación de admin
 */
function requireAdminAuth(): void {
    if (!isAdminAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Obtener datos del usuario admin actual
 */
function getCurrentAdmin(): ?array {
    return $_SESSION['admin_user'] ?? null;
}

/**
 * Establecer mensaje flash
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Obtener y limpiar mensaje flash
 */
function getFlashMessage(): ?array {
    $message = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);
    return $message;
}

/**
 * Destruir sesión de admin
 */
function destroyAdminSession(): void {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

