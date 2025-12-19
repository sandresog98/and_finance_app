<?php
/**
 * Editar Categoría
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['and_finance_user'])) {
    header('Location: ../../../login.php');
    exit;
}

require_once dirname(__DIR__, 4) . '/ui/config/paths.php';
require_once dirname(__DIR__, 4) . '/utils/Database.php';
require_once dirname(__DIR__, 4) . '/utils/Env.php';
require_once __DIR__ . '/../models/Category.php';

use Utils\Database;
use Utils\Env;
use UI\Modules\Categorias\Models\Category;

$currentPage = 'categorias';
$currentUser = $_SESSION['and_finance_user'];
$userId = $currentUser['id'];
$error = '';
$categoria = null;

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

$icons = Category::getAvailableIcons();

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $categoryModel = new Category($db->getConnection());
    
    $categoria = $categoryModel->getById((int)$id, $userId);
    
    if (!$categoria) {
        header('Location: index.php');
        exit;
    }
    
    // Verificar que pertenece al usuario (ahora permitimos editar todas, incluso predeterminadas)
    if ($categoria['usuario_id'] != $userId) {
        header('Location: index.php?error=no_encontrada');
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo = $_POST['tipo'] ?? 'egreso';
        $icono = $_POST['icono'] ?? null;
        
        if (empty($nombre)) {
            $error = 'El nombre es requerido';
        } else {
            $result = $categoryModel->update((int)$id, $userId, [
                'nombre' => $nombre,
                'tipo' => $tipo,
                'icono' => $icono
            ]);
            
            if ($result['success']) {
                header('Location: index.php?success=1');
                exit;
            } else {
                $error = $result['message'] ?? 'Error al actualizar la categoría';
            }
        }
    }
} catch (Exception $e) {
    $error = 'Error al procesar la solicitud';
    error_log('Edit category error: ' . $e->getMessage());
}

require_once dirname(__DIR__, 4) . '/ui/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/ui/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-edit me-2"></i>Editar Categoría</h1>
        <a href="<?php echo getBaseUrl(); ?>modules/categorias/pages/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver
        </a>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" id="categoryForm">
                <!-- Nombre -->
                <div class="mb-4">
                    <label for="nombre" class="form-label fw-bold">Nombre de la Categoría <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg" id="nombre" name="nombre" required
                           value="<?php echo htmlspecialchars($categoria['nombre']); ?>"
                           placeholder="Ej: Supermercado, Ropa, etc.">
                </div>
                
                <!-- Tipo: Toggle Button -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block mb-3">Tipo <span class="text-danger">*</span></label>
                    <input type="hidden" id="tipo" name="tipo" value="<?php echo htmlspecialchars($categoria['tipo']); ?>">
                    <div class="d-flex gap-3">
                        <button type="button" class="btn tipo-toggle-btn flex-fill p-4 rounded-custom shadow-sm" 
                                data-tipo="ingreso" 
                                id="btn-ingreso"
                                style="font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease;">
                            <i class="fas fa-arrow-down me-2"></i>Ingreso
                        </button>
                        <button type="button" class="btn tipo-toggle-btn flex-fill p-4 rounded-custom shadow-sm" 
                                data-tipo="egreso" 
                                id="btn-egreso"
                                style="font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease;">
                            <i class="fas fa-arrow-up me-2"></i>Egreso
                        </button>
                    </div>
                </div>
                
                <!-- Icono: Botón con Modal -->
                <div class="mb-4">
                    <label class="form-label fw-bold d-block mb-3">Icono</label>
                    <input type="hidden" id="icono" name="icono" value="<?php echo htmlspecialchars($categoria['icono'] ?? ''); ?>">
                    <button type="button" class="btn btn-outline-primary btn-lg w-100 p-4 rounded-custom shadow-sm" 
                            data-bs-toggle="modal" 
                            data-bs-target="#iconModal"
                            style="border: 2px dashed var(--primary-color); background: #f8f9fa;">
                        <div class="d-flex align-items-center justify-content-center gap-3">
                            <div id="iconPreview" class="d-flex align-items-center justify-content-center" 
                                 style="width: 60px; height: 60px; background-color: <?php echo $categoria['tipo'] === 'ingreso' ? '#39843A' : '#F1B10B'; ?>; border-radius: 12px;">
                                <i class="fas <?php echo htmlspecialchars($categoria['icono'] ?? 'fa-tag'); ?> text-white" style="font-size: 1.8rem;"></i>
                            </div>
                            <div class="text-start">
                                <div class="fw-bold"><?php echo !empty($categoria['icono']) ? 'Icono seleccionado' : 'Seleccionar icono'; ?></div>
                                <small class="text-muted">Haz clic para elegir un icono</small>
                            </div>
                            <i class="fas fa-chevron-right ms-auto"></i>
                        </div>
                    </button>
                </div>
                
                <!-- Botones de acción -->
                <div class="mt-4 pt-3 border-top d-flex gap-3">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-save me-2"></i>Guardar Cambios
                    </button>
                    <a href="<?php echo getBaseUrl(); ?>modules/categorias/pages/index.php" class="btn btn-secondary btn-lg px-4">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Selección de Iconos -->
<div class="modal fade" id="iconModal" tabindex="-1" aria-labelledby="iconModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-custom">
            <div class="modal-header" style="background: var(--primary-color); color: white;">
                <h5 class="modal-title fw-bold" id="iconModalLabel">
                    <i class="fas fa-icons me-2"></i>Seleccionar Icono
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="icon-grid" style="max-height: 400px; overflow-y: auto;">
                    <div class="row g-3">
                        <div class="col-3 col-md-2">
                            <button type="button" class="icon-select-btn w-100 p-3 rounded-custom border-0 bg-light shadow-sm" 
                                    data-icon="" 
                                    style="transition: all 0.2s ease; aspect-ratio: 1;"
                                    title="Sin icono">
                                <i class="fas fa-times text-muted" style="font-size: 1.5rem;"></i>
                            </button>
                        </div>
                        <?php foreach ($icons as $iconValue => $iconName): ?>
                        <div class="col-3 col-md-2">
                            <button type="button" class="icon-select-btn w-100 p-3 rounded-custom border-0 bg-light shadow-sm" 
                                    data-icon="<?php echo htmlspecialchars($iconValue); ?>" 
                                    style="transition: all 0.2s ease; aspect-ratio: 1;"
                                    title="<?php echo htmlspecialchars($iconName); ?>">
                                <i class="fas <?php echo htmlspecialchars($iconValue); ?>" style="font-size: 1.5rem;"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
.tipo-toggle-btn {
    background: #e9ecef;
    color: #495057;
    border: 2px solid transparent;
}

.tipo-toggle-btn.active {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15) !important;
}

.tipo-toggle-btn[data-tipo="ingreso"].active {
    background: linear-gradient(135deg, #39843A 0%, #2d6a2e 100%);
    color: white;
    border-color: #39843A;
}

.tipo-toggle-btn[data-tipo="egreso"].active {
    background: linear-gradient(135deg, #F1B10B 0%, #d19e0a 100%);
    color: #1F4738;
    border-color: #F1B10B;
}

.icon-select-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    background: var(--primary-color) !important;
    color: white !important;
}

.icon-select-btn.selected {
    background: var(--primary-color) !important;
    color: white !important;
    border: 2px solid var(--third-color) !important;
    transform: scale(1.05);
}

.icon-grid::-webkit-scrollbar {
    width: 8px;
}

.icon-grid::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.icon-grid::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 10px;
}

.icon-grid::-webkit-scrollbar-thumb:hover {
    background: var(--third-color);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoInput = document.getElementById('tipo');
    const tipoActual = tipoInput.value;
    const btnIngreso = document.getElementById('btn-ingreso');
    const btnEgreso = document.getElementById('btn-egreso');
    const iconInput = document.getElementById('icono');
    const iconButtons = document.querySelectorAll('.icon-select-btn');
    const iconPreview = document.getElementById('iconPreview');
    
    // Función para obtener color según tipo
    function getColorByTipo(tipo) {
        return tipo === 'ingreso' ? '#39843A' : '#F1B10B';
    }
    
    // Función para actualizar preview
    function updatePreview() {
        const tipo = tipoInput.value;
        const color = getColorByTipo(tipo);
        const iconValue = iconInput.value || 'fa-tag';
        
        // Actualizar preview del botón de icono
        iconPreview.style.backgroundColor = color;
        iconPreview.querySelector('i').className = 'fas ' + iconValue + ' text-white';
    }
    
    // Inicializar tipo toggle
    if (tipoActual === 'ingreso') {
        btnIngreso.classList.add('active');
    } else {
        btnEgreso.classList.add('active');
    }
    
    // Toggle tipo
    [btnIngreso, btnEgreso].forEach(btn => {
        btn.addEventListener('click', function() {
            const tipo = this.getAttribute('data-tipo');
            tipoInput.value = tipo;
            
            // Actualizar botones
            btnIngreso.classList.remove('active');
            btnEgreso.classList.remove('active');
            this.classList.add('active');
            
            // Actualizar preview
            updatePreview();
        });
    });
    
    // Selección de iconos en el modal
    iconButtons.forEach(btn => {
        const iconValue = btn.getAttribute('data-icon');
        const currentIcon = iconInput.value;
        
        // Marcar icono seleccionado
        if (iconValue === currentIcon || (!currentIcon && iconValue === '')) {
            btn.classList.add('selected');
        }
        
        btn.addEventListener('click', function() {
            // Remover selección anterior
            iconButtons.forEach(b => b.classList.remove('selected'));
            
            // Agregar selección nueva
            this.classList.add('selected');
            iconInput.value = iconValue;
            
            // Actualizar preview
            updatePreview();
            
            // Cerrar modal después de un breve delay para mostrar la selección
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('iconModal'));
                if (modal) {
                    modal.hide();
                }
            }, 300);
        });
    });
    
    // Inicializar preview
    updatePreview();
});
</script>

<?php require_once dirname(__DIR__, 4) . '/ui/views/layouts/footer.php'; ?>
