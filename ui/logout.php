<?php
/**
 * Cerrar sesión
 */

session_start();

// Destruir sesión
if (isset($_SESSION['and_finance_user'])) {
    unset($_SESSION['and_finance_user']);
}

session_destroy();

// Redirigir al login
header('Location: login.php');
exit;
