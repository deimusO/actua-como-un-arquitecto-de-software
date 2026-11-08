-- ==============================================================================
-- SISTEMA INTEGRAL DE CONTROL DE ASISTENCIA ESCOLAR
-- LICEO BOLIVARIANO "ROSSANA MELIDEI"
-- Base de Datos: MySQL 8.0+ / MariaDB (XAMPP Compatible)
-- Charset: utf8mb4_unicode_ci
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `liceo_asistencia` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `liceo_asistencia`;

-- Desactivar temporalmente revisión de claves foráneas para recarga limpia
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `notas_atencion`;
DROP TABLE IF EXISTS `asistencia`;
DROP TABLE IF EXISTS `horarios`;
DROP TABLE IF EXISTS `estudiantes`;
DROP TABLE IF EXISTS `ip_autorizadas`;
DROP TABLE IF EXISTS `usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

-- ==============================================================================
-- 1. TABLA: usuarios
-- Gestión de accesos institucionales: Directivos/Administradores y Docentes
-- ==============================================================================
CREATE TABLE `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL COMMENT 'Hash BCRYPT / Argon2id',
  `rol` ENUM('admin', 'profesor') NOT NULL DEFAULT 'profesor',
  `estado` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `telefono` VARCHAR(30) NULL,
  `ultimo_acceso` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_usuario_email` (`email`),
  INDEX `idx_usuario_rol` (`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 2. TABLA: estudiantes
-- Datos curriculares y credenciales QR criptográficas
-- ==============================================================================
CREATE TABLE `estudiantes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombres` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(100) NOT NULL,
  `cedula` VARCHAR(20) NOT NULL UNIQUE,
  `ano_instruccion` VARCHAR(20) NOT NULL COMMENT '1er Año, 2do Año, 3er Año, 4to Año, 5to Año',
  `seccion` VARCHAR(5) NOT NULL COMMENT 'A, B, C, D, U',
  `correo_representante` VARCHAR(150) NOT NULL,
  `telefono_representante` VARCHAR(30) NULL,
  `token_qr` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Hash seguro para validación QR',
  `foto` VARCHAR(255) NULL DEFAULT 'assets/img/avatars/default_student.png',
  `estado` ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_estudiante_cedula` (`cedula`),
  INDEX `idx_estudiante_token` (`token_qr`),
  INDEX `idx_estudiante_ano_sec` (`ano_instruccion`, `seccion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 3. TABLA: horarios
-- Planificación académica semanal por docente, sección y bloques de tiempo
-- ==============================================================================
CREATE TABLE `horarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `profesor_id` INT NOT NULL,
  `materia` VARCHAR(100) NOT NULL,
  `ano_instruccion` VARCHAR(20) NOT NULL,
  `seccion` VARCHAR(5) NOT NULL,
  `dia_semana` ENUM('Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes') NOT NULL,
  `hora_entrada` TIME NOT NULL,
  `hora_salida` TIME NOT NULL,
  CONSTRAINT `fk_horarios_profesor` FOREIGN KEY (`profesor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_horarios_profesor` (`profesor_id`),
  INDEX `idx_horarios_dia` (`dia_semana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 4. TABLA: asistencia
-- Registro inmutable de capturas presenciales, retrasos y cierres de lista
-- ==============================================================================
CREATE TABLE `asistencia` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `estudiante_id` INT NOT NULL,
  `profesor_id` INT NOT NULL,
  `materia` VARCHAR(100) NOT NULL,
  `fecha` DATE NOT NULL,
  `hora_escaneo` TIME NOT NULL,
  `estado` ENUM('Presente', 'Retraso', 'Ausente') NOT NULL,
  `ip_registro` VARCHAR(45) NOT NULL DEFAULT '127.0.0.1',
  `sincronizado_offline` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si provino de cola contingencia',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_asistencia_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencia_profesor` FOREIGN KEY (`profesor_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  -- Clave única para evitar duplicación de escaneo en la misma materia y fecha
  UNIQUE KEY `idx_asistencia_unica` (`estudiante_id`, `materia`, `fecha`),
  INDEX `idx_asistencia_fecha` (`fecha`),
  INDEX `idx_asistencia_estado` (`estado`),
  INDEX `idx_asistencia_estudiante` (`estudiante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 5. TABLA: notas_atencion
-- Observaciones formativas/disciplinarias registradas por los docentes
-- ==============================================================================
CREATE TABLE `notas_atencion` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `estudiante_id` INT NOT NULL,
  `profesor_id` INT NOT NULL,
  `motivo` TEXT NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notificado_email` TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT `fk_notas_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notas_profesor` FOREIGN KEY (`profesor_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX `idx_notas_estudiante` (`estudiante_id`),
  INDEX `idx_notas_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 6. TABLA: ip_autorizadas
-- Tabla de apoyo para gestión dinámica de subredes e IPs del Liceo
-- ==============================================================================
CREATE TABLE `ip_autorizadas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_o_subred` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Ej: 192.168.1.0/24 o 127.0.0.1',
  `descripcion` VARCHAR(150) NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- DATOS INICIALES (SEED DATA)
-- Contraseñas encriptadas con BCRYPT ($2y$10$)
-- Clave Admin: Admin123*
-- Clave Profesores: Profesor123*
-- ==============================================================================

-- 1. Usuarios:
INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `estado`, `telefono`) VALUES
(1, 'Dirección Académica', 'admin@rossanamelidei.edu.ve', '$2y$10$wKkS1o6h8n9WpEhzZ0o7E.vLz4v5X7U2Y3fX9jK8yJ5gQ0wE8eX0a', 'admin', 'activo', '0212-5551122'),
(2, 'Prof. Carlos González', 'carlos.gonzalez@rossanamelidei.edu.ve', '$2y$10$3tK1o8xQ3m0VpFgyZ1p8E.uMy5w6Y8V3Z4gY0kL9zK6hR1xF9fY1b', 'profesor', 'activo', '0414-1112233'),
(3, 'Profª. Elena Mendoza', 'elena.mendoza@rossanamelidei.edu.ve', '$2y$10$3tK1o8xQ3m0VpFgyZ1p8E.uMy5w6Y8V3Z4gY0kL9zK6hR1xF9fY1b', 'profesor', 'activo', '0416-2223344'),
(4, 'Prof. Pedro Ramírez', 'pedro.ramirez@rossanamelidei.edu.ve', '$2y$10$3tK1o8xQ3m0VpFgyZ1p8E.uMy5w6Y8V3Z4gY0kL9zK6hR1xF9fY1b', 'profesor', 'activo', '0424-3334455');

-- 2. Subredes e IPs autorizadas (Red Local del Liceo y Loopback):
INSERT INTO `ip_autorizadas` (`ip_o_subred`, `descripcion`, `activo`) VALUES
('127.0.0.1', 'Localhost IPv4 (Pruebas XAMPP)', 1),
('::1', 'Localhost IPv6 (Pruebas XAMPP)', 1),
('192.168.1.0/24', 'Red WiFi Principal Liceo Bolivariano Rossana Melidei', 1),
('192.168.0.0/24', 'Red Secundaria Docentes y Aulas', 1),
('10.0.0.0/24', 'Subred Administrativa Interna', 1);

-- 3. Estudiantes:
INSERT INTO `estudiantes` (`id`, `nombres`, `apellidos`, `cedula`, `ano_instruccion`, `seccion`, `correo_representante`, `telefono_representante`, `token_qr`, `foto`) VALUES
(1, 'Alejandro José', 'Morales Silva', 'V-32.410.821', '4to Año', 'A', 'rep.morales@gmail.com', '0412-9876541', 'RM-QR-A4A9B1C3D8E2F1', 'assets/img/avatars/student_1.png'),
(2, 'Valeria Sofía', 'Castillo Rivas', 'V-32.881.045', '4to Año', 'A', 'rep.castillo@hotmail.com', '0414-8765432', 'RM-QR-B7E3C2D9F0A4E5', 'assets/img/avatars/student_2.png'),
(3, 'Gabriel Andrés', 'Hernández Peña', 'V-31.954.120', '5to Año', 'B', 'rep.hernandez@gmail.com', '0424-7654321', 'RM-QR-C1D8A5E7F3B6A9', 'assets/img/avatars/student_3.png'),
(4, 'Mariana Coromoto', 'Pérez Salazar', 'V-33.102.394', '3er Año', 'A', 'rep.perez@yahoo.com', '0416-6543210', 'RM-QR-D4F9E1A3C8B2D7', 'assets/img/avatars/student_4.png'),
(5, 'Diego Armando', 'Gómez Torrealba', 'V-32.551.487', '4to Año', 'A', 'rep.gomez@gmail.com', '0412-5432109', 'RM-QR-E8A2B6C4F1D9E3', 'assets/img/avatars/student_5.png'),
(6, 'Camila Isabella', 'Blanco Delgado', 'V-34.009.612', '1er Año', 'C', 'rep.blanco@gmail.com', '0414-4321098', 'RM-QR-F3C7E9D2A5B8F4', 'assets/img/avatars/student_6.png'),
(7, 'Samuel David', 'Navarro Ortiz', 'V-33.642.189', '2do Año', 'B', 'rep.navarro@outlook.com', '0424-3210987', 'RM-QR-A9D5B3F8E1C4A2', 'assets/img/avatars/student_7.png'),
(8, 'Daniela Victoria', 'Reyes Fuentes', 'V-32.903.456', '4to Año', 'A', 'rep.reyes@gmail.com', '0416-2109876', 'RM-QR-B2F4A7C9E3D6B8', 'assets/img/avatars/student_8.png'),
(9, 'Mateo Sebastián', 'Vargas Romero', 'V-31.847.230', '5to Año', 'A', 'rep.vargas@gmail.com', '0412-1098765', 'RM-QR-C6E1D8B4F9A3C5', 'assets/img/avatars/student_9.png'),
(10, 'Lucía Valentina', 'Sánchez Suárez', 'V-33.789.012', '2do Año', 'A', 'rep.sanchez@gmail.com', '0414-0987654', 'RM-QR-D8B3F2A6C5E1D9', 'assets/img/avatars/student_10.png');

-- 4. Horarios Académicos:
INSERT INTO `horarios` (`id`, `profesor_id`, `materia`, `ano_instruccion`, `seccion`, `dia_semana`, `hora_entrada`, `hora_salida`) VALUES
(1, 2, 'Matemáticas y Cálculo', '4to Año', 'A', 'Lunes', '07:00:00', '08:30:00'),
(2, 2, 'Física General', '4to Año', 'A', 'Lunes', '08:35:00', '10:05:00'),
(3, 3, 'Castellano y Literatura', '4to Año', 'A', 'Lunes', '10:20:00', '11:50:00'),
(4, 4, 'Biología y Ciencias', '3er Año', 'A', 'Lunes', '07:00:00', '08:30:00'),
(5, 2, 'Matemáticas Avanzadas', '5to Año', 'B', 'Martes', '07:00:00', '08:30:00'),
(6, 3, 'Castellano y Literatura', '5to Año', 'B', 'Martes', '08:35:00', '10:05:00'),
(7, 4, 'Química Orgánica', '4to Año', 'A', 'Miércoles', '07:00:00', '08:30:00'),
(8, 2, 'Física Aplicada', '5to Año', 'A', 'Jueves', '07:00:00', '08:30:00'),
(9, 3, 'Orientación y Convivencia', '4to Año', 'A', 'Viernes', '07:00:00', '08:30:00'),
(10, 2, 'Laboratorio de Física', '4to Año', 'A', 'Viernes', '08:35:00', '10:05:00');

-- 5. Historial de Asistencia de Muestra (Últimos días para poblar gráficas analíticas):
INSERT INTO `asistencia` (`estudiante_id`, `profesor_id`, `materia`, `fecha`, `hora_escaneo`, `estado`, `ip_registro`, `sincronizado_offline`) VALUES
-- Clase 1: Lunes anterior
(1, 2, 'Matemáticas y Cálculo', CURDATE() - INTERVAL 7 DAY, '07:04:12', 'Presente', '192.168.1.45', 0),
(2, 2, 'Matemáticas y Cálculo', CURDATE() - INTERVAL 7 DAY, '07:08:33', 'Presente', '192.168.1.45', 0),
(5, 2, 'Matemáticas y Cálculo', CURDATE() - INTERVAL 7 DAY, '07:22:15', 'Retraso', '192.168.1.45', 0),
(8, 2, 'Matemáticas y Cálculo', CURDATE() - INTERVAL 7 DAY, '07:02:40', 'Presente', '192.168.1.45', 0),
-- Clase 2: Viernes anterior
(1, 2, 'Laboratorio de Física', CURDATE() - INTERVAL 3 DAY, '08:36:10', 'Presente', '192.168.1.50', 0),
(2, 2, 'Laboratorio de Física', CURDATE() - INTERVAL 3 DAY, '08:39:44', 'Presente', '192.168.1.50', 0),
(5, 2, 'Laboratorio de Física', CURDATE() - INTERVAL 3 DAY, '08:35:12', 'Presente', '192.168.1.50', 0),
(8, 2, 'Laboratorio de Física', CURDATE() - INTERVAL 3 DAY, '08:35:00', 'Ausente', '192.168.1.50', 0),
-- Clase de Hoy (Muestra activa)
(1, 2, 'Matemáticas y Cálculo', CURDATE(), '07:05:22', 'Presente', '192.168.1.25', 0),
(2, 2, 'Matemáticas y Cálculo', CURDATE(), '07:12:05', 'Presente', '192.168.1.25', 0),
(5, 2, 'Matemáticas y Cálculo', CURDATE(), '07:28:40', 'Retraso', '192.168.1.25', 1);

-- 6. Notas de Atención de Muestra:
INSERT INTO `notas_atencion` (`id`, `estudiante_id`, `profesor_id`, `motivo`, `fecha`, `notificado_email`) VALUES
(1, 5, 2, 'Llegada con 28 minutos de retraso al bloque matutino de Matemáticas. Justificó retardo de transporte público.', CURDATE() - INTERVAL 2 DAY, 1),
(2, 1, 3, 'Excelente participación y liderazgo en el taller de oratoria y ensayo literario.', CURDATE() - INTERVAL 5 DAY, 1);
