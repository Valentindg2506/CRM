<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$queries = [
    "CREATE TABLE IF NOT EXISTS trigger_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        codigo VARCHAR(20) NOT NULL UNIQUE,
        url_destino VARCHAR(500) NOT NULL,
        accion_tipo ENUM('ninguna','tag','notificacion') DEFAULT 'ninguna',
        accion_valor VARCHAR(200) DEFAULT '',
        activo TINYINT(1) DEFAULT 1,
        total_clicks INT DEFAULT 0,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS trigger_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        link_id INT NOT NULL,
        cliente_id INT DEFAULT NULL,
        ip VARCHAR(45),
        user_agent TEXT,
        referer VARCHAR(500),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (link_id) REFERENCES trigger_links(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$success = true; $messages = [];
foreach ($queries as $sql) {
    try { $db->exec($sql); preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m); $messages[] = "OK: " . ($m[1] ?? 'query'); }
    catch (PDOException $e) { $success = false; $messages[] = "ERROR: " . $e->getMessage(); }
}
if (php_sapi_name() === 'cli') { foreach ($messages as $m) echo $m . "\n"; exit($success ? 0 : 1); }
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalacion Trigger Links</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-8"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Trigger Links</h4></div><div class="card-body"><?php foreach ($messages as $m): ?><div class="alert alert-<?= strpos($m,'ERROR')!==false?'danger':'success' ?> py-2"><?= htmlspecialchars($m) ?></div><?php endforeach; ?><?php if ($success): ?><a href="<?= APP_URL ?>/modules/marketing/trigger_links.php" class="btn btn-primary">Ir a Trigger Links</a><?php endif; ?></div></div></div></div></div></body></html>
