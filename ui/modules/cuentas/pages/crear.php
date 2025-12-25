<?php
/**
 * AND FINANCE APP - Crear Cuenta
 */

require_once __DIR__ . '/../models/CuentaModel.php';

$pageTitle = 'Nueva Cuenta';
$pageSubtitle = 'Agrega una nueva cuenta para administrar tu dinero';
$cuentaModel = new CuentaModel();
$userId = getCurrentUserId();

$errors = [];
$data = [
    'nombre' => '',
    'tipo' => 'efectivo',
    'banco_id' => '',
    'banco_personalizado' => '',
    'saldo_inicial' => 0,
    'color' => '#55A5C8',
    'icono' => 'bi-wallet2',
    'incluir_en_total' => 1,
    'es_predeterminada' => 0
];

// Obtener bancos activos
$db = Database::getInstance();
$bancos = $db->query("SELECT id, nombre, logo, color_primario FROM bancos WHERE estado = 1 ORDER BY orden ASC, nombre ASC")->fetchAll();

// Tipos de cuenta
$tiposCuenta = $cuentaModel->getTiposCuenta();

// Colores disponibles
$colores = [
    '#55A5C8' => 'Azul',
    '#9AD082' => 'Verde',
    '#FF6B6B' => 'Rojo',
    '#F7DC6F' => 'Amarillo',
    '#BB8FCE' => 'Púrpura',
    '#5DADE2' => 'Celeste',
    '#48C9B0' => 'Turquesa',
    '#F5B041' => 'Naranja',
    '#85929E' => 'Gris',
    '#E74C3C' => 'Rojo Intenso',
    '#2ECC71' => 'Verde Esmeralda',
    '#3498DB' => 'Azul Cielo'
];

// Iconos disponibles
$iconos = [
    'bi-wallet2' => 'Billetera',
    'bi-piggy-bank' => 'Alcancía',
    'bi-bank' => 'Banco',
    'bi-credit-card' => 'Tarjeta',
    'bi-cash-stack' => 'Efectivo',
    'bi-coin' => 'Monedas',
    'bi-safe' => 'Caja fuerte',
    'bi-graph-up-arrow' => 'Inversión',
    'bi-house' => 'Casa',
    'bi-car-front' => 'Auto',
    'bi-airplane' => 'Viajes',
    'bi-gift' => 'Regalos',
    'bi-heart' => 'Favoritos',
    'bi-star' => 'Especial',
    'bi-gem' => 'Premium',
    'bi-currency-dollar' => 'Dólar'
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['nombre'] = trim($_POST['nombre'] ?? '');
    $data['tipo'] = $_POST['tipo'] ?? 'efectivo';
    $data['banco_id'] = !empty($_POST['banco_id']) && $_POST['banco_id'] !== 'personalizado' ? (int)$_POST['banco_id'] : null;
    $data['banco_personalizado'] = trim($_POST['banco_personalizado'] ?? '');
    $data['saldo_inicial'] = (float)str_replace(['.', ','], ['', '.'], $_POST['saldo_inicial'] ?? 0);
    $data['color'] = $_POST['color'] ?? '#55A5C8';
    $data['icono'] = $_POST['icono'] ?? 'bi-wallet2';
    $data['incluir_en_total'] = isset($_POST['incluir_en_total']) ? 1 : 0;
    $data['es_predeterminada'] = isset($_POST['es_predeterminada']) ? 1 : 0;
    
    if ($_POST['banco_id'] === 'personalizado') {
        $data['banco_id'] = null;
        if (empty($data['banco_personalizado'])) {
            $errors[] = 'Debes ingresar el nombre del banco personalizado';
        }
    } else {
        $data['banco_personalizado'] = null;
    }
    
    if (empty($data['nombre'])) {
        $errors[] = 'El nombre de la cuenta es obligatorio';
    }
    
    if (empty($errors)) {
        $data['usuario_id'] = $userId;
        
        try {
            $cuentaModel->create($data);
            setFlashMessage('success', 'Cuenta creada correctamente');
            ob_end_clean();
            header('Location: ' . uiModuleUrl('cuentas'));
            exit;
        } catch (Exception $e) {
            $errors[] = 'Error al crear la cuenta: ' . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5">
        <div class="card fade-in-up">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-wallet-fill me-2"></i>
                    Crear Nueva Cuenta
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="formCuenta">
                    <!-- 1. Nombre de la cuenta -->
                    <div class="mb-4">
                        <label for="nombre" class="form-label">
                            Nombre de la cuenta <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control form-control-lg" 
                               id="nombre" 
                               name="nombre" 
                               value="<?= htmlspecialchars($data['nombre']) ?>"
                               placeholder="Ej: Mi Billetera, Ahorros..."
                               required
                               autofocus>
                    </div>
                    
                    <!-- 2. Tipo de cuenta -->
                    <div class="mb-4">
                        <label for="tipo" class="form-label">Tipo de cuenta</label>
                        <select class="form-select" id="tipo" name="tipo">
                            <?php foreach ($tiposCuenta as $key => $tipo): ?>
                            <option value="<?= $key ?>" <?= $data['tipo'] === $key ? 'selected' : '' ?>>
                                <?= $tipo['nombre'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- 3. Banco -->
                    <div class="mb-4">
                        <label class="form-label">Banco</label>
                        <input type="hidden" name="banco_id" id="banco_id" value="<?= htmlspecialchars($data['banco_id']) ?>">
                        <input type="hidden" name="banco_personalizado" id="banco_personalizado_input" value="<?= htmlspecialchars($data['banco_personalizado']) ?>">
                        
                        <button type="button" class="btn btn-outline-secondary w-100 selector-btn" 
                                data-bs-toggle="modal" data-bs-target="#modalBancos">
                            <div class="d-flex align-items-center" id="bancoSeleccionado">
                                <div class="selector-icon">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <span>Sin banco</span>
                            </div>
                        </button>
                    </div>
                    
                    <!-- 4. Nombre del banco (si selecciona otro) -->
                    <div class="mb-4" id="bancoPersonalizadoWrapper" style="display: none;">
                        <label for="banco_personalizado" class="form-label">
                            Nombre del banco <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="banco_personalizado" 
                               placeholder="Ej: Mi Cooperativa, Banco XYZ..."
                               value="<?= htmlspecialchars($data['banco_personalizado']) ?>">
                    </div>
                    
                    <!-- 5. Color y 6. Icono -->
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-4">
                                <label class="form-label">Color</label>
                                <input type="hidden" name="color" id="color_input" value="<?= htmlspecialchars($data['color']) ?>">
                                
                                <button type="button" class="btn btn-outline-secondary w-100 selector-btn" 
                                        data-bs-toggle="modal" data-bs-target="#modalColores">
                                    <div class="d-flex align-items-center" id="colorSeleccionado">
                                        <div class="color-preview" style="background-color: <?= $data['color'] ?>;"></div>
                                        <span><?= $colores[$data['color']] ?? 'Azul' ?></span>
                                    </div>
                                </button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-4">
                                <label class="form-label">Icono</label>
                                <input type="hidden" name="icono" id="icono_input" value="<?= htmlspecialchars($data['icono']) ?>">
                                
                                <button type="button" class="btn btn-outline-secondary w-100 selector-btn" 
                                        data-bs-toggle="modal" data-bs-target="#modalIconos">
                                    <div class="d-flex align-items-center" id="iconoSeleccionado">
                                        <div class="icon-preview">
                                            <i class="bi <?= $data['icono'] ?>"></i>
                                        </div>
                                        <span><?= $iconos[$data['icono']] ?? 'Billetera' ?></span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 7. Saldo Inicial -->
                    <div class="mb-4">
                        <label for="saldo_inicial" class="form-label">Saldo inicial</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" 
                                   class="form-control money-input" 
                                   id="saldo_inicial" 
                                   name="saldo_inicial" 
                                   value="<?= number_format($data['saldo_inicial'], 0, ',', '.') ?>"
                                   placeholder="0"
                                   inputmode="numeric">
                        </div>
                        <small class="text-muted">Ingresa el saldo con el que inicias esta cuenta</small>
                    </div>
                    
                    <!-- 8. Vista previa -->
                    <div class="mb-4">
                        <label class="form-label">Vista previa</label>
                        <div class="preview-cuenta-card">
                            <div class="preview-icon" id="previewIcon" style="background-color: <?= $data['color'] ?>;">
                                <i class="bi <?= $data['icono'] ?>"></i>
                            </div>
                            <div class="preview-info">
                                <h6 class="mb-0" id="previewNombre"><?= $data['nombre'] ?: 'Nombre de la cuenta' ?></h6>
                                <small class="text-muted" id="previewTipo"><?= $tiposCuenta[$data['tipo']]['nombre'] ?? 'Efectivo' ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 9 y 10. Flags -->
                    <div class="mb-4">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="incluir_en_total" name="incluir_en_total"
                                   <?= $data['incluir_en_total'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="incluir_en_total">
                                Incluir en el saldo total
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="es_predeterminada" name="es_predeterminada"
                                   <?= $data['es_predeterminada'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="es_predeterminada">
                                Cuenta predeterminada
                            </label>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- 11. Botones -->
                    <div class="d-flex justify-content-between">
                        <a href="<?= uiModuleUrl('cuentas') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Crear Cuenta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Selección de Banco -->
<div class="modal fade" id="modalBancos" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-bank me-2"></i>Seleccionar Banco
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="buscarBanco" placeholder="Buscar banco...">
                    </div>
                </div>
                
                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <div class="option-card special selected" data-banco-id="" data-banco-nombre="Sin banco">
                            <div class="option-icon"><i class="bi bi-cash-stack"></i></div>
                            <span>Sin banco</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="option-card special personalizado" data-banco-id="personalizado" data-banco-nombre="Otro banco">
                            <div class="option-icon" style="background: rgba(154, 208, 130, 0.2);"><i class="bi bi-plus-circle" style="color: #9AD082;"></i></div>
                            <span>Otro banco...</span>
                        </div>
                    </div>
                </div>
                
                <hr>
                <h6 class="text-muted mb-3">Bancos disponibles</h6>
                
                <div class="row g-2" id="listaBancos">
                    <?php foreach ($bancos as $banco): ?>
                    <div class="col-4 col-md-3 banco-item">
                        <div class="option-card" 
                             data-banco-id="<?= $banco['id'] ?>" 
                             data-banco-nombre="<?= htmlspecialchars($banco['nombre']) ?>"
                             data-banco-logo="<?= $banco['logo'] ? UPLOADS_URL . '/bancos/' . htmlspecialchars($banco['logo']) : '' ?>">
                            <div class="option-icon" style="<?= $banco['color_primario'] ? 'background-color: ' . $banco['color_primario'] . '20;' : '' ?>">
                                <?php if ($banco['logo']): ?>
                                <img src="<?= UPLOADS_URL ?>/bancos/<?= htmlspecialchars($banco['logo']) ?>" alt="">
                                <?php else: ?>
                                <i class="bi bi-bank" style="color: <?= htmlspecialchars($banco['color_primario'] ?? '#55A5C8') ?>"></i>
                                <?php endif; ?>
                            </div>
                            <span><?= htmlspecialchars($banco['nombre']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Selección de Color -->
<div class="modal fade" id="modalColores" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-palette me-2"></i>Seleccionar Color
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3" id="listaColores">
                    <?php foreach ($colores as $hex => $nombre): ?>
                    <div class="col-4 col-md-3">
                        <div class="color-card <?= $data['color'] === $hex ? 'selected' : '' ?>" 
                             data-color="<?= $hex ?>" 
                             data-nombre="<?= $nombre ?>">
                            <div class="color-circle" style="background-color: <?= $hex ?>;"></div>
                            <span><?= $nombre ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Selección de Icono -->
<div class="modal fade" id="modalIconos" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-emoji-smile me-2"></i>Seleccionar Icono
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3" id="listaIconos">
                    <?php foreach ($iconos as $icono => $nombre): ?>
                    <div class="col-4 col-md-3">
                        <div class="icon-card <?= $data['icono'] === $icono ? 'selected' : '' ?>" 
                             data-icono="<?= $icono ?>" 
                             data-nombre="<?= $nombre ?>">
                            <div class="icon-circle"><i class="bi <?= $icono ?>"></i></div>
                            <span><?= $nombre ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Preview Card */
.preview-cuenta-card {
    display: flex;
    align-items: center;
    padding: 16px;
    background: var(--bg-input, #f8f9fa);
    border-radius: 12px;
    border: 1px solid var(--border-color, #e9ecef);
}
.preview-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
    margin-right: 12px;
    flex-shrink: 0;
    transition: all 0.3s ease;
}
.preview-info h6 {
    font-weight: 600;
    color: var(--text-primary, var(--dark-blue));
}

/* Selector Buttons */
.selector-btn {
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid var(--border-color, #dee2e6);
    background: var(--bg-card, white);
    transition: all 0.2s ease;
    text-align: left;
    color: var(--text-primary, #333);
}
.selector-btn:hover {
    border-color: var(--primary-blue);
    background: rgba(85, 165, 200, 0.1);
}
.selector-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--bg-input, #f8f9fa);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    margin-right: 12px;
    flex-shrink: 0;
    overflow: hidden;
}
.selector-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}
.color-preview {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid rgba(0,0,0,0.1);
    margin-right: 12px;
    flex-shrink: 0;
}
.icon-preview {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--primary-blue);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
}

/* Option Cards (Modal) */
.option-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px 8px;
    border-radius: 12px;
    border: 2px solid var(--border-color, #e9ecef);
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    background: var(--bg-card, white);
}
.option-card:hover {
    border-color: var(--primary-blue);
    background: rgba(85, 165, 200, 0.1);
}
.option-card.selected {
    border-color: var(--primary-blue);
    background: rgba(85, 165, 200, 0.15);
}
.option-card.special {
    background: #f8f9fa;
}
.option-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    overflow: hidden;
}
.option-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}
.option-icon i {
    font-size: 20px;
    color: var(--primary-blue);
}
.option-card span {
    font-size: 12px;
    font-weight: 500;
    color: var(--dark-blue);
    line-height: 1.3;
}

/* Color Cards */
.color-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px;
    border-radius: 12px;
    border: 2px solid #e9ecef;
    cursor: pointer;
    transition: all 0.2s ease;
}
.color-card:hover {
    border-color: var(--primary-blue);
    transform: scale(1.05);
}
.color-card.selected {
    border-color: var(--primary-blue);
    background: rgba(85, 165, 200, 0.1);
}
.color-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    margin-bottom: 8px;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.color-card span {
    font-size: 11px;
    font-weight: 600;
    color: var(--dark-blue);
}

/* Icon Cards */
.icon-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px;
    border-radius: 12px;
    border: 2px solid #e9ecef;
    cursor: pointer;
    transition: all 0.2s ease;
}
.icon-card:hover {
    border-color: var(--primary-blue);
    transform: scale(1.05);
}
.icon-card.selected {
    border-color: var(--primary-blue);
    background: rgba(85, 165, 200, 0.1);
}
.icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--primary-blue);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-bottom: 8px;
}
.icon-card span {
    font-size: 11px;
    font-weight: 600;
    color: var(--dark-blue);
}
</style>

<?php ob_start(); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nombreInput = document.getElementById('nombre');
    const tipoSelect = document.getElementById('tipo');
    const bancoIdInput = document.getElementById('banco_id');
    const bancoPersonalizadoInput = document.getElementById('banco_personalizado_input');
    const bancoPersonalizadoField = document.getElementById('banco_personalizado');
    const bancoPersonalizadoWrapper = document.getElementById('bancoPersonalizadoWrapper');
    const bancoSeleccionado = document.getElementById('bancoSeleccionado');
    const colorInput = document.getElementById('color_input');
    const colorSeleccionado = document.getElementById('colorSeleccionado');
    const iconoInput = document.getElementById('icono_input');
    const iconoSeleccionado = document.getElementById('iconoSeleccionado');
    const previewIcon = document.getElementById('previewIcon');
    const previewNombre = document.getElementById('previewNombre');
    const previewTipo = document.getElementById('previewTipo');
    const buscarBanco = document.getElementById('buscarBanco');
    
    const tipos = <?= json_encode($tiposCuenta) ?>;
    const colores = <?= json_encode($colores) ?>;
    const iconos = <?= json_encode($iconos) ?>;
    
    // Actualizar preview
    nombreInput.addEventListener('input', () => previewNombre.textContent = nombreInput.value || 'Nombre de la cuenta');
    tipoSelect.addEventListener('change', () => previewTipo.textContent = tipos[tipoSelect.value]?.nombre || tipoSelect.value);
    
    // Selección de banco
    document.querySelectorAll('#modalBancos .option-card').forEach(card => {
        card.addEventListener('click', function() {
            const bancoId = this.dataset.bancoId;
            const bancoNombre = this.dataset.bancoNombre;
            const bancoLogo = this.dataset.bancoLogo || '';
            
            document.querySelectorAll('#modalBancos .option-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            bancoIdInput.value = bancoId;
            
            if (bancoId === 'personalizado') {
                bancoPersonalizadoWrapper.style.display = 'block';
                bancoSeleccionado.innerHTML = '<div class="selector-icon" style="background: rgba(154, 208, 130, 0.2);"><i class="bi bi-plus-circle" style="color: #9AD082;"></i></div><span>Otro banco...</span>';
            } else {
                bancoPersonalizadoWrapper.style.display = 'none';
                bancoPersonalizadoInput.value = '';
                bancoPersonalizadoField.value = '';
                
                let iconHtml = bancoLogo 
                    ? '<div class="selector-icon"><img src="' + bancoLogo + '" alt=""></div>'
                    : '<div class="selector-icon"><i class="bi bi-' + (bancoId ? 'bank' : 'cash-stack') + '"></i></div>';
                bancoSeleccionado.innerHTML = iconHtml + '<span>' + (bancoNombre || 'Sin banco') + '</span>';
            }
            
            bootstrap.Modal.getInstance(document.getElementById('modalBancos')).hide();
        });
    });
    
    bancoPersonalizadoField.addEventListener('input', function() {
        bancoPersonalizadoInput.value = this.value;
        if (this.value) bancoSeleccionado.querySelector('span').textContent = this.value;
    });
    
    // Selección de color
    document.querySelectorAll('.color-card').forEach(card => {
        card.addEventListener('click', function() {
            const color = this.dataset.color;
            const nombre = this.dataset.nombre;
            
            document.querySelectorAll('.color-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            colorInput.value = color;
            
            colorSeleccionado.innerHTML = '<div class="color-preview" style="background-color: ' + color + ';"></div><span>' + nombre + '</span>';
            previewIcon.style.backgroundColor = color;
            
            bootstrap.Modal.getInstance(document.getElementById('modalColores')).hide();
        });
    });
    
    // Selección de icono
    document.querySelectorAll('.icon-card').forEach(card => {
        card.addEventListener('click', function() {
            const icono = this.dataset.icono;
            const nombre = this.dataset.nombre;
            
            document.querySelectorAll('.icon-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            iconoInput.value = icono;
            
            iconoSeleccionado.innerHTML = '<div class="icon-preview"><i class="bi ' + icono + '"></i></div><span>' + nombre + '</span>';
            previewIcon.innerHTML = '<i class="bi ' + icono + '"></i>';
            
            bootstrap.Modal.getInstance(document.getElementById('modalIconos')).hide();
        });
    });
    
    // Buscador de bancos
    buscarBanco.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.banco-item').forEach(item => {
            const nombre = item.querySelector('span').textContent.toLowerCase();
            item.style.display = nombre.includes(query) ? 'block' : 'none';
        });
    });
    
    document.getElementById('modalBancos').addEventListener('show.bs.modal', () => {
        buscarBanco.value = '';
        document.querySelectorAll('.banco-item').forEach(item => item.style.display = 'block');
    });
});
</script>
<?php $extraScripts = ob_get_clean(); ?>
