<?php
/**
 * AND FINANCE APP - Database Configuration
 * Configuración de conexión a la base de datos
 */

// Cargar variables de entorno si existe el archivo
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        if (!defined($key)) {
            define($key, $value);
        }
    }
}

// Configuración por defecto si no existe .env
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', 'and_finance_app');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// Configuración de la aplicación
if (!defined('APP_NAME')) define('APP_NAME', 'AndFinance');

// Detectar APP_URL automáticamente
if (!defined('APP_URL')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Detectar la ruta base desde SCRIPT_NAME
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    // Buscar la posición de /admin/ o /ui/ para obtener la ruta base
    if (preg_match('#^(.*?)/(admin|ui)/#', $scriptName, $matches)) {
        $basePath = $matches[1];
    } elseif (preg_match('#^(.*?)/(admin|ui)$#', dirname($scriptName), $matches)) {
        $basePath = $matches[1];
    } else {
        // Fallback: obtener directorio padre del script
        $basePath = dirname(dirname($scriptName));
        if ($basePath === '/' || $basePath === '\\') {
            $basePath = '';
        }
    }
    
    define('APP_URL', $protocol . '://' . $host . $basePath);
}

if (!defined('APP_ENV')) define('APP_ENV', 'development');
if (!defined('APP_DEBUG')) define('APP_DEBUG', true);

// Configuración de archivos
if (!defined('UPLOAD_MAX_IMAGE_SIZE')) define('UPLOAD_MAX_IMAGE_SIZE', 5242880); // 5MB
if (!defined('UPLOAD_MAX_PDF_SIZE')) define('UPLOAD_MAX_PDF_SIZE', 10485760); // 10MB

/**
 * Clase Database - Singleton para conexión PDO
 */
class Database {
    private static ?PDO $instance = null;
    
    /**
     * Obtener instancia de conexión PDO
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
                
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    die("Error de conexión: " . $e->getMessage());
                } else {
                    die("Error de conexión a la base de datos");
                }
            }
        }
        
        return self::$instance;
    }
    
    /**
     * Cerrar conexión
     */
    public static function close(): void {
        self::$instance = null;
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
}

