<?php
/**
 * Punto de Entrada Principal del Sistema
 * Liceo Bolivariano "Rossana Melidei"
 * Enrutamiento inteligente según el rol de la sesión
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['rol'] === 'admin') {
        header('Location: dashboard_admin.php');
        exit;
    } else {
        header('Location: dashboard_profesor.php');
        exit;
    }
}

// Redireccionar al formulario de autenticación
header('Location: login.php');
exit;
