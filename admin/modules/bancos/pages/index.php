<?php
/**
 * AND FINANCE APP - Listado de Bancos
 */

require_once __DIR__ . '/../models/BancoModel.php';

$pageTitle = 'Gestión de Bancos';
$bancoModel = new BancoModel();

// Manejar acciones
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'toggle' && $id > 0) {
    if ($bancoModel->toggleEstado($id)) {
        setFlashMessage('success', 'Estado del banco actualizado correctamente');
    } else {
        setFlashMessage('error', 'No se pudo actualizar el estado del banco');
    }
    ob_end_clean();
    header('Location: ' . moduleUrl('bancos'));
    exit;
}

if ($action === 'delete' && $id > 0) {
    if ($bancoModel->delete($id)) {
        setFlashMessage('success', 'Banco eliminado correctamente');
    } else {
        setFlashMessage('error', 'No se puede eliminar el banco porque tiene cuentas asociadas');
    }
    ob_end_clean();
    header('Location: ' . moduleUrl('bancos'));
    exit;
}

// Obtener todos los bancos
$bancos = $bancoModel->getAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Bancos</li>
            </ol>
        </nav>
    </div>
    <a href="<?= moduleUrl('bancos', 'crear') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Nuevo Banco
    </a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-bank me-2"></i>
            Listado de Bancos
            <span class="badge bg-primary ms-2"><?= count($bancos) ?></span>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="tablaBancos">
                <thead>
                    <tr>
                        <th width="60">Logo</th>
                        <th>Nombre</th>
                        <th>Código</th>
                        <th width="80">Color</th>
                        <th width="100">Orden</th>
                        <th width="100">Estado</th>
                        <th width="150">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bancos)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="bi bi-bank fs-1 text-muted d-block mb-3"></i>
                            <p class="text-muted mb-3">No hay bancos registrados</p>
                            <a href="<?= moduleUrl('bancos', 'crear') ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-lg me-1"></i>Agregar primer banco
                            </a>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($bancos as $banco): ?>
                        <tr>
                            <td>
                                <?php if ($banco['logo']): ?>
                                    <img src="<?= UPLOADS_URL . '/bancos/' . htmlspecialchars($banco['logo']) ?>" 
                                         alt="<?= htmlspecialchars($banco['nombre']) ?>"
                                         class="banco-logo"
                                         onerror="this.src='<?= assetUrl('img/bank-placeholder.png') ?>'">
                                <?php else: ?>
                                    <div class="banco-logo-placeholder" 
                                         style="background-color: <?= htmlspecialchars($banco['color_primario'] ?? '#B1BCBF') ?>">
                                        <?= strtoupper(substr($banco['nombre'], 0, 2)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($banco['nombre']) ?></strong>
                            </td>
                            <td>
                                <?php if ($banco['codigo']): ?>
                                    <code><?= htmlspecialchars($banco['codigo']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($banco['color_primario']): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="color-preview" 
                                              style="background-color: <?= htmlspecialchars($banco['color_primario']) ?>"></span>
                                        <small><?= htmlspecialchars($banco['color_primario']) ?></small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= $banco['orden'] ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $banco['estado'] == 1 ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $banco['estado'] == 1 ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= moduleUrl('bancos', 'editar') ?>&id=<?= $banco['id'] ?>" 
                                       class="btn btn-outline-primary" 
                                       title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?= moduleUrl('bancos') ?>&action=toggle&id=<?= $banco['id'] ?>" 
                                       class="btn btn-outline-<?= $banco['estado'] == 1 ? 'warning' : 'success' ?>" 
                                       title="<?= $banco['estado'] == 1 ? 'Desactivar' : 'Activar' ?>">
                                        <i class="bi bi-<?= $banco['estado'] == 1 ? 'pause' : 'play' ?>"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            onclick="confirmDelete('<?= moduleUrl('bancos') ?>&action=delete&id=<?= $banco['id'] ?>', '<?= htmlspecialchars($banco['nombre']) ?>')"
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.banco-logo {
    width: 40px;
    height: 40px;
    object-fit: contain;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    padding: 4px;
    background: white;
}

.banco-logo-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 12px;
}

.color-preview {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid rgba(0,0,0,0.1);
}
</style>

<?php
$extraScripts = <<<SCRIPT
<script>
$(document).ready(function() {
    $('#tablaBancos').DataTable({
        order: [[4, 'asc']],
        columnDefs: [
            { orderable: false, targets: [0, 6] }
        ]
    });
});
</script>
SCRIPT;
?>

