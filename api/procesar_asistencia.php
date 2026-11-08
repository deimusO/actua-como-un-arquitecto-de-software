<?php
/**
 * API: Procesar Escaneo de Asistencia en Tiempo Real
 * Liceo Bolivariano "Rossana Melidei"
 * Valida Restricción por IP (Anti-Fraude), Token Estudiantil, Horario y Notifica por Correo
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ip_guard.php';
require_once __DIR__ . '/../config/mailer.php';

// 1. RESTRICCIÓN DE SEGURIDAD POR IP ANTI-FRAUDE
enforceIPAccess(true);

// 2. Comprobar autenticación de usuario (Profesor o Administrador)
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión expirada o no iniciada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = getCurrentUser();
$profesorId = $currentUser['id'];

// 3. Procesar payload JSON entrante
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (empty($data['token_qr']) || empty($data['horario_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan parámetros requeridos: token_qr y horario_id.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tokenQr   = trim((string)$data['token_qr']);
$horarioId = (int)$data['horario_id'];
$fechaHoy  = date('Y-m-d');
$horaActual = date('H:i:s');
$clientIp   = getClientIP();

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de conexión con la base de datos MySQL.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 4. Buscar estudiante por token QR
    $stmtEst = $pdo->prepare("SELECT id, nombres, apellidos, cedula, ano_instruccion, seccion, correo_representante, estado FROM estudiantes WHERE token_qr = ? LIMIT 1");
    $stmtEst->execute([$tokenQr]);
    $estudiante = $stmtEst->fetch();

    if (!$estudiante) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Código QR no reconocido o estudiante no matriculado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($estudiante['estado'] !== 'activo') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'El estudiante se encuentra en estado inactivo en el sistema.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 5. Validar horario y materia correspondiente
    $stmtHor = $pdo->prepare("SELECT id, materia, ano_instruccion, seccion, hora_entrada, hora_salida FROM horarios WHERE id = ? LIMIT 1");
    $stmtHor->execute([$horarioId]);
    $horario = $stmtHor->fetch();

    if (!$horario) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bloque de horario no válido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $materia = $horario['materia'];

    // 6. Verificar si ya fue registrada la asistencia hoy para esta materia
    $stmtCheck = $pdo->prepare("SELECT id, hora_escaneo, estado FROM asistencia WHERE estudiante_id = ? AND materia = ? AND fecha = ? LIMIT 1");
    $stmtCheck->execute([$estudiante['id'], $materia, $fechaHoy]);
    $asistenciaExistente = $stmtCheck->fetch();

    if ($asistenciaExistente) {
        echo json_encode([
            'success' => false,
            'duplicado' => true,
            'error' => sprintf('El estudiante ya registró asistencia para %s a las %s (Condición: %s).', $materia, $asistenciaExistente['hora_escaneo'], $asistenciaExistente['estado']),
            'estudiante' => $estudiante
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 7. Evaluar condición: 'Presente' vs 'Retraso' según tolerancia de minutos
    $horaEntradaTimestamp = strtotime($fechaHoy . ' ' . $horario['hora_entrada']);
    $horaActualTimestamp  = strtotime($fechaHoy . ' ' . $horaActual);
    $diferenciaMinutos    = round(($horaActualTimestamp - $horaEntradaTimestamp) / 60);

    $estadoAsistencia = 'Presente';
    if ($diferenciaMinutos > MINUTOS_TOLERANCIA_PRESENTE) {
        $estadoAsistencia = 'Retraso';
    }

    // 8. Insertar registro seguro con PDO
    $stmtInsert = $pdo->prepare("
        INSERT INTO asistencia (estudiante_id, profesor_id, materia, fecha, hora_escaneo, estado, ip_registro, sincronizado_offline)
        VALUES (?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmtInsert->execute([
        $estudiante['id'],
        $profesorId,
        $materia,
        $fechaHoy,
        $horaActual,
        $estadoAsistencia,
        $clientIp
    ]);

    // 9. Despacho de Notificación Automática por Correo al Representante (PHPMailer)
    $emailSent = false;
    if (!empty($estudiante['correo_representante'])) {
        $nombreCompleto = $estudiante['nombres'] . ' ' . $estudiante['apellidos'];
        $emailSent = notificarAsistenciaRepresentante(
            $estudiante['correo_representante'],
            $nombreCompleto,
            $materia,
            date('h:i A', strtotime($horaActual)),
            $estadoAsistencia,
            $currentUser['nombre']
        );
    }

    echo json_encode([
        'success' => true,
        'mensaje' => 'Asistencia registrada satisfactoriamente.',
        'estado' => $estadoAsistencia,
        'hora' => date('h:i A', strtotime($horaActual)),
        'email_enviado' => $emailSent,
        'estudiante' => [
            'id' => $estudiante['id'],
            'nombres' => $estudiante['nombres'],
            'apellidos' => $estudiante['apellidos'],
            'cedula' => $estudiante['cedula'],
            'ano_instruccion' => $estudiante['ano_instruccion'],
            'seccion' => $estudiante['seccion']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    error_log('[API procesar_asistencia Error]: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno al registrar la asistencia.'], JSON_UNESCAPED_UNICODE);
}
