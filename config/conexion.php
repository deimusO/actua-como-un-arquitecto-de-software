<?php
/**
 * Script de Conexión PDO Seguro a MySQL
 * Liceo Bolivariano "Rossana Melidei"
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function getDBConnection(): ?PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // En entorno de desarrollo registramos el error en log
        error_log('[DB Error]: ' . $e->getMessage());

        if (APP_ENV === 'development' && php_sapi_name() === 'cli') {
            echo "Error de conexión: " . $e->getMessage() . PHP_EOL;
        }

        // Si se llama desde una API JSON, responder con error controlado
        if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'No fue posible conectar con el servidor de base de datos MySQL en XAMPP. Verifique que el servicio MySQL esté activo.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        return null;
    }
}

// Variable global estándar para scripts directos
$pdo = getDBConnection();
