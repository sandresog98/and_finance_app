<?php
/**
 * AND FINANCE APP - Perfil de Usuario
 */

ob_start();

require_once __DIR__ . '/../../../../config/database.php';

$pageTitle = 'Mi Perfil';
$pageSubtitle = 'Configuración de tu cuenta';

$db = Database::getInstance();
$userId = getCurrentUserId();

// Obtener datos del usuario
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute(['id' => $userId]);
$usuario = $stmt->fetch();

// Obtener configuración
$stmt = $db->prepare("SELECT * FROM configuracion_usuario WHERE usuario_id = :id");
$stmt->execute(['id' => $userId]);
$config = $stmt->fetch();

if (!$config) {
    // Crear configuración por defecto
    $stmt = $db->prepare("INSERT INTO configuracion_usuario (usuario_id) VALUES (:id)");
    $stmt->execute(['id' => $userId]);
    $config = [
        'moneda_principal' => 'COP',
        'tema' => 'light',
        'idioma' => 'es',
        'formato_fecha' => 'd/m/Y',
        'primer_dia_semana' => 1,
        'notificaciones_email' => 1,
        'notificaciones_push' => 1
    ];
}

// Estadísticas del usuario
$stmt = $db->prepare("SELECT COUNT(*) FROM cuentas WHERE usuario_id = :id AND estado = 1");
$stmt->execute(['id' => $userId]);
$totalCuentas = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM transacciones WHERE usuario_id = :id AND estado = 1");
$stmt->execute(['id' => $userId]);
$totalTransacciones = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM categorias WHERE usuario_id = :id AND estado = 1");
$stmt->execute(['id' => $userId]);
$totalCategorias = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM gastos_recurrentes WHERE usuario_id = :id AND estado = 1");
$stmt->execute(['id' => $userId]);
$totalRecurrentes = $stmt->fetchColumn();

// Calcular tiempo desde registro
$fechaRegistro = new DateTime($usuario['fecha_creacion']);
$ahora = new DateTime();
$diferencia = $fechaRegistro->diff($ahora);

if ($diferencia->y > 0) {
    $tiempoRegistro = $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '');
} elseif ($diferencia->m > 0) {
    $tiempoRegistro = $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '');
} elseif ($diferencia->d > 0) {
    $tiempoRegistro = $diferencia->d . ' día' . ($diferencia->d > 1 ? 's' : '');
} else {
    $tiempoRegistro = 'Hoy';
}

$errors = [];
$success = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Actualizar datos personales
    if ($action === 'datos_personales') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($nombre)) {
            $errors[] = 'El nombre es requerido';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ingresa un email válido';
        }

        // Verificar email único
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
            $stmt->execute(['email' => $email, 'id' => $userId]);
            if ($stmt->fetch()) {
                $errors[] = 'Este email ya está en uso';
            }
        }

        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE usuarios SET nombre = :nombre, email = :email WHERE id = :id");
            $stmt->execute(['nombre' => $nombre, 'email' => $email, 'id' => $userId]);
            $usuario['nombre'] = $nombre;
            $usuario['email'] = $email;
            $success = 'Datos actualizados correctamente';
        }
    }

    // Cambiar contraseña
    if ($action === 'cambiar_password') {
        $passwordActual = $_POST['password_actual'] ?? '';
        $passwordNueva = $_POST['password_nueva'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($passwordActual)) {
            $errors[] = 'Ingresa tu contraseña actual';
        } elseif (!password_verify($passwordActual, $usuario['password'])) {
            $errors[] = 'La contraseña actual es incorrecta';
        }

        if (strlen($passwordNueva) < 6) {
            $errors[] = 'La nueva contraseña debe tener al menos 6 caracteres';
        }

        if ($passwordNueva !== $passwordConfirm) {
            $errors[] = 'Las contraseñas no coinciden';
        }

        if (empty($errors)) {
            $hash = password_hash($passwordNueva, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE usuarios SET password = :password WHERE id = :id");
            $stmt->execute(['password' => $hash, 'id' => $userId]);
            $success = 'Contraseña actualizada correctamente';
        }
    }

    // Actualizar preferencias
    if ($action === 'preferencias') {
        $tema = $_POST['tema'] ?? 'light';
        $moneda = $_POST['moneda_principal'] ?? 'COP';
        $notifEmail = isset($_POST['notificaciones_email']) ? 1 : 0;
        $notifPush = isset($_POST['notificaciones_push']) ? 1 : 0;

        $stmt = $db->prepare("
            UPDATE configuracion_usuario SET 
                tema = :tema,
                moneda_principal = :moneda,
                notificaciones_email = :notif_email,
                notificaciones_push = :notif_push
            WHERE usuario_id = :id
        ");
        $stmt->execute([
            'tema' => $tema,
            'moneda' => $moneda,
            'notif_email' => $notifEmail,
            'notif_push' => $notifPush,
            'id' => $userId
        ]);

        // Redirigir al dashboard para ver los cambios del tema
        setFlashMessage('success', '¡Tema actualizado! 🎨');
        ob_end_clean();
        header('Location: ' . uiUrl('index.php'));
        exit;
    }
}
?>

<style>
.profile-header {
    background: linear-gradient(135deg, #35719E 0%, #55A5C8 100%);
    border-radius: 16px;
    padding: 24px;
    color: white;
    margin-bottom: 1.5rem;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin-right: 20px;
    border: 3px solid rgba(255, 255, 255, 0.3);
}

.profile-info h3 {
    margin: 0;
    font-weight: 700;
}

.profile-info p {
    margin: 0;
    opacity: 0.9;
}

.profile-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-top: 16px;
}

.profile-stat {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    padding: 10px 8px;
    text-align: center;
    overflow: hidden;
}

.profile-stat-value {
    font-size: 18px;
    font-weight: 700;
    display: block;
    line-height: 1.2;
}

.profile-stat-label {
    font-size: 10px;
    opacity: 0.8;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Selector buttons para preferencias */
.pref-selector-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: 2px solid var(--border-color, #dee2e6);
    border-radius: 12px;
    background: var(--bg-card, white);
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;
    text-align: left;
}

.pref-selector-btn:hover {
    border-color: var(--primary-blue, #55A5C8);
    background: var(--bg-hover, rgba(85, 165, 200, 0.05));
}

.pref-selector-btn .pref-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    background: var(--bg-input, #f8f9fa);
}

.pref-selector-btn .pref-info {
    flex-grow: 1;
}

.pref-selector-btn .pref-info span {
    font-weight: 600;
    display: block;
    font-size: 14px;
    color: var(--text-primary, #333);
}

.pref-selector-btn .pref-info small {
    color: var(--text-muted, #6c757d);
    font-size: 12px;
}

/* Options in modal */
.pref-option {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 8px;
    border: 2px solid transparent;
}

.pref-option:hover {
    background: var(--bg-hover, rgba(85, 165, 200, 0.1));
}

.pref-option.selected {
    background: rgba(85, 165, 200, 0.15);
    border-color: var(--primary-blue, #55A5C8);
}

.pref-option .pref-option-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    background: var(--bg-input, #f0f0f0);
}

.pref-option .pref-option-info {
    flex-grow: 1;
}

.pref-option .pref-option-info strong {
    display: block;
    font-size: 15px;
    color: var(--text-primary, #333);
}

.pref-option .pref-option-info small {
    color: var(--text-muted, #6c757d);
}

.settings-card {
    border-radius: 16px;
    margin-bottom: 1rem;
    overflow: hidden;
    background: var(--bg-card, white);
}

.settings-card .card-header {
    background: transparent;
    border-bottom: 1px solid var(--border-light, rgba(0,0,0,0.05));
    padding: 16px 20px;
}

.settings-card .card-header h6 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-primary, #333);
}

.settings-card .card-body {
    padding: 20px;
}

.form-switch-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.form-switch-custom:last-child {
    border-bottom: none;
}

.form-switch-custom label {
    flex-grow: 1;
}

.form-switch-custom label span {
    display: block;
    font-weight: 500;
}

.form-switch-custom label small {
    color: #6c757d;
}

.danger-zone {
    border: 2px solid #FF6B6B;
    border-radius: 16px;
    padding: 20px;
    background: rgba(255, 107, 107, 0.05);
}

.danger-zone h6 {
    color: #FF6B6B;
    margin-bottom: 12px;
}

/* Password collapse */
.collapse-icon {
    transition: transform 0.3s ease;
}

[aria-expanded="true"] .collapse-icon {
    transform: rotate(180deg);
}

.password-form-container {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 16px;
    border: 1px solid #e9ecef;
}

@media (max-width: 767.98px) {
    .profile-header {
        padding: 16px;
    }
    
    .profile-avatar {
        width: 50px;
        height: 50px;
        font-size: 20px;
        margin-right: 12px;
        flex-shrink: 0;
    }
    
    .profile-info h3 {
        font-size: 16px;
        word-break: break-word;
    }
    
    .profile-info p {
        font-size: 13px;
    }
    
    .profile-info small {
        font-size: 11px;
    }
    
    .profile-stats {
        gap: 6px;
    }
    
    .profile-stat {
        padding: 8px 4px;
        border-radius: 8px;
    }
    
    .profile-stat-value {
        font-size: 15px;
    }
    
    .profile-stat-label {
        font-size: 9px;
    }
    
    .pref-selector-btn {
        padding: 10px 12px;
    }
    
    .pref-selector-btn .pref-icon {
        width: 36px;
        height: 36px;
        font-size: 18px;
    }
}
</style>

<!-- Header del perfil -->
<div class="profile-header fade-in-up">
    <div class="d-flex align-items-center">
        <div class="profile-avatar">
            <?php if ($usuario['avatar']): ?>
            <img src="<?= htmlspecialchars($usuario['avatar']) ?>" alt="Avatar" class="w-100 h-100 rounded-circle">
            <?php else: ?>
            <i class="bi bi-person-fill"></i>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <h3><?= htmlspecialchars($usuario['nombre']) ?></h3>
            <p><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($usuario['email']) ?></p>
            <small class="opacity-75">
                <i class="bi bi-calendar-check me-1"></i>Miembro desde hace <?= $tiempoRegistro ?>
            </small>
        </div>
    </div>
    <div class="profile-stats">
        <div class="profile-stat">
            <span class="profile-stat-value"><?= $totalCuentas ?></span>
            <span class="profile-stat-label">Cuentas</span>
        </div>
        <div class="profile-stat">
            <span class="profile-stat-value"><?= $totalTransacciones ?></span>
            <span class="profile-stat-label">Transacciones</span>
        </div>
        <div class="profile-stat">
            <span class="profile-stat-value"><?= $totalCategorias ?></span>
            <span class="profile-stat-label">Categorías</span>
        </div>
        <div class="profile-stat">
            <span class="profile-stat-value"><?= $totalRecurrentes ?></span>
            <span class="profile-stat-label">Recurrentes</span>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger fade-in-up">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success fade-in-up">
    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <!-- Datos Personales -->
        <div class="card settings-card fade-in-up" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h6><i class="bi bi-person text-primary"></i>Datos Personales</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="datos_personales">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre completo</label>
                        <input type="text" class="form-control" name="nombre" 
                               value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?= htmlspecialchars($usuario['email']) ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                    </button>
                </form>
            </div>
        </div>

        <!-- Cambiar Contraseña -->
        <div class="card settings-card fade-in-up" style="animation-delay: 0.2s;">
            <div class="card-body">
                <button class="btn btn-outline-warning w-100 d-flex align-items-center justify-content-between" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapsePassword" 
                        aria-expanded="false" 
                        aria-controls="collapsePassword">
                    <span>
                        <i class="bi bi-shield-lock me-2"></i>Cambiar Contraseña
                    </span>
                    <i class="bi bi-chevron-down collapse-icon"></i>
                </button>
                
                <div class="collapse mt-3" id="collapsePassword">
                    <div class="password-form-container">
                        <form method="POST">
                            <input type="hidden" name="action" value="cambiar_password">
                            
                            <div class="mb-3">
                                <label class="form-label">Contraseña actual</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password_actual" id="password_actual" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_actual')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nueva contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password_nueva" id="password_nueva" minlength="6" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_nueva')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Mínimo 6 caracteres</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirmar contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password_confirm" id="password_confirm" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_confirm')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary flex-grow-1" data-bs-toggle="collapse" data-bs-target="#collapsePassword">
                                    Cancelar
                                </button>
                                <button type="submit" class="btn btn-warning flex-grow-1">
                                    <i class="bi bi-check-lg me-1"></i>Guardar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
// Datos para los selectores visuales
$monedas = [
    'COP' => ['nombre' => 'Peso Colombiano', 'bandera' => '🇨🇴', 'simbolo' => '$'],
    'USD' => ['nombre' => 'Dólar Estadounidense', 'bandera' => '🇺🇸', 'simbolo' => '$'],
    'EUR' => ['nombre' => 'Euro', 'bandera' => '🇪🇺', 'simbolo' => '€'],
    'MXN' => ['nombre' => 'Peso Mexicano', 'bandera' => '🇲🇽', 'simbolo' => '$'],
    'ARS' => ['nombre' => 'Peso Argentino', 'bandera' => '🇦🇷', 'simbolo' => '$'],
    'PEN' => ['nombre' => 'Sol Peruano', 'bandera' => '🇵🇪', 'simbolo' => 'S/'],
    'CLP' => ['nombre' => 'Peso Chileno', 'bandera' => '🇨🇱', 'simbolo' => '$'],
];

$temas = [
    'light' => ['nombre' => 'Claro', 'icono' => 'bi-sun-fill', 'color' => '#F39C12', 'desc' => 'Fondo blanco, texto oscuro'],
    'dark' => ['nombre' => 'Oscuro', 'icono' => 'bi-moon-fill', 'color' => '#5D6D7E', 'desc' => 'Fondo oscuro, texto claro'],
    'auto' => ['nombre' => 'Automático', 'icono' => 'bi-circle-half', 'color' => '#55A5C8', 'desc' => 'Según tu sistema'],
];

$monedaActual = $config['moneda_principal'] ?? 'COP';
$temaActual = $config['tema'] ?? 'light';
?>
    <div class="col-lg-6">
        <!-- Preferencias -->
        <div class="card settings-card fade-in-up" style="animation-delay: 0.3s;">
            <div class="card-header">
                <h6><i class="bi bi-gear text-success"></i>Preferencias</h6>
            </div>
            <div class="card-body">
                <form method="POST" id="formPreferencias">
                    <input type="hidden" name="action" value="preferencias">
                    <input type="hidden" name="moneda_principal" id="moneda_principal" value="<?= $monedaActual ?>">
                    <input type="hidden" name="tema" id="tema" value="<?= $temaActual ?>">
                    
                    <!-- Selector Moneda -->
                    <div class="mb-3">
                        <label class="form-label">Moneda principal</label>
                        <button type="button" class="pref-selector-btn" data-bs-toggle="modal" data-bs-target="#modalMoneda">
                            <div class="pref-icon">
                                <span id="monedaBandera"><?= $monedas[$monedaActual]['bandera'] ?></span>
                            </div>
                            <div class="pref-info">
                                <span id="monedaNombre"><?= $monedas[$monedaActual]['nombre'] ?></span>
                                <small id="monedaCodigo"><?= $monedaActual ?></small>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </button>
                    </div>
                    
                    <!-- Selector Tema -->
                    <div class="mb-4">
                        <label class="form-label">Tema de la aplicación</label>
                        <button type="button" class="pref-selector-btn" data-bs-toggle="modal" data-bs-target="#modalTema">
                            <div class="pref-icon" id="temaIconWrapper" style="background-color: <?= $temas[$temaActual]['color'] ?>20;">
                                <i class="bi <?= $temas[$temaActual]['icono'] ?>" id="temaIcono" style="color: <?= $temas[$temaActual]['color'] ?>;"></i>
                            </div>
                            <div class="pref-info">
                                <span id="temaNombre"><?= $temas[$temaActual]['nombre'] ?></span>
                                <small id="temaDesc"><?= $temas[$temaActual]['desc'] ?></small>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </button>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="form-switch-custom">
                        <label for="notif_email">
                            <span>Notificaciones por email</span>
                            <small>Recibe alertas de presupuestos y recordatorios</small>
                        </label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notif_email" 
                                   name="notificaciones_email" <?= $config['notificaciones_email'] ? 'checked' : '' ?>>
                        </div>
                    </div>
                    
                    <div class="form-switch-custom">
                        <label for="notif_push">
                            <span>Notificaciones push</span>
                            <small>Alertas en tiempo real en el navegador</small>
                        </label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notif_push" 
                                   name="notificaciones_push" <?= $config['notificaciones_push'] ? 'checked' : '' ?>>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100 mt-3">
                        <i class="bi bi-check-lg me-1"></i>Guardar Preferencias
                    </button>
                </form>
            </div>
        </div>

        <!-- Zona de peligro -->
        <div class="danger-zone fade-in-up" style="animation-delay: 0.4s;">
            <h6><i class="bi bi-exclamation-triangle me-2"></i>Zona de Peligro</h6>
            <p class="text-muted small mb-3">
                Estas acciones son irreversibles. Procede con precaución.
            </p>
            <button type="button" class="btn btn-outline-danger w-100" 
                    onclick="alert('Esta función estará disponible próximamente')">
                <i class="bi bi-trash me-1"></i>Eliminar mi cuenta
            </button>
        </div>
    </div>
</div>

<!-- Modal Moneda -->
<div class="modal fade" id="modalMoneda" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="bi bi-currency-exchange me-2"></i>Seleccionar Moneda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php foreach ($monedas as $codigo => $moneda): ?>
                <div class="pref-option <?= $monedaActual === $codigo ? 'selected' : '' ?>"
                     data-codigo="<?= $codigo ?>"
                     data-nombre="<?= $moneda['nombre'] ?>"
                     data-bandera="<?= $moneda['bandera'] ?>"
                     onclick="seleccionarMoneda(this)">
                    <div class="pref-option-icon">
                        <span style="font-size: 24px;"><?= $moneda['bandera'] ?></span>
                    </div>
                    <div class="pref-option-info">
                        <strong><?= $moneda['nombre'] ?></strong>
                        <small><?= $codigo ?> (<?= $moneda['simbolo'] ?>)</small>
                    </div>
                    <?php if ($monedaActual === $codigo): ?>
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tema -->
<div class="modal fade" id="modalTema" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="bi bi-palette me-2"></i>Seleccionar Tema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php foreach ($temas as $codigo => $tema): ?>
                <div class="pref-option <?= $temaActual === $codigo ? 'selected' : '' ?>"
                     data-codigo="<?= $codigo ?>"
                     data-nombre="<?= $tema['nombre'] ?>"
                     data-icono="<?= $tema['icono'] ?>"
                     data-color="<?= $tema['color'] ?>"
                     data-desc="<?= $tema['desc'] ?>"
                     onclick="seleccionarTema(this)">
                    <div class="pref-option-icon" style="background-color: <?= $tema['color'] ?>20;">
                        <i class="bi <?= $tema['icono'] ?>" style="color: <?= $tema['color'] ?>;"></i>
                    </div>
                    <div class="pref-option-info">
                        <strong><?= $tema['nombre'] ?></strong>
                        <small><?= $tema['desc'] ?></small>
                    </div>
                    <?php if ($temaActual === $codigo): ?>
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalMoneda = new bootstrap.Modal(document.getElementById('modalMoneda'));
    const modalTema = new bootstrap.Modal(document.getElementById('modalTema'));
    
    // Toggle password visibility
    window.togglePassword = function(inputId) {
        const input = document.getElementById(inputId);
        const btn = input.nextElementSibling;
        const icon = btn.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    };
    
    // Hacer funciones globales
    window.seleccionarMoneda = function(element) {
        const codigo = element.dataset.codigo;
        const nombre = element.dataset.nombre;
        const bandera = element.dataset.bandera;
        
        // Actualizar hidden input
        document.getElementById('moneda_principal').value = codigo;
        
        // Actualizar botón
        document.getElementById('monedaBandera').textContent = bandera;
        document.getElementById('monedaNombre').textContent = nombre;
        document.getElementById('monedaCodigo').textContent = codigo;
        
        // Actualizar selección visual
        document.querySelectorAll('#modalMoneda .pref-option').forEach(opt => {
            opt.classList.remove('selected');
            const check = opt.querySelector('.bi-check-circle-fill');
            if (check) check.remove();
        });
        element.classList.add('selected');
        
        // Agregar check
        const checkIcon = document.createElement('i');
        checkIcon.className = 'bi bi-check-circle-fill text-success';
        element.appendChild(checkIcon);
        
        modalMoneda.hide();
        
        // Guardar preferencias automáticamente
        document.getElementById('formPreferencias').submit();
    };
    
    window.seleccionarTema = function(element) {
        const codigo = element.dataset.codigo;
        const nombre = element.dataset.nombre;
        const icono = element.dataset.icono;
        const color = element.dataset.color;
        const desc = element.dataset.desc;
        
        // Actualizar hidden input
        document.getElementById('tema').value = codigo;
        
        // Actualizar botón
        document.getElementById('temaNombre').textContent = nombre;
        document.getElementById('temaDesc').textContent = desc;
        document.getElementById('temaIconWrapper').style.backgroundColor = color + '20';
        document.getElementById('temaIcono').className = 'bi ' + icono;
        document.getElementById('temaIcono').style.color = color;
        
        // Actualizar selección visual
        document.querySelectorAll('#modalTema .pref-option').forEach(opt => {
            opt.classList.remove('selected');
            const check = opt.querySelector('.bi-check-circle-fill');
            if (check) check.remove();
        });
        element.classList.add('selected');
        
        // Agregar check
        const checkIcon = document.createElement('i');
        checkIcon.className = 'bi bi-check-circle-fill text-success';
        element.appendChild(checkIcon);
        
        modalTema.hide();
        
        // Guardar preferencias automáticamente
        document.getElementById('formPreferencias').submit();
    };
});
</script>
<?php $extraScripts = ob_get_clean(); ?>

