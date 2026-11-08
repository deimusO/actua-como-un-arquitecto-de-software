<?php
/**
 * Pantalla de Inicio de Sesión Institucional (Glassmorphism UI)
 * Liceo Bolivariano "Rossana Melidei"
 * PHP 8.0+ / PDO / CSRF Protected
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/auth.php';

// Si ya está autenticado, redirigir al panel correspondiente
if (isLoggedIn()) {
    $user = getCurrentUser();
    header('Location: ' . ($user['rol'] === 'admin' ? 'dashboard_admin.php' : 'dashboard_profesor.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'La sesión del formulario expiró por seguridad. Intente nuevamente.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Por favor ingrese su correo institucional y su contraseña.';
        } else {
            $pdo = getDBConnection();
            if (!$pdo) {
                $error = 'No fue posible conectar con el servidor de base de datos MySQL en XAMPP.';
            } else {
                $stmt = $pdo->prepare("SELECT id, nombre, email, password, rol, estado FROM usuarios WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && $user['estado'] === 'activo' && password_verify($password, $user['password'])) {
                    // Actualizar marca de último acceso
                    $updateStmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);

                    // Iniciar sesión segura
                    loginUser($user);

                    header('Location: ' . ($user['rol'] === 'admin' ? 'dashboard_admin.php' : 'dashboard_profesor.php'));
                    exit;
                } else {
                    $error = 'Credenciales no válidas o usuario suspendido. Verifique sus datos.';
                }
            }
        }
    }
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema - <?= htmlspecialchars(INSTITUCION_NOMBRE) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/glassmorphism.css">
</head>
<body class="bg-app min-h-screen flex items-center justify-center p-4">
    <div class="glass-card max-w-md w-full p-8 shadow-2xl animate-fade-in text-center relative overflow-hidden">
        <!-- Detalle decorativo de gradiente superior -->
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 6px; background: linear-gradient(90deg, #3b82f6, #10b981);"></div>

        <!-- Emblema Institucional -->
        <div class="mb-4">
            <img src="assets/img/logo_liceo.svg" alt="Liceo Logo" width="80" height="80" class="mx-auto rounded-full shadow-md">
        </div>

        <span class="chip chip-blue mb-2">Sistema Integral de Asistencia</span>
        <h1 class="text-xl font-extrabold text-slate-800 leading-snug mb-1"><?= htmlspecialchars(INSTITUCION_NOMBRE) ?></h1>
        <p class="text-xs text-slate-500 mb-6 font-medium">Autenticación para Personal Directivo y Docente</p>

        <?php if ($error): ?>
            <div class="glass-card-subtle p-3 rounded-xl mb-4 text-xs font-semibold text-rose-600 border border-rose-200 bg-rose-50/70">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Formulario Seguro con CSRF -->
        <form method="POST" action="login.php" class="text-left">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label class="form-label" for="email">Correo Electrónico Institucional</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="ejemplo@rossanamelidei.edu.ve" required autocomplete="username">
            </div>

            <div class="form-group">
                <div class="flex justify-between items-center mb-1">
                    <label class="form-label mb-0" for="password">Contraseña de Acceso</label>
                </div>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary w-full py-3 mb-4 text-sm font-bold shadow-lg">
                Ingresar al Sistema
            </button>
        </form>

        <!-- Accesos Rápidos de Demostración para Pruebas en XAMPP -->
        <div class="glass-card-subtle p-4 rounded-xl mt-6 text-left">
            <div class="text-xs font-bold text-slate-700 mb-2 flex items-center justify-between">
                <span>Credenciales de Demostración:</span>
                <span class="chip chip-mint" style="font-size: 10px;">XAMPP Ready</span>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="btn btn-subtle btn-sm w-full text-xs" onclick="llenarCredenciales('admin@rossanamelidei.edu.ve', 'Admin123*')">
                    Directivo (Admin)
                </button>
                <button type="button" class="btn btn-subtle btn-sm w-full text-xs" onclick="llenarCredenciales('carlos.gonzalez@rossanamelidei.edu.ve', 'Profesor123*')">
                    Prof. González
                </button>
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-white/50 text-xs text-slate-400">
            Seguridad Criptográfica &bull; Control Territorial de IPs v2.5
        </div>
    </div>

    <script>
        function llenarCredenciales(email, pass) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = pass;
        }
    </script>
</body>
</html>
