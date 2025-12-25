<?php
/**
 * AND FINANCE APP - Obtener Archivos de Transacción
 * Endpoint para listar comprobantes de una transacción
 */

require_once __DIR__ . '/../../../config/paths.php';
require_once __DIR__ . '/../../../utils/session.php';

header('Content-Type: application/json');

try {
    // Verificar autenticación
    if (!isUserAuthenticated()) {
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }

    $userId = getCurrentUserId();
    $transaccionId = isset($_GET['transaccion_id']) ? (int)$_GET['transaccion_id'] : 0;

    if ($transaccionId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de transacción inválido']);
        exit;
    }

    // Verificar que la transacción pertenece al usuario
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id FROM transacciones WHERE id = :id AND usuario_id = :usuario_id");
    $stmt->execute(['id' => $transaccionId, 'usuario_id' => $userId]);

    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'No tienes permiso para ver esta transacción']);
        exit;
    }

    // Obtener archivos
    $stmt = $db->prepare("
        SELECT id, nombre_original, tipo_archivo, tamano
        FROM transaccion_archivos
        WHERE transaccion_id = :transaccion_id
        ORDER BY fecha_creacion DESC
    ");
    $stmt->execute(['transaccion_id' => $transaccionId]);
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Construir URL base para la API
    $baseUrl = UI_URL . '/modules/transacciones/api/ver_archivo.php';

    // Agregar URLs
    foreach ($archivos as &$archivo) {
        $archivo['url_ver'] = $baseUrl . '?id=' . $archivo['id'];
        $archivo['url_descargar'] = $archivo['url_ver'] . '&download=1';
        $archivo['tamano_kb'] = round($archivo['tamano'] / 1024, 1);
    }

    echo json_encode(['archivos' => $archivos]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}

