<?php
/**
 * API para eliminar cuenta
 */

session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['and_finance_user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once dirname(__DIR__, 5) . '/utils/Database.php';
require_once dirname(__DIR__, 5) . '/utils/Env.php';
require_once __DIR__ . '/../models/Account.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\Cuentas\Models\Account;

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
    $env = new Env(dirname(__DIR__, 5) . '/.env');
    $db = new Database($env);
    $accountModel = new Account($db->getConnection());
    
    $result = $accountModel->delete((int)$id, $userId);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('Delete account error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la cuenta']);
}
