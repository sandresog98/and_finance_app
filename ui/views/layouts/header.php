<?php
/**
 * AND FINANCE APP - User Header Layout
 * Con soporte para tema claro/oscuro/auto
 */

// Verificar autenticación
require_once __DIR__ . '/../../utils/session.php';
requireUserAuth();

$currentUser = getCurrentUser();
$flashMessage = getFlashMessage();

// Obtener preferencia de tema del usuario
$db = Database::getInstance();
$stmt = $db->prepare("SELECT tema FROM configuracion_usuario WHERE usuario_id = :id");
$stmt->execute(['id' => getCurrentUserId()]);
$userConfig = $stmt->fetch();
$userTheme = $userConfig['tema'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= $userTheme === 'auto' ? 'light' : $userTheme ?>" data-theme-preference="<?= $userTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= APP_NAME ?></title>
    
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="<?= assetUrl('css/app.css') ?>" rel="stylesheet">
    
    <style>
        /* =====================================================
           VARIABLES CSS - TEMA CLARO (Por defecto)
           ===================================================== */
        :root, [data-theme="light"] {
            /* Colores corporativos */
            --primary-blue: #55A5C8;
            --secondary-green: #9AD082;
            --tertiary-gray: #B1BCBF;
            --dark-blue: #35719E;
            --success-color: #9AD082;
            --danger-color: #FF6B6B;
            
            /* Fondos */
            --bg-body: #f4f7fa;
            --bg-card: #ffffff;
            --bg-sidebar: #ffffff;
            --bg-header: #ffffff;
            --bg-input: #ffffff;
            --bg-hover: rgba(85, 165, 200, 0.1);
            --bg-table-head: #f8fafc;
            
            /* Textos */
            --text-primary: #1a1d21;
            --text-secondary: #5a6f7c;
            --text-muted: #6c757d;
            --text-light: #B1BCBF;
            
            /* Bordes */
            --border-color: #e9ecef;
            --border-light: #f0f0f0;
            
            /* Sombras */
            --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-sidebar: 4px 0 20px rgba(0, 0, 0, 0.05);
            
            /* Layout */
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --header-height: 70px;
            
            /* Scrollbar */
            --scrollbar-track: #f1f1f1;
            --scrollbar-thumb: #ccc;
        }
        
        /* =====================================================
           VARIABLES CSS - TEMA OSCURO
           ===================================================== */
        [data-theme="dark"] {
            /* Colores corporativos (ligeramente ajustados para contraste) */
            --primary-blue: #5fb5d8;
            --secondary-green: #a5db8f;
            --tertiary-gray: #8a9499;
            --dark-blue: #6ba8cc;
            --success-color: #a5db8f;
            --danger-color: #ff7b7b;
            
            /* Fondos */
            --bg-body: #0f1214;
            --bg-card: #1a1d21;
            --bg-sidebar: #1a1d21;
            --bg-header: #1a1d21;
            --bg-input: #23272b;
            --bg-hover: rgba(95, 181, 216, 0.15);
            --bg-table-head: #23272b;
            
            /* Textos */
            --text-primary: #f0f2f5;
            --text-secondary: #a8b3bd;
            --text-muted: #8a9499;
            --text-light: #5a6268;
            
            /* Bordes */
            --border-color: #2d3238;
            --border-light: #23272b;
            
            /* Sombras */
            --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.25);
            --shadow-lg: 0 8px 30px rgba(0, 0, 0, 0.35);
            --shadow-sidebar: 4px 0 20px rgba(0, 0, 0, 0.3);
            
            /* Scrollbar */
            --scrollbar-track: #1a1d21;
            --scrollbar-thumb: #3d4349;
        }
        
        /* =====================================================
           ESTILOS BASE
           ===================================================== */
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: var(--bg-body);
            color: var(--text-primary);
            min-height: 100vh;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /* =====================================================
           SIDEBAR
           ===================================================== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--bg-sidebar);
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sidebar);
            overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid var(--border-light);
            background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
        }
        
        .sidebar-brand img {
            max-width: 180px;
            height: auto;
        }
        
        .sidebar-brand h2 {
            color: white;
            font-weight: 800;
            font-size: 22px;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .sidebar-brand h2 i {
            font-size: 28px;
        }
        
        /* User Section */
        .sidebar-user {
            padding: 20px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .user-info h6 {
            margin: 0;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }
        
        .user-info small {
            color: var(--text-muted);
            font-size: 12px;
        }
        
        /* Navigation */
        .sidebar-nav {
            padding: 15px;
        }
        
        .nav-section {
            color: var(--text-muted);
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
            color: var(--text-secondary);
            border-radius: 12px;
            margin-bottom: 4px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }
        
        .sidebar-nav .nav-link i {
            font-size: 20px;
            margin-right: 12px;
            width: 24px;
            text-align: center;
        }
        
        .sidebar-nav .nav-link:hover {
            background: var(--bg-hover);
            color: var(--primary-blue);
        }
        
        .sidebar-nav .nav-link.active {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            box-shadow: 0 4px 15px rgba(85, 165, 200, 0.3);
        }
        
        .sidebar-nav .nav-link.text-danger {
            color: var(--danger-color);
        }
        
        .sidebar-nav .nav-link.text-danger:hover {
            background: rgba(255, 107, 107, 0.1);
        }
        
        /* =====================================================
           MAIN CONTENT
           ===================================================== */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        /* Top Header */
        .top-header {
            height: var(--header-height);
            background: var(--bg-header);
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: background-color 0.3s ease;
        }
        
        .page-title-section {
            display: flex;
            flex-direction: column;
        }
        
        .page-title {
            font-weight: 700;
            font-size: 20px;
            color: var(--text-primary);
            margin: 0;
        }
        
        .page-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notification-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .notification-btn:hover {
            background: var(--bg-hover);
            border-color: var(--primary-blue);
        }
        
        .notification-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: var(--danger-color);
            border-radius: 50%;
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text-primary);
            padding: 5px;
        }
        
        /* Content Area */
        .content-area {
            padding: 25px 30px;
        }
        
        /* =====================================================
           CARDS
           ===================================================== */
        .card {
            border: none;
            border-radius: 16px;
            background: var(--bg-card);
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-light);
            padding: 18px 22px;
        }
        
        .card-header h5, .card-header h6 {
            margin: 0;
            font-weight: 700;
            font-size: 16px;
            color: var(--text-primary);
        }
        
        .card-body {
            padding: 22px;
        }
        
        /* Stat Cards (mantienen sus colores de fondo) */
        .stat-card {
            border-radius: 16px;
            padding: 22px;
            height: 100%;
        }
        
        .stat-card.income {
            background: linear-gradient(135deg, #9AD082 0%, #7ab85c 100%);
        }
        
        .stat-card.expense {
            background: linear-gradient(135deg, #FF6B6B 0%, #ee5a5a 100%);
        }
        
        .stat-card.balance {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #35719E 100%);
        }
        
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }
        
        .stat-card .stat-label {
            color: rgba(255, 255, 255, 0.85);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-value {
            color: white;
            font-size: 26px;
            font-weight: 800;
            margin: 0;
        }
        
        /* =====================================================
           FORMS
           ===================================================== */
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
            background-color: var(--bg-input);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(85, 165, 200, 0.15);
            background-color: var(--bg-input);
            color: var(--text-primary);
        }
        
        .form-control::placeholder {
            color: var(--text-muted);
        }
        
        .input-group-text {
            border: 2px solid var(--border-color);
            background: var(--bg-input);
            color: var(--text-muted);
        }
        
        /* =====================================================
           TABLES
           ===================================================== */
        .table {
            margin: 0;
            color: var(--text-primary);
        }
        
        .table thead th {
            background: var(--bg-table-head);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 14px 16px;
        }
        
        .table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            border-color: var(--border-light);
            font-size: 14px;
            color: var(--text-primary);
        }
        
        .table tbody tr:hover {
            background-color: var(--bg-hover);
        }
        
        /* =====================================================
           BUTTONS
           ===================================================== */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            border: none;
            padding: 10px 22px;
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
        
        .btn-danger {
            background: linear-gradient(135deg, #FF6B6B, #ee5a5a);
            border: none;
        }
        
        .btn-outline-secondary {
            border-color: var(--border-color);
            color: var(--text-secondary);
        }
        
        .btn-outline-secondary:hover {
            background: var(--bg-hover);
            border-color: var(--primary-blue);
            color: var(--primary-blue);
        }
        
        /* =====================================================
           BADGES & ALERTS
           ===================================================== */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
        }
        
        .badge-income, .badge-ingreso {
            background: rgba(154, 208, 130, 0.15);
            color: var(--success-color);
        }
        
        .badge-expense, .badge-egreso {
            background: rgba(255, 107, 107, 0.15);
            color: var(--danger-color);
        }
        
        .badge-transfer, .badge-transferencia {
            background: rgba(85, 165, 200, 0.15);
            color: var(--primary-blue);
        }
        
        /* =====================================================
           BOOTSTRAP OVERRIDES PARA TEMA OSCURO
           ===================================================== */
        /* Texto muted adaptativo */
        .text-muted {
            color: var(--text-muted) !important;
        }
        
        /* Fondo light */
        .bg-light {
            background-color: var(--bg-input) !important;
        }
        
        .bg-body-secondary {
            background-color: var(--bg-input) !important;
        }
        
        /* Card header y body */
        .card-header.bg-light {
            background-color: var(--bg-input) !important;
            color: var(--text-primary);
        }
        
        /* Bordes */
        .border-bottom, .border-top, .border {
            border-color: var(--border-color) !important;
        }
        
        /* Textos secundarios */
        .text-secondary {
            color: var(--text-secondary) !important;
        }
        
        /* List group */
        .list-group-item {
            background-color: var(--bg-card);
            color: var(--text-primary);
            border-color: var(--border-light);
        }
        
        /* Dropdown */
        .dropdown-menu {
            background-color: var(--bg-card);
            border-color: var(--border-color);
        }
        
        .dropdown-item {
            color: var(--text-primary);
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            background-color: var(--bg-hover);
            color: var(--text-primary);
        }
        
        /* Offcanvas */
        .offcanvas {
            background-color: var(--bg-card);
            color: var(--text-primary);
        }
        
        .offcanvas-header {
            border-bottom-color: var(--border-color);
        }
        
        .offcanvas-header .btn-close {
            filter: none;
        }
        
        [data-theme="dark"] .offcanvas-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        /* Cards */
        .card {
            background-color: var(--bg-card);
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .card-body {
            color: var(--text-primary);
        }
        
        .card-header {
            background-color: var(--bg-card);
            border-bottom-color: var(--border-color);
            color: var(--text-primary);
        }
        
        .card-footer {
            background-color: var(--bg-card);
            border-top-color: var(--border-color);
        }
        
        .card h5, .card h6, .card .card-title {
            color: var(--text-primary);
        }
        
        .alert {
            border: none;
            border-radius: 12px;
            padding: 16px 20px;
        }
        
        .alert-success {
            background: rgba(154, 208, 130, 0.15);
            color: var(--success-color);
        }
        
        .alert-danger {
            background: rgba(255, 107, 107, 0.15);
            color: var(--danger-color);
        }
        
        .alert-info {
            background: rgba(85, 165, 200, 0.15);
            color: var(--primary-blue);
        }
        
        .alert-warning {
            background: rgba(255, 193, 7, 0.15);
            color: #e0a800;
        }
        
        /* Botón cerrar de alertas en modo oscuro */
        .alert .btn-close {
            filter: none;
        }
        
        [data-theme="dark"] .alert .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        /* =====================================================
           MODALS
           ===================================================== */
        .modal-content {
            background: var(--bg-card);
            border: none;
            border-radius: 16px;
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-light);
            padding: 20px 25px;
        }
        
        .modal-header .modal-title {
            color: var(--text-primary);
        }
        
        .modal-header .btn-close {
            filter: var(--bs-btn-close-white-filter);
        }
        
        [data-theme="dark"] .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        .modal-body {
            padding: 25px;
            color: var(--text-primary);
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-light);
            padding: 15px 25px;
        }
        
        /* =====================================================
           QUICK ACTION BUTTONS
           ===================================================== */
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 15px;
            border-radius: 16px;
            background: var(--bg-card);
            border: 2px dashed var(--border-color);
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .quick-action-btn:hover {
            border-color: var(--primary-blue);
            background: var(--bg-hover);
            transform: translateY(-3px);
            color: var(--text-primary);
        }
        
        .quick-action-btn i {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--primary-blue);
        }
        
        .quick-action-btn span {
            font-weight: 600;
            font-size: 13px;
        }
        
        /* =====================================================
           DROPDOWN
           ===================================================== */
        .dropdown-menu {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            border-radius: 12px;
        }
        
        .dropdown-item {
            color: var(--text-primary);
            border-radius: 8px;
        }
        
        .dropdown-item:hover {
            background: var(--bg-hover);
            color: var(--primary-blue);
        }
        
        .dropdown-divider {
            border-color: var(--border-light);
        }
        
        /* =====================================================
           LISTA GROUP
           ===================================================== */
        .list-group-item {
            background: var(--bg-card);
            border-color: var(--border-light);
            color: var(--text-primary);
        }
        
        .list-group-item:hover {
            background: var(--bg-hover);
        }
        
        .list-group-item.active {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        /* =====================================================
           TEXT COLORS
           ===================================================== */
        .text-muted {
            color: var(--text-muted) !important;
        }
        
        .text-secondary {
            color: var(--text-secondary) !important;
        }
        
        /* =====================================================
           SCROLLBAR
           ===================================================== */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--scrollbar-track);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--scrollbar-thumb);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-blue);
        }
        
        /* =====================================================
           RESPONSIVE
           ===================================================== */
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
            
            .stat-card .stat-value {
                font-size: 22px;
            }
        }
        
        /* =====================================================
           ANIMATIONS
           ===================================================== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.4s ease-out;
        }
        
        /* Transición suave al cambiar de tema */
        body, .sidebar, .card, .top-header, .modal-content,
        .form-control, .form-select, .btn, .dropdown-menu {
            transition: background-color 0.3s ease, 
                        border-color 0.3s ease, 
                        color 0.3s ease,
                        box-shadow 0.3s ease;
        }
    </style>
    
    <!-- Script para manejar tema auto -->
    <script>
        (function() {
            const html = document.documentElement;
            const themePref = html.getAttribute('data-theme-preference');
            
            if (themePref === 'auto') {
                // Detectar preferencia del sistema
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                html.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
                
                // Escuchar cambios en la preferencia del sistema
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                    if (html.getAttribute('data-theme-preference') === 'auto') {
                        html.setAttribute('data-theme', e.matches ? 'dark' : 'light');
                    }
                });
            }
        })();
    </script>
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="<?= assetUrl('img/logo-horizontal-white.png') ?>" alt="<?= APP_NAME ?>">
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <?php if ($currentUser['avatar']): ?>
                    <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" alt="Avatar">
                <?php else: ?>
                    <?= strtoupper(substr($currentUser['nombre'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <h6><?= htmlspecialchars($currentUser['nombre']) ?></h6>
                <small><?= htmlspecialchars($currentUser['email']) ?></small>
            </div>
        </div>
        
        <div class="sidebar-nav">
            <p class="nav-section">Principal</p>
            
            <a href="<?= uiUrl('index.php') ?>" class="nav-link <?= ($currentModule ?? '') === '' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i>
                Dashboard
            </a>
            
            <a href="<?= uiModuleUrl('transacciones') ?>" class="nav-link <?= ($currentModule ?? '') === 'transacciones' ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i>
                Transacciones
            </a>
            
            <p class="nav-section">Gestión</p>
            
            <a href="<?= uiModuleUrl('cuentas') ?>" class="nav-link <?= ($currentModule ?? '') === 'cuentas' ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i>
                Cuentas
            </a>
            
            <a href="<?= uiModuleUrl('categorias') ?>" class="nav-link <?= ($currentModule ?? '') === 'categorias' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                Categorías
            </a>
            
            <a href="<?= uiModuleUrl('recurrentes') ?>" class="nav-link <?= ($currentModule ?? '') === 'recurrentes' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i>
                Gastos Recurrentes
            </a>
            
            <p class="nav-section">Análisis</p>
            
            <a href="<?= uiModuleUrl('reportes') ?>" class="nav-link <?= ($currentModule ?? '') === 'reportes' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                Reportes
            </a>
            
            <a href="<?= uiModuleUrl('presupuestos') ?>" class="nav-link <?= ($currentModule ?? '') === 'presupuestos' ? 'active' : '' ?>">
                <i class="bi bi-pie-chart"></i>
                Presupuestos
            </a>
            
            <p class="nav-section">Configuración</p>
            
            <a href="<?= uiModuleUrl('perfil') ?>" class="nav-link <?= ($currentModule ?? '') === 'perfil' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                Mi Perfil
            </a>
            
            <a href="<?= uiUrl('logout.php') ?>" class="nav-link text-danger">
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
                <div class="page-title-section">
                    <h1 class="page-title"><?= $pageTitle ?? 'Dashboard' ?></h1>
                    <?php if (isset($pageSubtitle)): ?>
                    <p class="page-subtitle"><?= $pageSubtitle ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="header-actions">
                <button class="notification-btn" title="Notificaciones">
                    <i class="bi bi-bell"></i>
                    <span class="notification-badge"></span>
                </button>
                
                <a href="<?= uiModuleUrl('transacciones', 'crear') ?>" class="btn btn-primary d-none d-md-flex">
                    <i class="bi bi-plus-lg me-2"></i>
                    Nueva Transacción
                </a>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="content-area">
            <?php if ($flashMessage): ?>
            <div class="alert alert-<?= $flashMessage['type'] === 'success' ? 'success' : ($flashMessage['type'] === 'error' ? 'danger' : 'info') ?> alert-dismissible fade show fade-in-up" role="alert">
                <i class="bi bi-<?= $flashMessage['type'] === 'success' ? 'check-circle' : ($flashMessage['type'] === 'error' ? 'exclamation-circle' : 'info-circle') ?> me-2"></i>
                <?= htmlspecialchars($flashMessage['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
