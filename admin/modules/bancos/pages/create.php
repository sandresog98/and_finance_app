<?php
/**
 * Crear Banco
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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $env = new Env(dirname(__DIR__, 4) . '/.env');
        $db = new Database($env);
        $bankModel = new Bank($db->getConnection());
        
        $nombre = trim($_POST['nombre'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $pais = trim($_POST['pais'] ?? 'Colombia');
        
        if (empty($nombre)) {
            throw new Exception('El nombre es requerido');
        }
        
        $logoUrl = null;
        
        // Procesar logo si se subió
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__, 4) . '/uploads/bancos/';
            
            // Construir webPath con la base del proyecto (igual que en we_are_app)
            $scriptName = $_SERVER['SCRIPT_NAME'];
            $marker = '/and_finance_app/';
            $pos = strpos($scriptName, $marker);
            $webBase = $pos !== false ? substr($scriptName, 0, $pos + strlen($marker)) : '/and_finance_app/';
            $webPath = $webBase . 'uploads/bancos';
            
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
            
            $logoUrl = $result['web_path'];
            error_log("Bank create - Logo URL guardado: $logoUrl, File name: {$result['file_name']}, File path: {$result['file_path']}");
        }
        
        $result = $bankModel->create([
            'nombre' => $nombre,
            'codigo' => $codigo ?: null,
            'pais' => $pais,
            'logo_url' => $logoUrl
        ]);
        
        if ($result['success']) {
            header('Location: index.php?success=1');
            exit;
        } else {
            $error = $result['message'] ?? 'Error al crear el banco';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once dirname(__DIR__, 4) . '/admin/views/layouts/header.php';
require_once dirname(__DIR__, 4) . '/admin/views/layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-plus-circle me-2"></i>Nuevo Banco</h1>
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
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="codigo" class="form-label">Código</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" 
                               placeholder="Ej: BANCOLOMBIA">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="pais" class="form-label">País</label>
                        <input type="text" class="form-control" id="pais" name="pais" value="Colombia">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="logo" class="form-label">Logo</label>
                        <input type="file" class="form-control" id="logo" name="logo" 
                               accept="image/jpeg,image/png,image/jpg">
                        <small class="text-muted">Máximo 5MB. Formatos: JPG, PNG</small>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Guardar
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
