<?php
/**
 * Clase para manejar la conexión a la base de datos
 */

namespace Utils;

use PDO;
use PDOException;

class Database {
    private ?PDO $conn = null;
    private Env $env;
    
    public function __construct(Env $env) {
        $this->env = $env;
    }
    
    public function getConnection(): PDO {
        if ($this->conn === null) {
            try {
                $host = $this->env->get('DB_HOST', 'localhost');
                $dbname = $this->env->get('DB_NAME', 'and_finance_db');
                $username = $this->env->get('DB_USER', 'root');
                $password = $this->env->get('DB_PASS', '');
                
                $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                $this->conn = new PDO($dsn, $username, $password, $options);
                
            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                throw new \Exception("Error de conexión a la base de datos");
            }
        }
        
        return $this->conn;
    }
}
