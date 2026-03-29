<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS encuestas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT,
        preguntas JSON NOT NULL DEFAULT '[]',
        logica_condicional JSON DEFAULT NULL,
        color_primario VARCHAR(7) DEFAULT '#10b981',
        activo TINYINT(1) DEFAULT 1,
        crear_cliente TINYINT(1) DEFAULT 0,
        total_respuestas INT DEFAULT 0,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS encuesta_respuestas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        encuesta_id INT NOT NULL,
        cliente_id INT DEFAULT NULL,
        respuestas JSON NOT NULL,
        puntuacion DECIMAL(5,2) DEFAULT 0,
        ip VARCHAR(45),
        completada TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
$ok=true;$msgs=[];foreach($queries as $sql){try{$db->exec($sql);$msgs[]="OK";}catch(PDOException $e){$ok=false;$msgs[]="ERROR: ".$e->getMessage();}}
?><!DOCTYPE html><html><head><title>Install Encuestas</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Encuestas</h4></div><div class="card-body"><?php foreach($msgs as $m):?><div class="alert alert-<?=strpos($m,'ERROR')!==false?'danger':'success'?> py-2"><?=htmlspecialchars($m)?></div><?php endforeach;?></div></div></div></body></html>
