<?php
/**
 * AND FINANCE APP - Admin Auth Controller
 * Controlador de autenticación para administradores
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/session.php';

class AuthController {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Intentar login de administrador
     */
    public function login(string $email, string $password): array {
        $response = ['success' => false, 'message' => ''];
        
        // Validar campos
        if (empty($email) || empty($password)) {
            $response['message'] = 'Por favor complete todos los campos';
            return $response;
        }
        
        // Validar formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'El formato del email no es válido';
            return $response;
        }
        
        try {
            // Buscar usuario
            $stmt = $this->db->prepare("
                SELECT id, nombre, email, password, rol, estado, avatar
                FROM usuarios 
                WHERE email = :email AND rol = 'admin'
                LIMIT 1
            ");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            
            // Verificar si existe
            if (!$user) {
                $response['message'] = 'Credenciales incorrectas';
                return $response;
            }
            
            // Verificar estado
            if ($user['estado'] != 1) {
                $response['message'] = 'Esta cuenta está desactivada';
                return $response;
            }
            
            // Verificar contraseña
            if (!password_verify($password, $user['password'])) {
                $response['message'] = 'Credenciales incorrectas';
                return $response;
            }
            
            // Actualizar último acceso
            $stmt = $this->db->prepare("
                UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id
            ");
            $stmt->execute(['id' => $user['id']]);
            
            // Crear sesión
            $_SESSION['admin_user'] = [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'email' => $user['email'],
                'rol' => $user['rol'],
                'avatar' => $user['avatar']
            ];
            
            $response['success'] = true;
            $response['message'] = 'Login exitoso';
            
        } catch (PDOException $e) {
            $response['message'] = APP_DEBUG ? $e->getMessage() : 'Error al procesar la solicitud';
        }
        
        return $response;
    }
    
    /**
     * Cerrar sesión
     */
    public function logout(): void {
        destroyAdminSession();
    }
    
    /**
     * Verificar si hay sesión activa de admin
     */
    public function checkSession(): bool {
        return isAdminAuthenticated();
    }
}

