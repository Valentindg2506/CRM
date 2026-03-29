<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS campanas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT,
        tipo ENUM('email','sms','mixta') DEFAULT 'email',
        estado ENUM('borrador','activa','pausada','completada') DEFAULT 'borrador',
        filtro_tags JSON DEFAULT NULL,
        total_contactos INT DEFAULT 0,
        enviados INT DEFAULT 0,
        abiertos INT DEFAULT 0,
        clicks INT DEFAULT 0,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS campana_pasos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campana_id INT NOT NULL,
        orden INT DEFAULT 1,
        tipo ENUM('email','sms','esperar','condicion') DEFAULT 'email',
        asunto VARCHAR(300) DEFAULT '',
        contenido TEXT,
        plantilla_id INT DEFAULT NULL,
        esperar_minutos INT DEFAULT 0,
        condicion_campo VARCHAR(100) DEFAULT '',
        condicion_valor VARCHAR(200) DEFAULT '',
        enviados INT DEFAULT 0,
        abiertos INT DEFAULT 0,
        clicks INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS campana_contactos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campana_id INT NOT NULL,
        cliente_id INT NOT NULL,
        paso_actual INT DEFAULT 0,
        estado ENUM('pendiente','activo','completado','cancelado') DEFAULT 'pendiente',
        proximo_envio DATETIME DEFAULT NULL,
        datos JSON DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
$ok=true;$msgs=[];
foreach($queries as $sql){try{$db->exec($sql);$msgs[]="OK";}catch(PDOException $e){$ok=false;$msgs[]="ERROR: ".$e->getMessage();}}
?><!DOCTYPE html><html><head><title>Install Campanas</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Campanas Drip</h4></div><div class="card-body"><?php foreach($msgs as $m):?><div class="alert alert-<?=strpos($m,'ERROR')!==false?'danger':'success'?> py-2"><?=htmlspecialchars($m)?></div><?php endforeach;?><?php if($ok):?><a href="modules/campanas/index.php" class="btn btn-primary">Ir a Campanas</a><?php endif;?></div></div></div></body></html>
