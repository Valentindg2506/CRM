<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS conversaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        canal ENUM('email','sms','whatsapp','chat') NOT NULL,
        asunto VARCHAR(300) DEFAULT '',
        ultimo_mensaje TEXT,
        ultimo_mensaje_at DATETIME DEFAULT NULL,
        no_leidos INT DEFAULT 0,
        estado ENUM('abierta','cerrada','archivada') DEFAULT 'abierta',
        asignado_a INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        FOREIGN KEY (asignado_a) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS conversacion_mensajes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversacion_id INT NOT NULL,
        direccion ENUM('entrante','saliente') NOT NULL,
        contenido TEXT NOT NULL,
        tipo ENUM('texto','html','adjunto') DEFAULT 'texto',
        metadata JSON DEFAULT NULL,
        leido TINYINT(1) DEFAULT 0,
        usuario_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversacion_id) REFERENCES conversaciones(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
$ok=true;$msgs=[];foreach($queries as $sql){try{$db->exec($sql);$msgs[]="OK";}catch(PDOException $e){$ok=false;$msgs[]="ERROR: ".$e->getMessage();}}
?><!DOCTYPE html><html><head><title>Install Conversaciones</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Conversaciones</h4></div><div class="card-body"><?php foreach($msgs as $m):?><div class="alert alert-<?=strpos($m,'ERROR')!==false?'danger':'success'?> py-2"><?=htmlspecialchars($m)?></div><?php endforeach;?></div></div></div></body></html>
