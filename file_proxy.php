<?php
/**
 * Proxy para servir archivos protegidos desde uploads/
 * Verifica autenticación antes de servir el archivo
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['and_finance_user'])) {
    http_response_code(403);
    die('Acceso denegado');
}

$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    die('Archivo no especificado');
}

// Decodificar URL
$file = urldecode($file);

// Remover el prefijo /uploads/ si existe
$file = ltrim($file, '/');
if (strpos($file, 'uploads/') === 0) {
    $file = substr($file, 8); // Remover "uploads/"
}

// Sanitizar ruta para prevenir directory traversal
$fileParts = explode('/', $file);
$sanitizedParts = [];
foreach ($fileParts as $part) {
    // Remover caracteres peligrosos pero mantener punto para extensiones
    $part = preg_replace('/[^a-zA-Z0-9._-]/', '', $part);
    if (!empty($part) && $part !== '.' && $part !== '..') {
        $sanitizedParts[] = $part;
    }
}

$file = implode('/', $sanitizedParts);
$basePath = __DIR__ . '/uploads/';

// Verificar que el directorio base existe
if (!is_dir($basePath)) {
    error_log("File proxy error: Base directory does not exist: $basePath");
    error_log("File proxy error: __DIR__ is: " . __DIR__);
    http_response_code(500);
    die('Error de configuración del servidor');
}

// Logging para debugging (solo en desarrollo o si hay error)
$debug = ($_GET['debug'] ?? '0') === '1';
if ($debug) {
    error_log("File proxy - Requested file: " . ($_GET['file'] ?? ''));
    error_log("File proxy - Processed file: $file");
    error_log("File proxy - Base path: $basePath");
    error_log("File proxy - Full path: " . $basePath . $file);
}

// Intentar encontrar el archivo
$fullPath = $basePath . $file;
$filePath = realpath($fullPath);

// Verificar si el archivo existe directamente
if (!$filePath || !is_file($filePath)) {
    // Si el archivo no tiene extensión, intentar con extensiones comunes
    $pathInfo = pathinfo($file);
    if (empty($pathInfo['extension'])) {
        $possibleExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        $baseName = $pathInfo['basename'];
        $dir = !empty($pathInfo['dirname']) && $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . '/' : '';
        
        foreach ($possibleExtensions as $ext) {
            $testPath = realpath($basePath . $dir . $baseName . '.' . $ext);
            if ($testPath && is_file($testPath)) {
                $filePath = $testPath;
                break;
            }
        }
    }
}

// Verificar que el archivo existe y está dentro del directorio uploads
$realBasePath = realpath($basePath);
if (!$realBasePath) {
    http_response_code(500);
    error_log("File proxy error: Cannot resolve base path: $basePath");
    die('Error de configuración del servidor');
}

if (!$filePath || !is_file($filePath) || strpos($filePath, $realBasePath) !== 0) {
    http_response_code(404);
    
    // Logging detallado para debugging
    error_log("File proxy error: File not found");
    error_log("  - Requested: " . ($_GET['file'] ?? ''));
    error_log("  - Processed: $file");
    error_log("  - Base path: $basePath");
    error_log("  - Real base: $realBasePath");
    error_log("  - Full path tried: $fullPath");
    error_log("  - File path result: " . ($filePath ?: 'null'));
    
    // Verificar si el directorio del archivo existe
    $fileDir = $basePath . dirname($file);
    if (is_dir($fileDir)) {
        $files = @scandir($fileDir);
        if ($files) {
            error_log("  - Files in directory $fileDir: " . implode(', ', array_slice($files, 2))); // Excluir . y ..
        }
    } else {
        error_log("  - Directory does not exist: $fileDir");
    }
    
    // Intentar con la ruta original sin procesar (fallback)
    $originalFile = urldecode($_GET['file'] ?? '');
    $originalFile = ltrim($originalFile, '/');
    if (strpos($originalFile, 'uploads/') === 0) {
        $originalFile = substr($originalFile, 8);
    }
    $originalPath = realpath($basePath . $originalFile);
    if ($originalPath && is_file($originalPath) && strpos($originalPath, $realBasePath) === 0) {
        $filePath = $originalPath;
        error_log("  - File found using original path: $originalPath");
    } else {
        die('Archivo no encontrado: ' . htmlspecialchars($file));
    }
}

// Verificar que el archivo existe
if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    die('Archivo no encontrado');
}

// Obtener tipo MIME
$mimeType = mime_content_type($filePath);
if (!$mimeType) {
    $mimeType = 'application/octet-stream';
}

// Enviar archivo
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Cache-Control: private, max-age=3600');

readfile($filePath);
exit;
