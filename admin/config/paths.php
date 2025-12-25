<?php
/**
 * AND FINANCE APP - Admin Paths Configuration
 * Configuración de rutas y URLs para la interfaz de administración
 */

// Prevenir acceso directo
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../../config/database.php';
}

// Rutas del sistema de archivos
define('ADMIN_ROOT', dirname(__DIR__));
define('ADMIN_MODULES', ADMIN_ROOT . '/modules');
define('ADMIN_VIEWS', ADMIN_ROOT . '/views');
define('ADMIN_CONTROLLERS', ADMIN_ROOT . '/controllers');

// Rutas de la aplicación
define('APP_ROOT', dirname(ADMIN_ROOT));
define('ASSETS_PATH', APP_ROOT . '/assets');
define('UPLOADS_PATH', APP_ROOT . '/uploads');

// URLs base - Detección automática
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detectar la ruta base automáticamente desde SCRIPT_NAME
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
// Buscar /admin/ en la ruta para extraer la base
if (preg_match('#^(.*?)/admin(?:/|$)#', $scriptName, $matches)) {
    $appBasePath = $matches[1];
} else {
    // Fallback
    $appBasePath = dirname(dirname($scriptName));
    if ($appBasePath === '/' || $appBasePath === '\\') {
        $appBasePath = '';
    }
}

define('ADMIN_URL', $protocol . '://' . $host . $appBasePath . '/admin');
define('APP_BASE_URL', $protocol . '://' . $host . $appBasePath);
define('ASSETS_URL', APP_BASE_URL . '/assets');
define('UPLOADS_URL', APP_BASE_URL . '/uploads');

/**
 * Función helper para generar URLs del admin
 */
function adminUrl(string $path = ''): string {
    return ADMIN_URL . '/' . ltrim($path, '/');
}

/**
 * Función helper para assets
 */
function assetUrl(string $path = ''): string {
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Función helper para URLs de módulos
 */
function moduleUrl(string $module, string $page = ''): string {
    $url = ADMIN_URL . '/index.php?module=' . $module;
    if ($page) {
        $url .= '&page=' . $page;
    }
    return $url;
}

