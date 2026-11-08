<?php
/**
 * API: Procesar "Cierre de Lista"
 * Liceo Bolivariano "Rossana Melidei"
 * Registra inasistencias automáticas para estudiantes no escaneados en la sesión y despacha correos
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ip_guard.php';
require_once __DIR__ . '/../config/mailer.php';

// Seguridad territorial y de sesión
enforceIPAccess(true);

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión no autorizada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = getCurrentUser();
$profesorId  = $currentUser['id'];

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (empty($data['horario_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El identificador del horario es obligatorio.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$horarioId  = (int)$data['horario_id'];
$fechaHoy   = date('Y-m-d');
$horaActual = date('H:i:s');
$clientIp   = getClientIP();

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Sin conexión a base de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. Obtener datos del bloque de horario
    $stmtH = $pdo->prepare("SELECT id, materia, ano_instruccion, seccion FROM horarios WHERE id = ? LIMIT 1");
    $stmtH->execute([$horarioId]);
    $horario = $stmtH->fetch();

    if (!$horario) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Horario no encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $materia = $horario['materia'];
    $ano     = $horario['ano_instruccion'];
    $seccion = $horario['seccion'];

    // 2. Obtener lista de todos los estudiantes activos de esa sección
    $stmtEst = $pdo->prepare("
        SELECT id, nombres, apellidos, cedula, correo_representante 
        FROM estudiantes 
        WHERE ano_instruccion = ? AND seccion = ? AND estado = 'activo'
        ORDER BY apellidos, nombres ASC
    ");
    $stmtEst->execute([$ano, $seccion]);
    $todosEstudiantes = $stmtEst->fetchAll();

    // 3. Obtener los IDs de estudiantes que ya tienen registro hoy para esta materia
    $stmtAsist = $pdo->prepare("SELECT estudiante_id FROM asistencia WHERE materia = ? AND fecha = ?");
    $stmtAsist->execute([$materia, $fechaHoy]);
    $yaRegistrados = $stmtAsist->fetchAll(PDO::FETCH_COLUMN);

    $ausentesRegistrados = 0;
    $listaAusentes = [];

    // Preparar inserción de ausencia
    $stmtInsertAusente = $pdo->prepare("
        INSERT INTO asistencia (estudiante_id, profesor_id, materia, fecha, hora_escaneo, estado, ip_registro, sincronizado_offline)
        VALUES (?, ?, ?, ?, ?, 'Ausente', ?, 0)
    ");

    foreach ($todosEstudiantes as $est) {
        if (!in_array($est['id'], $yaRegistrados, true)) {
            // Estudiante no escaneado -> Marcar Ausente
            $stmtInsertAusente->execute([
                $est['id'],
                $profesorId,
                $materia,
                $fechaHoy,
                $horaActual,
                $clientIp
            ]);

            // Despachar alerta de inasistencia por correo
            if (!empty($est['correo_representante'])) {
                $nombreCompleto = $est['nombres'] . ' ' . $est['apellidos'];
                notificarInasistenciaRepresentante(
                    $est['correo_representante'],
                    $nombreCompleto,
                    $materia,
                    date('d/m/Y', strtotime($fechaHoy)),
                    $currentUser['nombre']
                );
            }

            $ausentesRegistrados++;
            $listaAusentes[] = [
                'nombres' => $est['nombres'],
                'apellidos' => $est['apellidos'],
                'cedula' => $est['cedula']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'mensaje' => sprintf('Cierre de lista ejecutado. Se registraron y notificaron %d inasistencias.', $ausentesRegistrados),
        'materia' => $materia,
        'fecha' => date('d/m/Y', strtotime($fechaHoy)),
        'total_estudiantes_seccion' => count($todosEstudiantes),
        'presentes_o_retardos' => count($yaRegistrados),
        'ausentes_nuevos' => $ausentesRegistrados,
        'lista_ausentes' => $listaAusentes
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    error_log('[Cierre de Lista Error]: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error durante el cierre de lista: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
