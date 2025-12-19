<?php
/**
 * API para ejecutar gasto recurrente (crear transacción)
 */

// Desactivar output buffering y errores visibles
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// Limpiar cualquier output previo
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!isset($_SESSION['and_finance_user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once dirname(__DIR__, 4) . '/utils/Database.php';
require_once dirname(__DIR__, 4) . '/utils/Env.php';
require_once __DIR__ . '/../models/RecurringExpense.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\GastosRecurrentes\Models\RecurringExpense;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$gastoId = $data['gasto_id'] ?? null;
$mes = $data['mes'] ?? null;
$anio = $data['anio'] ?? null;
$userId = $_SESSION['and_finance_user']['id'];

if (!$gastoId || !$mes || !$anio) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $recurringModel = new RecurringExpense($db->getConnection());
    
    $result = $recurringModel->execute((int)$gastoId, $userId, (int)$mes, (int)$anio);
    
    if (!isset($result['success'])) {
        $result = ['success' => false, 'message' => 'Respuesta inválida del servidor'];
    }
    
    // Asegurar que no hay output antes del JSON
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (\PDOException $e) {
    error_log('Execute recurring expense PDO error: ' . $e->getMessage());
    error_log('SQL State: ' . $e->getCode());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos al ejecutar el gasto recurrente',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (\Exception $e) {
    error_log('Execute recurring expense error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error al ejecutar el gasto recurrente',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (\Error $e) {
    error_log('Execute recurring expense fatal error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error fatal al ejecutar el gasto recurrente',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
