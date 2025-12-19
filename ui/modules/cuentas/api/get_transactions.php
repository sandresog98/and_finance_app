<?php
/**
 * API: Obtener últimas transacciones de una cuenta
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['and_finance_user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once dirname(__DIR__, 4) . '/utils/Database.php';
require_once dirname(__DIR__, 4) . '/utils/Env.php';
require_once dirname(__DIR__, 4) . '/ui/modules/transacciones/models/Transaction.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\Transacciones\Models\Transaction;

header('Content-Type: application/json');

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    $currentUser = $_SESSION['and_finance_user'];
    $userId = $currentUser['id'];
    
    // Obtener cuenta_id del POST
    $input = json_decode(file_get_contents('php://input'), true);
    $accountId = isset($input['cuenta_id']) ? (int)$input['cuenta_id'] : 0;
    
    if ($accountId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de cuenta inválido']);
        exit;
    }
    
    // Verificar que la cuenta pertenece al usuario
    $stmt = $conn->prepare("SELECT id FROM cuentas_cuentas WHERE id = ? AND usuario_id = ? AND estado_activo = TRUE LIMIT 1");
    $stmt->execute([$accountId, $userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cuenta no encontrada']);
        exit;
    }
    
    // Obtener últimas 10 transacciones
    $transactionModel = new Transaction($conn);
    $transacciones = $transactionModel->getLastByAccount($accountId, $userId, 10);
    
    echo json_encode([
        'success' => true,
        'transacciones' => $transacciones
    ]);
    
} catch (Exception $e) {
    error_log('Get transactions error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener las transacciones']);
}
