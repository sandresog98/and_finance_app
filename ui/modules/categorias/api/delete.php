<?php
/**
 * API para eliminar categoría
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
require_once __DIR__ . '/../models/Category.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\Categorias\Models\Category;

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
    $categoryModel = new Category($db->getConnection());
    
    $result = $categoryModel->delete((int)$id, $userId);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('Delete category error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la categoría']);
}
