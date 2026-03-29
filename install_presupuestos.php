<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS presupuestos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero VARCHAR(20) NOT NULL,
        cliente_id INT DEFAULT NULL,
        propiedad_id INT DEFAULT NULL,
        titulo VARCHAR(300) NOT NULL,
        descripcion TEXT,
        lineas JSON NOT NULL DEFAULT '[]',
        subtotal DECIMAL(12,2) DEFAULT 0,
        iva_total DECIMAL(12,2) DEFAULT 0,
        total DECIMAL(12,2) DEFAULT 0,
        estado ENUM('borrador','enviado','aceptado','rechazado','expirado','convertido') DEFAULT 'borrador',
        validez_dias INT DEFAULT 30,
        fecha_emision DATE NOT NULL,
        fecha_expiracion DATE DEFAULT NULL,
        notas TEXT,
        condiciones TEXT,
        token VARCHAR(64) DEFAULT NULL,
        aceptado_at DATETIME DEFAULT NULL,
        aceptado_ip VARCHAR(45) DEFAULT NULL,
        factura_id INT DEFAULT NULL,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
$ok=true;$msgs=[];foreach($queries as $sql){try{$db->exec($sql);$msgs[]="OK";}catch(PDOException $e){$ok=false;$msgs[]="ERROR: ".$e->getMessage();}}
?><!DOCTYPE html><html><head><title>Install Presupuestos</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Presupuestos</h4></div><div class="card-body"><?php foreach($msgs as $m):?><div class="alert alert-<?=strpos($m,'ERROR')!==false?'danger':'success'?> py-2"><?=htmlspecialchars($m)?></div><?php endforeach;?></div></div></div></body></html>
