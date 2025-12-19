<?php
/**
 * Configuración de rutas para la interfaz Admin
 */

// Definir la ruta base de la aplicación
define('BASE_PATH', dirname(__DIR__, 2));

// Función para obtener la URL base absoluta de Admin (server-relative)
function getBaseUrl() {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $marker = '/admin/';
    $pos = strpos($scriptName, $marker);
    if ($pos !== false) {
        return substr($scriptName, 0, $pos + strlen($marker));
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

// Función para obtener URL de assets
function getAssetUrl($path) {
    // Obtener la ruta del script actual
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    // Buscar el patrón /admin/ en la ruta
    $marker = '/admin/';
    $pos = strpos($scriptName, $marker);
    if ($pos !== false) {
        // Extraer la parte antes de /admin/ y agregar /assets/
        $basePath = substr($scriptName, 0, $pos);
        return $basePath . '/assets/' . ltrim($path, '/');
    }
    
    // Fallback: intentar con getBaseUrl
    $baseUrl = getBaseUrl();
    $baseUrl = rtrim($baseUrl, '/');
    $parts = explode('/', $baseUrl);
    array_pop($parts); // Eliminar el último segmento (admin)
    return implode('/', $parts) . '/assets/' . ltrim($path, '/');
}

// Función para obtener URL de archivos subidos (usa file_proxy)
function getFileUrl($filePath) {
    // Normalizar la ruta - remover /uploads/ si existe al inicio
    $filePath = ltrim($filePath, '/');
    if (strpos($filePath, 'uploads/') === 0) {
        $filePath = substr($filePath, 8); // Remover "uploads/"
    }
    
    // Obtener la URL base del proyecto
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $marker = '/and_finance_app/';
    $pos = strpos($scriptName, $marker);
    $baseProjectUrl = $pos !== false ? substr($scriptName, 0, $pos + strlen($marker)) : '/and_finance_app/';
    
    return $baseProjectUrl . 'file_proxy.php?file=' . urlencode($filePath);
}
