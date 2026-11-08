<?php
/**
 * API: Sincronización Masiva de Asistencias Offline (Contingencia)
 * Liceo Bolivariano "Rossana Melidei"
 * Procesa la cola acumulada en LocalStorage/IndexedDB cuando se restablece la red
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ip_guard.php';
require_once __DIR__ . '/../config/mailer.php';

// Validar seguridad territorial por IP
enforceIPAccess(true);

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión no activa.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = getCurrentUser();
$profesorId  = $currentUser['id'];

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (empty($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'La lista de ítems para sincronización está vacía o es inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Base de datos no disponible.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$clientIp = getClientIP();
$procesados = 0;
$errores = [];

$stmtEst = $pdo->prepare("SELECT id, nombres, apellidos, correo_representante FROM estudiantes WHERE token_qr = ? AND estado = 'activo' LIMIT 1");
$stmtHor = $pdo->prepare("SELECT materia, hora_entrada FROM horarios WHERE id = ? LIMIT 1");
$stmtCheck = $pdo->prepare("SELECT id FROM asistencia WHERE estudiante_id = ? AND materia = ? AND fecha = ? LIMIT 1");
$stmtInsert = $pdo->prepare("
    INSERT INTO asistencia (estudiante_id, profesor_id, materia, fecha, hora_escaneo, estado, ip_registro, sincronizado_offline)
    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
");

foreach ($data['items'] as $item) {
    try {
        $tokenQr     = trim((string)($item['token_qr'] ?? ''));
        $horarioId   = (int)($item['horario_id'] ?? 0);
        $fecha       = !empty($item['fecha']) ? (string)$item['fecha'] : date('Y-m-d');
        $horaEscaneo = !empty($item['hora_escaneo']) ? (string)$item['hora_escaneo'] : date('H:i:s');

        if (empty($tokenQr) || $horarioId <= 0) {
            continue;
        }

        // Buscar estudiante
        $stmtEst->execute([$tokenQr]);
        $estudiante = $stmtEst->fetch();
        if (!$estudiante) {
            $errores[] = "Token QR no válido: {$tokenQr}";
            continue;
        }

        // Buscar horario
        $stmtHor->execute([$horarioId]);
        $horario = $stmtHor->fetch();
        if (!$horario) {
            $errores[] = "Horario ID {$horarioId} no encontrado";
            continue;
        }

        $materia = $horario['materia'];

        // Verificar si ya existe asistencia
        $stmtCheck->execute([$estudiante['id'], $materia, $fecha]);
        if ($stmtCheck->fetch()) {
            // Ya estaba registrada, omitir para evitar duplicado
            continue;
        }

        // Calcular estado de puntualidad según la hora de captura offline
        $horaEntradaTimestamp = strtotime($fecha . ' ' . $horario['hora_entrada']);
        $horaCapturaTimestamp = strtotime($fecha . ' ' . $horaEscaneo);
        $diffMin = round(($horaCapturaTimestamp - $horaEntradaTimestamp) / 60);

        $estado = ($diffMin > MINUTOS_TOLERANCIA_PRESENTE) ? 'Retraso' : 'Presente';

        // Guardar asistencia marcada con flag de sincronización diferida
        $stmtInsert->execute([
            $estudiante['id'],
            $profesorId,
            $materia,
            $fecha,
            $horaEscaneo,
            $estado,
            $clientIp
        ]);

        // Despachar correo diferido al representante
        if (!empty($estudiante['correo_representante'])) {
            $nombreCompleto = $estudiante['nombres'] . ' ' . $estudiante['apellidos'];
            notificarAsistenciaRepresentante(
                $estudiante['correo_representante'],
                $nombreCompleto,
                $materia,
                date('h:i A', strtotime($horaEscaneo)) . ' (Sincronizado diferido)',
                $estado,
                $currentUser['nombre']
            );
        }

        $procesados++;
    } catch (\Throwable $e) {
        $errores[] = $e->getMessage();
    }
}

echo json_encode([
    'success' => true,
    'mensaje' => sprintf('Se sincronizaron exitosamente %d asistencias diferidas.', $procesados),
    'total_recibidos' => count($data['items']),
    'total_procesados' => $procesados,
    'errores' => $errores
], JSON_UNESCAPED_UNICODE);
