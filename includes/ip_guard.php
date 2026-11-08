<?php
/**
 * Middleware de Seguridad y Restricción por IP (Anti-Fraude)
 * Sistema de Control de Asistencia - Liceo Bolivariano "Rossana Melidei"
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexion.php';

/**
 * Obtiene la dirección IP real del cliente evitando spoofing de cabeceras arbitrarias
 */
function getClientIP(): string
{
    // Por seguridad, en redes locales y XAMPP el valor más confiable es REMOTE_ADDR.
    // Solo si el servidor está configurado detrás de un proxy inverso de confianza se evalúan cabeceras forwarded.
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Normalizar loopback IPv6 a IPv4 para compatibilidad visual si aplica
    if ($remoteAddr === '::1' || $remoteAddr === '0:0:0:0:0:0:0:1') {
        return '127.0.0.1';
    }

    return trim($remoteAddr);
}

/**
 * Verifica si una dirección IPv4 pertenece a una subred con notación CIDR
 */
function ipInSubnet(string $ip, string $cidr): bool
{
    if ($ip === $cidr) {
        return true;
    }

    if (!str_contains($cidr, '/')) {
        return $ip === $cidr;
    }

    [$subnet, $mask] = explode('/', $cidr, 2);
    $mask = (int)$mask;

    // Validación para IPv4
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if ($mask < 0 || $mask > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $netmask = ~((1 << (32 - $mask)) - 1);

        return ($ipLong & $netmask) === ($subnetLong & $netmask);
    }

    return false;
}

/**
 * Determina si la IP del cliente está autorizada según configuración y BD
 */
function isClientIPAuthorized(?string $ip = null): bool
{
    if (!ENFORCE_IP_SECURITY) {
        return true;
    }

    $clientIp = $ip ?? getClientIP();

    // 1. Verificar lista base en archivo de configuración
    $allowedList = array_merge(ALLOWED_SUBNETS, ALLOWED_ADMIN_EXTERNAL_IPS);

    foreach ($allowedList as $range) {
        if (ipInSubnet($clientIp, $range)) {
            return true;
        }
    }

    // 2. Verificar lista dinámica en Base de Datos si la conexión está disponible
    $pdo = getDBConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT ip_o_subred FROM ip_autorizadas WHERE activo = 1");
            $stmt->execute();
            $dynamicSubnets = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($dynamicSubnets as $range) {
                if (ipInSubnet($clientIp, (string)$range)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            error_log('[IP Guard DB Warning]: ' . $e->getMessage());
        }
    }

    return false;
}

/**
 * Bloquea la petición si la IP no pertenece a la red del liceo
 */
function enforceIPAccess(bool $isApi = false): void
{
    $clientIp = getClientIP();

    if (!isClientIPAuthorized($clientIp)) {
        // Registrar intento fraudulento en log del servidor
        error_log(sprintf('[ANTI-FRAUDE BLOQUEADO]: Intento de escaneo o acceso desde IP no autorizada: %s en %s', $clientIp, $_SERVER['REQUEST_URI'] ?? ''));

        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'codigo' => 'IP_NO_AUTORIZADA',
                'error' => 'Acceso denegado: Su dispositivo (' . htmlspecialchars($clientIp) . ') no está conectado a la red local del Liceo Rossana Melidei.',
                'ip' => $clientIp
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Pantalla de bloqueo Glassmorphism amigable pero estricta
        http_response_code(403);
        renderAccessDeniedScreen($clientIp);
        exit;
    }
}

/**
 * Renderiza la interfaz de restricción anti-fraude
 */
function renderAccessDeniedScreen(string $clientIp): void
{
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Restringido por Red - L.B. Rossana Melidei</title>
        <link rel="stylesheet" href="assets/css/glassmorphism.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    </head>
    <body class="bg-app flex-center min-h-screen">
        <div class="glass-card max-w-lg w-full text-center p-8 animate-fade-in">
            <div class="badge-icon badge-rose mx-auto mb-4">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <span class="chip chip-rose mb-3">Módulo Anti-Fraude Activo</span>
            <h1 class="text-2xl font-bold text-slate-800 mb-2">Restricción de Red Territorial</h1>
            <p class="text-slate-600 mb-6 text-sm leading-relaxed">
                Por políticas de seguridad y auditoría académica del <strong>Liceo Bolivariano "Rossana Melidei"</strong>, el módulo de escaneo de asistencia y registro en vivo únicamente puede operarse desde la red local autorizada (WiFi institucional o red física de las aulas).
            </p>
            <div class="glass-card-subtle p-4 rounded-xl text-left mb-6">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-xs font-semibold text-slate-500">Dirección IP detectada:</span>
                    <span class="text-xs font-mono font-bold text-rose-600"><?= htmlspecialchars($clientIp) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs font-semibold text-slate-500">Estado de validación:</span>
                    <span class="text-xs font-semibold text-rose-500">No autorizada en subred local</span>
                </div>
            </div>
            <div class="flex flex-col gap-3">
                <a href="dashboard_profesor.php" class="btn btn-primary w-full">Volver al Panel</a>
                <a href="login.php" class="btn btn-subtle w-full text-xs">Cerrar Sesión</a>
            </div>
            <div class="mt-6 pt-4 border-t border-white/40 text-xs text-slate-400">
                Liceo Bolivariano Rossana Melidei &bull; Seguridad Perimetral v2.5
            </div>
        </div>
    </body>
    </html>
    <?php
}
