<?php
/**
 * API para cambiar contraseña - UI
 */

session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['and_finance_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once dirname(__DIR__, 2) . '/utils/Database.php';
require_once dirname(__DIR__, 2) . '/utils/Env.php';
require_once dirname(__DIR__, 2) . '/utils/Auth.php';

use Utils\Database;
use Utils\Env;
use Utils\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $env = new Env(dirname(__DIR__, 2) . '/.env');
    $db = new Database($env);
    $auth = new Auth($db->getConnection());
    
    $userId = $_SESSION['and_finance_user']['id'];
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
        exit;
    }
    
    $result = $auth->changePassword($userId, $currentPassword, $newPassword);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('Change password API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
}
