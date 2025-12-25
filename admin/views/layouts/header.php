<?php
/**
 * AND FINANCE APP - Admin Header Layout
 */

// Verificar autenticación
require_once __DIR__ . '/../../utils/session.php';
requireAdminAuth();

$currentAdmin = getCurrentAdmin();
$flashMessage = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= APP_NAME ?> Admin</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= assetUrl('favicons/favicon.ico') ?>">
    
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?= assetUrl('css/admin.css') ?>" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #55A5C8;
            --secondary-green: #9AD082;
            --tertiary-gray: #B1BCBF;
            --dark-blue: #35719E;
            --sidebar-width: 280px;
            --header-height: 70px;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f4f7fa;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--dark-blue) 0%, #2a5a7e 100%);
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-brand h2 {
            color: white;
            font-weight: 800;
            font-size: 24px;
            margin: 0;
        }
        
        .sidebar-brand span {
            color: var(--secondary-green);
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 1px;
        }
        
        .sidebar-nav {
            padding: 20px 15px;
        }
        
        .nav-section {
            color: rgba(255, 255, 255, 0.5);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin: 20px 10px 10px;
        }
        
        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            color: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 500;
        }
        
        .sidebar-nav .nav-link i {
            font-size: 20px;
            margin-right: 12px;
            width: 24px;
            text-align: center;
        }
        
        .sidebar-nav .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar-nav .nav-link.active {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-green));
            color: white;
            box-shadow: 0 4px 15px rgba(85, 165, 200, 0.3);
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        /* Top Header */
        .top-header {
            height: var(--header-height);
            background: white;
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .page-title {
            font-weight: 700;
            font-size: 22px;
            color: var(--dark-blue);
            margin: 0;
        }
        
        .user-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            border-radius: 50px;
            background: #f8f9fa;
            border: none;
            transition: all 0.3s ease;
        }
        
        .user-dropdown .dropdown-toggle:hover {
            background: #e9ecef;
        }
        
        .user-dropdown .dropdown-toggle::after {
            display: none;
        }
        
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .user-info {
            text-align: left;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--dark-blue);
        }
        
        .user-role {
            font-size: 11px;
            color: var(--tertiary-gray);
        }
        
        /* Content Area */
        .content-area {
            padding: 30px;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #f0f0f0;
            padding: 20px 25px;
            border-radius: 16px 16px 0 0 !important;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 700;
            color: var(--dark-blue);
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(85, 165, 200, 0.4);
            background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--secondary-green), #7ab85c);
            border: none;
        }
        
        .btn-outline-primary {
            color: var(--primary-blue);
            border: 2px solid var(--primary-blue);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        /* Tables */
        .table {
            margin: 0;
        }
        
        .table thead th {
            background: #f8fafc;
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 15px;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-color: #f0f0f0;
        }
        
        /* Forms */
        .form-label {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(85, 165, 200, 0.15);
        }
        
        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .badge-active {
            background: rgba(154, 208, 130, 0.2);
            color: #5a9a3e;
        }
        
        .badge-inactive {
            background: rgba(177, 188, 191, 0.2);
            color: #6c757d;
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--dark-blue);
            padding: 5px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }
        
        @media (max-width: 576px) {
            .top-header {
                padding: 0 15px;
            }
            
            .content-area {
                padding: 15px;
            }
            
            .page-title {
                font-size: 18px;
            }
            
            .user-info {
                display: none;
            }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--tertiary-gray);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-blue);
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-wallet2" style="font-size: 32px; color: white;"></i>
            <h2><?= APP_NAME ?></h2>
            <span>Panel de Administración</span>
        </div>
        
        <div class="sidebar-nav">
            <p class="nav-section">Principal</p>
            
            <a href="<?= adminUrl('index.php') ?>" class="nav-link <?= ($currentModule ?? '') === '' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i>
                Dashboard
            </a>
            
            <p class="nav-section">Gestión</p>
            
            <a href="<?= moduleUrl('bancos') ?>" class="nav-link <?= ($currentModule ?? '') === 'bancos' ? 'active' : '' ?>">
                <i class="bi bi-bank"></i>
                Bancos
            </a>
            
            <a href="<?= moduleUrl('usuarios') ?>" class="nav-link <?= ($currentModule ?? '') === 'usuarios' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                Usuarios
            </a>
            
            <a href="<?= moduleUrl('categorias') ?>" class="nav-link <?= ($currentModule ?? '') === 'categorias' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                Categorías
            </a>
            
            <p class="nav-section">Sistema</p>
            
            <a href="<?= moduleUrl('reportes') ?>" class="nav-link <?= ($currentModule ?? '') === 'reportes' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                Reportes
            </a>
            
            <a href="<?= moduleUrl('configuracion') ?>" class="nav-link <?= ($currentModule ?? '') === 'configuracion' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                Configuración
            </a>
            
            <a href="<?= adminUrl('logout.php') ?>" class="nav-link text-danger mt-4">
                <i class="bi bi-box-arrow-left"></i>
                Cerrar Sesión
            </a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title"><?= $pageTitle ?? 'Dashboard' ?></h1>
            </div>
            
            <div class="dropdown user-dropdown">
                <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-avatar">
                        <?= strtoupper(substr($currentAdmin['nombre'], 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($currentAdmin['nombre']) ?></div>
                        <div class="user-role">Administrador</div>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="<?= moduleUrl('perfil') ?>">
                            <i class="bi bi-person me-2"></i> Mi Perfil
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= adminUrl('logout.php') ?>">
                            <i class="bi bi-box-arrow-left me-2"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="content-area">
            <?php if ($flashMessage): ?>
            <div class="alert alert-<?= $flashMessage['type'] === 'success' ? 'success' : ($flashMessage['type'] === 'error' ? 'danger' : 'info') ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $flashMessage['type'] === 'success' ? 'check-circle' : ($flashMessage['type'] === 'error' ? 'exclamation-circle' : 'info-circle') ?> me-2"></i>
                <?= htmlspecialchars($flashMessage['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

