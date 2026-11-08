<?php
/**
 * Módulo de Escaneo QR en Vivo
 * Liceo Bolivariano "Rossana Melidei"
 * Integración con html5-qrcode, Web Audio API, Anti-Fraude IP y Resiliencia Offline
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ip_guard.php';

// 1. Validar restricción territorial de red IP
enforceIPAccess();

// 2. Exigir autenticación de docente o directivo
requireRole('profesor', 'admin');

$pageTitle = 'Escáner QR de Asistencia';
$includeScannerScript = true;

$currentUser = getCurrentUser();
$profesorId  = $currentUser['id'];

$horarioId = (int)($_GET['horario_id'] ?? 0);
$pdo = getDBConnection();

$horarioActivo = null;
$todosHorarios = [];

if ($pdo) {
    try {
        $stmtH = $pdo->prepare("SELECT * FROM horarios WHERE profesor_id = ? ORDER BY hora_entrada ASC");
        $stmtH->execute([$profesorId]);
        $todosHorarios = $stmtH->fetchAll();

        if ($horarioId > 0) {
            $stmtAct = $pdo->prepare("SELECT * FROM horarios WHERE id = ? LIMIT 1");
            $stmtAct->execute([$horarioId]);
            $horarioActivo = $stmtAct->fetch();
        } elseif (!empty($todosHorarios)) {
            $horarioActivo = $todosHorarios[0];
            $horarioId = (int)$horarioActivo['id'];
        }
    } catch (\Throwable $e) {
        error_log('[Escaneo Page Error]: ' . $e->getMessage());
    }
}

$clientIp = getClientIP();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Encabezado del Módulo de Escaneo -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <span class="chip chip-mint">Escáner Activo</span>
            <span class="chip chip-blue font-mono text-xs">IP: <?= htmlspecialchars($clientIp) ?> (Verificada)</span>
        </div>
        <h2 class="text-2xl font-extrabold text-slate-800">Captura de Asistencia Escolar</h2>
        <p class="text-xs text-slate-600">Apunta el carnet del estudiante frente al lente de la cámara institucional.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="dashboard_profesor.php?horario_id=<?= $horarioId ?>" class="btn btn-subtle text-xs">
            Volver al Panel
        </a>
    </div>
</div>

<!-- Selector de Clase Activa y Controles del Escáner -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Columna Izquierda: Visor del Escáner de Cámara -->
    <div class="lg:col-span-2 glass-card p-5 flex flex-col items-center">
        <!-- Barra de Estado de la Cátedra -->
        <div class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-200/70 mb-4">
            <div id="clase-activa-banner">
                <?php if ($horarioActivo): ?>
                    <span class="chip chip-blue text-xs mb-1">Cátedra Seleccionada</span>
                    <h3 class="text-sm font-bold text-slate-800">
                        <?= htmlspecialchars($horarioActivo['materia']) ?> (<?= htmlspecialchars($horarioActivo['ano_instruccion']) ?> "<?= htmlspecialchars($horarioActivo['seccion']) ?>")
                    </h3>
                <?php else: ?>
                    <span class="chip chip-amber text-xs">Seleccione una clase para iniciar</span>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-2">
                <button type="button" id="btn-start-scanner" class="btn btn-primary text-xs" onclick="window.liceoQRScanner.startCamera()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="23 7 16 12 23 17 23 7"></polygon>
                        <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                    </svg>
                    Encender Cámara
                </button>
                <button type="button" id="btn-stop-scanner" class="btn btn-rose text-xs" style="display:none;" onclick="window.liceoQRScanner.stopCamera()">
                    Apagar Cámara
                </button>
            </div>
        </div>

        <!-- Visor del Lector HTML5-QRCode -->
        <div class="w-full max-w-md my-2">
            <div class="scanner-wrapper">
                <div id="reader"></div>
                <div id="scanner-overlay" class="scanner-frame-overlay" style="display:none;">
                    <div class="scanner-scanline"></div>
                </div>
            </div>
            <div id="scanner-feedback-badge" class="text-center mt-3" style="display:none;"></div>
        </div>

        <!-- Alternativa: Cargar archivo de imagen QR -->
        <div class="w-full max-w-md mt-4 pt-3 border-t border-slate-200/60 text-center">
            <label class="text-xs text-slate-500 block mb-2 font-medium">¿Problemas con el lente o dispositivo sin cámara?</label>
            <input type="file" id="qr-file-input" accept="image/*" class="text-xs text-slate-600 file:btn file:btn-subtle file:btn-sm file:mr-2">
        </div>

        <!-- Botones de Demostración Rápida (Simulador de Escaneo en XAMPP) -->
        <div class="w-full glass-card-subtle p-3 rounded-xl mt-4 text-left">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-700">Simulador de Tokens QR (Pruebas Locales):</span>
                <span class="chip chip-mint" style="font-size: 10px;">Clic para testear</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-subtle btn-sm text-xs" onclick="simularEscaneo('RM-QR-A4A9B1C3D8E2F1')">
                    Morales Silva (4to A)
                </button>
                <button type="button" class="btn btn-subtle btn-sm text-xs" onclick="simularEscaneo('RM-QR-B7E3C2D9F0A4E5')">
                    Castillo Rivas (4to A)
                </button>
                <button type="button" class="btn btn-subtle btn-sm text-xs" onclick="simularEscaneo('RM-QR-E8A2B6C4F1D9E3')">
                    Gómez Torrealba (4to A)
                </button>
                <button type="button" class="btn btn-subtle btn-sm text-xs" onclick="simularEscaneo('RM-QR-INVALIDO-999')">
                    Token No Válido
                </button>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Configuración de Clase & Resiliencia Offline -->
    <div class="space-y-4">
        <!-- Tarjeta de Estado Territorial de Red (Anti-Fraude) -->
        <div class="glass-card p-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="badge-icon badge-mint" style="width:40px; height:40px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-slate-800">Red Territorial Validada</h4>
                    <span class="text-xs text-emerald-600 font-semibold">Subred Autorizada Activa</span>
                </div>
            </div>
            <p class="text-xs text-slate-600 leading-relaxed">
                Su dispositivo se encuentra conectado a la red local institucional autorizada (<strong><?= htmlspecialchars($clientIp) ?></strong>). El módulo anti-fraude valida las coordenadas de red antes de autorizar cualquier marcaje de asistencia.
            </p>
        </div>

        <!-- Tarjeta de Resiliencia y Contingencia Offline -->
        <div class="glass-card p-4">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-xs font-bold text-slate-800 flex items-center gap-1">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                    </svg>
                    Almacenamiento Offline
                </h4>
                <span class="chip chip-amber offline-count-badge hidden" style="display:none;">0</span>
            </div>
            <p class="text-xs text-slate-600 mb-3 leading-relaxed">
                Si la conectividad local o el internet sufren interrupciones, las capturas de asistencia se protegen inmediatamente en la memoria local (LocalStorage / IndexedDB). Al retornar el enlace, se sincronizan automáticamente sin pérdida de datos.
            </p>
            <button type="button" class="btn btn-mint btn-sm w-full text-xs btn-sync-offline" onclick="window.offlineAttendanceManager.syncQueue()">
                Sincronizar Cola Local
            </button>
        </div>

        <!-- Selector de Otras Cátedras Asignadas -->
        <div class="glass-card p-4">
            <h4 class="text-xs font-bold text-slate-800 mb-3">Cambiar de Asignatura:</h4>
            <div class="space-y-2">
                <?php foreach ($todosHorarios as $th): ?>
                    <a href="escanear.php?horario_id=<?= $th['id'] ?>" 
                       class="block p-2 rounded-lg border text-xs text-decoration-none <?= ($th['id'] == $horarioId) ? 'border-blue-400 bg-blue-50/60 font-bold text-blue-700' : 'border-slate-200 text-slate-600 hover:bg-white/80' ?>">
                        <?= htmlspecialchars($th['materia']) ?> (<?= htmlspecialchars($th['dia_semana']) ?> <?= date('h:i A', strtotime($th['hora_entrada'])) ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Flotante de Resultado de Escaneo -->
<div id="modal-scan-result" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="glass-card max-w-sm w-full p-5 shadow-2xl relative bg-white/95 animate-fade-in modal-content-body">
        <!-- Rellenado dinámicamente por scanner.js -->
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar clase activa en el escáner
    <?php if ($horarioActivo): ?>
        window.liceoQRScanner.setClaseActiva(<?= (int)$horarioActivo['id'] ?>, <?= json_encode($horarioActivo['materia']) ?>);
    <?php endif; ?>

    // Manejar carga de archivos de imagen QR
    const fileInput = document.getElementById('qr-file-input');
    if (fileInput) {
        fileInput.addEventListener('change', async (e) => {
            if (e.target.files.length === 0) return;
            const imageFile = e.target.files[0];
            try {
                if (!window.Html5Qrcode) return;
                const html5QrCode = new Html5Qrcode("reader");
                const decodedText = await html5QrCode.scanFile(imageFile, true);
                window.liceoQRScanner.onQrCodeSuccess(decodedText);
            } catch (err) {
                alert('No se detectó un código QR legible en la imagen seleccionada.');
            }
        });
    }

    // Inicializar contador de cola offline
    window.offlineAttendanceManager.updateBadgeUI();
});

function simularEscaneo(token) {
    if (!window.liceoQRScanner.selectedHorarioId) {
        alert('Por favor active primero una clase u horario.');
        return;
    }
    window.liceoQRScanner.onQrCodeSuccess(token);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
