<?php
/**
 * Generador y Exportador Masivo de Carnets Estudiantiles
 * Liceo Bolivariano "Rossana Melidei"
 * Formato institucional optimizado para impresión por pliegos/hojas y exportación a PDF
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/auth.php';

requireRole('admin', 'profesor');

$pageTitle = 'Generador de Carnets Estudiantiles';
$pdo = getDBConnection();

$filtroAno     = trim((string)($_GET['ano'] ?? ''));
$filtroSeccion = trim((string)($_GET['seccion'] ?? ''));
$estudianteId  = (int)($_GET['id'] ?? 0);

$estudiantes = [];

if ($pdo) {
    try {
        $sql = "SELECT * FROM estudiantes WHERE estado = 'activo'";
        $params = [];

        if ($estudianteId > 0) {
            $sql .= " AND id = ?";
            $params[] = $estudianteId;
        } else {
            if (!empty($filtroAno)) {
                $sql .= " AND ano_instruccion = ?";
                $params[] = $filtroAno;
            }
            if (!empty($filtroSeccion)) {
                $sql .= " AND seccion = ?";
                $params[] = $filtroSeccion;
            }
        }

        $sql .= " ORDER BY ano_instruccion ASC, seccion ASC, apellidos ASC, nombres ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $estudiantes = $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log('[Carnets Error]: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carnets Estudiantiles - <?= htmlspecialchars(INSTITUCION_NOMBRE) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/glassmorphism.css">

    <style>
        .carnet-wrapper {
            width: 340px;
            height: 215px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
            break-inside: avoid;
            margin: 10px auto;
        }

        .carnet-top-bar {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 60%, #059669 100%);
            color: #ffffff;
            padding: 6px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .carnet-top-bar img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ffffff;
            padding: 1px;
        }

        .carnet-titles {
            flex: 1;
            line-height: 1.15;
        }

        .carnet-titles .inst-rep {
            font-size: 6.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            opacity: 0.9;
            font-weight: 600;
        }

        .carnet-titles .inst-name {
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: 0.2px;
            color: #fef08a;
        }

        .carnet-content {
            flex: 1;
            padding: 8px 10px;
            display: flex;
            gap: 10px;
            align-items: center;
            background: #ffffff;
            position: relative;
        }

        .carnet-watermark {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 100px;
            height: 100px;
            opacity: 0.06;
            pointer-events: none;
        }

        .carnet-avatar {
            width: 74px;
            height: 90px;
            border-radius: 8px;
            object-fit: cover;
            border: 1.5px solid #94a3b8;
            background: #f1f5f9;
        }

        .carnet-data {
            flex: 1;
            z-index: 1;
        }

        .carnet-student-name {
            font-size: 11.5px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .carnet-detail-row {
            font-size: 9px;
            color: #475569;
            margin-bottom: 2px;
            line-height: 1.3;
        }

        .carnet-detail-row strong {
            color: #1e293b;
        }

        .carnet-qr-box {
            width: 68px;
            height: 68px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }

        .carnet-qr-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .carnet-bottom-bar {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 4px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 7.5px;
            color: #64748b;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; padding: 0 !important; }
            .print-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 8mm 6mm !important;
                margin: 0 !important;
                padding: 10mm !important;
            }
            .carnet-wrapper {
                box-shadow: none !important;
                border: 1px solid #64748b !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body class="bg-app min-h-screen has-bottom-nav">
    <!-- Barra Superior de Control de Impresión (Se oculta al imprimir) -->
    <div class="glass-header no-print">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <a href="dashboard_admin.php" class="btn btn-subtle btn-sm text-xs px-2.5 py-1">
                    ← Volver
                </a>
                <div>
                    <h2 class="text-xs sm:text-sm font-bold text-slate-800 leading-tight">Generador de Carnets Escolares</h2>
                    <span class="text-[11px] text-slate-500"><?= count($estudiantes) ?> carnets listos para imprimir</span>
                </div>
            </div>

            <!-- Filtros Rápidos -->
            <form method="GET" class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <select name="ano" class="form-control text-xs py-1 px-2.5 flex-1 sm:w-32" onchange="this.form.submit()">
                    <option value="">Todos los Años</option>
                    <option value="1er Año" <?= $filtroAno === '1er Año' ? 'selected' : '' ?>>1er Año</option>
                    <option value="2do Año" <?= $filtroAno === '2do Año' ? 'selected' : '' ?>>2do Año</option>
                    <option value="3er Año" <?= $filtroAno === '3er Año' ? 'selected' : '' ?>>3er Año</option>
                    <option value="4to Año" <?= $filtroAno === '4to Año' ? 'selected' : '' ?>>4to Año</option>
                    <option value="5to Año" <?= $filtroAno === '5to Año' ? 'selected' : '' ?>>5to Año</option>
                </select>

                <select name="seccion" class="form-control text-xs py-1 px-2.5 flex-1 sm:w-28" onchange="this.form.submit()">
                    <option value="">Sección</option>
                    <option value="A" <?= $filtroSeccion === 'A' ? 'selected' : '' ?>>Sección A</option>
                    <option value="B" <?= $filtroSeccion === 'B' ? 'selected' : '' ?>>Sección B</option>
                    <option value="C" <?= $filtroSeccion === 'C' ? 'selected' : '' ?>>Sección C</option>
                </select>

                <button type="button" class="btn btn-mint btn-sm text-xs w-full sm:w-auto" onclick="window.print()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Imprimir / PDF
                </button>
            </form>
        </div>
    </div>
                    <option value="B" <?= $filtroSeccion === 'B' ? 'selected' : '' ?>>Sección B</option>
                    <option value="C" <?= $filtroSeccion === 'C' ? 'selected' : '' ?>>Sección C</option>
                </select>

                <button type="button" class="btn btn-mint btn-sm text-xs" onclick="window.print()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Imprimir / Guardar PDF (Pliego)
                </button>
            </form>
        </div>
    </div>

    <!-- Guía y Contenedor de Cuadrícula de Carnets -->
    <div class="max-w-7xl mx-auto p-4 md:p-6">
        <div class="no-print glass-card p-4 mb-6 text-xs text-slate-600 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="chip chip-mint">Formato Normalizado</span>
                <span>Distribución optimizada: <strong>8 carnets por pliego (hoja carta/A4)</strong> listos para plastificar.</span>
            </div>
            <span class="text-slate-400 font-mono">DPI 300 Listo</span>
        </div>

        <div class="print-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php if (empty($estudiantes)): ?>
                <div class="col-span-4 text-center py-12 glass-card">
                    <p class="text-slate-500 text-sm">No se encontraron estudiantes para los filtros seleccionados.</p>
                </div>
            <?php else: ?>
                <?php foreach ($estudiantes as $e): ?>
                    <?php
                        // URL de generación de QR dinámico con el token del estudiante
                        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($e['token_qr']);
                    ?>
                    <div class="carnet-wrapper">
                        <!-- Cabecera Institucional -->
                        <div class="carnet-top-bar">
                            <img src="assets/img/logo_liceo.svg" alt="Logo">
                            <div class="carnet-titles">
                                <div class="inst-rep">República Bolivariana de Venezuela &bull; MPPE</div>
                                <div class="inst-name"><?= htmlspecialchars(INSTITUCION_NOMBRE) ?></div>
                            </div>
                        </div>

                        <!-- Cuerpo del Carnet -->
                        <div class="carnet-content">
                            <!-- Marca de agua de seguridad -->
                            <img src="assets/img/logo_liceo.svg" class="carnet-watermark" alt="Sello">

                            <!-- Fotografía del Alumno -->
                            <img src="assets/img/avatars/default_student.svg" alt="Foto Alumno" class="carnet-avatar">

                            <!-- Datos de Identificación -->
                            <div class="carnet-data">
                                <div class="carnet-student-name">
                                    <?= htmlspecialchars($e['apellidos']) ?><br>
                                    <span style="font-weight:600; color:#334155;"><?= htmlspecialchars($e['nombres']) ?></span>
                                </div>
                                <div class="carnet-detail-row">
                                    C.I.: <strong><?= htmlspecialchars($e['cedula']) ?></strong>
                                </div>
                                <div class="carnet-detail-row">
                                    Año: <strong><?= htmlspecialchars($e['ano_instruccion']) ?></strong> &bull; Sec: <strong><?= htmlspecialchars($e['seccion']) ?></strong>
                                </div>
                                <div class="carnet-detail-row">
                                    Periodo: <strong><?= htmlspecialchars(INSTITUCION_ANIO_ESCOLAR) ?></strong>
                                </div>
                            </div>

                            <!-- Código QR Criptográfico -->
                            <div class="carnet-qr-box" title="Token: <?= htmlspecialchars($e['token_qr']) ?>">
                                <img src="<?= $qrUrl ?>" alt="QR" loading="lazy">
                            </div>
                        </div>

                        <!-- Pie de Carnet -->
                        <div class="carnet-bottom-bar">
                            <span class="font-mono"><?= htmlspecialchars($e['token_qr']) ?></span>
                            <span>DEA: <?= htmlspecialchars(INSTITUCION_DEA) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Barra de Navegación Inferior para Móviles -->
    <nav class="mobile-bottom-nav no-print">
        <a href="dashboard_admin.php" class="mobile-nav-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            <span>Panel</span>
        </a>
        <a href="exportar_carnets.php" class="mobile-nav-btn active">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                <line x1="7" y1="8" x2="17" y2="8"></line>
                <line x1="7" y1="12" x2="13" y2="12"></line>
            </svg>
            <span>Carnets</span>
        </a>
        <button type="button" class="mobile-nav-btn" onclick="window.print()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            <span>Imprimir</span>
        </button>
        <a href="logout.php" class="mobile-nav-btn text-rose-500">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Salir</span>
        </a>
    </nav>
</body>
</html>
