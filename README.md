# Sistema Integral de Control de Asistencia Escolar
### Liceo Bolivariano "Rossana Melidei"

Sistema web modular para el control automatizado de asistencia escolar mediante credenciales QR dinámicas, diseñado para operar en entornos locales **XAMPP (Apache, MySQL 8 / MariaDB, PHP 8+)** con capacidades de **alta resiliencia territorial**, **contingencia offline (LocalStorage / IndexedDB)** y **notificaciones en tiempo real a representantes vía PHPMailer**.

---

## 🏛️ 1. Arquitectura de Software y Módulos

```
asistencia_liceo/
├── config/
│   ├── config.php                 # Parámetros institucionales, subredes autorizadas y credenciales SMTP
│   ├── conexion.php               # Conexión PDO segura (UTF8mb4, emulación desactivada, PDO::ERRMODE_EXCEPTION)
│   └── mailer.php                 # Servicio de correo electrónico (PHPMailer / plantillas HTML responsivas)
├── includes/
│   ├── auth.php                   # Gestión de sesiones blindadas, tokens CSRF y RBAC (Admin / Docente)
│   ├── ip_guard.php               # Middleware de seguridad anti-fraude con cálculo CIDR de subredes locales
│   ├── header.php                 # Cabecera institucional Glassmorphism con indicador de red en vivo
│   └── footer.php                 # Pie de página y carga condicional de scripts
├── api/
│   ├── procesar_asistencia.php    # Endpoint de validación y registro en tiempo real con tolerancia puntual
│   ├── procesar_cierre_lista.php  # Cierre de lista: registro masivo de ausencias y envío de alertas
│   ├── guardar_nota_atencion.php  # Registro de observaciones formativas y notificación instantánea
│   ├── sincronizar_offline.php    # Endpoint de sincronización por lotes para asistencias capturadas sin internet
│   ├── estudiantes_api.php        # CRUD seguro de estudiantes y regeneración de tokens criptográficos
│   └── horarios_api.php           # CRUD de bloques académicos semanales
├── database/
│   ├── schema.sql                 # Estructura de BD en MySQL InnoDB (tablas, índices únicos, FKs y seeds)
│   └── seed.php                   # Asistente web para inicializar o restaurar la BD con un solo clic
├── assets/
│   ├── css/
│   │   └── glassmorphism.css      # Sistema de diseño con efectos de cristal (blur), paleta pastel azul y menta
│   ├── js/
│   │   ├── scanner.js             # Escáner en vivo con html5-qrcode y sonido Web Audio API
│   │   ├── offline_manager.js     # Motor de contingencia offline con persistencia en LocalStorage
│   │   └── dashboard_charts.js    # Visualizaciones analíticas con Chart.js
│   └── img/
│       ├── logo_liceo.svg         # Emblema oficial del Liceo Bolivariano "Rossana Melidei"
│       └── avatars/               # Identificadores fotográficos para carnets estudiantiles
├── login.php                      # Inicio de sesión protegido contra CSRF y fijación de sesión
├── logout.php                     # Destrucción segura de credenciales de sesión
├── dashboard_admin.php            # Panel directivo: métricas, gráficos Chart.js, gestión de alumnos y horarios
├── dashboard_profesor.php         # Panel docente: selección de cátedra, pase de lista y notas de atención
├── escanear.php                   # Módulo de escaneo QR con cámara en vivo y verificación perimetral
├── exportar_carnets.php           # Generador de carnets normalizados listos para imprimir en pliegos/PDF
├── index.php                      # Enrutador inteligente para servidores PHP en XAMPP
└── index.html                     # Portal interactivo y demostrador de arquitectura en vivo para navegadores
```

---

## 🗄️ 2. Estructura de la Base de Datos MySQL (`liceo_asistencia`)

El esquema de base de datos (`database/schema.sql`) incluye las 5 tablas obligatorias más la tabla de apoyo perimetral:

1. **`usuarios`**:
   - `id`, `nombre`, `email`, `password` (hash BCRYPT), `rol` ('admin', 'profesor'), `estado` ('activo', 'inactivo'), `telefono`, `ultimo_acceso`, `created_at`.
2. **`estudiantes`**:
   - `id`, `nombres`, `apellidos`, `cedula`, `ano_instruccion`, `seccion`, `correo_representante`, `telefono_representante`, `token_qr` (hash criptográfico único), `foto`, `estado`, `created_at`.
3. **`horarios`**:
   - `id`, `profesor_id` (FK `usuarios.id`), `materia`, `ano_instruccion`, `seccion`, `dia_semana`, `hora_entrada`, `hora_salida`.
4. **`asistencia`**:
   - `id`, `estudiante_id` (FK `estudiantes.id`), `profesor_id` (FK `usuarios.id`), `materia`, `fecha`, `hora_escaneo`, `estado` ('Presente', 'Retraso', 'Ausente'), `ip_registro`, `sincronizado_offline`, `created_at`.
   - **Clave Única:** `(estudiante_id, materia, fecha)` para evitar doble marcaje el mismo día.
5. **`notas_atencion`**:
   - `id`, `estudiante_id` (FK), `profesor_id` (FK), `motivo` (TEXT), `fecha`, `notificado_email`.
6. **`ip_autorizadas`**:
   - `id`, `ip_o_subred`, `descripcion`, `activo`, `created_at`.

---

## 🛡️ 3. Seguridad Perimetral y Restricción por IP (Anti-Fraude)

El archivo `includes/ip_guard.php` implementa un middleware estricto que analiza la dirección `$_SERVER['REMOTE_ADDR']`:
- **Cálculo de Subredes CIDR:** Valida direcciones IPv4 e IPv6 contra rangos autorizados (ej. `192.168.1.0/24`, `192.168.0.0/24`, `10.0.0.0/24` o `127.0.0.1`).
- **Bloqueo Inmediato:** Si un dispositivo intenta acceder a `escanear.php` o consumir `api/procesar_asistencia.php` desde una IP fuera del plantel (ej. desde su hogar para falsificar asistencia):
  * La API responde con **HTTP 403 Forbidden** y código `IP_NO_AUTORIZADA`.
  * La interfaz web despliega la pantalla de advertencia anti-fraude institucional y registra el incidente en el log de auditoría.
- **Configuración:** Las subredes autorizadas se gestionan de forma centralizada en `config/config.php` y en la tabla `ip_autorizadas`.

---

## ⚡ 4. Resiliencia y Contingencia Offline

Para mitigar problemas de conectividad en el aula:
1. **Detección Automática:** El escáner (`assets/js/scanner.js` y `assets/js/offline_manager.js`) escucha eventos `online` / `offline` de `navigator.onLine` e intercepta excepciones de red en las peticiones fetch.
2. **Cola Criptográfica Local:** Si se interrumpe la red, las marcas de asistencia se guardan inmediatamente en **LocalStorage / IndexedDB** con su firma de tiempo y token.
3. **Sincronización por Lotes:** Cuando la conexión se reanuda, el sistema ejecuta automáticamente una petición POST a `api/sincronizar_offline.php`, guardando los registros con la bandera `sincronizado_offline = 1` y disparando los correos pendientes a los representantes.
4. **Retroalimentación Sonora:** Mediante la **Web Audio API** nativa de HTML5, el docente recibe confirmaciones sonoras inmediatas sin requerir archivos de audio externos:
   - Tono agudo ascendente: *Presente*
   - Tono medio: *Retraso*
   - Zumbido grave: *Error / Duplicado / Bloqueo IP*

---

## 📧 5. Notificaciones Automáticas por Correo (PHPMailer / SMTP)

El servicio `config/mailer.php` genera correos electrónicos en formato HTML responsivo con la identidad gráfica del liceo:
- **Al Escanear:** Informa al representante la hora exacta del ingreso del estudiante a la cátedra y su condición (*Presente* o *Retraso* tras los 15 minutos de tolerancia).
- **Cierre de Lista:** Cuando el docente presiona *"Cerrar Lista"*, todos los estudiantes no escaneados son marcados automáticamente como *Ausentes* y se envía una alerta formal a sus representantes recordando el lapso de 48 horas para justificaciones médicas.
- **Notas de Atención:** Permite a los docentes registrar observaciones conductuales o formativas, enviando copia íntegra instantánea al correo del representante.

---

## 🪪 6. Generador de Carnets Estudiantiles (PDF / Pliego de Impresión)

Ubicado en `exportar_carnets.php`:
- Genera credenciales plastificables con membrete oficial del MPPE, escudo del plantel, fotografía, cédula, año, sección, año escolar 2025-2026 y **código QR dinámico** con el token criptográfico del estudiante.
- Reglas `@media print` optimizadas para imprimir **8 carnets por pliego (hoja carta o A4)** con líneas de corte para guillotina.

---

## 🚀 7. Guía Rápida de Instalación en XAMPP

1. **Clonar o copiar la carpeta del proyecto** en el directorio raíz de Apache:
   ```text
   C:\xampp\htdocs\asistencia_liceo\
   ```
2. **Iniciar Apache y MySQL** desde el *XAMPP Control Panel*.
3. **Instalar la Base de Datos:**
   - Opción A (Automática): Ingrese en el navegador a:
     ```text
     http://localhost/asistencia_liceo/database/seed.php
     ```
   - Opción B (Manual): Abra `http://localhost/phpmyadmin/`, cree la base de datos `liceo_asistencia` e importe el archivo `database/schema.sql`.
4. **Abrir el Sistema:**
   ```text
   http://localhost/asistencia_liceo/
   ```

### 🔑 Credenciales de Acceso Sembradas:
| Rol | Correo Electrónico | Contraseña |
| :--- | :--- | :--- |
| **Directivo / Admin** | `admin@rossanamelidei.edu.ve` | `Admin123*` |
| **Docente Titular** | `carlos.gonzalez@rossanamelidei.edu.ve` | `Profesor123*` |
| **Docente Titular** | `elena.mendoza@rossanamelidei.edu.ve` | `Profesor123*` |
| **Docente Titular** | `pedro.ramirez@rossanamelidei.edu.ve` | `Profesor123*` |

---
*Desarrollado para el Liceo Bolivariano "Rossana Melidei" &bull; Ministerio del Poder Popular para la Educación.*
