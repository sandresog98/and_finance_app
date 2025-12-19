<?php
/**
 * Página de debug para diagnosticar problemas
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h1>Debug - Módulo Bancos</h1>";

echo "<h2>1. Verificación de Sesión</h2>";
if (isset($_SESSION['and_finance_user'])) {
    echo "<p style='color: green;'>✓ Sesión activa</p>";
    echo "<pre>";
    print_r($_SESSION['and_finance_user']);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>✗ No hay sesión activa</p>";
}

echo "<h2>2. Verificación de Rutas</h2>";
$basePath = dirname(__DIR__, 3);
echo "<p>Base Path: <code>$basePath</code></p>";
echo "<p>Ruta .env esperada: <code>" . $basePath . "/.env</code></p>";

echo "<h2>3. Verificación de Archivos</h2>";
$files = [
    '.env' => $basePath . '/.env',
    'Database.php' => $basePath . '/utils/Database.php',
    'Env.php' => $basePath . '/utils/Env.php',
    'Bank.php' => __DIR__ . '/../models/Bank.php',
    'paths.php' => $basePath . '/admin/config/paths.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "<p style='color: green;'>✓ $name existe: <code>$path</code></p>";
    } else {
        echo "<p style='color: red;'>✗ $name NO existe: <code>$path</code></p>";
    }
}

echo "<h2>4. Verificación de .env</h2>";
$envPath = $basePath . '/.env';
if (file_exists($envPath)) {
    echo "<p style='color: green;'>✓ Archivo .env encontrado</p>";
    $envContent = file_get_contents($envPath);
    // Ocultar contraseñas
    $envContent = preg_replace('/DB_PASS=(.*)/', 'DB_PASS=***', $envContent);
    echo "<pre>$envContent</pre>";
} else {
    echo "<p style='color: red;'>✗ Archivo .env NO encontrado en: <code>$envPath</code></p>";
    echo "<p>Por favor, crea el archivo .env basándote en env.example</p>";
}

echo "<h2>5. Prueba de Conexión a Base de Datos</h2>";
try {
    require_once $basePath . '/utils/Database.php';
    require_once $basePath . '/utils/Env.php';
    
    use Utils\Database;
    use Utils\Env;
    
    $env = new Env($envPath);
    echo "<p style='color: green;'>✓ Clase Env cargada correctamente</p>";
    
    $db = new Database($env);
    echo "<p style='color: green;'>✓ Clase Database creada</p>";
    
    $conn = $db->getConnection();
    echo "<p style='color: green;'>✓ Conexión a base de datos establecida</p>";
    
    // Probar query
    $stmt = $conn->query("SELECT COUNT(*) as total FROM bancos_bancos");
    $result = $stmt->fetch();
    echo "<p style='color: green;'>✓ Query ejecutada. Total de bancos: " . $result['total'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>6. Prueba de Modelo Bank</h2>";
try {
    require_once __DIR__ . '/../models/Bank.php';
    use Admin\Modules\Bancos\Models\Bank;
    
    if (isset($conn)) {
        $bankModel = new Bank($conn);
        echo "<p style='color: green;'>✓ Modelo Bank creado</p>";
        
        $bancos = $bankModel->getAll();
        echo "<p style='color: green;'>✓ Método getAll() ejecutado. Total: " . count($bancos) . " bancos</p>";
    } else {
        echo "<p style='color: orange;'>⚠ No se puede probar el modelo sin conexión a BD</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Volver a Bancos</a></p>";
