<?php
/**
 * API: Gestión CRUD de Horarios Académicos
 * Liceo Bolivariano "Rossana Melidei"
 * Acceso exclusivo para el Rol Administrador
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/auth.php';

if (!hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permiso denegado. Se requiere rol de Administrador.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de conexión con la base de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

try {
    switch ($action) {
        case 'listar':
            $sql = "
                SELECT h.id, h.profesor_id, u.nombre as profesor_nombre, h.materia, 
                       h.ano_instruccion, h.seccion, h.dia_semana, h.hora_entrada, h.hora_salida
                FROM horarios h
                JOIN usuarios u ON h.profesor_id = u.id
                ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'), h.hora_entrada ASC
            ";
            $stmt = $pdo->query($sql);
            $horarios = $stmt->fetchAll();

            echo json_encode(['success' => true, 'horarios' => $horarios], JSON_UNESCAPED_UNICODE);
            break;

        case 'crear':
            $profesorId  = (int)($data['profesor_id'] ?? 0);
            $materia     = trim((string)($data['materia'] ?? ''));
            $ano         = trim((string)($data['ano_instruccion'] ?? ''));
            $seccion     = strtoupper(trim((string)($data['seccion'] ?? 'A')));
            $diaSemana   = trim((string)($data['dia_semana'] ?? 'Lunes'));
            $horaEntrada = trim((string)($data['hora_entrada'] ?? '07:00'));
            $horaSalida  = trim((string)($data['hora_salida'] ?? '08:30'));

            if ($profesorId <= 0 || empty($materia) || empty($ano) || empty($horaEntrada)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Todos los campos del bloque horario son obligatorios.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO horarios (profesor_id, materia, ano_instruccion, seccion, dia_semana, hora_entrada, hora_salida)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$profesorId, $materia, $ano, $seccion, $diaSemana, $horaEntrada, $horaSalida]);

            echo json_encode(['success' => true, 'mensaje' => 'Bloque de horario asignado exitosamente.', 'id' => $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar':
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM horarios WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'mensaje' => 'Bloque horario eliminado.'], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
