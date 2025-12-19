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
    
    // Obtener la URL base del proyecto de múltiples formas
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $marker = '/and_finance_app/';
    
    // Verificar si el servidor tiene el patrón /and_finance_app/ en su estructura
    $hasMarkerInServer = (strpos($scriptName, $marker) !== false) || (strpos($requestUri, $marker) !== false);
    
    // Si la ruta contiene /and_finance_app/ pero el servidor no lo tiene, removerlo
    if (strpos($filePath, $marker) !== false && !$hasMarkerInServer) {
        // Remover /and_finance_app/ de la ruta
        $filePath = str_replace($marker, '/', $filePath);
        $filePath = ltrim($filePath, '/');
    }
    
    // Si ya empieza con / y el servidor tiene el patrón, verificar si es correcto
    if (strpos($filePath, '/') === 0) {
        // Si el servidor tiene el patrón y la ruta también, usarla directamente
        if ($hasMarkerInServer && strpos($filePath, $marker) !== false) {
            return $filePath;
        }
        // Si el servidor no tiene el patrón pero la ruta sí, ya la removimos arriba
        // Si la ruta no tiene el patrón, construir la base correcta
        if (!$hasMarkerInServer) {
            // Construir base sin /and_finance_app/
            $baseUrl = getBaseUrl();
            if (strpos($baseUrl, '/admin/') !== false) {
                $baseUrl = str_replace('/admin/', '/', $baseUrl);
            }
            $baseUrl = rtrim($baseUrl, '/') . '/';
            return $baseUrl . ltrim($filePath, '/');
        }
    }
    
    // Si la ruta ya incluye la base del proyecto y el servidor tiene el patrón, usarla directamente
    if ($hasMarkerInServer && strpos($filePath, $marker) !== false) {
        return $filePath;
    }
    
    // Normalizar la ruta (eliminar / inicial si existe)
    $filePath = ltrim($filePath, '/');
    
    // Intentar encontrar el patrón /and_finance_app/ en SCRIPT_NAME
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
