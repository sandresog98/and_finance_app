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

// Remover el prefijo /uploads/ si existe
$file = ltrim($file, '/');
if (strpos($file, 'uploads/') === 0) {
    $file = substr($file, 8); // Remover "uploads/"
}

// Sanitizar ruta para prevenir directory traversal
$fileParts = explode('/', $file);
$sanitizedParts = [];
foreach ($fileParts as $part) {
    // Remover caracteres peligrosos
    $part = preg_replace('/[^a-zA-Z0-9._-]/', '', $part);
    if (!empty($part) && $part !== '.' && $part !== '..') {
        $sanitizedParts[] = $part;
    }
}

$file = implode('/', $sanitizedParts);
$basePath = __DIR__ . '/uploads/';
$filePath = realpath($basePath . $file);

// Verificar que el archivo existe y está dentro del directorio uploads
$realBasePath = realpath($basePath);
if (!$filePath || !$realBasePath || strpos($filePath, $realBasePath) !== 0) {
    http_response_code(404);
    die('Archivo no encontrado');
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
