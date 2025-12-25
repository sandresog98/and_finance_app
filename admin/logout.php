<?php
/**
 * AND FINANCE APP - Admin Logout
 */

require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/controllers/AuthController.php';

$auth = new AuthController();
$auth->logout();

header('Location: login.php');
exit;

