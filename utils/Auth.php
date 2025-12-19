<?php
/**
 * Clase para manejar autenticación de usuarios
 */

namespace Utils;

use PDO;

class Auth {
    private PDO $conn;
    private string $sessionKey = 'and_finance_user';
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Autenticar usuario con email y contraseña
     */
    public function login(string $email, string $password): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, email, password, nombre_completo, rol, estado_activo, google_id, avatar_url
                FROM control_usuarios
                WHERE email = ? AND estado_activo = TRUE
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Credenciales incorrectas'];
            }
            
            // Si tiene contraseña, verificar
            if (!empty($user['password'])) {
                if (!password_verify($password, $user['password'])) {
                    return ['success' => false, 'message' => 'Credenciales incorrectas'];
                }
            } else {
                // Usuario registrado solo con Google
                return ['success' => false, 'message' => 'Este usuario debe iniciar sesión con Google'];
            }
            
            // Guardar en sesión (sin password)
            unset($user['password']);
            $_SESSION[$this->sessionKey] = $user;
            
            return ['success' => true, 'user' => $user];
            
        } catch (\PDOException $e) {
            error_log('Auth::login error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al iniciar sesión'];
        }
    }
    
    /**
     * Autenticar o registrar usuario con Google
     */
    public function loginWithGoogle(array $googleUser): array {
        try {
            $googleId = $googleUser['id'];
            $email = $googleUser['email'];
            $nombre = $googleUser['name'] ?? $email;
            $avatar = $googleUser['picture'] ?? null;
            
            // Buscar usuario existente
            $stmt = $this->conn->prepare("
                SELECT id, email, nombre_completo, rol, estado_activo, google_id, avatar_url
                FROM control_usuarios
                WHERE google_id = ? OR email = ?
                LIMIT 1
            ");
            $stmt->execute([$googleId, $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Actualizar datos de Google si cambió
                if ($user['google_id'] !== $googleId || $user['avatar_url'] !== $avatar) {
                    $updateStmt = $this->conn->prepare("
                        UPDATE control_usuarios 
                        SET google_id = ?, avatar_url = ?, fecha_actualizacion = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$googleId, $avatar, $user['id']]);
                    $user['google_id'] = $googleId;
                    $user['avatar_url'] = $avatar;
                }
            } else {
                // Crear nuevo usuario
                $insertStmt = $this->conn->prepare("
                    INSERT INTO control_usuarios (email, nombre_completo, google_id, avatar_url, rol)
                    VALUES (?, ?, ?, ?, 'usuario')
                ");
                $insertStmt->execute([$email, $nombre, $googleId, $avatar]);
                
                $userId = $this->conn->lastInsertId();
                
                // Crear cuenta por defecto "Billetera"
                $this->createDefaultAccount($userId);
                
                // Crear categorías predeterminadas para el usuario
                $this->createDefaultCategories($userId);
                
                // Obtener usuario creado
                $stmt = $this->conn->prepare("
                    SELECT id, email, nombre_completo, rol, estado_activo, google_id, avatar_url
                    FROM control_usuarios
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Guardar en sesión
            $_SESSION[$this->sessionKey] = $user;
            
            return ['success' => true, 'user' => $user];
            
        } catch (\PDOException $e) {
            error_log('Auth::loginWithGoogle error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al autenticar con Google'];
        }
    }
    
    /**
     * Registrar nuevo usuario con email y contraseña
     */
    public function register(string $email, string $password, string $nombreCompleto): array {
        try {
            // Verificar si el email ya existe
            $stmt = $this->conn->prepare("SELECT id FROM control_usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'El email ya está registrado'];
            }
            
            // Crear usuario
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("
                INSERT INTO control_usuarios (email, password, nombre_completo, rol)
                VALUES (?, ?, ?, 'usuario')
            ");
            $stmt->execute([$email, $passwordHash, $nombreCompleto]);
            
            $userId = $this->conn->lastInsertId();
            
            // Crear cuenta por defecto "Billetera"
            $this->createDefaultAccount($userId);
            
            // Crear categorías predeterminadas para el usuario
            $this->createDefaultCategories($userId);
            
            // Obtener usuario creado
            $stmt = $this->conn->prepare("
                SELECT id, email, nombre_completo, rol, estado_activo, google_id, avatar_url
                FROM control_usuarios
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Iniciar sesión automáticamente
            $_SESSION[$this->sessionKey] = $user;
            
            return ['success' => true, 'user' => $user];
            
        } catch (\PDOException $e) {
            error_log('Auth::register error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al registrar usuario'];
        }
    }
    
    /**
     * Crear cuenta por defecto "Billetera" para nuevo usuario
     */
    private function createDefaultAccount(int $userId): void {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO cuentas_cuentas (usuario_id, nombre, banco_id, tipo, saldo_inicial, saldo_actual)
                VALUES (?, 'Billetera', NULL, 'efectivo', 0.00, 0.00)
            ");
            $stmt->execute([$userId]);
        } catch (\PDOException $e) {
            error_log('Auth::createDefaultAccount error: ' . $e->getMessage());
        }
    }
    
    /**
     * Crear categorías predeterminadas para nuevo usuario (copiar del sistema)
     */
    private function createDefaultCategories(int $userId): void {
        try {
            // Obtener categorías predeterminadas del sistema
            $stmt = $this->conn->query("
                SELECT nombre, tipo, icono, color
                FROM categorias_categorias
                WHERE es_predeterminada = TRUE AND usuario_id IS NULL
            ");
            $defaultCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Insertar para el usuario
            $insertStmt = $this->conn->prepare("
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
        } catch (\PDOException $e) {
            error_log('Auth::createDefaultCategories error: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated(): bool {
        return isset($_SESSION[$this->sessionKey]) && 
               isset($_SESSION[$this->sessionKey]['id']);
    }
    
    /**
     * Obtener usuario actual
     */
    public function getUser(): ?array {
        return $_SESSION[$this->sessionKey] ?? null;
    }
    
    /**
     * Obtener ID del usuario actual
     */
    public function getUserId(): ?int {
        return $_SESSION[$this->sessionKey]['id'] ?? null;
    }
    
    /**
     * Verificar si el usuario tiene un rol específico
     */
    public function hasRole(string $role): bool {
        $user = $this->getUser();
        return $user && $user['rol'] === $role;
    }
    
    /**
     * Cerrar sesión
     */
    public function logout(): void {
        unset($_SESSION[$this->sessionKey]);
        session_destroy();
    }
}
