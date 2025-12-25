<?php
/**
 * AND FINANCE APP - User Auth Controller
 * Controlador de autenticación para usuarios
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/VerificacionModel.php';
require_once __DIR__ . '/../../assets/PHPMailer/EmailHelper.php';

class AuthController {
    private PDO $db;
    private UserModel $userModel;
    private VerificacionModel $verificacionModel;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->userModel = new UserModel();
        $this->verificacionModel = new VerificacionModel();
    }
    
    /**
     * Intentar login de usuario
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
            $user = $this->userModel->getByEmail($email);
            
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
            
            // Verificar si tiene contraseña (podría ser usuario de Google)
            if (empty($user['password'])) {
                $response['message'] = 'Esta cuenta fue creada con Google. Por favor inicia sesión con Google.';
                return $response;
            }
            
            // Verificar contraseña
            if (!password_verify($password, $user['password'])) {
                $response['message'] = 'Credenciales incorrectas';
                return $response;
            }
            
            // Actualizar último acceso
            $this->userModel->updateLastAccess($user['id']);
            
            // Crear sesión
            $this->createSession($user);
            
            $response['success'] = true;
            $response['message'] = 'Login exitoso';
            
        } catch (PDOException $e) {
            $response['message'] = APP_DEBUG ? $e->getMessage() : 'Error al procesar la solicitud';
        }
        
        return $response;
    }
    
    /**
     * Paso 1: Iniciar registro - Enviar código de verificación
     */
    public function iniciarRegistro(string $nombre, string $email, string $password, string $confirmPassword): array {
        $response = ['success' => false, 'message' => '', 'errors' => []];
        
        // Validaciones
        if (empty($nombre)) {
            $response['errors'][] = 'El nombre es obligatorio';
        } elseif (strlen($nombre) < 2) {
            $response['errors'][] = 'El nombre debe tener al menos 2 caracteres';
        }
        
        if (empty($email)) {
            $response['errors'][] = 'El email es obligatorio';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['errors'][] = 'El formato del email no es válido';
        } elseif ($this->userModel->emailExists($email)) {
            $response['errors'][] = 'Este email ya está registrado';
        }
        
        if (empty($password)) {
            $response['errors'][] = 'La contraseña es obligatoria';
        } elseif (strlen($password) < 6) {
            $response['errors'][] = 'La contraseña debe tener al menos 6 caracteres';
        }
        
        if ($password !== $confirmPassword) {
            $response['errors'][] = 'Las contraseñas no coinciden';
        }
        
        if (!empty($response['errors'])) {
            $response['message'] = 'Por favor corrija los errores';
            return $response;
        }
        
        try {
            // Verificar si puede solicitar código (anti-spam)
            $puedeReenviar = $this->verificacionModel->puedeReenviar($email, 'registro');
            if (!$puedeReenviar['puede']) {
                $response['message'] = 'Por favor espera ' . $puedeReenviar['segundos_restantes'] . ' segundos antes de solicitar otro código';
                return $response;
            }
            
            // Guardar datos temporalmente
            $datosTemp = json_encode([
                'nombre' => $nombre,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ]);
            
            // Crear código de verificación
            $codigo = $this->verificacionModel->crearCodigo($email, 'registro', $datosTemp);
            
            // Enviar email
            $resultEmail = EmailHelper::sendVerificationCode($email, $codigo, $nombre);
            
            if (!$resultEmail['success']) {
                $response['message'] = 'Error al enviar el código. Por favor intenta de nuevo.';
                return $response;
            }
            
            $response['success'] = true;
            $response['message'] = 'Código enviado a ' . $email;
            $response['email'] = $email;
            
        } catch (Exception $e) {
            $response['message'] = APP_DEBUG ? $e->getMessage() : 'Error al procesar la solicitud';
        }
        
        return $response;
    }
    
    /**
     * Paso 2: Verificar código y completar registro
     */
    public function verificarYRegistrar(string $email, string $codigo): array {
        $response = ['success' => false, 'message' => ''];
        
        try {
            // Verificar código
            $resultado = $this->verificacionModel->verificarCodigo($email, $codigo, 'registro');
            
            if (!$resultado['valido']) {
                $response['message'] = $resultado['mensaje'];
                return $response;
            }
            
            // Obtener datos del usuario
            $datos = $resultado['datos'];
            
            if (!$datos) {
                $response['message'] = 'Error: No se encontraron los datos del registro';
                return $response;
            }
            
            // Crear usuario
            $userId = $this->userModel->create([
                'nombre' => $datos['nombre'],
                'email' => $datos['email'],
                'password' => $datos['password'], // Ya está hasheado
                'rol' => 'usuario',
                'estado' => 1
            ]);
            
            // Ejecutar onboarding (crear cuenta por defecto, categorías, etc.)
            $this->userModel->ejecutarOnboarding($userId);
            
            // Obtener usuario creado
            $user = $this->userModel->getById($userId);
            
            // Crear sesión
            $this->createSession($user);
            
            // Enviar email de bienvenida (async, no bloquear si falla)
            try {
                EmailHelper::sendWelcome($user['email'], $user['nombre']);
            } catch (Exception $e) {
                // Log error but don't fail
                error_log('Error enviando email de bienvenida: ' . $e->getMessage());
            }
            
            $response['success'] = true;
            $response['message'] = '¡Cuenta creada exitosamente!';
            
        } catch (PDOException $e) {
            $response['message'] = APP_DEBUG ? $e->getMessage() : 'Error al crear la cuenta';
        }
        
        return $response;
    }
    
    /**
     * Reenviar código de verificación
     */
    public function reenviarCodigo(string $email, string $tipo): array {
        $response = ['success' => false, 'message' => ''];
        
        // Verificar si puede reenviar
        $puedeReenviar = $this->verificacionModel->puedeReenviar($email, $tipo);
        if (!$puedeReenviar['puede']) {
            $response['message'] = 'Espera ' . $puedeReenviar['segundos_restantes'] . ' segundos';
            $response['segundos_restantes'] = $puedeReenviar['segundos_restantes'];
            return $response;
        }
        
        try {
            if ($tipo === 'registro') {
                // Para registro, necesitamos los datos temporales del código anterior
                $stmt = $this->db->prepare("
                    SELECT datos_temporales FROM verificacion_codigos 
                    WHERE email = :email AND tipo = 'registro' 
                    ORDER BY fecha_creacion DESC LIMIT 1
                ");
                $stmt->execute(['email' => $email]);
                $registro = $stmt->fetch();
                
                if (!$registro || !$registro['datos_temporales']) {
                    $response['message'] = 'Sesión expirada. Por favor inicia el registro nuevamente.';
                    return $response;
                }
                
                $datos = json_decode($registro['datos_temporales'], true);
                $codigo = $this->verificacionModel->crearCodigo($email, 'registro', $registro['datos_temporales']);
                $result = EmailHelper::sendVerificationCode($email, $codigo, $datos['nombre']);
                
            } else { // recuperacion_password
                $user = $this->userModel->getByEmail($email);
                if (!$user) {
                    $response['message'] = 'Email no encontrado';
                    return $response;
                }
                
                $codigo = $this->verificacionModel->crearCodigo($email, 'recuperacion_password');
                $result = EmailHelper::sendPasswordResetCode($email, $codigo, $user['nombre']);
            }
            
            if (!$result['success']) {
                $response['message'] = 'Error al enviar el código';
                return $response;
            }
            
            $response['success'] = true;
            $response['message'] = 'Código reenviado correctamente';
            
        } catch (Exception $e) {
            $response['message'] = APP_DEBUG ? $e->getMessage() : 'Error al reenviar código';
        }
        
        return $response;
    }
    
    /**
     * Paso 1: Solicitar recuperación de contraseña
     */
    public function solicitarRecuperacion(string $email): array {
        $response = ['success' => false, 'message' => ''];
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Email no válido';
            return $response;
        }
        
        try {
            // Buscar usuario
            $user = $this->userModel->getByEmail($email);
            
            // Siempre mostrar éxito por seguridad (no revelar si email existe)
            if (!$user) {
                $response['success'] = true;
                $response['message'] = 'Si el email existe, recibirás un código de verificación';
                return $response;
            }
            
            // Verificar si es usuario de Google
            if (!empty($user['google_id']) && empty($user['password'])) {
                $response['success'] = true;
                $response['message'] = 'Si el email existe, recibirás un código de verificación';
                return $response;
            }
            
            // Verificar anti-spam
            $puedeReenviar = $this->verificacionModel->puedeReenviar($email, 'recuperacion_password');
            if (!$puedeReenviar['puede']) {
                $response['message'] = 'Por favor espera ' . $puedeReenviar['segundos_restantes'] . ' segundos';
                $response['segundos_restantes'] = $puedeReenviar['segundos_restantes'];
                return $response;
            }
            
            // Crear código
            $codigo = $this->verificacionModel->crearCodigo($email, 'recuperacion_password');
            
            // Enviar email
            $result = EmailHelper::sendPasswordResetCode($email, $codigo, $user['nombre']);
            
            $response['success'] = true;
            $response['message'] = 'Si el email existe, recibirás un código de verificación';
            $response['email'] = $email;
            
        } catch (Exception $e) {
            $response['message'] = APP_DEBUG ? $e->getMessage() : 'Error al procesar la solicitud';
        }
        
        return $response;
    }
    
    /**
     * Paso 2: Verificar código de recuperación
     */
    public function verificarCodigoRecuperacion(string $email, string $codigo): array {
        $response = ['success' => false, 'message' => ''];
        
        $resultado = $this->verificacionModel->verificarCodigo($email, $codigo, 'recuperacion_password');
        
        $response['success'] = $resultado['valido'];
        $response['message'] = $resultado['mensaje'];
        
        return $response;
    }
    
    /**
     * Paso 3: Cambiar contraseña
     */
    public function cambiarPassword(string $email, string $codigo, string $newPassword, string $confirmPassword): array {
        $response = ['success' => false, 'message' => ''];
        
        // Validar contraseñas
        if (strlen($newPassword) < 6) {
            $response['message'] = 'La contraseña debe tener al menos 6 caracteres';
            return $response;
        }
        
        if ($newPassword !== $confirmPassword) {
            $response['message'] = 'Las contraseñas no coinciden';
            return $response;
        }
        
        try {
            // Buscar usuario
            $user = $this->userModel->getByEmail($email);
            
            if (!$user) {
                $response['message'] = 'Usuario no encontrado';
                return $response;
            }
            
            // Actualizar contraseña
            $this->userModel->update($user['id'], [
                'password' => password_hash($newPassword, PASSWORD_DEFAULT)
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Contraseña actualizada correctamente';
            
        } catch (Exception $e) {
            $response['message'] = APP_DEBUG ? $e->getMessage() : 'Error al cambiar contraseña';
        }
        
        return $response;
    }
    
    /**
     * Registrar nuevo usuario (LEGACY - mantener compatibilidad)
     * @deprecated Use iniciarRegistro() y verificarYRegistrar() en su lugar
     */
    public function register(string $nombre, string $email, string $password, string $confirmPassword): array {
        // Redirigir al nuevo flujo con verificación
        return $this->iniciarRegistro($nombre, $email, $password, $confirmPassword);
    }
    
    /**
     * Login/Registro con Google
     */
    public function handleGoogleAuth(array $googleUser): array {
        $response = ['success' => false, 'message' => ''];
        
        try {
            // Buscar por Google ID
            $user = $this->userModel->getByGoogleId($googleUser['id']);
            
            if (!$user) {
                // Buscar por email
                $user = $this->userModel->getByEmail($googleUser['email']);
                
                if ($user) {
                    // Usuario existe, vincular Google ID
                    $this->userModel->update($user['id'], [
                        'google_id' => $googleUser['id'],
                        'avatar' => $googleUser['avatar'] ?? $user['avatar']
                    ]);
                    $user = $this->userModel->getById($user['id']);
                } else {
                    // Crear nuevo usuario
                    $userId = $this->userModel->create([
                        'nombre' => $googleUser['name'],
                        'email' => $googleUser['email'],
                        'google_id' => $googleUser['id'],
                        'avatar' => $googleUser['avatar'] ?? null,
                        'rol' => 'usuario',
                        'estado' => 1
                    ]);
                    
                    // Ejecutar onboarding
                    $this->userModel->ejecutarOnboarding($userId);
                    
                    $user = $this->userModel->getById($userId);
                    
                    // Enviar email de bienvenida
                    try {
                        EmailHelper::sendWelcome($user['email'], $user['nombre']);
                    } catch (Exception $e) {
                        error_log('Error enviando email de bienvenida: ' . $e->getMessage());
                    }
                }
            }
            
            // Verificar estado
            if ($user['estado'] != 1) {
                $response['message'] = 'Esta cuenta está desactivada';
                return $response;
            }
            
            // Actualizar último acceso
            $this->userModel->updateLastAccess($user['id']);
            
            // Crear sesión
            $this->createSession($user);
            
            $response['success'] = true;
            $response['message'] = 'Login exitoso';
            
        } catch (PDOException $e) {
            $response['message'] = APP_DEBUG ? $e->getMessage() : 'Error al procesar la solicitud';
        }
        
        return $response;
    }
    
    /**
     * Crear sesión de usuario
     */
    private function createSession(array $user): void {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'nombre' => $user['nombre'],
            'email' => $user['email'],
            'avatar' => $user['avatar'],
            'rol' => $user['rol']
        ];
    }
    
    /**
     * Cerrar sesión
     */
    public function logout(): void {
        destroyUserSession();
    }
    
    /**
     * Verificar si hay sesión activa
     */
    public function checkSession(): bool {
        return isUserAuthenticated();
    }
}
