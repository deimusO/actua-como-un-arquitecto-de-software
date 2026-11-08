<?php
/**
 * Configuración Global del Sistema
 * Liceo Bolivariano "Rossana Melidei"
 * PHP 8.0+ / XAMPP Production Ready
 */

declare(strict_types=1);

// Configuración de Zona Horaria Oficial
date_default_timezone_set('America/Caracas');

// Entorno de ejecución: 'development' o 'production'
if (!defined('APP_ENV')) {
    define('APP_ENV', 'development');
}

// Configuración de Base de Datos MySQL
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'liceo_asistencia');
define('DB_USER', 'root');
define('DB_PASS', ''); // Por defecto en XAMPP suele ser vacío
define('DB_CHARSET', 'utf8mb4');

// Parámetros Institucionales
define('INSTITUCION_NOMBRE', 'Liceo Bolivariano "Rossana Melidei"');
define('INSTITUCION_SIGLAS', 'L.B. Rossana Melidei');
define('INSTITUCION_DEA', 'OD01540107');
define('INSTITUCION_ANIO_ESCOLAR', '2025 - 2026');
define('INSTITUCION_EMAIL_DIRECTIVO', 'direccion@rossanamelidei.edu.ve');

// Seguridad y Restricción por IP (Anti-Fraude)
// El escaneo de QR solo está permitido dentro de las subredes del liceo o IPs expresamente autorizadas
define('ENFORCE_IP_SECURITY', true);
define('ALLOWED_SUBNETS', [
    '127.0.0.1',        // Localhost IPv4 para entorno local XAMPP
    '::1',              // Localhost IPv6
    '192.168.1.0/24',   // Red WiFi principal Liceo
    '192.168.0.0/24',   // Red aulas y laboratorios
    '10.0.0.0/24'       // Subred administrativa interna
]);

// IPs públicas adicionales para administradores externos autorizados
define('ALLOWED_ADMIN_EXTERNAL_IPS', [
    // '200.74.192.45', // Ejemplo de IP estática externa
]);

// Reglas de Negocio para Asistencia
define('MINUTOS_TOLERANCIA_PRESENTE', 15); // Si llega después de 15 min de la hora_entrada se califica como 'Retraso'

// Configuración de Correo Electrónico (PHPMailer / SMTP)
define('MAIL_ENABLED', true);
define('MAIL_HOST', 'smtp.gmail.com');      // Reemplazar con smtp real o Mailtrap para pruebas
define('MAIL_PORT', 587);
define('MAIL_AUTH', true);
define('MAIL_USER', 'notificaciones.rossanamelidei@gmail.com');
define('MAIL_PASS', 'app_password_here');    // App Password de Google
define('MAIL_ENCRYPTION', 'tls');          // 'tls' o 'ssl'
define('MAIL_FROM_ADDRESS', 'no-reply@rossanamelidei.edu.ve');
define('MAIL_FROM_NAME', 'Control de Asistencia - L.B. Rossana Melidei');

// Rutas del Sistema
define('BASE_URL', '/');
define('UPLOAD_DIR_STUDENTS', __DIR__ . '/../assets/img/avatars/');
