<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS email_plantillas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        asunto VARCHAR(300) NOT NULL,
        contenido TEXT NOT NULL,
        categoria ENUM('general','seguimiento','bienvenida','oferta','visita','factura','recordatorio','personalizada') DEFAULT 'general',
        activa TINYINT(1) DEFAULT 1,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "INSERT IGNORE INTO email_plantillas (id, nombre, asunto, contenido, categoria) VALUES
    (1, 'Bienvenida nuevo cliente', 'Bienvenido/a {{nombre}}!', '<h2>Hola {{nombre}},</h2><p>Gracias por confiar en nosotros. Estamos encantados de ayudarte a encontrar tu propiedad ideal.</p><p>Nuestro equipo se pondra en contacto contigo pronto para conocer tus necesidades.</p><p>Un saludo,<br>{{empresa_nombre}}</p>', 'bienvenida'),
    (2, 'Seguimiento post-visita', 'Como fue tu visita a {{propiedad_referencia}}?', '<h2>Hola {{nombre}},</h2><p>Esperamos que la visita a la propiedad <strong>{{propiedad_titulo}}</strong> ({{propiedad_referencia}}) haya sido de tu agrado.</p><p>Nos gustaria conocer tu opinion. Si tienes alguna pregunta o quieres programar otra visita, no dudes en contactarnos.</p><p>Saludos,<br>{{empresa_nombre}}</p>', 'seguimiento'),
    (3, 'Propuesta de visita', 'Te invitamos a visitar {{propiedad_titulo}}', '<h2>Hola {{nombre}},</h2><p>Tenemos una propiedad que creemos puede interesarte:</p><p><strong>{{propiedad_titulo}}</strong><br>Ref: {{propiedad_referencia}}<br>Precio: {{propiedad_precio}}</p><p>Te gustaria programar una visita? Respondenos a este email o llamanos.</p><p>Saludos,<br>{{empresa_nombre}}</p>', 'visita'),
    (4, 'Envio de factura', 'Factura {{factura_numero}}', '<h2>Hola {{nombre}},</h2><p>Adjunto encontraras la factura <strong>{{factura_numero}}</strong> por un total de <strong>{{factura_total}}</strong>.</p><p>Si tienes alguna consulta sobre esta factura, no dudes en contactarnos.</p><p>Saludos,<br>{{empresa_nombre}}</p>', 'factura'),
    (5, 'Recordatorio de cita', 'Recordatorio: tu cita el {{fecha_visita}}', '<h2>Hola {{nombre}},</h2><p>Te recordamos que tienes una cita programada:</p><p><strong>Fecha:</strong> {{fecha_visita}}<br><strong>Hora:</strong> {{hora_visita}}</p><p>Si necesitas reprogramar, avisanos con antelacion.</p><p>Saludos,<br>{{empresa_nombre}}</p>', 'recordatorio')"
];
$success = true; $messages = [];
foreach ($queries as $sql) {
    try { $db->exec($sql); $messages[] = "OK"; }
    catch (PDOException $e) { $success = false; $messages[] = "ERROR: " . $e->getMessage(); }
}
if (php_sapi_name() === 'cli') { foreach ($messages as $m) echo $m."\n"; exit($success?0:1); }
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalacion Plantillas</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-8"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Plantillas Email</h4></div><div class="card-body"><?php foreach ($messages as $m): ?><div class="alert alert-<?= strpos($m,'ERROR')!==false?'danger':'success' ?> py-2"><?= htmlspecialchars($m) ?></div><?php endforeach; ?><?php if ($success): ?><p class="text-success">Plantillas instaladas con 5 plantillas predefinidas.</p><a href="<?= APP_URL ?>/modules/email/plantillas.php" class="btn btn-primary">Ir a Plantillas</a><?php endif; ?></div></div></div></div></div></body></html>
