<?php
/**
 * Módulo de Autenticación, Sesiones y Seguridad CSRF
 * Liceo Bolivariano "Rossana Melidei"
 * PHP 8.0+
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Endurecimiento de cookies de sesión
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}

/**
 * Genera o recupera el token CSRF de la sesión actual
 */
function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida un token CSRF enviado en peticiones POST o cabeceras
 */
function verifyCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Inicia la sesión de un usuario de forma segura
 */
function loginUser(array $user): void
{
    // Regenerar ID de sesión para prevenir Session Fixation
    session_regenerate_id(true);

    $_SESSION['usuario_id']     = (int)$user['id'];
    $_SESSION['usuario_nombre'] = (string)$user['nombre'];
    $_SESSION['usuario_email']  = (string)$user['email'];
    $_SESSION['usuario_rol']    = (string)$user['rol'];
    $_SESSION['ultimo_acceso']  = time();
}

/**
 * Destruye la sesión actual
 */
function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Comprueba si hay una sesión activa
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['usuario_id']);
}

/**
 * Retorna los datos básicos del usuario autenticado
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id'     => $_SESSION['usuario_id'],
        'nombre' => $_SESSION['usuario_nombre'],
        'email'  => $_SESSION['usuario_email'],
        'rol'    => $_SESSION['usuario_rol'],
    ];
}

/**
 * Verifica si el usuario actual posee determinado rol
 */
function hasRole(string $role): bool
{
    return isLoggedIn() && ($_SESSION['usuario_rol'] ?? '') === $role;
}

/**
 * Exige inicio de sesión previo para acceder a la página
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Exige pertenecer a uno de los roles autorizados
 */
function requireRole(string ...$roles): void
{
    requireLogin();

    $userRole = $_SESSION['usuario_rol'] ?? '';

    if (!in_array($userRole, $roles, true)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>403 - Acceso No Autorizado</title>
            <link rel="stylesheet" href="assets/css/glassmorphism.css">
        </head>
        <body class="bg-app flex-center min-h-screen">
            <div class="glass-card max-w-md text-center p-8">
                <h2 class="text-xl font-bold text-slate-800 mb-2">Permiso Insuficiente</h2>
                <p class="text-slate-600 text-sm mb-4">No cuenta con privilegios para acceder a este módulo institucional.</p>
                <a href="<?= $userRole === 'admin' ? 'dashboard_admin.php' : 'dashboard_profesor.php' ?>" class="btn btn-primary">Volver a mi panel</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
