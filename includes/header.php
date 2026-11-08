<?php
/**
 * Cabecera y Navegación Principal Glassmorphism
 * Liceo Bolivariano "Rossana Melidei"
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$currentUser = getCurrentUser();
$role = $currentUser['rol'] ?? 'invitado';
$nombreUsuario = $currentUser['nombre'] ?? 'Usuario';
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?><?= htmlspecialchars(INSTITUCION_SIGLAS) ?></title>
    
    <!-- Fuentes Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Estilos Glassmorphism -->
    <link rel="stylesheet" href="assets/css/glassmorphism.css">

    <!-- Librerías Externas (Chart.js & Html5-Qrcode) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body class="bg-app min-h-screen flex flex-col">
    <!-- Barra Superior Glassmorphic -->
    <header class="glass-header">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <!-- Marca y Logo del Liceo -->
            <a href="<?= $role === 'admin' ? 'dashboard_admin.php' : 'dashboard_profesor.php' ?>" class="flex items-center gap-3 text-decoration-none">
                <img src="assets/img/logo_liceo.svg" alt="Emblema Liceo" width="42" height="42" class="rounded-full shadow-sm">
                <div>
                    <h1 class="text-sm font-extrabold text-slate-800 leading-tight"><?= htmlspecialchars(INSTITUCION_NOMBRE) ?></h1>
                    <span class="text-xs font-semibold text-slate-500">Control de Asistencia Digital &bull; DEA <?= htmlspecialchars(INSTITUCION_DEA) ?></span>
                </div>
            </a>

            <!-- Estado de Red y Enlaces Rápidos -->
            <div class="flex items-center gap-4">
                <div id="network-status-indicator">
                    <span class="chip chip-mint text-xs">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1 animate-pulse"></span> Red Activa
                    </span>
                </div>

                <!-- Botón Contingencia Offline (con contador) -->
                <button type="button" class="btn btn-subtle btn-sm btn-sync-offline text-xs" onclick="window.offlineAttendanceManager.syncQueue()" title="Sincronizar asistencias pendientes">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                    </svg>
                    <span>Sincronizar</span>
                    <span class="chip chip-amber offline-count-badge hidden" style="display:none; padding: 2px 6px; font-size: 10px;">0</span>
                </button>

                <!-- Menú de Usuario -->
                <div class="flex items-center gap-2 pl-2 border-l border-slate-200/60">
                    <div class="text-right hide-mobile">
                        <div class="text-xs font-bold text-slate-800"><?= htmlspecialchars($nombreUsuario) ?></div>
                        <span class="chip <?= $role === 'admin' ? 'chip-blue' : 'chip-mint' ?>" style="font-size: 10px; padding: 1px 6px;">
                            <?= $role === 'admin' ? 'Directivo' : 'Docente' ?>
                        </span>
                    </div>

                    <?php if ($role === 'admin'): ?>
                        <a href="exportar_carnets.php" class="btn btn-subtle btn-sm hide-mobile text-xs" title="Generador de Carnets">Carnets</a>
                        <a href="dashboard_admin.php" class="btn btn-subtle btn-sm hide-mobile text-xs">Panel</a>
                    <?php else: ?>
                        <a href="escanear.php" class="btn btn-mint btn-sm text-xs">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Escanear QR
                        </a>
                    <?php endif; ?>

                    <a href="logout.php" class="btn btn-subtle btn-sm text-xs text-rose-600" title="Cerrar Sesión">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Contenedor Principal -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6">
