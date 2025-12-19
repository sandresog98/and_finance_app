<?php
// paths.php debe ser cargado antes de incluir este header
if (!function_exists('getBaseUrl')) {
    require_once dirname(__DIR__, 2) . '/config/paths.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'And Finance App'; ?></title>
    <link rel="icon" href="<?php echo getAssetUrl('img/favicon.ico'); ?>" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php 
    $cssPath = dirname(__DIR__, 2) . '/../assets/css/common.css';
    $cssVersion = file_exists($cssPath) ? '?v=' . filemtime($cssPath) : '';
    ?>
    <link href="<?php echo getAssetUrl('css/common.css') . $cssVersion; ?>" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<!-- Header móvil fijo -->
<header class="mobile-header d-md-none">
    <div class="d-flex align-items-center justify-content-between w-100 px-3">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="d-flex align-items-center">
            <i class="fas fa-wallet me-2"></i>
            <span class="fw-bold">And Finance</span>
        </div>
        <div style="width: 45px;"></div> <!-- Spacer para centrar el título -->
    </div>
</header>
<!-- Overlay para cerrar sidebar en móviles -->
<div class="sidebar-overlay d-md-none" id="sidebarOverlay"></div>
