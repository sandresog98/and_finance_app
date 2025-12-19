<?php
/**
 * Clase para manejar variables de entorno desde archivo .env
 */

namespace Utils;

class Env {
    private array $vars = [];
    
    public function __construct(string $envPath) {
        if (!file_exists($envPath)) {
            throw new \Exception("Archivo .env no encontrado en: {$envPath}");
        }
        
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Separar clave y valor
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remover comillas si existen
                $value = trim($value, '"\'');
                
                $this->vars[$key] = $value;
            }
        }
    }
    
    public function get(string $key, ?string $default = null): ?string {
        return $this->vars[$key] ?? $default;
    }
    
    public function getAll(): array {
        return $this->vars;
    }
}
