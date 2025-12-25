<?php
/**
 * AND FINANCE APP - Ver/Descargar Archivo de Transacción
 * Endpoint seguro para servir comprobantes
 */

require_once __DIR__ . '/../../../config/paths.php';
require_once __DIR__ . '/../../../utils/session.php';

// Verificar autenticación
if (!isUserAuthenticated()) {
    http_response_code(403);
    die('No autorizado');
}

$userId = getCurrentUserId();
$archivoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$download = isset($_GET['download']) && $_GET['download'] == '1';

if ($archivoId <= 0) {
    http_response_code(400);
    die('ID de archivo inválido');
}

// Obtener información del archivo
$db = Database::getInstance();
$stmt = $db->prepare("
    SELECT ta.*, t.usuario_id 
    FROM transaccion_archivos ta
    JOIN transacciones t ON ta.transaccion_id = t.id
    WHERE ta.id = :id
");
$stmt->execute(['id' => $archivoId]);
$archivo = $stmt->fetch();

if (!$archivo) {
    http_response_code(404);
    die('Archivo no encontrado');
}

// Verificar que el archivo pertenece al usuario
if ($archivo['usuario_id'] != $userId) {
    http_response_code(403);
    die('No tienes permiso para ver este archivo');
}

// Construir ruta completa
$rutaCompleta = UPLOADS_PATH . '/' . $archivo['ruta'];

if (!file_exists($rutaCompleta)) {
    http_response_code(404);
    die('Archivo no encontrado en el servidor');
}

// Configurar headers
$mimeType = $archivo['mime_type'];
$nombreOriginal = $archivo['nombre_original'];

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($rutaCompleta));

if ($download) {
    // Forzar descarga
    header('Content-Disposition: attachment; filename="' . $nombreOriginal . '"');
} else {
    // Ver en navegador (inline)
    header('Content-Disposition: inline; filename="' . $nombreOriginal . '"');
}

// Evitar cache
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Enviar archivo
readfile($rutaCompleta);
exit;

