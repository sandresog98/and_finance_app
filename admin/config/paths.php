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
    return dirname(getBaseUrl(), 1) . '/assets/' . $path;
}
