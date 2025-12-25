<?php
/**
 * AND FINANCE APP - Listado de Categorías
 */

require_once __DIR__ . '/../models/CategoriaModel.php';

$pageTitle = 'Categorías';
$pageSubtitle = 'Organiza tus ingresos y gastos';
$categoriaModel = new CategoriaModel();
$userId = getCurrentUserId();

// Manejar acciones
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'delete' && $id > 0) {
    $categoria = $categoriaModel->getById($id);
    if ($categoria && $categoria['usuario_id'] == $userId && $categoria['es_sistema'] == 0) {
        if ($categoriaModel->delete($id)) {
            setFlashMessage('success', 'Categoría eliminada correctamente');
        } else {
            setFlashMessage('error', 'No se pudo eliminar la categoría. Puede tener transacciones asociadas.');
        }
    }
    ob_end_clean();
    header('Location: ' . uiModuleUrl('categorias'));
    exit;
}

// Obtener categorías del usuario (sin las del sistema)
$categoriasIngreso = $categoriaModel->getAllByUser($userId, 'ingreso', false);
$categoriasEgreso = $categoriaModel->getAllByUser($userId, 'egreso', false);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div></div>
    <a href="<?= uiModuleUrl('categorias', 'crear') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Nueva Categoría
    </a>
</div>

<div class="row g-4">
    <!-- Categorías de Ingreso -->
    <div class="col-lg-6">
        <div class="card fade-in-up">
            <div class="card-header bg-success-subtle">
                <h5 class="mb-0 text-success">
                    <i class="bi bi-arrow-down-circle me-2"></i>
                    Categorías de Ingreso
                    <span class="badge bg-success ms-2"><?= count($categoriasIngreso) ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($categoriasIngreso)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-tags display-4 text-muted"></i>
                    <p class="text-muted mt-3">No hay categorías de ingreso</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($categoriasIngreso as $cat): ?>
                    <div class="list-group-item d-flex align-items-center justify-content-between py-3">
                        <div class="d-flex align-items-center">
                            <div class="categoria-icon me-3" style="background-color: <?= htmlspecialchars($cat['color']) ?>20; color: <?= htmlspecialchars($cat['color']) ?>;">
                                <i class="bi <?= htmlspecialchars($cat['icono']) ?>"></i>
                            </div>
                            <span class="fw-semibold"><?= htmlspecialchars($cat['nombre']) ?></span>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= uiModuleUrl('categorias', 'editar', ['id' => $cat['id']]) ?>" 
                               class="btn btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-outline-danger" 
                                    onclick="confirmDelete('<?= uiModuleUrl('categorias') ?>&action=delete&id=<?= $cat['id'] ?>', '<?= htmlspecialchars($cat['nombre']) ?>')"
                                    title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Categorías de Egreso -->
    <div class="col-lg-6">
        <div class="card fade-in-up" style="animation-delay: 0.1s;">
            <div class="card-header bg-danger-subtle">
                <h5 class="mb-0 text-danger">
                    <i class="bi bi-arrow-up-circle me-2"></i>
                    Categorías de Gasto
                    <span class="badge bg-danger ms-2"><?= count($categoriasEgreso) ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($categoriasEgreso)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-tags display-4 text-muted"></i>
                    <p class="text-muted mt-3">No hay categorías de gasto</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($categoriasEgreso as $cat): ?>
                    <div class="list-group-item d-flex align-items-center justify-content-between py-3">
                        <div class="d-flex align-items-center">
                            <div class="categoria-icon me-3" style="background-color: <?= htmlspecialchars($cat['color']) ?>20; color: <?= htmlspecialchars($cat['color']) ?>;">
                                <i class="bi <?= htmlspecialchars($cat['icono']) ?>"></i>
                            </div>
                            <span class="fw-semibold"><?= htmlspecialchars($cat['nombre']) ?></span>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= uiModuleUrl('categorias', 'editar', ['id' => $cat['id']]) ?>" 
                               class="btn btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-outline-danger" 
                                    onclick="confirmDelete('<?= uiModuleUrl('categorias') ?>&action=delete&id=<?= $cat['id'] ?>', '<?= htmlspecialchars($cat['nombre']) ?>')"
                                    title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.bg-success-subtle {
    background-color: rgba(154, 208, 130, 0.15) !important;
}

.bg-danger-subtle {
    background-color: rgba(255, 107, 107, 0.15) !important;
}

.categoria-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.list-group-item {
    border-left: none;
    border-right: none;
    transition: all 0.2s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

.list-group-item:first-child {
    border-top: none;
}
</style>

