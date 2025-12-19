<?php
/**
 * Archivo de redirección principal
 * Redirige a la interfaz de usuario según el entorno
 */

// Cargar configuración de entorno
require_once __DIR__ . '/utils/Env.php';

use Utils\Env;

try {
    $env = new Env(__DIR__ . '/.env');
    $appEnv = $env->get('APP_ENV', 'development');
} catch (Exception $e) {
    // Si no existe .env, usar development por defecto
    $appEnv = 'development';
}

// Determinar la URL de destino según el entorno
if ($appEnv === 'production') {
    $target = 'https://finance.andapps.cloud/ui/';
} else {
    // Development: usar la ruta local
    $target = 'http://localhost/projects/and_finance_app/ui/';
}

// Redirección permanente (301) o temporal (302) según el entorno
$redirectCode = ($appEnv === 'production') ? 301 : 302;
header('Location: ' . $target, true, $redirectCode);
exit;

