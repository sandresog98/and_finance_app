<?php
/**
 * AND FINANCE APP - Email Helper
 * Utilidad para envío de correos usando PHPMailer
 * Configurado con credenciales SMTP desde .env
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';

class EmailHelper {
    
    private static ?array $config = null;
    
    /**
     * Cargar configuración SMTP desde .env
     */
    private static function loadConfig(): array {
        if (self::$config !== null) {
            return self::$config;
        }
        
        // Buscar archivo .env
        $envPath = __DIR__ . '/../../.env';
        
        if (!file_exists($envPath)) {
            throw new Exception('Archivo .env no encontrado');
        }
        
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];
        
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas
            if (preg_match('/^"(.*)"$/', $value, $m)) $value = $m[1];
            if (preg_match("/^'(.*)'$/", $value, $m)) $value = $m[1];
            
            $config[$key] = $value;
        }
        
        self::$config = $config;
        return $config;
    }
    
    /**
     * Obtener valor de configuración
     */
    private static function getConfig(string $key, string $default = ''): string {
        $config = self::loadConfig();
        return $config[$key] ?? $default;
    }
    
    /**
     * Envía un correo electrónico
     * 
     * @param string $to Email del destinatario
     * @param string $subject Asunto del email
     * @param string $body Contenido del email (HTML)
     * @param string $fromName Nombre del remitente (default: 'AndFinance')
     * @return array ['success' => bool, 'message' => string]
     */
    public static function send(string $to, string $subject, string $body, string $fromName = 'AndFinance'): array {
        $mail = new PHPMailer(true);
        
        try {
            // Obtener configuración
            $smtpHost = self::getConfig('SMTP_HOST', 'smtp.hostinger.com');
            $smtpUser = self::getConfig('SMTP_USER');
            $smtpPass = self::getConfig('SMTP_PASS');
            $smtpPort = self::getConfig('SMTP_PORT', '465');
            
            // Validar credenciales
            if (empty($smtpUser)) {
                throw new Exception('SMTP_USER no está configurado en .env');
            }
            if (empty($smtpPass)) {
                throw new Exception('SMTP_PASS no está configurado en .env');
            }
            
            // Configuración SMTP
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int)$smtpPort;
            
            // Configuración de caracteres
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            
            // Remitente y destinatario
            $mail->setFrom($smtpUser, $fromName);
            $mail->addAddress($to);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            
            $mail->send();
            
            return [
                'success' => true,
                'message' => 'Correo enviado correctamente'
            ];
            
        } catch (Exception $e) {
            error_log("EmailHelper Error: " . $mail->ErrorInfo);
            return [
                'success' => false,
                'message' => $mail->ErrorInfo
            ];
        }
    }
    
    /**
     * Genera un código de verificación de 6 dígitos
     */
    public static function generateCode(): string {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Envía código de verificación para registro
     */
    public static function sendVerificationCode(string $to, string $code, string $nombre): array {
        $subject = "Tu código de verificación - AndFinance";
        
        $body = self::getEmailTemplate('verification', [
            'nombre' => $nombre,
            'codigo' => $code
        ]);
        
        return self::send($to, $subject, $body);
    }
    
    /**
     * Envía código para recuperación de contraseña
     */
    public static function sendPasswordResetCode(string $to, string $code, string $nombre): array {
        $subject = "Recuperar contraseña - AndFinance";
        
        $body = self::getEmailTemplate('password_reset', [
            'nombre' => $nombre,
            'codigo' => $code
        ]);
        
        return self::send($to, $subject, $body);
    }
    
    /**
     * Envía notificación de bienvenida
     */
    public static function sendWelcome(string $to, string $nombre): array {
        $subject = "¡Bienvenido a AndFinance! 🎉";
        
        $body = self::getEmailTemplate('welcome', [
            'nombre' => $nombre
        ]);
        
        return self::send($to, $subject, $body);
    }
    
    /**
     * Obtener plantilla de email
     */
    private static function getEmailTemplate(string $template, array $data): string {
        // Colores corporativos
        $primaryBlue = '#55A5C8';
        $darkBlue = '#35719E';
        $secondaryGreen = '#9AD082';
        $tertiaryGray = '#B1BCBF';
        
        $baseStyle = "
            body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f4f7f8; margin: 0; padding: 20px; }
            .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, $darkBlue, $primaryBlue); padding: 30px; text-align: center; color: white; }
            .logo { font-size: 28px; font-weight: 800; margin-bottom: 5px; }
            .content { padding: 30px; }
            .code-box { background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 12px; padding: 25px; text-align: center; margin: 25px 0; }
            .code { font-size: 36px; font-weight: 800; color: $darkBlue; letter-spacing: 8px; }
            .footer { background: #f8f9fa; padding: 20px 30px; text-align: center; font-size: 12px; color: #6c757d; }
            .btn { display: inline-block; background: linear-gradient(135deg, $darkBlue, $primaryBlue); color: white; padding: 14px 30px; border-radius: 10px; text-decoration: none; font-weight: 600; }
            p { color: #495057; line-height: 1.6; margin: 15px 0; }
            .highlight { color: $darkBlue; font-weight: 600; }
        ";
        
        switch ($template) {
            case 'verification':
                return "
                <!DOCTYPE html>
                <html>
                <head><style>$baseStyle</style></head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <div class='logo'>💰 AndFinance</div>
                            <p style='margin:0;opacity:0.9'>Verificación de cuenta</p>
                        </div>
                        <div class='content'>
                            <p>¡Hola <span class='highlight'>{$data['nombre']}</span>!</p>
                            <p>Gracias por registrarte en AndFinance. Para completar tu registro, ingresa el siguiente código de verificación:</p>
                            <div class='code-box'>
                                <div class='code'>{$data['codigo']}</div>
                            </div>
                            <p style='font-size:13px;color:#6c757d'>Este código expira en <strong>15 minutos</strong>.</p>
                            <p style='font-size:13px;color:#6c757d'>Si no solicitaste este código, puedes ignorar este mensaje.</p>
                        </div>
                        <div class='footer'>
                            <p>© " . date('Y') . " AndFinance. Tu compañero de finanzas personales.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
            case 'password_reset':
                return "
                <!DOCTYPE html>
                <html>
                <head><style>$baseStyle</style></head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <div class='logo'>💰 AndFinance</div>
                            <p style='margin:0;opacity:0.9'>Recuperar contraseña</p>
                        </div>
                        <div class='content'>
                            <p>¡Hola <span class='highlight'>{$data['nombre']}</span>!</p>
                            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta. Usa el siguiente código para continuar:</p>
                            <div class='code-box'>
                                <div class='code'>{$data['codigo']}</div>
                            </div>
                            <p style='font-size:13px;color:#6c757d'>Este código expira en <strong>15 minutos</strong>.</p>
                            <p style='font-size:13px;color:#dc3545'><strong>⚠️ Si no solicitaste este cambio</strong>, ignora este mensaje. Tu contraseña seguirá siendo la misma.</p>
                        </div>
                        <div class='footer'>
                            <p>© " . date('Y') . " AndFinance. Tu compañero de finanzas personales.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
            case 'welcome':
                return "
                <!DOCTYPE html>
                <html>
                <head><style>$baseStyle</style></head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <div class='logo'>💰 AndFinance</div>
                            <p style='margin:0;opacity:0.9'>¡Bienvenido!</p>
                        </div>
                        <div class='content'>
                            <p>¡Hola <span class='highlight'>{$data['nombre']}</span>! 🎉</p>
                            <p>Tu cuenta ha sido creada exitosamente. Ahora puedes comenzar a gestionar tus finanzas personales de forma inteligente.</p>
                            <p><strong>Con AndFinance podrás:</strong></p>
                            <ul style='color:#495057;'>
                                <li>📊 Controlar tus ingresos y gastos</li>
                                <li>🏦 Gestionar múltiples cuentas bancarias</li>
                                <li>📅 Programar gastos recurrentes</li>
                                <li>📈 Ver reportes y estadísticas</li>
                            </ul>
                            <p>¡Comienza ahora y toma el control de tu dinero!</p>
                        </div>
                        <div class='footer'>
                            <p>© " . date('Y') . " AndFinance. Tu compañero de finanzas personales.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
            default:
                return '';
        }
    }
}

