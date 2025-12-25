<?php
/**
 * AND FINANCE APP - Reset Database Script
 * Este script reinicia la base de datos a su estado inicial
 * Ejecutar desde la línea de comandos: php reset_db.php
 */

// Cargar configuración
require_once __DIR__ . '/../config/database.php';

echo "=========================================\n";
echo "   AND FINANCE APP - Reset Database\n";
echo "=========================================\n\n";

try {
    // Conectar sin seleccionar base de datos
    $pdo = new PDO(
        "mysql:host=" . DB_HOST,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "[1/5] Conectado al servidor de base de datos...\n";
    
    // Eliminar base de datos si existe
    $pdo->exec("DROP DATABASE IF EXISTS " . DB_NAME);
    echo "[2/5] Base de datos anterior eliminada...\n";
    
    // Crear nueva base de datos
    $pdo->exec("CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[3/5] Base de datos creada...\n";
    
    // Cerrar conexión actual y reconectar a la nueva BD
    $pdo = null;
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Leer DDL
    $ddl = file_get_contents(__DIR__ . '/ddl.sql');
    
    // Eliminar líneas de CREATE DATABASE y USE
    $ddl = preg_replace('/^CREATE\s+DATABASE.*$/mi', '', $ddl);
    $ddl = preg_replace('/^USE\s+\S+;.*$/mi', '', $ddl);
    
    // Eliminar comentarios de línea completa (pero mantener COMMENT dentro de CREATE TABLE)
    $lines = explode("\n", $ddl);
    $cleanLines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Solo eliminar líneas que comienzan con -- (comentarios puros)
        if (empty($trimmed) || strpos($trimmed, '--') === 0) {
            // Mantener líneas vacías para separación
            $cleanLines[] = '';
            continue;
        }
        $cleanLines[] = $line;
    }
    $ddl = implode("\n", $cleanLines);
    
    // Separar statements correctamente usando regex que detecta ; al final de línea
    // pero no dentro de cadenas
    $statements = [];
    $currentStatement = '';
    
    $lines = explode("\n", $ddl);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed)) continue;
        
        $currentStatement .= $line . "\n";
        
        // Detectar fin de statement (línea que termina en ;)
        if (preg_match('/;[\s]*$/', $trimmed)) {
            $stmt = trim($currentStatement);
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
            $currentStatement = '';
        }
    }
    
    // Agregar último statement si existe
    if (!empty(trim($currentStatement))) {
        $statements[] = trim($currentStatement);
    }
    
    echo "[4/5] Ejecutando DDL (" . count($statements) . " statements)...\n";
    
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $i => $statement) {
        if (empty(trim($statement))) continue;
        
        try {
            $pdo->exec($statement);
            $executed++;
            
            // Mostrar progreso
            if ($executed % 5 == 0) {
                echo ".";
            }
        } catch (PDOException $e) {
            $errors++;
            if ($errors <= 5) {
                echo "\n[!] Error en statement " . ($i + 1) . ": " . substr($e->getMessage(), 0, 100) . "\n";
            }
        }
    }
    
    echo "\n\n[5/5] DDL ejecutado: $executed exitosos, $errors errores\n\n";
    
    // Mostrar estadísticas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas creadas: " . count($tables) . "\n";
    
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "  - $table: $count registros\n";
        } catch (Exception $e) {
            echo "  - $table: [error al contar]\n";
        }
    }
    
    echo "\n=========================================\n";
    echo "   Reset completado exitosamente!\n";
    echo "=========================================\n";
    echo "\nCredenciales de acceso:\n";
    echo "  Email: admin@andfinance.com\n";
    echo "  Password: Admin123!\n\n";
    
} catch (PDOException $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
