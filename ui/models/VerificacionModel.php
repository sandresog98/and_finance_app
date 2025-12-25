<?php
/**
 * AND FINANCE APP - Verificacion Model
 * Modelo para gestión de códigos de verificación
 */

require_once __DIR__ . '/../../config/database.php';

class VerificacionModel {
    private PDO $db;
    
    // Tiempo de expiración en minutos
    private const EXPIRACION_MINUTOS = 15;
    
    // Máximo de intentos permitidos
    private const MAX_INTENTOS = 5;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Crear nuevo código de verificación
     * @param string $email
     * @param string $tipo ('registro', 'recuperacion_password')
     * @param string|null $datosTemporales JSON con datos del usuario
     * @return string El código generado
     */
    public function crearCodigo(string $email, string $tipo, ?string $datosTemporales = null): string {
        // Invalidar códigos anteriores del mismo tipo
        $this->invalidarCodigosAnteriores($email, $tipo);
        
        // Generar código de 6 dígitos
        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Calcular fecha de expiración
        $expiracion = date('Y-m-d H:i:s', strtotime('+' . self::EXPIRACION_MINUTOS . ' minutes'));
        
        $stmt = $this->db->prepare("
            INSERT INTO verificacion_codigos (email, codigo, tipo, datos_temporales, fecha_expiracion)
            VALUES (:email, :codigo, :tipo, :datos, :expiracion)
        ");
        
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'codigo' => $codigo,
            'tipo' => $tipo,
            'datos' => $datosTemporales,
            'expiracion' => $expiracion
        ]);
        
        return $codigo;
    }
    
    /**
     * Verificar un código
     * @return array ['valido' => bool, 'mensaje' => string, 'datos' => ?array]
     */
    public function verificarCodigo(string $email, string $codigo, string $tipo): array {
        $email = strtolower(trim($email));
        
        // Buscar código activo
        $stmt = $this->db->prepare("
            SELECT * FROM verificacion_codigos 
            WHERE email = :email 
            AND tipo = :tipo 
            AND usado = 0 
            AND fecha_expiracion > NOW()
            ORDER BY fecha_creacion DESC 
            LIMIT 1
        ");
        
        $stmt->execute(['email' => $email, 'tipo' => $tipo]);
        $registro = $stmt->fetch();
        
        if (!$registro) {
            return [
                'valido' => false,
                'mensaje' => 'No hay un código válido para este correo. Solicita uno nuevo.',
                'datos' => null
            ];
        }
        
        // Verificar intentos
        if ($registro['intentos'] >= self::MAX_INTENTOS) {
            return [
                'valido' => false,
                'mensaje' => 'Has excedido el número máximo de intentos. Solicita un nuevo código.',
                'datos' => null
            ];
        }
        
        // Verificar código
        if ($registro['codigo'] !== $codigo) {
            // Incrementar intentos
            $this->incrementarIntentos($registro['id']);
            $intentosRestantes = self::MAX_INTENTOS - $registro['intentos'] - 1;
            
            return [
                'valido' => false,
                'mensaje' => "Código incorrecto. Te quedan $intentosRestantes intentos.",
                'datos' => null
            ];
        }
        
        // Código válido - marcarlo como usado
        $this->marcarUsado($registro['id']);
        
        return [
            'valido' => true,
            'mensaje' => 'Código verificado correctamente',
            'datos' => $registro['datos_temporales'] ? json_decode($registro['datos_temporales'], true) : null
        ];
    }
    
    /**
     * Verificar si puede solicitar un nuevo código (anti-spam)
     * Mínimo 1 minuto entre solicitudes
     */
    public function puedeReenviar(string $email, string $tipo): array {
        $email = strtolower(trim($email));
        
        $stmt = $this->db->prepare("
            SELECT fecha_creacion FROM verificacion_codigos 
            WHERE email = :email AND tipo = :tipo AND usado = 0
            ORDER BY fecha_creacion DESC 
            LIMIT 1
        ");
        
        $stmt->execute(['email' => $email, 'tipo' => $tipo]);
        $registro = $stmt->fetch();
        
        if (!$registro) {
            return ['puede' => true, 'segundos_restantes' => 0];
        }
        
        $fechaCreacion = strtotime($registro['fecha_creacion']);
        $tiempoMinimo = 60; // 1 minuto
        $diferencia = time() - $fechaCreacion;
        
        if ($diferencia < $tiempoMinimo) {
            return [
                'puede' => false,
                'segundos_restantes' => $tiempoMinimo - $diferencia
            ];
        }
        
        return ['puede' => true, 'segundos_restantes' => 0];
    }
    
    /**
     * Invalidar códigos anteriores
     */
    private function invalidarCodigosAnteriores(string $email, string $tipo): void {
        $stmt = $this->db->prepare("
            UPDATE verificacion_codigos 
            SET usado = 1 
            WHERE email = :email AND tipo = :tipo AND usado = 0
        ");
        $stmt->execute(['email' => strtolower(trim($email)), 'tipo' => $tipo]);
    }
    
    /**
     * Incrementar intentos fallidos
     */
    private function incrementarIntentos(int $id): void {
        $stmt = $this->db->prepare("
            UPDATE verificacion_codigos 
            SET intentos = intentos + 1 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }
    
    /**
     * Marcar código como usado
     */
    private function marcarUsado(int $id): void {
        $stmt = $this->db->prepare("
            UPDATE verificacion_codigos SET usado = 1 WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }
    
    /**
     * Limpiar códigos expirados (para mantenimiento)
     */
    public function limpiarExpirados(): int {
        $stmt = $this->db->prepare("
            DELETE FROM verificacion_codigos 
            WHERE fecha_expiracion < NOW() OR usado = 1
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Obtener tiempo restante del código activo
     */
    public function getTiempoRestante(string $email, string $tipo): ?int {
        $email = strtolower(trim($email));
        
        $stmt = $this->db->prepare("
            SELECT fecha_expiracion FROM verificacion_codigos 
            WHERE email = :email AND tipo = :tipo AND usado = 0 AND fecha_expiracion > NOW()
            ORDER BY fecha_creacion DESC 
            LIMIT 1
        ");
        
        $stmt->execute(['email' => $email, 'tipo' => $tipo]);
        $registro = $stmt->fetch();
        
        if (!$registro) {
            return null;
        }
        
        return max(0, strtotime($registro['fecha_expiracion']) - time());
    }
}

