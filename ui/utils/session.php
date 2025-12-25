<?php
/**
 * AND FINANCE APP - Session Management
 * Manejo de sesiones para la interfaz de usuario
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_name('and_finance_user');
    session_start();
}

/**
 * Verificar si el usuario está autenticado
 */
function isUserAuthenticated(): bool {
    return isset($_SESSION['user']) && 
           isset($_SESSION['user']['id']);
}

/**
 * Requerir autenticación de usuario
 */
function requireUserAuth(): void {
    if (!isUserAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Obtener datos del usuario actual
 */
function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Obtener ID del usuario actual
 */
function getCurrentUserId(): ?int {
    return $_SESSION['user']['id'] ?? null;
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
 * Destruir sesión de usuario
 */
function destroyUserSession(): void {
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

