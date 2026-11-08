<?php
/**
 * Cierre de Sesión Seguro
 * Liceo Bolivariano "Rossana Melidei"
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logoutUser();

header('Location: login.php');
exit;
