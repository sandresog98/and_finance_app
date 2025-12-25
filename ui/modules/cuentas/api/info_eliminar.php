<?php
/**
 * API - Obtener información para eliminar cuenta
 */

// Capturar errores
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../../config/database.php';
    require_once __DIR__ . '/../../../utils/session.php';
    require_once __DIR__ . '/../models/CuentaModel.php';
    
    if (!isUserAuthenticated()) {
        echo json_encode(['error' => 'No autorizado - sesión no iniciada']);
        exit;
    }
    
    $userId = getCurrentUserId();
    $cuentaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$cuentaId) {
        echo json_encode(['error' => 'ID de cuenta requerido']);
        exit;
    }
    
    $cuentaModel = new CuentaModel();
    $info = $cuentaModel->getInfoParaEliminar($cuentaId);
    
    if (!$info['existe']) {
        echo json_encode(['error' => 'Cuenta no encontrada']);
        exit;
    }
    
    // Verificar que la cuenta pertenece al usuario
    if ($info['cuenta']['usuario_id'] != $userId) {
        echo json_encode(['error' => 'No autorizado - cuenta no pertenece al usuario']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'nombre' => $info['cuenta']['nombre'],
        'saldo' => $info['cuenta']['saldo_actual'] ?? 0,
        'transacciones_normales' => $info['transacciones_normales'],
        'transferencias' => $info['transferencias'],
        'total_transacciones' => $info['total_transacciones'],
        'antiguedad' => $info['antiguedad'],
        'fecha_creacion' => $info['fecha_creacion'],
        'color' => $info['cuenta']['color'] ?? '#55A5C8',
        'icono' => $info['cuenta']['icono'] ?? 'bi-wallet2'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['error' => 'Error PHP: ' . $e->getMessage()]);
}

