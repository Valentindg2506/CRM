<?php
/**
 * Sistema de Email - Compatible con Hostinger
 * Soporta mail() nativo de PHP y SMTP basico
 */

/**
 * Enviar email usando el metodo configurado
 */
function enviarEmail($destinatario, $asunto, $cuerpo, $esHTML = true) {
    try {
        if (MAIL_METHOD === 'smtp' && !empty(SMTP_HOST)) {
            return enviarEmailSMTP($destinatario, $asunto, $cuerpo, $esHTML);
        } else {
            return enviarEmailMail($destinatario, $asunto, $cuerpo, $esHTML);
        }
    } catch (Exception $e) {
        logError('Email send error: ' . $e->getMessage(), [
            'to' => $destinatario,
            'subject' => $asunto
        ]);
        return false;
    }
}

/**
 * Enviar usando mail() nativo de PHP
 */
function enviarEmailMail($destinatario, $asunto, $cuerpo, $esHTML = true) {
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = $esHTML ? 'Content-type: text/html; charset=UTF-8' : 'Content-type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . '>';
    $headers[] = 'Reply-To: ' . SMTP_FROM;
    $headers[] = 'X-Mailer: InmoCRM';

    if ($esHTML) {
        $cuerpo = plantillaEmail($cuerpo);
    }

    $result = mail($destinatario, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, implode("\r\n", $headers));

    if ($result) {
        logError('Email sent successfully', ['to' => $destinatario, 'subject' => $asunto]);
    }

    return $result;
}

/**
 * Enviar usando SMTP directo (sockets) - sin dependencias externas
 */
function enviarEmailSMTP($destinatario, $asunto, $cuerpo, $esHTML = true) {
    $socket = @fsockopen(
        (SMTP_PORT == 465 ? 'ssl://' : '') . SMTP_HOST,
        SMTP_PORT,
        $errno, $errstr, 30
    );

    if (!$socket) {
        throw new Exception("No se pudo conectar al servidor SMTP: $errstr ($errno)");
    }

    // Leer saludo
    smtpRead($socket);

    // EHLO
    smtpCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));

    // STARTTLS para puerto 587
    if (SMTP_PORT == 587) {
        smtpCommand($socket, "STARTTLS");
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        smtpCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    }

    // Autenticacion
    if (!empty(SMTP_USER)) {
        smtpCommand($socket, "AUTH LOGIN");
        smtpCommand($socket, base64_encode(SMTP_USER));
        smtpCommand($socket, base64_encode(SMTP_PASS));
    }

    // Enviar
    smtpCommand($socket, "MAIL FROM:<" . SMTP_FROM . ">");
    smtpCommand($socket, "RCPT TO:<$destinatario>");
    smtpCommand($socket, "DATA");

    // Headers del mensaje
    $contentType = $esHTML ? 'text/html' : 'text/plain';
    if ($esHTML) {
        $cuerpo = plantillaEmail($cuerpo);
    }

    $mensaje = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    $mensaje .= "To: $destinatario\r\n";
    $mensaje .= "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n";
    $mensaje .= "MIME-Version: 1.0\r\n";
    $mensaje .= "Content-Type: $contentType; charset=UTF-8\r\n";
    $mensaje .= "Content-Transfer-Encoding: base64\r\n";
    $mensaje .= "\r\n";
    $mensaje .= chunk_split(base64_encode($cuerpo));
    $mensaje .= "\r\n.";

    smtpCommand($socket, $mensaje);
    smtpCommand($socket, "QUIT");

    fclose($socket);
    return true;
}

function smtpCommand($socket, $command) {
    fwrite($socket, $command . "\r\n");
    return smtpRead($socket);
}

function smtpRead($socket) {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) == ' ') break;
    }
    return $response;
}

/**
 * Plantilla HTML base para emails
 */
function plantillaEmail($contenido) {
    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="background: #1e293b; color: #fff; padding: 20px; text-align: center;">
            <h2 style="margin: 0;">' . APP_NAME . '</h2>
        </div>
        <div style="padding: 30px;">
            ' . $contenido . '
        </div>
        <div style="background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d;">
            <p>' . RGPD_EMPRESA . ' - ' . RGPD_DIRECCION . '</p>
            <p>Este email fue enviado desde ' . APP_NAME . '</p>
        </div>
    </div>
</body>
</html>';
}

/**
 * Enviar notificacion de nueva visita programada
 */
function notificarNuevaVisita($visita, $propiedad, $cliente, $agente) {
    $asunto = "Nueva visita programada - " . $propiedad['referencia'];
    $cuerpo = "<h3>Nueva visita programada</h3>
        <p><strong>Propiedad:</strong> {$propiedad['referencia']} - " . htmlspecialchars($propiedad['titulo']) . "</p>
        <p><strong>Cliente:</strong> " . htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']) . "</p>
        <p><strong>Fecha:</strong> " . date('d/m/Y', strtotime($visita['fecha'])) . " a las " . substr($visita['hora'], 0, 5) . "</p>
        <p><strong>Direccion:</strong> " . htmlspecialchars($propiedad['direccion'] . ', ' . $propiedad['localidad']) . "</p>
        <p><a href='" . APP_URL . "/modules/visitas/index.php' style='background:#2563eb; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;'>Ver en el CRM</a></p>";

    if (!empty($agente['email'])) {
        enviarEmail($agente['email'], $asunto, $cuerpo);
    }
}

/**
 * Enviar notificacion de tarea asignada
 */
function notificarTareaAsignada($tarea, $agente) {
    $asunto = "Nueva tarea asignada: " . $tarea['titulo'];
    $prioridades = ['urgente' => '🔴 URGENTE', 'alta' => '🟠 Alta', 'media' => '🟡 Media', 'baja' => '🟢 Baja'];
    $cuerpo = "<h3>Nueva tarea asignada</h3>
        <p><strong>Titulo:</strong> " . htmlspecialchars($tarea['titulo']) . "</p>
        <p><strong>Prioridad:</strong> " . ($prioridades[$tarea['prioridad']] ?? $tarea['prioridad']) . "</p>";
    if (!empty($tarea['fecha_vencimiento'])) {
        $cuerpo .= "<p><strong>Vencimiento:</strong> " . date('d/m/Y H:i', strtotime($tarea['fecha_vencimiento'])) . "</p>";
    }
    $cuerpo .= "<p><a href='" . APP_URL . "/modules/tareas/index.php' style='background:#2563eb; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;'>Ver en el CRM</a></p>";

    if (!empty($agente['email'])) {
        enviarEmail($agente['email'], $asunto, $cuerpo);
    }
}

/**
 * Enviar propiedades compatibles a un cliente
 */
function enviarMatchingCliente($cliente, $propiedades) {
    if (empty($cliente['email'])) return false;

    $asunto = "Propiedades que pueden interesarte - " . APP_NAME;
    $cuerpo = "<h3>Hola " . htmlspecialchars($cliente['nombre']) . ",</h3>
        <p>Hemos encontrado estas propiedades que coinciden con tus preferencias:</p><hr>";

    foreach ($propiedades as $p) {
        $cuerpo .= "<div style='margin: 15px 0; padding: 15px; border: 1px solid #eee; border-radius: 8px;'>
            <h4 style='margin:0 0 5px 0;'>" . htmlspecialchars($p['titulo']) . "</h4>
            <p style='color: #2563eb; font-size: 18px; font-weight: bold; margin: 5px 0;'>" . number_format($p['precio'], 0, ',', '.') . " &euro;</p>
            <p style='color: #666; margin: 5px 0;'>" . htmlspecialchars($p['localidad'] . ', ' . $p['provincia']) . "</p>
            <p style='margin: 5px 0;'>";
        if ($p['habitaciones']) $cuerpo .= $p['habitaciones'] . " hab. | ";
        if ($p['superficie_construida']) $cuerpo .= $p['superficie_construida'] . " m&sup2; | ";
        $cuerpo .= ucfirst($p['tipo']) . "</p>
        </div>";
    }

    $cuerpo .= "<hr><p style='font-size:11px; color:#999;'>Este email se ha enviado porque tienes registradas preferencias de busqueda en nuestro sistema. Si deseas dejar de recibir estos emails, contacta con nosotros en " . RGPD_EMAIL_DPD . "</p>";

    return enviarEmail($cliente['email'], $asunto, $cuerpo);
}
