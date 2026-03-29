<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$queries = [
    "CREATE TABLE IF NOT EXISTS chat_conversaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visitor_id VARCHAR(64) NOT NULL,
        nombre VARCHAR(100) DEFAULT 'Visitante',
        email VARCHAR(200) DEFAULT '',
        telefono VARCHAR(20) DEFAULT '',
        pagina_origen VARCHAR(500) DEFAULT '',
        ip VARCHAR(45),
        estado ENUM('activa','cerrada','esperando') DEFAULT 'esperando',
        agente_id INT DEFAULT NULL,
        cliente_id INT DEFAULT NULL,
        ultimo_mensaje DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agente_id) REFERENCES usuarios(id) ON DELETE SET NULL,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS chat_mensajes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversacion_id INT NOT NULL,
        emisor ENUM('visitante','agente','sistema') NOT NULL,
        mensaje TEXT NOT NULL,
        leido TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversacion_id) REFERENCES chat_conversaciones(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS chat_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(200) DEFAULT 'Hola! Como podemos ayudarte?',
        subtitulo VARCHAR(300) DEFAULT 'Normalmente respondemos en pocos minutos',
        color_primario VARCHAR(7) DEFAULT '#10b981',
        posicion ENUM('bottom-right','bottom-left') DEFAULT 'bottom-right',
        mensaje_bienvenida TEXT DEFAULT 'Bienvenido! Escribenos tu consulta.',
        pedir_datos TINYINT(1) DEFAULT 1,
        activo TINYINT(1) DEFAULT 1,
        horario_inicio TIME DEFAULT '09:00:00',
        horario_fin TIME DEFAULT '20:00:00',
        mensaje_fuera_horario TEXT DEFAULT 'Estamos fuera de horario. Dejanos tu mensaje y te contactaremos.',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "INSERT IGNORE INTO chat_config (id) VALUES (1)"
];

$success = true; $messages = [];
foreach ($queries as $sql) {
    try { $db->exec($sql); preg_match('/(?:CREATE TABLE IF NOT EXISTS|INSERT IGNORE INTO) (\w+)/', $sql, $m); $messages[] = "OK: " . ($m[1] ?? 'query'); }
    catch (PDOException $e) { $success = false; $messages[] = "ERROR: " . $e->getMessage(); }
}
if (php_sapi_name() === 'cli') { foreach ($messages as $m) echo $m . "\n"; exit($success ? 0 : 1); }
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalacion Chat</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-8"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Chat Widget</h4></div><div class="card-body"><?php foreach ($messages as $m): ?><div class="alert alert-<?= strpos($m,'ERROR')!==false?'danger':'success' ?> py-2"><?= htmlspecialchars($m) ?></div><?php endforeach; ?><?php if ($success): ?><a href="<?= APP_URL ?>/modules/chat/index.php" class="btn btn-primary">Ir al Chat</a><?php endif; ?></div></div></div></div></div></body></html>
