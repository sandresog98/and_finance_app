<?php
/**
 * Listado de Bancos
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['and_finance_user']) || $_SESSION['and_finance_user']['rol'] !== 'admin') {
    header('Location: ../../../ui/login.php');
    exit;
}

require_once dirname(__DIR__, 4) . '/admin/config/paths.php';
require_once dirname(__DIR__, 4) . '/utils/Database.php';
require_once dirname(__DIR__, 4) . '/utils/Env.php';
require_once __DIR__ . '/../models/Bank.php';

use Utils\Database;
use Utils\Env;
use Admin\Modules\Bancos\Models\Bank;

$currentPage = 'bancos';

try {
    $envPath = dirname(__DIR__, 4) . '/.env';
    if (!file_exists($envPath)) {
        throw new Exception('Archivo .env no encontrado. Por favor, crea el archivo .env basado en env.example');
    }
    
    $env = new Env($envPath);
    $db = new Database($env);
    $bankModel = new Bank($db->getConnection());
    
    $bancos = $bankModel->getAll();
} catch (Exception $e) {
    $bancos = [];
    $error = 'Error al cargar los bancos: ' . $e->getMessage();
    error_log('Bancos index error: ' . $e->getMessage());
}

require_once dirname(__DIR__, 4) . '/admin/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/admin/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-university me-2"></i>Gestión de Bancos</h1>
        <a href="<?php echo getBaseUrl(); ?>modules/bancos/pages/create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Nuevo Banco
        </a>
    </div>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>País</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bancos)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                No hay bancos registrados
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($bancos as $banco): ?>
                        <tr>
                            <td>
                                <?php if (!empty($banco['logo_url'])): ?>
                                <img src="<?php echo htmlspecialchars(getFileUrl($banco['logo_url'])); ?>" 
                                     alt="<?php echo htmlspecialchars($banco['nombre']); ?>" 
                                     style="max-width: 50px; max-height: 50px; object-fit: contain;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                <i class="fas fa-university text-muted fa-2x" style="display: none;"></i>
                                <?php else: ?>
                                <i class="fas fa-university text-muted fa-2x"></i>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($banco['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($banco['codigo'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($banco['pais']); ?></td>
                            <td>
                                <?php if ($banco['estado_activo']): ?>
                                <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo getBaseUrl(); ?>modules/bancos/pages/edit.php?id=<?php echo $banco['id']; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" 
                                        class="btn btn-sm btn-danger" 
                                        onclick="deleteBank(<?php echo $banco['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function deleteBank(id) {
    if (confirm('¿Está seguro de eliminar este banco?')) {
        fetch('<?php echo getBaseUrl(); ?>modules/bancos/api/delete.php', {
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
                alert('Error: ' + (data.message || 'No se pudo eliminar el banco'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar el banco');
        });
    }
}
</script>

<?php require_once dirname(__DIR__, 4) . '/admin/views/layouts/footer.php'; ?>
