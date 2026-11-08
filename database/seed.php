<?php
/**
 * Instalador y Seeder Automático de Base de Datos
 * Liceo Bolivariano "Rossana Melidei"
 * Ejecutable desde CLI o Navegador Web en XAMPP
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/html; charset=utf-8');

$dsnBase = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Instalador BD - Liceo Rossana Melidei</title>';
echo '<link rel="stylesheet" href="../assets/css/glassmorphism.css">';
echo '</head><body class="bg-app min-h-screen flex items-center justify-center p-4">';
echo '<div class="glass-card max-w-xl w-full p-8 text-center">';
echo '<img src="../assets/img/logo_liceo.svg" width="64" height="64" class="mx-auto mb-3 rounded-full">';
echo '<h2 class="text-xl font-extrabold text-slate-800 mb-2">Instalador de Base de Datos MySQL</h2>';
echo '<p class="text-xs text-slate-500 mb-6">Liceo Bolivariano "Rossana Melidei" &bull; XAMPP Local Deployment</p>';

try {
    $pdo = new PDO($dsnBase, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $sqlPath = __DIR__ . '/schema.sql';
    if (!file_exists($sqlPath)) {
        throw new Exception("El archivo schema.sql no se encuentra en " . $sqlPath);
    }

    $sqlContent = file_get_contents($sqlPath);

    // Ejecutar creación del schema y tablas
    $pdo->exec($sqlContent);

    echo '<div class="chip chip-mint mb-4 text-sm font-bold">✓ ¡Base de Datos Inicializada con Éxito!</div>';
    echo '<div class="glass-card-subtle p-4 rounded-xl text-left text-xs mb-6 space-y-2">';
    echo '<div><strong>Base de datos:</strong> <code>liceo_asistencia</code></div>';
    echo '<div><strong>Tablas creadas:</strong> usuarios, estudiantes, horarios, asistencia, notas_atencion, ip_autorizadas</div>';
    echo '<div><strong>Semilla de datos:</strong> 4 cuentas (1 Admin, 3 Docentes), 10 estudiantes con credenciales QR, 10 bloques académicos y registros de prueba.</div>';
    echo '</div>';

    echo '<div class="glass-card-subtle p-3 rounded-xl text-left text-xs mb-6 bg-blue-50/70 border border-blue-200">';
    echo '<div class="font-bold text-blue-800 mb-1">Credenciales de Acceso Creadas:</div>';
    echo '<div><strong>Directivo (Admin):</strong> admin@rossanamelidei.edu.ve / <code>Admin123*</code></div>';
    echo '<div><strong>Profesor (Docente):</strong> carlos.gonzalez@rossanamelidei.edu.ve / <code>Profesor123*</code></div>';
    echo '</div>';

    echo '<a href="../login.php" class="btn btn-primary w-full">Ir al Formulario de Ingreso (Login)</a>';

} catch (\Throwable $e) {
    echo '<div class="chip chip-rose mb-4 text-sm font-bold">Error de Instalación</div>';
    echo '<div class="glass-card-subtle p-4 rounded-xl text-left text-xs text-rose-600 mb-4">';
    echo '<strong>Detalle del Error:</strong> ' . htmlspecialchars($e->getMessage());
    echo '</div>';
    echo '<p class="text-xs text-slate-500 mb-4">Verifique que el módulo MySQL en el Panel de Control de XAMPP esté encendido y que el usuario/contraseña en <code>config/config.php</code> sean correctos.</p>';
}

echo '</div></body></html>';
