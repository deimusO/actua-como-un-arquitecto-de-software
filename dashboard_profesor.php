<?php
/**
 * Panel del Docente (Rol Profesor)
 * Liceo Bolivariano "Rossana Melidei"
 * Selección de cátedra según horario, resumen de asistencia del día,
 * Cierre de Lista y emisión de Notas de Atención
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/auth.php';

requireRole('profesor', 'admin');

$pageTitle = 'Panel del Docente';
$currentUser = getCurrentUser();
$profesorId = $currentUser['id'];

$diasEspanol = [
    'Monday'    => 'Lunes',
    'Tuesday'   => 'Martes',
    'Wednesday' => 'Miercoles',
    'Thursday'  => 'Jueves',
    'Friday'    => 'Viernes',
    'Saturday'  => 'Lunes', // Fallback para fines de semana en pruebas
    'Sunday'    => 'Lunes'
];
$diaHoy = $diasEspanol[date('l')] ?? 'Lunes';
$fechaHoy = date('Y-m-d');

$pdo = getDBConnection();
$horariosHoy = [];
$todosHorarios = [];
$asistenciasHoy = [];
$notasRecientes = [];
$estudiantesClase = [];

$horarioSeleccionadoId = (int)($_GET['horario_id'] ?? 0);

if ($pdo) {
    try {
        // 1. Obtener horarios del profesor
        $stmtH = $pdo->prepare("
            SELECT * FROM horarios 
            WHERE profesor_id = ? 
            ORDER BY FIELD(dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'), hora_entrada ASC
        ");
        $stmtH->execute([$profesorId]);
        $todosHorarios = $stmtH->fetchAll();

        foreach ($todosHorarios as $h) {
            if ($h['dia_semana'] === $diaHoy) {
                $horariosHoy[] = $h;
            }
        }

        // Si no hay horario seleccionado, auto-seleccionar el primero de hoy o el primero en general
        if ($horarioSeleccionadoId === 0 && !empty($horariosHoy)) {
            $horarioSeleccionadoId = (int)$horariosHoy[0]['id'];
        } elseif ($horarioSeleccionadoId === 0 && !empty($todosHorarios)) {
            $horarioSeleccionadoId = (int)$todosHorarios[0]['id'];
        }

        // 2. Obtener datos del horario activo
        $horarioActivo = null;
        if ($horarioSeleccionadoId > 0) {
            $stmtAct = $pdo->prepare("SELECT * FROM horarios WHERE id = ? LIMIT 1");
            $stmtAct->execute([$horarioSeleccionadoId]);
            $horarioActivo = $stmtAct->fetch();

            if ($horarioActivo) {
                // Obtener lista de asistencia hoy para esta materia
                $stmtAsist = $pdo->prepare("
                    SELECT a.*, e.nombres, e.apellidos, e.cedula, e.correo_representante
                    FROM asistencia a
                    JOIN estudiantes e ON a.estudiante_id = e.id
                    WHERE a.materia = ? AND a.fecha = ?
                    ORDER BY a.hora_escaneo DESC
                ");
                $stmtAsist->execute([$horarioActivo['materia'], $fechaHoy]);
                $asistenciasHoy = $stmtAsist->fetchAll();

                // Obtener todos los estudiantes de la sección para el modal de notas de atención
                $stmtEstSec = $pdo->prepare("
                    SELECT id, nombres, apellidos, cedula 
                    FROM estudiantes 
                    WHERE ano_instruccion = ? AND seccion = ? AND estado = 'activo'
                    ORDER BY apellidos, nombres ASC
                ");
                $stmtEstSec->execute([$horarioActivo['ano_instruccion'], $horarioActivo['seccion']]);
                $estudiantesClase = $stmtEstSec->fetchAll();
            }
        }

        // 3. Obtener notas de atención recientes registradas por este docente
        $stmtN = $pdo->prepare("
            SELECT n.*, e.nombres, e.apellidos, e.cedula 
            FROM notas_atencion n
            JOIN estudiantes e ON n.estudiante_id = e.id
            WHERE n.profesor_id = ?
            ORDER BY n.fecha DESC LIMIT 5
        ");
        $stmtN->execute([$profesorId]);
        $notasRecientes = $stmtN->fetchAll();

    } catch (\Throwable $e) {
        error_log('[Profesor Dashboard Error]: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Encabezado y Selector de Clase -->
<div class="glass-card p-3 sm:p-5 mb-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="chip chip-mint text-[11px]">Docente Titular</span>
                <span class="text-xs text-slate-500 font-medium">Jornada de hoy: <strong><?= $diaHoy ?> <?= date('d/m/Y') ?></strong></span>
            </div>
            <h2 class="text-lg sm:text-xl md:text-2xl font-extrabold text-slate-800 leading-tight">Control de Aula y Registro de Asistencia</h2>
            <p class="text-xs text-slate-500 mt-0.5">Seleccione su cátedra para escanear credenciales QR y gestionar la sesión.</p>
        </div>
        
        <?php if ($horarioActivo): ?>
            <div class="grid grid-cols-2 sm:flex items-center gap-2">
                <a href="escanear.php?horario_id=<?= $horarioActivo['id'] ?>" class="btn btn-mint btn-sm text-xs w-full sm:w-auto">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    Escanear QR
                </a>
                <button type="button" class="btn btn-rose btn-sm text-xs w-full sm:w-auto" onclick="ejecutarCierreLista(<?= $horarioActivo['id'] ?>)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 14 14"></polyline>
                    </svg>
                    Cierre Lista
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Selector de Bloques Horarios del Docente -->
<div class="glass-card p-5 mb-6">
    <div class="text-xs font-bold text-slate-700 uppercase mb-3 flex items-center justify-between">
        <span>Mis Asignaturas y Horarios:</span>
        <span class="text-slate-500 font-normal">Haga clic en una tarjeta para activar la sesión</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php if (empty($todosHorarios)): ?>
            <div class="col-span-3 text-center py-4 text-slate-500 text-xs">No tiene bloques de horarios asignados en el sistema.</div>
        <?php else: ?>
            <?php foreach ($todosHorarios as $h): ?>
                <?php 
                    $esActivo = ($h['id'] == $horarioSeleccionadoId);
                    $esHoy = ($h['dia_semana'] === $diaHoy);
                ?>
                <a href="dashboard_profesor.php?horario_id=<?= $h['id'] ?>" 
                   class="glass-card p-4 text-decoration-none border transition-all <?= $esActivo ? 'border-blue-400 bg-blue-50/50 shadow-md' : 'border-slate-200/80 hover:bg-white/90' ?>">
                    <div class="flex items-center justify-between mb-2">
                        <span class="chip <?= $esHoy ? 'chip-mint' : 'chip-blue' ?>" style="font-size: 10px;">
                            <?= htmlspecialchars($h['dia_semana']) ?> <?= $esHoy ? '(Hoy)' : '' ?>
                        </span>
                        <span class="text-xs font-mono font-bold text-slate-600">
                            <?= date('h:i A', strtotime($h['hora_entrada'])) ?>
                        </span>
                    </div>
                    <div class="font-bold text-slate-800 text-sm mb-1"><?= htmlspecialchars($h['materia']) ?></div>
                    <div class="text-xs text-slate-500">
                        <?= htmlspecialchars($h['ano_instruccion']) ?> &bull; Sección "<?= htmlspecialchars($h['seccion']) ?>"
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($horarioActivo): ?>
<!-- Resumen de Sesión Activa -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Tabla de Asistencias Registradas Hoy -->
    <div class="lg:col-span-2 glass-card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-200/70 mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-800">
                    Pase de Lista: <?= htmlspecialchars($horarioActivo['materia']) ?>
                </h3>
                <span class="text-xs text-slate-500">
                    <?= htmlspecialchars($horarioActivo['ano_instruccion']) ?> "<?= htmlspecialchars($horarioActivo['seccion']) ?>" &bull; <?= count($asistenciasHoy) ?> registros procesados hoy
                </span>
            </div>
            <div>
                <button type="button" class="btn btn-subtle btn-sm text-xs" onclick="document.getElementById('modal-nota-atencion').classList.remove('hidden')">
                    + Nota de Atención
                </button>
            </div>
        </div>

        <div class="table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Cédula</th>
                        <th>Hora Escaneo</th>
                        <th>Estado</th>
                        <th>Notificación</th>
                    </tr>
                </thead>
                <tbody id="tabla-asistencia-body">
                    <?php if (empty($asistenciasHoy)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-8 text-slate-500 text-xs">
                                No se han escaneado estudiantes aún para esta sesión de hoy.<br>
                                <a href="escanear.php?horario_id=<?= $horarioActivo['id'] ?>" class="btn btn-mint btn-sm mt-3 inline-flex">
                                    Iniciar Escaneo de Credenciales QR
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($asistenciasHoy as $a): ?>
                            <tr>
                                <td>
                                    <div class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($a['apellidos']) ?>, <?= htmlspecialchars($a['nombres']) ?></div>
                                </td>
                                <td><span class="text-xs font-mono text-slate-600"><?= htmlspecialchars($a['cedula']) ?></span></td>
                                <td class="font-mono text-xs font-semibold text-slate-700">
                                    <?= date('h:i:s A', strtotime($a['hora_escaneo'])) ?>
                                </td>
                                <td>
                                    <?php if ($a['estado'] === 'Presente'): ?>
                                        <span class="chip chip-mint text-xs">Presente</span>
                                    <?php elseif ($a['estado'] === 'Retraso'): ?>
                                        <span class="chip chip-amber text-xs">Retraso</span>
                                    <?php else: ?>
                                        <span class="chip chip-rose text-xs">Ausente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-xs text-emerald-600 font-semibold flex items-center gap-1">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                        Enviada
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 pt-3 border-t border-slate-200/60 flex items-center justify-between text-xs text-slate-400">
            <span>🛡️ Por integridad de auditoría, las asistencias grabadas no pueden eliminarse por el docente.</span>
        </div>
    </div>

    <!-- Notas de Atención Recientes del Docente -->
    <div class="glass-card p-5 flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200/70 mb-4">
            <h3 class="text-sm font-bold text-slate-800">Notas de Atención</h3>
            <button type="button" class="btn btn-primary btn-sm text-xs" onclick="document.getElementById('modal-nota-atencion').classList.remove('hidden')">
                Nueva
            </button>
        </div>

        <div class="flex-1 overflow-y-auto space-y-3">
            <?php if (empty($notasRecientes)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">No ha emitido notas de atención recientemente.</div>
            <?php else: ?>
                <?php foreach ($notasRecientes as $nota): ?>
                    <div class="glass-card-subtle p-3 rounded-xl border border-blue-100 bg-white/70">
                        <div class="flex justify-between items-start mb-1">
                            <strong class="text-xs text-slate-800"><?= htmlspecialchars($nota['nombres']) ?> <?= htmlspecialchars($nota['apellidos']) ?></strong>
                            <span class="text-xs text-slate-400 font-mono"><?= date('d/m H:i', strtotime($nota['fecha'])) ?></span>
                        </div>
                        <p class="text-xs text-slate-600 italic leading-relaxed">
                            “<?= htmlspecialchars($nota['motivo']) ?>”
                        </p>
                        <div class="mt-2 text-right">
                            <span class="chip chip-blue" style="font-size: 9px; padding: 1px 5px;">Notificado al Rep.</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Registrar Nota de Atención -->
<div id="modal-nota-atencion" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="glass-card max-w-md w-full p-6 shadow-2xl relative bg-white/95">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <h3 class="text-lg font-bold text-slate-800">Emitir Nota de Atención</h3>
            <button type="button" class="text-slate-400 hover:text-slate-700" onclick="document.getElementById('modal-nota-atencion').classList.add('hidden')">✕</button>
        </div>
        <form id="form-nota-atencion" onsubmit="guardarNotaAtencion(event)">
            <div class="form-group">
                <label class="form-label">Estudiante de la Cátedra</label>
                <select name="estudiante_id" class="form-control" required>
                    <?php if (empty($estudiantesClase)): ?>
                        <option value="">No hay estudiantes cargados en esta sección</option>
                    <?php else: ?>
                        <?php foreach ($estudiantesClase as $ec): ?>
                            <option value="<?= $ec['id'] ?>">
                                <?= htmlspecialchars($ec['apellidos']) ?>, <?= htmlspecialchars($ec['nombres']) ?> (<?= htmlspecialchars($ec['cedula']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Motivo u Observación Pedagógica / Convivencia</label>
                <textarea name="motivo" class="form-control" rows="4" required placeholder="Describa el motivo pedagógico o disciplinario. Se despachará una notificación por correo al representante de forma inmediata..."></textarea>
            </div>
            <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-slate-200">
                <button type="button" class="btn btn-subtle text-xs" onclick="document.getElementById('modal-nota-atencion').classList.add('hidden')">Cancelar</button>
                <button type="submit" class="btn btn-primary text-xs">Guardar y Enviar Notificación</button>
            </div>
        </form>
    </div>
</div>

<script>
async function ejecutarCierreLista(horarioId) {
    if (!confirm('¿Confirma que desea realizar el CIERRE DE LISTA para esta sesión? Todos los alumnos de la sección que no fueron escaneados serán marcados automáticamente como AUSENTES y se notificará a sus representantes por correo.')) {
        return;
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('api/procesar_cierre_lista.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ horario_id: horarioId })
        });
        const data = await res.json();
        if (data.success) {
            alert(data.mensaje);
            window.location.reload();
        } else {
            alert(data.error || 'Error en el cierre de lista.');
        }
    } catch (e) {
        alert('Error al comunicar con el servidor.');
    }
}

async function guardarNotaAtencion(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    try {
        const res = await fetch('api/guardar_nota_atencion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            alert('Nota de atención guardada y notificada por correo al representante.');
            window.location.reload();
        } else {
            alert(data.error || 'Error al guardar.');
        }
    } catch (err) {
        alert('Error de red al registrar nota.');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
