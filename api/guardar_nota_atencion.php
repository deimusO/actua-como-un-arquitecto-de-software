<?php
/**
 * API: Guardar Nota de Atención y Notificar al Representante
 * Liceo Bolivariano "Rossana Melidei"
 * El docente no puede eliminar asistencias, pero puede dejar notas pedagógicas formativas
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/mailer.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión expirada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = getCurrentUser();
$profesorId  = $currentUser['id'];

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (empty($data['estudiante_id']) || empty($data['motivo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Debe indicar el estudiante y el motivo de la nota de atención.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$estudianteId = (int)$data['estudiante_id'];
$motivo       = trim((string)$data['motivo']);

if (mb_strlen($motivo) < 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El motivo debe tener al menos 5 caracteres.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de conexión con la base de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. Obtener datos del estudiante y de su representante
    $stmtEst = $pdo->prepare("SELECT id, nombres, apellidos, correo_representante FROM estudiantes WHERE id = ? LIMIT 1");
    $stmtEst->execute([$estudianteId]);
    $estudiante = $stmtEst->fetch();

    if (!$estudiante) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Estudiante no encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fechaActual = date('Y-m-d H:i:s');

    // 2. Insertar nota de atención en base de datos
    $stmtInsert = $pdo->prepare("
        INSERT INTO notas_atencion (estudiante_id, profesor_id, motivo, fecha, notificado_email)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmtInsert->execute([
        $estudianteId,
        $profesorId,
        $motivo,
        $fechaActual
    ]);

    $notaId = (int)$pdo->lastInsertId();

    // 3. Notificación instantánea por correo electrónico al representante
    $emailSent = false;
    if (!empty($estudiante['correo_representante'])) {
        $nombreCompleto = $estudiante['nombres'] . ' ' . $estudiante['apellidos'];
        $emailSent = notificarNotaAtencionRepresentante(
            $estudiante['correo_representante'],
            $nombreCompleto,
            $motivo,
            date('d/m/Y h:i A', strtotime($fechaActual)),
            $currentUser['nombre']
        );
    }

    echo json_encode([
        'success' => true,
        'mensaje' => 'Nota de atención registrada y notificada al representante.',
        'nota_id' => $notaId,
        'email_enviado' => $emailSent,
        'fecha' => date('d/m/Y h:i A', strtotime($fechaActual)),
        'estudiante' => $estudiante['nombres'] . ' ' . $estudiante['apellidos']
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    error_log('[Nota Atencion Error]: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar nota de atención: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
