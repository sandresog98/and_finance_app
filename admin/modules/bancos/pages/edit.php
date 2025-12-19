<?php
/**
 * Editar Banco
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
require_once dirname(__DIR__, 4) . '/utils/FileUploadManager.php';
require_once __DIR__ . '/../models/Bank.php';

use Utils\Database;
use Utils\Env;
use Admin\Modules\Bancos\Models\Bank;

$currentPage = 'bancos';
$error = '';
$banco = null;

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit;
}

try {
    $env = new Env(dirname(__DIR__, 4) . '/.env');
    $db = new Database($env);
    $bankModel = new Bank($db->getConnection());
    
    $banco = $bankModel->getById((int)$id);
    
    if (!$banco) {
        header('Location: index.php');
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $pais = trim($_POST['pais'] ?? 'Colombia');
        
        if (empty($nombre)) {
            throw new Exception('El nombre es requerido');
        }
        
        $data = [
            'nombre' => $nombre,
            'codigo' => $codigo ?: null,
            'pais' => $pais
        ];
        
        // Procesar logo si se subió uno nuevo
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__, 4) . '/uploads/bancos/';
            
            // Construir webPath con la base del proyecto
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            
            // Intentar encontrar el patrón /and_finance_app/ en SCRIPT_NAME
            $marker = '/and_finance_app/';
            $webBase = null;
            $pos = strpos($scriptName, $marker);
            if ($pos !== false) {
                $webBase = substr($scriptName, 0, $pos + strlen($marker));
            } else {
                // Intentar encontrar el patrón en REQUEST_URI
                $pos = strpos($requestUri, $marker);
                if ($pos !== false) {
                    $webBase = substr($requestUri, 0, $pos + strlen($marker));
                    $webBase = strtok($webBase, '?'); // Limpiar query string
                } else {
                    // Fallback: usar getBaseUrl() y remover /admin/
                    $baseUrl = getBaseUrl();
                    if (strpos($baseUrl, '/admin/') !== false) {
                        $webBase = str_replace('/admin/', '/', $baseUrl);
                    } else {
                        $webBase = rtrim($baseUrl, '/') . '/';
                    }
                }
            }
            
            $webPath = rtrim($webBase, '/') . '/uploads/bancos';
            
            $result = FileUploadManager::saveUploadedFile(
                $_FILES['logo'],
                $uploadDir,
                [
                    'maxSize' => 5 * 1024 * 1024, // 5MB
                    'allowedExtensions' => ['jpg', 'jpeg', 'png'],
                    'prefix' => 'banco',
                    'createSubdirs' => true,
                    'webPath' => $webPath
                ]
            );
            
            $data['logo_url'] = $result['web_path'];
            error_log("Bank update - Logo URL guardado: {$data['logo_url']}, File name: {$result['file_name']}, File path: {$result['file_path']}");
        }
        
        $result = $bankModel->update((int)$id, $data);
        
        if ($result['success']) {
            header('Location: index.php?success=1');
            exit;
        } else {
            $error = $result['message'] ?? 'Error al actualizar el banco';
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once dirname(__DIR__, 4) . '/admin/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/admin/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-edit me-2"></i>Editar Banco</h1>
        <a href="<?php echo getBaseUrl(); ?>modules/bancos/pages/index.php" class="btn btn-secondary">
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
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nombre" class="form-label">Nombre del Banco <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($banco['nombre']); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="codigo" class="form-label">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" 
                               value="<?php echo htmlspecialchars($banco['codigo'] ?? ''); ?>"
                               placeholder="Ej: BANCOLOMBIA">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="pais" class="form-label">País</label>
                        <input type="text" class="form-control" id="pais" name="pais" 
                               value="<?php echo htmlspecialchars($banco['pais']); ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="logo" class="form-label">Logo</label>
                        <?php if (!empty($banco['logo_url'])): ?>
                        <div class="mb-2">
                            <img src="<?php echo htmlspecialchars(getFileUrl($banco['logo_url'])); ?>" 
                                 alt="Logo actual" 
                                 style="max-width: 100px; max-height: 100px; object-fit: contain; border: 1px solid #dee2e6; padding: 5px; border-radius: 8px;"
                                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect fill=\'%23ddd\' width=\'100\' height=\'100\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3EImagen no disponible%3C/text%3E%3C/svg%3E';">
                        </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="logo" name="logo" 
                               accept="image/jpeg,image/png,image/jpg">
                        <small class="text-muted">Máximo 5MB. Formatos: JPG, PNG. Dejar vacío para mantener el actual.</small>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Guardar Cambios
                    </button>
                    <a href="<?php echo getBaseUrl(); ?>modules/bancos/pages/index.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 4) . '/admin/views/layouts/footer.php'; ?>
