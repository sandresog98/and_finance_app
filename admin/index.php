<?php
/**
 * AND FINANCE APP - Admin Router
 * Router principal de la interfaz de administración
 */

// Iniciar output buffering para permitir redirecciones
ob_start();

require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/utils/session.php';

// Verificar autenticación
requireAdminAuth();

// Obtener módulo y página solicitados
$module = $_GET['module'] ?? '';
$page = $_GET['page'] ?? 'index';
$action = $_GET['action'] ?? '';

// Variable para el módulo actual (para el sidebar)
$currentModule = $module;

// Si no hay módulo, mostrar dashboard
if (empty($module)) {
    $pageTitle = 'Dashboard';
    require_once __DIR__ . '/views/layouts/header.php';
    require_once __DIR__ . '/pages/dashboard.php';
    require_once __DIR__ . '/views/layouts/footer.php';
    ob_end_flush();
    exit;
}

// Validar que el módulo exista
$modulePath = ADMIN_MODULES . '/' . $module;
if (!is_dir($modulePath)) {
    setFlashMessage('error', 'El módulo solicitado no existe');
    header('Location: index.php');
    ob_end_flush();
    exit;
}

// Determinar el archivo a cargar
$pageFile = $modulePath . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    // Intentar cargar index por defecto
    $pageFile = $modulePath . '/pages/index.php';
    if (!file_exists($pageFile)) {
        setFlashMessage('error', 'La página solicitada no existe');
        header('Location: index.php');
        ob_end_flush();
        exit;
    }
}

// Cargar el header, la página y el footer
require_once __DIR__ . '/views/layouts/header.php';
require_once $pageFile;
require_once __DIR__ . '/views/layouts/footer.php';

// Enviar el contenido del buffer
ob_end_flush();

