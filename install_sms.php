<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$queries = [
    "CREATE TABLE IF NOT EXISTS sms_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proveedor ENUM('twilio','vonage') DEFAULT 'twilio',
        api_sid VARCHAR(200) DEFAULT '',
        api_token VARCHAR(200) DEFAULT '',
        telefono_remitente VARCHAR(20) DEFAULT '',
        activo TINYINT(1) DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "INSERT IGNORE INTO sms_config (id) VALUES (1)",

    "CREATE TABLE IF NOT EXISTS sms_mensajes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT DEFAULT NULL,
        telefono_destino VARCHAR(20) NOT NULL,
        mensaje TEXT NOT NULL,
        estado ENUM('pendiente','enviado','fallido','entregado') DEFAULT 'pendiente',
        proveedor_id VARCHAR(100) DEFAULT NULL,
        error_mensaje TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$success = true; $messages = [];
foreach ($queries as $sql) {
    try { $db->exec($sql); preg_match('/(?:CREATE TABLE IF NOT EXISTS|INSERT IGNORE INTO) (\w+)/', $sql, $m); $messages[] = "OK: " . ($m[1] ?? 'query'); }
    catch (PDOException $e) { $success = false; $messages[] = "ERROR: " . $e->getMessage(); }
}
if (php_sapi_name() === 'cli') { foreach ($messages as $m) echo $m . "\n"; exit($success ? 0 : 1); }
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalacion SMS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-8"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - SMS</h4></div><div class="card-body"><?php foreach ($messages as $m): ?><div class="alert alert-<?= strpos($m,'ERROR')!==false?'danger':'success' ?> py-2"><?= htmlspecialchars($m) ?></div><?php endforeach; ?><?php if ($success): ?><a href="<?= APP_URL ?>/modules/sms/index.php" class="btn btn-primary">Ir a SMS</a><?php endif; ?></div></div></div></div></div></body></html>
