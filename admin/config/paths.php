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

// Función para obtener URL de archivos subidos (igual que en we_are_app)
function getFileUrl($filePath) {
    // Si está vacío, retornar vacío
    if (empty($filePath)) {
        return '';
    }
    
    // Si ya es una URL completa (http/https), usarla directamente
    if (strpos($filePath, 'http://') === 0 || strpos($filePath, 'https://') === 0) {
        return $filePath;
    }
    
    // Si ya empieza con /, es una ruta absoluta - usarla directamente
    if (strpos($filePath, '/') === 0) {
        return $filePath;
    }
    
    // Si la ruta ya incluye la base del proyecto, usarla directamente
    if (strpos($filePath, '/and_finance_app/') !== false) {
        return $filePath;
    }
    
    // Normalizar la ruta (eliminar / inicial si existe)
    $filePath = ltrim($filePath, '/');
    
    // Obtener la URL base del proyecto de múltiples formas
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Intentar encontrar el patrón /and_finance_app/ en SCRIPT_NAME
    $marker = '/and_finance_app/';
    $pos = strpos($scriptName, $marker);
    if ($pos !== false) {
        $baseProjectUrl = substr($scriptName, 0, $pos + strlen($marker));
        return $baseProjectUrl . $filePath;
    }
    
    // Intentar encontrar el patrón en REQUEST_URI
    $pos = strpos($requestUri, $marker);
    if ($pos !== false) {
        $baseProjectUrl = substr($requestUri, 0, $pos + strlen($marker));
        // Limpiar query string si existe
        $baseProjectUrl = strtok($baseProjectUrl, '?');
        return $baseProjectUrl . $filePath;
    }
    
    // Fallback: usar getBaseUrl() y construir desde ahí
    $baseUrl = getBaseUrl();
    // Si getBaseUrl() retorna algo con /admin/, removerlo
    if (strpos($baseUrl, '/admin/') !== false) {
        $baseUrl = str_replace('/admin/', '/', $baseUrl);
    }
    // Asegurar que termine con /
    $baseUrl = rtrim($baseUrl, '/') . '/';
    
    return $baseUrl . $filePath;
}
