<?php
/**
 * Listado de Categorías
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

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $categoryModel = new Category($db->getConnection());
    
    $categorias = $categoryModel->getAllByUser($userId);
    
    // Separar por tipo
    $ingresos = array_filter($categorias, fn($c) => $c['tipo'] === 'ingreso');
    $egresos = array_filter($categorias, fn($c) => $c['tipo'] === 'egreso');
    
} catch (Exception $e) {
    $categorias = [];
    $ingresos = [];
    $egresos = [];
    $error = 'Error al cargar las categorías';
}

require_once dirname(__DIR__, 4) . '/ui/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/ui/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-tags me-2"></i>Mis Categorías</h1>
        <a href="<?php echo getBaseUrl(); ?>modules/categorias/pages/create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Nueva Categoría
        </a>
    </div>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Categorías de Ingresos -->
    <div class="card mb-4">
        <div class="card-header text-white" style="background-color: #198754;">
            <h5 class="mb-0 text-white"><i class="fas fa-arrow-down me-2"></i>Ingresos</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($ingresos)): ?>
            <div class="text-center text-muted py-4">
                No hay categorías de ingresos
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;"></th>
                            <th>Nombre</th>
                            <th style="width: 120px;" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ingresos as $cat): ?>
                        <tr>
                            <td>
                                <?php 
                                $icono = !empty($cat['icono']) ? trim($cat['icono']) : 'fa-tag';
                                // Asegurar que tenga el prefijo 'fas' si no lo tiene
                                if (!empty($icono)) {
                                    if (strpos($icono, 'fas ') === 0 || strpos($icono, 'far ') === 0 || strpos($icono, 'fab ') === 0) {
                                        // Ya tiene prefijo, usar tal cual
                                    } elseif (strpos($icono, 'fa-') === 0) {
                                        // Tiene fa- pero no el prefijo, agregar fas
                                        $icono = 'fas ' . $icono;
                                    } else {
                                        // No tiene fa-, agregar ambos
                                        $icono = 'fas fa-' . $icono;
                                    }
                                } else {
                                    $icono = 'fas fa-tag';
                                }
                                ?>
                                <div class="d-flex align-items-center justify-content-center" 
                                     style="width: 45px; height: 45px; background-color: <?php echo htmlspecialchars($cat['color'] ?? '#39843A'); ?>; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <?php if (!empty($icono)): ?>
                                    <i class="<?php echo htmlspecialchars($icono); ?> text-white" style="font-size: 1.2rem;"></i>
                                    <?php else: ?>
                                    <i class="fas fa-tag text-white" style="font-size: 1.2rem;"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <strong class="me-2"><?php echo htmlspecialchars($cat['nombre']); ?></strong>
                                    <?php if ($cat['es_predeterminada']): ?>
                                    <span class="badge bg-secondary">Predeterminada</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo getBaseUrl(); ?>modules/categorias/pages/edit.php?id=<?php echo $cat['id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteCategory(<?php echo $cat['id']; ?>)" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Categorías de Egresos -->
    <div class="card">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Egresos</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($egresos)): ?>
            <div class="text-center text-muted py-4">
                No hay categorías de egresos
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;"></th>
                            <th>Nombre</th>
                            <th style="width: 120px;" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($egresos as $cat): ?>
                        <tr>
                            <td>
                                <?php 
                                $icono = !empty($cat['icono']) ? trim($cat['icono']) : 'fa-tag';
                                // Asegurar que tenga el prefijo 'fas' si no lo tiene
                                if (!empty($icono)) {
                                    if (strpos($icono, 'fas ') === 0 || strpos($icono, 'far ') === 0 || strpos($icono, 'fab ') === 0) {
                                        // Ya tiene prefijo, usar tal cual
                                    } elseif (strpos($icono, 'fa-') === 0) {
                                        // Tiene fa- pero no el prefijo, agregar fas
                                        $icono = 'fas ' . $icono;
                                    } else {
                                        // No tiene fa-, agregar ambos
                                        $icono = 'fas fa-' . $icono;
                                    }
                                } else {
                                    $icono = 'fas fa-tag';
                                }
                                ?>
                                <div class="d-flex align-items-center justify-content-center" 
                                     style="width: 45px; height: 45px; background-color: <?php echo htmlspecialchars($cat['color'] ?? '#F1B10B'); ?>; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <?php if (!empty($icono)): ?>
                                    <i class="<?php echo htmlspecialchars($icono); ?> text-white" style="font-size: 1.2rem;"></i>
                                    <?php else: ?>
                                    <i class="fas fa-tag text-white" style="font-size: 1.2rem;"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <strong class="me-2"><?php echo htmlspecialchars($cat['nombre']); ?></strong>
                                    <?php if ($cat['es_predeterminada']): ?>
                                    <span class="badge bg-secondary">Predeterminada</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo getBaseUrl(); ?>modules/categorias/pages/edit.php?id=<?php echo $cat['id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteCategory(<?php echo $cat['id']; ?>)" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteCategory(id) {
    if (confirm('¿Está seguro de eliminar esta categoría? Esta acción no se puede deshacer.')) {
        fetch('<?php echo getBaseUrl(); ?>modules/categorias/api/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'No se pudo eliminar la categoría'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar la categoría');
        });
    }
}
</script>

<?php require_once dirname(__DIR__, 4) . '/ui/views/layouts/footer.php'; ?>
