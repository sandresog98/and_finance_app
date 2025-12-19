<?php
/**
 * API para ignorar gasto recurrente
 */

session_start();
header('Content-Type: application/json');

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
    
    $result = $recurringModel->ignore((int)$gastoId, $userId, (int)$mes, (int)$anio);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('Ignore recurring expense error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al ignorar el gasto recurrente']);
}
