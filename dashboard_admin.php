<?php
/**
 * Panel de Administración y Control Escolar (Rol Administrador)
 * Liceo Bolivariano "Rossana Melidei"
 * Incluye KPIs, Gráficos Analíticos (Chart.js), CRUD de Estudiantes y Horarios
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/auth.php';

requireRole('admin');

$pageTitle = 'Panel Administrativo';
$includeChartsScript = true;

$pdo = getDBConnection();
$fechaHoy = date('Y-m-d');

// Métricas Estadísticas del Sistema
$totalEstudiantes = 0;
$presentesHoy     = 0;
$retardosHoy      = 0;
$ausentesHoy      = 0;
$totalNotas       = 0;
$estudiantes      = [];
$horarios         = [];
$profesores       = [];

if ($pdo) {
    try {
        // 1. Total estudiantes activos
        $totalEstudiantes = (int)$pdo->query("SELECT COUNT(*) FROM estudiantes WHERE estado = 'activo'")->fetchColumn();

        // 2. Asistencias de hoy desglosadas
        $stmtHoy = $pdo->prepare("SELECT estado, COUNT(*) as cant FROM asistencia WHERE fecha = ? GROUP BY estado");
        $stmtHoy->execute([$fechaHoy]);
        $statsHoy = $stmtHoy->fetchAll(PDO::FETCH_KEY_PAIR);

        $presentesHoy = (int)($statsHoy['Presente'] ?? 0);
        $retardosHoy  = (int)($statsHoy['Retraso'] ?? 0);
        $ausentesHoy  = (int)($statsHoy['Ausente'] ?? 0);

        // 3. Total notas de atención registradas
        $totalNotas = (int)$pdo->query("SELECT COUNT(*) FROM notas_atencion")->fetchColumn();

        // 4. Lista de Estudiantes
        $stmtEst = $pdo->query("
            SELECT id, nombres, apellidos, cedula, ano_instruccion, seccion, correo_representante, token_qr, estado 
            FROM estudiantes 
            ORDER BY ano_instruccion ASC, seccion ASC, apellidos ASC
        ");
        $estudiantes = $stmtEst->fetchAll();

        // 5. Lista de Horarios
        $stmtHor = $pdo->query("
            SELECT h.*, u.nombre as profesor_nombre 
            FROM horarios h
            JOIN usuarios u ON h.profesor_id = u.id
            ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'), h.hora_entrada ASC
        ");
        $horarios = $stmtHor->fetchAll();

        // 6. Lista de Docentes para asignación
        $stmtProf = $pdo->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'profesor' AND estado = 'activo'");
        $profesores = $stmtProf->fetchAll();

    } catch (\Throwable $e) {
        error_log('[Admin Dashboard Error]: ' . $e->getMessage());
    }
}

$totalRegistrosHoy = $presentesHoy + $retardosHoy + $ausentesHoy;
$tasaPuntualidad = ($totalRegistrosHoy > 0) ? round(($presentesHoy / $totalRegistrosHoy) * 100, 1) : 0.0;

require_once __DIR__ . '/includes/header.php';
?>

<!-- Encabezado de Sección y Acciones Rápidas -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <span class="chip chip-blue">Dirección del Plantel</span>
            <span class="text-xs text-slate-500 font-mono"><?= date('d/m/Y') ?></span>
        </div>
        <h2 class="text-2xl font-extrabold text-slate-800">Panel Integral de Control Académico</h2>
        <p class="text-xs text-slate-600">Monitoreo territorial de puntualidad, ausentismo y expedición de credenciales.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="exportar_carnets.php" class="btn btn-mint text-xs">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            Imprimir Carnets (PDF)
        </a>
        <button type="button" class="btn btn-primary text-xs" onclick="document.getElementById('modal-nuevo-estudiante').classList.remove('hidden')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Nuevo Estudiante
        </button>
    </div>
</div>

<!-- Tarjetas KPI Estadísticas -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="glass-card p-5 glass-card-hover flex items-center gap-4">
        <div class="badge-icon badge-blue">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <div>
            <div class="text-xs font-semibold text-slate-500 uppercase">Matrícula Activa</div>
            <div class="text-2xl font-extrabold text-slate-800"><?= $totalEstudiantes ?></div>
            <div class="text-xs text-blue-600 font-medium">Estudiantes registrados</div>
        </div>
    </div>

    <div class="glass-card p-5 glass-card-hover flex items-center gap-4">
        <div class="badge-icon badge-mint">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <div>
            <div class="text-xs font-semibold text-slate-500 uppercase">Asistencia Hoy</div>
            <div class="text-2xl font-extrabold text-emerald-600"><?= $presentesHoy ?></div>
            <div class="text-xs text-slate-500 font-medium">+<?= $retardosHoy ?> con retardo</div>
        </div>
    </div>

    <div class="glass-card p-5 glass-card-hover flex items-center gap-4">
        <div class="badge-icon badge-rose">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
        </div>
        <div>
            <div class="text-xs font-semibold text-slate-500 uppercase">Inasistencias Hoy</div>
            <div class="text-2xl font-extrabold text-rose-500"><?= $ausentesHoy ?></div>
            <div class="text-xs text-slate-500 font-medium">Notificados a representantes</div>
        </div>
    </div>

    <div class="glass-card p-5 glass-card-hover flex items-center gap-4">
        <div class="badge-icon badge-blue" style="background:#fef3c7; color:#d97706;">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
        </div>
        <div>
            <div class="text-xs font-semibold text-slate-500 uppercase">Notas de Atención</div>
            <div class="text-2xl font-extrabold text-amber-600"><?= $totalNotas ?></div>
            <div class="text-xs text-slate-500 font-medium">Observaciones de cátedra</div>
        </div>
    </div>
</div>

<!-- Gráficos Analíticos Chart.js -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Gráfica 1: Distribución Asistencia Hoy -->
    <div class="glass-card p-5 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-slate-800">Distribución de Hoy</h3>
            <span class="chip chip-mint text-xs"><?= $tasaPuntualidad ?>% Puntual</span>
        </div>
        <div style="position: relative; height: 230px; width: 100%;">
            <canvas id="chartAttendanceToday" 
                    data-presentes="<?= $presentesHoy ?>" 
                    data-retardos="<?= $retardosHoy ?>" 
                    data-ausentes="<?= $ausentesHoy ?>">
            </canvas>
        </div>
    </div>

    <!-- Gráfica 2: Tendencia Semanal -->
    <div class="glass-card p-5 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-slate-800">Tendencia Semanal (%)</h3>
            <span class="chip chip-blue text-xs">Lunes - Viernes</span>
        </div>
        <div style="position: relative; height: 230px; width: 100%;">
            <canvas id="chartWeeklyTrend"></canvas>
        </div>
    </div>

    <!-- Gráfica 3: Ausentismo por Años de Estudio -->
    <div class="glass-card p-5 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-slate-800">Ausentismo por Nivel</h3>
            <span class="chip chip-rose text-xs">1ro a 5to Año</span>
        </div>
        <div style="position: relative; height: 230px; width: 100%;">
            <canvas id="chartAbsenteeismLevels"></canvas>
        </div>
    </div>
</div>

<!-- Pestañas de Gestión: Estudiantes y Horarios -->
<div class="glass-card p-6 mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/70 pb-4 mb-6">
        <div class="flex gap-2">
            <button type="button" class="btn btn-primary btn-sm tab-btn active" id="tab-btn-estudiantes" onclick="cambiarTab('estudiantes')">
                Estudiantes Registrados (<?= count($estudiantes) ?>)
            </button>
            <button type="button" class="btn btn-subtle btn-sm tab-btn" id="tab-btn-horarios" onclick="cambiarTab('horarios')">
                Horarios Académicos (<?= count($horarios) ?>)
            </button>
        </div>
        <div class="text-xs text-slate-500">
            Control Escolar v2.5 &bull; Filtro Dinámico
        </div>
    </div>

    <!-- TAB 1: GESTIÓN DE ESTUDIANTES -->
    <div id="tab-content-estudiantes">
        <div class="table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Cédula</th>
                        <th>Año y Sección</th>
                        <th>Representante</th>
                        <th>Token QR</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($estudiantes)): ?>
                        <tr><td colspan="6" class="text-center py-6 text-slate-500">No hay estudiantes registrados aún.</td></tr>
                    <?php else: ?>
                        <?php foreach ($estudiantes as $est): ?>
                            <tr>
                                <td>
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($est['apellidos']) ?>, <?= htmlspecialchars($est['nombres']) ?></div>
                                    <span class="text-xs text-slate-400">ID: #<?= $est['id'] ?></span>
                                </td>
                                <td><span class="chip chip-blue font-mono"><?= htmlspecialchars($est['cedula']) ?></span></td>
                                <td>
                                    <div class="font-semibold text-slate-700"><?= htmlspecialchars($est['ano_instruccion']) ?></div>
                                    <span class="chip chip-mint text-xs">Sección <?= htmlspecialchars($est['seccion']) ?></span>
                                </td>
                                <td>
                                    <div class="text-xs font-semibold text-slate-800"><?= htmlspecialchars($est['correo_representante']) ?></div>
                                </td>
                                <td>
                                    <span class="font-mono text-xs bg-white/70 px-2 py-1 rounded border border-slate-200"><?= htmlspecialchars($est['token_qr']) ?></span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <a href="exportar_carnets.php?id=<?= $est['id'] ?>" class="btn btn-subtle btn-sm text-xs" title="Ver Carnet">
                                            Carnet
                                        </a>
                                        <button type="button" class="btn btn-subtle btn-sm text-xs" onclick="regenerarToken(<?= $est['id'] ?>)" title="Regenerar Token QR">
                                            QR
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: GESTIÓN DE HORARIOS -->
    <div id="tab-content-horarios" class="hidden" style="display:none;">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-sm font-bold text-slate-800">Planificación de Bloques Semanales</h4>
            <button type="button" class="btn btn-primary btn-sm text-xs" onclick="document.getElementById('modal-nuevo-horario').classList.remove('hidden')">
                + Asignar Bloque Horario
            </button>
        </div>
        <div class="table-container">
            <table class="glass-table">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Horario</th>
                        <th>Asignatura</th>
                        <th>Año y Sección</th>
                        <th>Docente Responsable</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($horarios)): ?>
                        <tr><td colspan="6" class="text-center py-6 text-slate-500">No se han registrado horarios aún.</td></tr>
                    <?php else: ?>
                        <?php foreach ($horarios as $h): ?>
                            <tr>
                                <td><span class="chip chip-blue font-bold"><?= htmlspecialchars($h['dia_semana']) ?></span></td>
                                <td class="font-mono text-xs font-bold text-slate-800">
                                    <?= date('h:i A', strtotime($h['hora_entrada'])) ?> - <?= date('h:i A', strtotime($h['hora_salida'])) ?>
                                </td>
                                <td class="font-bold text-slate-800"><?= htmlspecialchars($h['materia']) ?></td>
                                <td><?= htmlspecialchars($h['ano_instruccion']) ?> "<?= htmlspecialchars($h['seccion']) ?>"</td>
                                <td class="font-semibold text-slate-700"><?= htmlspecialchars($h['profesor_nombre']) ?></td>
                                <td>
                                    <button type="button" class="btn btn-rose btn-sm text-xs" onclick="eliminarHorario(<?= $h['id'] ?>)">Eliminar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Registro de Nuevo Estudiante -->
<div id="modal-nuevo-estudiante" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="glass-card max-w-lg w-full p-6 shadow-2xl relative animate-fade-in bg-white/95">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <h3 class="text-lg font-bold text-slate-800">Inscribir Nuevo Estudiante</h3>
            <button type="button" class="text-slate-400 hover:text-slate-700" onclick="document.getElementById('modal-nuevo-estudiante').classList.add('hidden')">✕</button>
        </div>
        <form id="form-nuevo-estudiante" onsubmit="guardarEstudiante(event)">
            <div class="grid grid-cols-2 gap-3">
                <div class="form-group">
                    <label class="form-label">Nombres</label>
                    <input type="text" name="nombres" class="form-control" required placeholder="Ej: Jesús David">
                </div>
                <div class="form-group">
                    <label class="form-label">Apellidos</label>
                    <input type="text" name="apellidos" class="form-control" required placeholder="Ej: Rodríguez Gómez">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="form-group">
                    <label class="form-label">Cédula de Identidad</label>
                    <input type="text" name="cedula" class="form-control" required placeholder="V-32.123.456">
                </div>
                <div class="form-group">
                    <label class="form-label">Año de Instrucción</label>
                    <select name="ano_instruccion" class="form-control" required>
                        <option value="1er Año">1er Año</option>
                        <option value="2do Año">2do Año</option>
                        <option value="3er Año">3er Año</option>
                        <option value="4to Año" selected>4to Año</option>
                        <option value="5to Año">5to Año</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="form-group">
                    <label class="form-label">Sección</label>
                    <select name="seccion" class="form-control" required>
                        <option value="A" selected>Sección A</option>
                        <option value="B">Sección B</option>
                        <option value="C">Sección C</option>
                        <option value="D">Sección D</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Teléfono Representante</label>
                    <input type="text" name="telefono_representante" class="form-control" placeholder="0412-1234567">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Correo Electrónico del Representante (Notificaciones)</label>
                <input type="email" name="correo_representante" class="form-control" required placeholder="representante@gmail.com">
                <span class="text-xs text-slate-500 mt-1 block">Recibirá alertas de asistencia, retrasos y notas de atención.</span>
            </div>
            <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-slate-200">
                <button type="button" class="btn btn-subtle" onclick="document.getElementById('modal-nuevo-estudiante').classList.add('hidden')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar y Generar QR</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Asignar Horario -->
<div id="modal-nuevo-horario" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="glass-card max-w-lg w-full p-6 shadow-2xl relative bg-white/95">
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
            <h3 class="text-lg font-bold text-slate-800">Asignar Bloque Académico</h3>
            <button type="button" class="text-slate-400 hover:text-slate-700" onclick="document.getElementById('modal-nuevo-horario').classList.add('hidden')">✕</button>
        </div>
        <form id="form-nuevo-horario" onsubmit="guardarHorario(event)">
            <div class="form-group">
                <label class="form-label">Docente a Cargo</label>
                <select name="profesor_id" class="form-control" required>
                    <?php foreach ($profesores as $prof): ?>
                        <option value="<?= $prof['id'] ?>"><?= htmlspecialchars($prof['nombre']) ?> (<?= htmlspecialchars($prof['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Asignatura / Cátedra</label>
                <input type="text" name="materia" class="form-control" required placeholder="Ej: Física General">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="form-group">
                    <label class="form-label">Año</label>
                    <select name="ano_instruccion" class="form-control" required>
                        <option value="1er Año">1er Año</option>
                        <option value="2do Año">2do Año</option>
                        <option value="3er Año">3er Año</option>
                        <option value="4to Año">4to Año</option>
                        <option value="5to Año">5to Año</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sección</label>
                    <input type="text" name="seccion" class="form-control" required value="A" maxlength="2">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="form-group">
                    <label class="form-label">Día</label>
                    <select name="dia_semana" class="form-control" required>
                        <option value="Lunes">Lunes</option>
                        <option value="Martes">Martes</option>
                        <option value="Miercoles">Miércoles</option>
                        <option value="Jueves">Jueves</option>
                        <option value="Viernes">Viernes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Hora Entrada</label>
                    <input type="time" name="hora_entrada" class="form-control" required value="07:00">
                </div>
                <div class="form-group">
                    <label class="form-label">Hora Salida</label>
                    <input type="time" name="hora_salida" class="form-control" required value="08:30">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-slate-200">
                <button type="button" class="btn btn-subtle" onclick="document.getElementById('modal-nuevo-horario').classList.add('hidden')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Asignar Bloque</button>
            </div>
        </form>
    </div>
</div>

<script>
function cambiarTab(tab) {
    const tabEst = document.getElementById('tab-content-estudiantes');
    const tabHor = document.getElementById('tab-content-horarios');
    const btnEst = document.getElementById('tab-btn-estudiantes');
    const btnHor = document.getElementById('tab-btn-horarios');

    if (tab === 'estudiantes') {
        tabEst.style.display = 'block';
        tabHor.style.display = 'none';
        btnEst.classList.add('btn-primary');
        btnEst.classList.remove('btn-subtle');
        btnHor.classList.add('btn-subtle');
        btnHor.classList.remove('btn-primary');
    } else {
        tabEst.style.display = 'none';
        tabHor.style.display = 'block';
        btnHor.classList.add('btn-primary');
        btnHor.classList.remove('btn-subtle');
        btnEst.classList.add('btn-subtle');
        btnEst.classList.remove('btn-primary');
    }
}

async function guardarEstudiante(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    try {
        const res = await fetch('api/estudiantes_api.php?action=crear', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            alert('Estudiante registrado exitosamente con Token QR: ' + data.token_qr);
            window.location.reload();
        } else {
            alert(data.error || 'Error al guardar');
        }
    } catch (err) {
        alert('Error de red al guardar estudiante');
    }
}

async function regenerarToken(id) {
    if (!confirm('¿Desea generar un nuevo código QR para este estudiante? El código anterior quedará invalidado.')) return;
    try {
        const res = await fetch('api/estudiantes_api.php?action=regenerar_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            alert('Nuevo código QR asignado: ' + data.nuevo_token);
            window.location.reload();
        }
    } catch (e) {
        alert('Error al regenerar token');
    }
}

async function guardarHorario(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    try {
        const res = await fetch('api/horarios_api.php?action=crear', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            alert('Bloque de horario asignado exitosamente.');
            window.location.reload();
        } else {
            alert(data.error || 'Error al asignar');
        }
    } catch (err) {
        alert('Error de conexión');
    }
}

async function eliminarHorario(id) {
    if (!confirm('¿Está seguro de eliminar este bloque académico?')) return;
    try {
        const res = await fetch('api/horarios_api.php?action=eliminar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
        }
    } catch (e) {
        alert('Error al eliminar');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
