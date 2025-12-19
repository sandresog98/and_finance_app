<?php
/**
 * API para eliminar transacción
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
require_once __DIR__ . '/../models/Transaction.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\Transacciones\Models\Transaction;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$userId = $_SESSION['and_finance_user']['id'];

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID requerido']);
    exit;
}

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $transactionModel = new Transaction($db->getConnection());
    
    $result = $transactionModel->delete((int)$id, $userId);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('Delete transaction error: ' . $e->getMessage());
    error_log('Delete transaction error trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la transacción: ' . $e->getMessage()]);
}
