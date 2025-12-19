<?php
/**
 * Script para resetear la base de datos
 * Ejecutar con cuidado: elimina todos los datos
 */

require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Env.php';

use Utils\Database;
use Utils\Env;

try {
    $env = new Env(__DIR__ . '/../.env');
    $db = new Database($env);
    $conn = $db->getConnection();
    
    // Leer el archivo DDL
    $ddl = file_get_contents(__DIR__ . '/ddl.sql');
    
    // Dividir en statements (separados por ;)
    $statements = array_filter(
        array_map('trim', explode(';', $ddl)),
        function($stmt) {
            return !empty($stmt) && 
                   !preg_match('/^(--|CREATE DATABASE|USE)/i', $stmt);
        }
    );
    
    // Ejecutar cada statement
    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            $conn->exec($statement);
        }
    }
    
    echo "Base de datos resetada exitosamente.\n";
    
} catch (Exception $e) {
    echo "Error al resetear la base de datos: " . $e->getMessage() . "\n";
    exit(1);
}
