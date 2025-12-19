<?php
/**
 * Script para crear un usuario administrador
 * Ejecutar una vez desde la línea de comandos o navegador
 */

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Env.php';

use Utils\Database;
use Utils\Env;

// Permitir ejecución desde navegador o CLI
if (php_sapi_name() !== 'cli') {
    // Verificar que se ejecute solo en desarrollo o con token de seguridad
    $token = $_GET['token'] ?? '';
    if ($token !== 'create_admin_2024') {
        die('Acceso denegado. Usa: ?token=create_admin_2024');
    }
}

try {
    $env = new Env(__DIR__ . '/../.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    // Datos del administrador
    $email = 'admin@andfinance.com';
    $password = 'admin123'; // Cambiar después del primer login
    $nombreCompleto = 'Administrador';
    
    // Verificar si ya existe
    $stmt = $conn->prepare("SELECT id FROM control_usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Actualizar a admin si ya existe
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE control_usuarios 
            SET password = ?, rol = 'admin', estado_activo = TRUE 
            WHERE email = ?
        ");
        $stmt->execute([$passwordHash, $email]);
        echo "Usuario admin actualizado exitosamente.\n";
        echo "Email: {$email}\n";
        echo "Password: {$password}\n";
    } else {
        // Crear nuevo admin
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO control_usuarios (email, password, nombre_completo, rol, estado_activo)
            VALUES (?, ?, ?, 'admin', TRUE)
        ");
        $stmt->execute([$email, $passwordHash, $nombreCompleto]);
        
        $userId = $conn->lastInsertId();
        
        // Crear cuenta por defecto
        $stmt = $conn->prepare("
            INSERT INTO cuentas_cuentas (usuario_id, nombre, banco_id, tipo, saldo_inicial, saldo_actual)
            VALUES (?, 'Billetera', NULL, 'efectivo', 0.00, 0.00)
        ");
        $stmt->execute([$userId]);
        
        // Crear categorías predeterminadas
        $stmt = $conn->query("
            SELECT nombre, tipo, icono, color
            FROM categorias_categorias
            WHERE es_predeterminada = TRUE AND usuario_id IS NULL
        ");
        $defaultCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $insertStmt = $conn->prepare("
            INSERT INTO categorias_categorias (usuario_id, nombre, tipo, icono, color, es_predeterminada)
            VALUES (?, ?, ?, ?, ?, FALSE)
        ");
        
        foreach ($defaultCategories as $cat) {
            $insertStmt->execute([
                $userId,
                $cat['nombre'],
                $cat['tipo'],
                $cat['icono'],
                $cat['color']
            ]);
        }
        
        echo "Usuario administrador creado exitosamente.\n";
        echo "Email: {$email}\n";
        echo "Password: {$password}\n";
        echo "\nIMPORTANTE: Cambia la contraseña después del primer login.\n";
    }
    
    if (php_sapi_name() !== 'cli') {
        echo "<br><br><a href='index.php'>Ir al panel de administración</a>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log('Create admin error: ' . $e->getMessage());
    exit(1);
}
