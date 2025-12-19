<?php
/**
 * Configuración de rutas para la interfaz Admin
 */

// Definir la ruta base de la aplicación
define('BASE_PATH', dirname(__DIR__, 2));

// Cargar configuración de entorno si está disponible
$appUrl = null;
try {
    require_once BASE_PATH . '/utils/Env.php';
    $env = new \Utils\Env(BASE_PATH . '/.env');
    $appUrl = $env->get('APP_URL');
} catch (Exception $e) {
    // Si no se puede cargar .env, continuar con detección automática
}

// Función para obtener la URL base absoluta de Admin
function getBaseUrl() {
    global $appUrl;
    
    // Si APP_URL está definido, usarlo como base
    if ($appUrl) {
        return rtrim($appUrl, '/') . '/admin/';
    }
    
    // Fallback: detección automática desde SCRIPT_NAME
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $marker = '/admin/';
    $pos = strpos($scriptName, $marker);
    if ($pos !== false) {
        return substr($scriptName, 0, $pos + strlen($marker));
    }
    
    // Si no se encuentra, construir desde REQUEST_URI
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $pos = strpos($requestUri, '/admin/');
    if ($pos !== false) {
        return substr($requestUri, 0, $pos + strlen('/admin/'));
    }
    
    return './';
}

// Función para obtener rutas absolutas en el filesystem
function getAbsolutePath($relativePath) {
    return BASE_PATH . '/' . $relativePath;
}

// Función para obtener rutas de redirección
function getRedirectPath($path) {
    return getBaseUrl() . $path;
}

// Función para obtener URL de assets (absoluta con protocolo si APP_URL está definido)
function getAssetUrl($path) {
    global $appUrl;
    
    // Si APP_URL está definido, construir URL absoluta
    if ($appUrl) {
        return rtrim($appUrl, '/') . '/assets/' . $path;
    }
    
    // Fallback: ruta relativa al servidor
    return dirname(getBaseUrl(), 1) . '/assets/' . $path;
}
