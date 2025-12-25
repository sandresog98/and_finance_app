<?php
/**
 * AND FINANCE APP - UI Paths Configuration
 * Configuración de rutas y URLs para la interfaz de usuario
 */

// Prevenir acceso directo
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../../config/database.php';
}

// Rutas del sistema de archivos
define('UI_ROOT', dirname(__DIR__));
define('UI_MODULES', UI_ROOT . '/modules');
define('UI_VIEWS', UI_ROOT . '/views');
define('UI_CONTROLLERS', UI_ROOT . '/controllers');

// Rutas de la aplicación (si no están definidas)
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(UI_ROOT));
}
if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', APP_ROOT . '/assets');
}
if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', APP_ROOT . '/uploads');
}

// URLs base - Detección automática
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detectar la ruta base automáticamente desde SCRIPT_NAME
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
// Buscar /ui/ en la ruta para extraer la base
if (preg_match('#^(.*?)/ui(?:/|$)#', $scriptName, $matches)) {
    $appBasePath = $matches[1];
} else {
    // Fallback
    $appBasePath = dirname(dirname($scriptName));
    if ($appBasePath === '/' || $appBasePath === '\\') {
        $appBasePath = '';
    }
}

define('UI_URL', $protocol . '://' . $host . $appBasePath . '/ui');

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', $protocol . '://' . $host . $appBasePath);
}
if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', APP_BASE_URL . '/assets');
}
if (!defined('UPLOADS_URL')) {
    define('UPLOADS_URL', APP_BASE_URL . '/uploads');
}

/**
 * Función helper para generar URLs del UI
 */
function uiUrl(string $path = ''): string {
    return UI_URL . '/' . ltrim($path, '/');
}

/**
 * Función helper para assets
 */
if (!function_exists('assetUrl')) {
    function assetUrl(string $path = ''): string {
        return ASSETS_URL . '/' . ltrim($path, '/');
    }
}

/**
 * Función helper para URLs de módulos
 */
function uiModuleUrl(string $module, string $page = '', array $params = []): string {
    $url = UI_URL . '/index.php?module=' . $module;
    if ($page) {
        $url .= '&page=' . $page;
    }
    foreach ($params as $key => $value) {
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }
    return $url;
}

/**
 * Función para formatear moneda
 */
function formatMoney(float $amount, string $currency = 'COP'): string {
    return '$' . number_format($amount, 0, ',', '.');
}

/**
 * Función para formatear fecha
 */
function formatDate(string $date, string $format = 'd/m/Y'): string {
    return date($format, strtotime($date));
}

