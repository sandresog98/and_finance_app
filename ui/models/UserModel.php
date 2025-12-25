<?php
/**
 * AND FINANCE APP - User Model
 * Modelo para gestión de usuarios
 */

class UserModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener usuario por ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Obtener usuario por email
     */
    public function getByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Obtener usuario por Google ID
     */
    public function getByGoogleId(string $googleId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE google_id = :google_id");
        $stmt->execute(['google_id' => $googleId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Crear nuevo usuario
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (nombre, email, password, google_id, avatar, rol, estado)
            VALUES (:nombre, :email, :password, :google_id, :avatar, :rol, :estado)
        ");
        
        $stmt->execute([
            'nombre' => $data['nombre'],
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'google_id' => $data['google_id'] ?? null,
            'avatar' => $data['avatar'] ?? null,
            'rol' => $data['rol'] ?? 'usuario',
            'estado' => $data['estado'] ?? 1
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Actualizar usuario
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['nombre', 'email', 'password', 'google_id', 'avatar', 'estado', 'onboarding_completado'];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Actualizar último acceso
     */
    public function updateLastAccess(int $id): bool {
        $stmt = $this->db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Verificar si existe email
     */
    public function emailExists(string $email, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email";
        $params = ['email' => $email];
        
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Crear cuenta billetera por defecto para un usuario
     */
    public function crearCuentaDefault(int $userId): int {
        $stmt = $this->db->prepare("
            INSERT INTO cuentas (usuario_id, nombre, tipo, saldo_inicial, saldo_actual, es_predeterminada, icono, color)
            VALUES (:usuario_id, 'Billetera', 'efectivo', 0, 0, 1, 'bi-wallet2', '#9AD082')
        ");
        $stmt->execute(['usuario_id' => $userId]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Copiar categorías del sistema al usuario
     */
    public function copiarCategoriasDefault(int $userId): void {
        // Obtener categorías del sistema
        $categorias = $this->db->query("
            SELECT nombre, tipo, icono, color, orden FROM categorias WHERE es_sistema = 1
        ")->fetchAll();
        
        $stmt = $this->db->prepare("
            INSERT INTO categorias (usuario_id, nombre, tipo, icono, color, es_sistema, orden)
            VALUES (:usuario_id, :nombre, :tipo, :icono, :color, 0, :orden)
        ");
        
        foreach ($categorias as $cat) {
            $stmt->execute([
                'usuario_id' => $userId,
                'nombre' => $cat['nombre'],
                'tipo' => $cat['tipo'],
                'icono' => $cat['icono'],
                'color' => $cat['color'],
                'orden' => $cat['orden']
            ]);
        }
    }
    
    /**
     * Crear configuración por defecto para usuario
     */
    public function crearConfiguracionDefault(int $userId): void {
        $stmt = $this->db->prepare("
            INSERT INTO configuracion_usuario (usuario_id) VALUES (:usuario_id)
        ");
        $stmt->execute(['usuario_id' => $userId]);
    }
    
    /**
     * Ejecutar onboarding completo para nuevo usuario
     */
    public function ejecutarOnboarding(int $userId): void {
        // Crear cuenta billetera por defecto
        $this->crearCuentaDefault($userId);
        
        // Copiar categorías del sistema
        $this->copiarCategoriasDefault($userId);
        
        // Crear configuración por defecto
        $this->crearConfiguracionDefault($userId);
        
        // Marcar onboarding como completado
        $this->update($userId, ['onboarding_completado' => 1]);
    }
}

