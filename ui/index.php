<?php
/**
 * AND FINANCE APP - User Router
 * Router principal de la interfaz de usuario
 */

ob_start(); // Iniciar output buffering

require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/utils/session.php';

// Verificar autenticación
requireUserAuth();

// Obtener módulo y página solicitados
$module = $_GET['module'] ?? '';
$page = $_GET['page'] ?? 'index';
$action = $_GET['action'] ?? '';

// Variable para el módulo actual (para el sidebar)
$currentModule = $module;

// Si no hay módulo, mostrar dashboard
if (empty($module)) {
    $pageTitle = 'Dashboard';
    $pageSubtitle = 'Resumen de tus finanzas';
    require_once __DIR__ . '/views/layouts/header.php';
    require_once __DIR__ . '/pages/dashboard.php';
    require_once __DIR__ . '/views/layouts/footer.php';
    ob_end_flush();
    exit;
}

// Validar que el módulo exista
$modulePath = UI_MODULES . '/' . $module;
if (!is_dir($modulePath)) {
    setFlashMessage('error', 'El módulo solicitado no existe');
    ob_end_clean();
    header('Location: index.php');
    exit;
}

// Determinar el archivo a cargar
$pageFile = $modulePath . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    // Intentar cargar index por defecto
    $pageFile = $modulePath . '/pages/index.php';
    if (!file_exists($pageFile)) {
        setFlashMessage('error', 'La página solicitada no existe');
        ob_end_clean();
        header('Location: index.php');
        exit;
    }
}

// Cargar el header, la página y el footer
require_once __DIR__ . '/views/layouts/header.php';
require_once $pageFile;
require_once __DIR__ . '/views/layouts/footer.php';

ob_end_flush();
