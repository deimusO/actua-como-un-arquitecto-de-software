<?php
/**
 * API: Gestión CRUD de Estudiantes
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
            $ano = $_GET['ano'] ?? '';
            $sec = $_GET['seccion'] ?? '';

            $sql = "SELECT id, nombres, apellidos, cedula, ano_instruccion, seccion, correo_representante, telefono_representante, token_qr, estado, created_at FROM estudiantes WHERE 1=1";
            $params = [];

            if (!empty($ano)) {
                $sql .= " AND ano_instruccion = ?";
                $params[] = $ano;
            }
            if (!empty($sec)) {
                $sql .= " AND seccion = ?";
                $params[] = $sec;
            }

            $sql .= " ORDER BY ano_instruccion ASC, seccion ASC, apellidos ASC, nombres ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $estudiantes = $stmt->fetchAll();

            echo json_encode(['success' => true, 'estudiantes' => $estudiantes], JSON_UNESCAPED_UNICODE);
            break;

        case 'crear':
            $nombres   = trim((string)($data['nombres'] ?? ''));
            $apellidos = trim((string)($data['apellidos'] ?? ''));
            $cedula    = trim((string)($data['cedula'] ?? ''));
            $ano       = trim((string)($data['ano_instruccion'] ?? ''));
            $seccion   = strtoupper(trim((string)($data['seccion'] ?? 'A')));
            $correo    = trim((string)($data['correo_representante'] ?? ''));
            $telefono  = trim((string)($data['telefono_representante'] ?? ''));

            if (empty($nombres) || empty($apellidos) || empty($cedula) || empty($ano) || empty($correo)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nombres, apellidos, cédula, año y correo son obligatorios.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Generar token QR criptográfico único
            $tokenQr = 'RM-QR-' . strtoupper(bin2hex(random_bytes(7)));

            $stmt = $pdo->prepare("
                INSERT INTO estudiantes (nombres, apellidos, cedula, ano_instruccion, seccion, correo_representante, telefono_representante, token_qr, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo')
            ");
            $stmt->execute([$nombres, $apellidos, $cedula, $ano, $seccion, $correo, $telefono, $tokenQr]);

            echo json_encode([
                'success' => true,
                'mensaje' => 'Estudiante registrado satisfactoriamente.',
                'id' => $pdo->lastInsertId(),
                'token_qr' => $tokenQr
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar':
            $id        = (int)($data['id'] ?? 0);
            $nombres   = trim((string)($data['nombres'] ?? ''));
            $apellidos = trim((string)($data['apellidos'] ?? ''));
            $cedula    = trim((string)($data['cedula'] ?? ''));
            $ano       = trim((string)($data['ano_instruccion'] ?? ''));
            $seccion   = strtoupper(trim((string)($data['seccion'] ?? 'A')));
            $correo    = trim((string)($data['correo_representante'] ?? ''));
            $telefono  = trim((string)($data['telefono_representante'] ?? ''));
            $estado    = trim((string)($data['estado'] ?? 'activo'));

            if ($id <= 0 || empty($nombres) || empty($cedula)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Datos insuficientes para actualizar.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE estudiantes 
                SET nombres = ?, apellidos = ?, cedula = ?, ano_instruccion = ?, seccion = ?, correo_representante = ?, telefono_representante = ?, estado = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombres, $apellidos, $cedula, $ano, $seccion, $correo, $telefono, $estado, $id]);

            echo json_encode(['success' => true, 'mensaje' => 'Datos del estudiante actualizados con éxito.'], JSON_UNESCAPED_UNICODE);
            break;

        case 'regenerar_token':
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $nuevoToken = 'RM-QR-' . strtoupper(bin2hex(random_bytes(7)));
            $stmt = $pdo->prepare("UPDATE estudiantes SET token_qr = ? WHERE id = ?");
            $stmt->execute([$nuevoToken, $id]);

            echo json_encode(['success' => true, 'nuevo_token' => $nuevoToken, 'mensaje' => 'Nuevo código QR generado.'], JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar':
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM estudiantes WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'mensaje' => 'Estudiante eliminado del sistema.'], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no especificada o no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (\PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'La cédula ingresada ya se encuentra registrada en otro estudiante.'], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
