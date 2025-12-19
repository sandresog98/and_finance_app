<?php
/**
 * API: Ajustar Saldo de Cuenta
 */

// Activar manejo de errores para capturar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

header('Content-Type: application/json');

// Cargar archivos necesarios (fuera del try para que los use statements funcionen)
$basePath = dirname(__DIR__, 4);

require_once $basePath . '/utils/Database.php';
require_once $basePath . '/utils/Env.php';
require_once $basePath . '/ui/modules/transacciones/models/Transaction.php';
require_once dirname(__DIR__, 1) . '/models/Account.php';

// Los use statements deben estar al nivel superior, después de los require_once
use Utils\Database;
use Utils\Env;
use UI\Modules\Transacciones\Models\Transaction;
use UI\Modules\Cuentas\Models\Account;

try {
    // Verificar autenticación
    if (!isset($_SESSION['and_finance_user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
    
    $currentUser = $_SESSION['and_finance_user'];
    $userId = $currentUser['id'];
    
    // Obtener datos del POST
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        throw new Exception('No se recibieron datos');
    }
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
    }
    
    if (!isset($input['cuenta_id']) || !isset($input['nuevo_saldo'])) {
        throw new Exception('Datos incompletos. Se requiere cuenta_id y nuevo_saldo');
    }
    
    $cuentaId = (int)$input['cuenta_id'];
    $nuevoSaldo = (float)$input['nuevo_saldo'];
    $comentario = trim($input['comentario'] ?? '');
    
    if ($cuentaId <= 0) {
        throw new Exception('ID de cuenta inválido');
    }
    
    if ($nuevoSaldo < 0) {
        throw new Exception('El saldo no puede ser negativo');
    }
    
    // Conectar a la base de datos
    $env = new Env($basePath . '/.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    $accountModel = new Account($conn);
    $transactionModel = new Transaction($conn);
    
    // Verificar que la cuenta pertenece al usuario
    $cuenta = $accountModel->getById($cuentaId, $userId);
    if (!$cuenta) {
        throw new Exception('Cuenta no encontrada o no pertenece al usuario');
    }
    
    $saldoActual = (float)$cuenta['saldo_actual'];
    $diferencia = $nuevoSaldo - $saldoActual;
    
    // Si no hay diferencia, no hacer nada
    if (abs($diferencia) < 0.01) {
        echo json_encode(['success' => true, 'message' => 'El saldo ya es el mismo']);
        exit;
    }
    
    // Crear transacción de ajuste
    // Guardar información del ajuste en el comentario para referencia
    $comentarioAjuste = $comentario ?: 'Ajuste de saldo';
    if ($diferencia > 0) {
        $comentarioAjuste .= " (Ajuste positivo: +$" . number_format($diferencia, 2, ',', '.') . ")";
    } else {
        $comentarioAjuste .= " (Ajuste negativo: -$" . number_format(abs($diferencia), 2, ',', '.') . ")";
    }
    $comentarioAjuste .= " - Saldo establecido: $" . number_format($nuevoSaldo, 2, ',', '.');
    
    $data = [
        'usuario_id' => $userId,
        'cuenta_id' => $cuentaId,
        'tipo' => 'ajuste',
        'monto' => abs($diferencia), // Guardamos el valor absoluto
        'fecha' => date('Y-m-d'),
        'comentario' => $comentarioAjuste,
        'nuevo_saldo' => $nuevoSaldo // Pasamos el nuevo saldo directamente
    ];
    
    // createAdjustment maneja la diferencia y actualiza el saldo
    $result = $transactionModel->createAdjustment($data, $diferencia);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true, 
            'message' => 'Saldo ajustado correctamente',
            'diferencia' => $diferencia
        ]);
    } else {
        throw new Exception($result['message'] ?? 'Error al crear el ajuste');
    }
    
} catch (Exception $e) {
    error_log('Adjust balance error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    error_log('Adjust balance fatal error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error fatal: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
