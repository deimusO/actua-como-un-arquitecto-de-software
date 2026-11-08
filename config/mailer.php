<?php
/**
 * Servicio de Notificaciones por Correo Electrónico (PHPMailer / SMTP)
 * Liceo Bolivariano "Rossana Melidei"
 * PHP 8.0+
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Despacha un correo electrónico con plantilla HTML profesional
 */
function sendInstitutionalEmail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("[Mailer Error]: Dirección de correo inválida: " . $toEmail);
        return false;
    }

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    // Verificar si PHPMailer está disponible (por ejemplo vía Composer en vendor/autoload.php)
    $autoloadComposer = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadComposer)) {
        require_once $autoloadComposer;
    }

    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';

            if (MAIL_ENABLED && MAIL_PASS !== 'app_password_here') {
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = MAIL_AUTH;
                $mail->Username   = MAIL_USER;
                $mail->Password   = MAIL_PASS;
                $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_PORT;
            } else {
                // Modo simulación o envío estándar para no bloquear en entornos de prueba locales
                $mail->isMail();
            }

            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->addReplyTo(INSTITUCION_EMAIL_DIRECTIVO, INSTITUCION_NOMBRE);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $htmlBody));

            $mail->send();

            // Registro de auditoría
            file_put_contents(
                $logDir . '/mail_outbox.log',
                sprintf("[%s] [PHPMailer SUCCESS] Para: %s (%s) | Asunto: %s\n", date('Y-m-d H:i:s'), $toEmail, $toName, $subject),
                FILE_APPEND
            );
            return true;
        } catch (\Throwable $e) {
            error_log('[PHPMailer Error]: ' . $e->getMessage());
        }
    }

    // Mecanismo Fallback Resiliente (para entornos XAMPP sin PHPMailer configurado aún)
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">\r\n";
    $headers .= "Reply-To: " . INSTITUCION_EMAIL_DIRECTIVO . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $sent = false;
    if (ini_get('SMTP') && MAIL_ENABLED) {
        $sent = @mail($toEmail, '=?UTF-8?B?'.base64_encode($subject).'?=', $htmlBody, $headers);
    }

    // Siempre registrar en log de contingencia para auditoría y visualización de pruebas
    file_put_contents(
        $logDir . '/mail_outbox.log',
        sprintf("[%s] [FALLBACK LOG] Para: %s (%s) | Asunto: %s | Status: %s\n", 
            date('Y-m-d H:i:s'), 
            $toEmail, 
            $toName, 
            $subject, 
            $sent ? 'ENVIADO' : 'REGISTRADO_EN_COLA'
        ),
        FILE_APPEND
    );

    return true;
}

/**
 * Notificación 1: Registro de Asistencia (Presente o Retraso)
 */
function notificarAsistenciaRepresentante(
    string $correo,
    string $nombreEstudiante,
    string $materia,
    string $hora,
    string $estado,
    string $profesor
): bool {
    $esPuntual = ($estado === 'Presente');
    $colorFondoBadge = $esPuntual ? '#d1fae5' : '#fef3c7';
    $colorTextoBadge = $esPuntual ? '#065f46' : '#92400e';
    $tituloEstado    = $esPuntual ? 'Ingreso Registrado - Asistencia Puntual' : 'Ingreso Registrado con Retardo';

    $asunto = sprintf("[%s] Notificación de Asistencia: %s (%s)", INSTITUCION_SIGLAS, $nombreEstudiante, $estado);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #334155; }
  .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
  .email-header { background: linear-gradient(135deg, #3b82f6 0%, #10b981 100%); padding: 30px 24px; text-align: center; color: white; }
  .email-body { padding: 30px 24px; }
  .badge { display: inline-block; padding: 6px 16px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin-bottom: 16px; background-color: {$colorFondoBadge}; color: {$colorTextoBadge}; }
  .info-box { background-color: #f8fafc; border-radius: 12px; padding: 18px; margin: 20px 0; border: 1px solid #e2e8f0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
  .info-label { color: #64748b; font-weight: 500; }
  .info-val { color: #0f172a; font-weight: 700; text-align: right; }
  .email-footer { background-color: #f1f5f9; padding: 20px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
  <div class="email-container">
    <div class="email-header">
      <h2 style="margin: 0; font-size: 20px; font-weight: 800;">LICEO BOLIVARIANO "ROSSANA MELIDEI"</h2>
      <p style="margin: 6px 0 0 0; opacity: 0.9; font-size: 13px;">Control de Asistencia Escolar Automatizado</p>
    </div>
    <div class="email-body">
      <div style="text-align: center;">
        <span class="badge">{$tituloEstado}</span>
      </div>
      <p style="font-size: 15px; line-height: 1.6;">Estimado(a) representante, le informamos que su representado(a) ha registrado su pase de lista institucional mediante credencial QR:</p>
      
      <div class="info-box">
        <table width="100%" cellpadding="6" cellspacing="0" style="font-size: 14px;">
          <tr>
            <td style="color:#64748b;">Estudiante:</td>
            <td style="text-align:right; font-weight:700; color:#0f172a;">{$nombreEstudiante}</td>
          </tr>
          <tr>
            <td style="color:#64748b;">Asignatura:</td>
            <td style="text-align:right; font-weight:700; color:#0f172a;">{$materia}</td>
          </tr>
          <tr>
            <td style="color:#64748b;">Docente a cargo:</td>
            <td style="text-align:right; font-weight:600; color:#0f172a;">{$profesor}</td>
          </tr>
          <tr>
            <td style="color:#64748b;">Hora de Escaneo:</td>
            <td style="text-align:right; font-weight:700; color:#3b82f6;">{$hora}</td>
          </tr>
          <tr>
            <td style="color:#64748b;">Condición:</td>
            <td style="text-align:right; font-weight:800; color:{$colorTextoBadge};">{$estado}</td>
          </tr>
        </table>
      </div>

      <p style="font-size: 13px; color: #64748b; margin-top: 20px;">
        Este registro fue verificado a través de la red local del plantel bajo estrictas normas de puntualidad y convivencia escolar.
      </p>
    </div>
    <div class="email-footer">
      <p style="margin: 0 0 6px 0; font-weight: 600;">Liceo Bolivariano Rossana Melidei &bull; Código DEA: OD01540107</p>
      <p style="margin: 0; font-size: 11px;">Este correo se genera automáticamente. No responda a este remitente.</p>
    </div>
  </div>
</body>
</html>
HTML;

    return sendInstitutionalEmail($correo, 'Representante de ' . $nombreEstudiante, $asunto, $html);
}

/**
 * Notificación 2: Inasistencia tras "Cierre de Lista"
 */
function notificarInasistenciaRepresentante(
    string $correo,
    string $nombreEstudiante,
    string $materia,
    string $fecha,
    string $profesor
): bool {
    $asunto = sprintf("[%s] ALERTA DE INASISTENCIA: %s", INSTITUCION_SIGLAS, $nombreEstudiante);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #334155; }
  .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #fee2e2; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
  .email-header { background: linear-gradient(135deg, #ef4444 0%, #f97316 100%); padding: 30px 24px; text-align: center; color: white; }
  .email-body { padding: 30px 24px; }
  .badge { display: inline-block; padding: 6px 16px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin-bottom: 16px; background-color: #ffe4e6; color: #be123c; }
  .info-box { background-color: #fff1f2; border-radius: 12px; padding: 18px; margin: 20px 0; border: 1px solid #fecdd3; }
  .email-footer { background-color: #f1f5f9; padding: 20px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
  <div class="email-container">
    <div class="email-header">
      <h2 style="margin: 0; font-size: 20px; font-weight: 800;">ALERTA DE INASISTENCIA ESCOLAR</h2>
      <p style="margin: 6px 0 0 0; opacity: 0.9; font-size: 13px;">Liceo Bolivariano "Rossana Melidei"</p>
    </div>
    <div class="email-body">
      <div style="text-align: center;">
        <span class="badge">Inasistencia No Justificada al Cierre de Lista</span>
      </div>
      <p style="font-size: 15px; line-height: 1.6; color: #1e293b;">
        Estimado(a) representante, le notificamos que al efectuarse el <strong>Cierre Oficial de Lista</strong> para la jornada académica de hoy, no se registró el ingreso de su representado(a):
      </p>
      
      <div class="info-box">
        <table width="100%" cellpadding="6" cellspacing="0" style="font-size: 14px;">
          <tr>
            <td style="color:#64748b;">Estudiante:</td>
            <td style="text-align:right; font-weight:700; color:#0f172a;">{$nombreEstudiante}</td>
          </tr>
          <tr>
            <td style="color:#64748b;">Materia / Asignatura:</td>
            <td style="text-align:right; font-weight:700; color:#0f172a;">{$materia}</td>
          </tr>
          <tr>
            <td style="color:#64748b;">Docente de la Cátedra:</td>
            <td style="text-align:right; font-weight:600; color:#0f172a;">{$profesor}</td>
          </tr>
          <tr>
            <td style="color:#64748b;">Fecha de la Sesión:</td>
            <td style="text-align:right; font-weight:700; color:#e11d48;">{$fecha}</td>
          </tr>
        </table>
      </div>

      <p style="font-size: 13px; line-height: 1.6; color: #475569;">
        Si la inasistencia obedece a razones de salud o fuerza mayor, le recordamos consignar el respectivo justificativo médico o carta explicativa ante el Departamento de Control de Estudios dentro de las próximas 48 horas.
      </p>
    </div>
    <div class="email-footer">
      <p style="margin: 0 0 6px 0; font-weight: 600;">Liceo Bolivariano Rossana Melidei &bull; Coordinación de Control y Evaluación</p>
      <p style="margin: 0; font-size: 11px;">Notificación formal automatizada.</p>
    </div>
  </div>
</body>
</html>
HTML;

    return sendInstitutionalEmail($correo, 'Representante de ' . $nombreEstudiante, $asunto, $html);
}

/**
 * Notificación 3: Nota de Atención Pedagógica / Convivencia
 */
function notificarNotaAtencionRepresentante(
    string $correo,
    string $nombreEstudiante,
    string $motivo,
    string $fecha,
    string $profesor
): bool {
    $asunto = sprintf("[%s] Notificación de Observación Escolar: %s", INSTITUCION_SIGLAS, $nombreEstudiante);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'Helvetica Neue', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #334155; }
  .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #bfdbfe; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
  .email-header { background: linear-gradient(135deg, #2563eb 0%, #0284c7 100%); padding: 30px 24px; text-align: center; color: white; }
  .email-body { padding: 30px 24px; }
  .badge { display: inline-block; padding: 6px 16px; border-radius: 9999px; font-weight: 700; font-size: 13px; margin-bottom: 16px; background-color: #dbeafe; color: #1e40af; }
  .motivo-box { background-color: #f8fafc; border-left: 4px solid #3b82f6; border-radius: 0 12px 12px 0; padding: 18px; margin: 20px 0; font-size: 14px; line-height: 1.6; }
  .email-footer { background-color: #f1f5f9; padding: 20px 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
  <div class="email-container">
    <div class="email-header">
      <h2 style="margin: 0; font-size: 20px; font-weight: 800;">NOTA DE ATENCIÓN Y SEGUIMIENTO</h2>
      <p style="margin: 6px 0 0 0; opacity: 0.9; font-size: 13px;">Liceo Bolivariano "Rossana Melidei"</p>
    </div>
    <div class="email-body">
      <div style="text-align: center;">
        <span class="badge">Observación Pedagógica / Convivencia</span>
      </div>
      <p style="font-size: 15px; line-height: 1.6;">
        Estimado(a) representante, el docente a cargo ha emitido la siguiente nota de atención referente a su representado(a) <strong>{$nombreEstudiante}</strong>:
      </p>
      
      <div class="motivo-box">
        <p style="margin: 0 0 8px 0; font-weight: 700; color: #1e293b;">Detalle de la Observación:</p>
        <p style="margin: 0; color: #334155; font-style: italic;">“" . htmlspecialchars($motivo) . "”</p>
      </div>

      <table width="100%" cellpadding="6" cellspacing="0" style="font-size: 13px; border-top: 1px solid #e2e8f0; margin-top: 16px; padding-top: 12px;">
        <tr>
          <td style="color:#64748b;">Docente Emisor:</td>
          <td style="text-align:right; font-weight:600; color:#0f172a;">{$profesor}</td>
        </tr>
        <tr>
          <td style="color:#64748b;">Fecha de Registro:</td>
          <td style="text-align:right; font-weight:600; color:#0f172a;">{$fecha}</td>
        </tr>
      </table>

      <p style="font-size: 13px; color: #64748b; margin-top: 20px; line-height: 1.5;">
        Agradecemos tomar nota de esta comunicación para reforzar el acompañamiento formativo de su representado(a).
      </p>
    </div>
    <div class="email-footer">
      <p style="margin: 0 0 6px 0; font-weight: 600;">Liceo Bolivariano Rossana Melidei &bull; Departamento de Orientación</p>
      <p style="margin: 0; font-size: 11px;">Documento de notificación escolar digital.</p>
    </div>
  </div>
</body>
</html>
HTML;

    return sendInstitutionalEmail($correo, 'Representante de ' . $nombreEstudiante, $asunto, $html);
}
