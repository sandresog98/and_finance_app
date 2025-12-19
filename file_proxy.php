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

// Intentar encontrar el archivo con diferentes extensiones si no tiene extensión
$filePath = realpath($basePath . $file);
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
if (!$filePath || !$realBasePath || strpos($filePath, $realBasePath) !== 0) {
    http_response_code(404);
    error_log("File proxy error: File not found. Requested: " . $_GET['file'] . ", Processed: $file, Base path: $basePath, Real base: " . ($realBasePath ?: 'null') . ", File path: " . ($filePath ?: 'null'));
    
    // Intentar listar archivos en el directorio para debugging
    $testDir = $basePath . dirname($file);
    if (is_dir($testDir)) {
        $files = scandir($testDir);
        error_log("Files in directory $testDir: " . implode(', ', $files));
    } else {
        error_log("Directory does not exist: $testDir");
    }
    
    // Intentar con la ruta original sin procesar
    $originalFile = urldecode($_GET['file'] ?? '');
    $originalFile = ltrim($originalFile, '/');
    if (strpos($originalFile, 'uploads/') === 0) {
        $originalFile = substr($originalFile, 8);
    }
    $originalPath = realpath($basePath . $originalFile);
    if ($originalPath && is_file($originalPath) && strpos($originalPath, $realBasePath) === 0) {
        $filePath = $originalPath;
        error_log("File found using original path: $originalPath");
    } else {
        die('Archivo no encontrado: ' . htmlspecialchars($file) . ' (Original: ' . htmlspecialchars($_GET['file'] ?? '') . ')');
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
