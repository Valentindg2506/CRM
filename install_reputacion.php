<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS resenas_solicitudes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        tipo ENUM('google','email','whatsapp') NOT NULL DEFAULT 'google',
        enlace_resena VARCHAR(500) DEFAULT '',
        estado ENUM('pendiente','enviada','completada','ignorada') DEFAULT 'pendiente',
        valoracion INT DEFAULT NULL,
        comentario TEXT,
        enviada_at DATETIME DEFAULT NULL,
        completada_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS reputacion_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        google_review_link VARCHAR(500) DEFAULT '',
        mensaje_solicitud TEXT DEFAULT 'Hola {{nombre}}, gracias por confiar en nosotros. Nos ayudaria mucho si pudieras dejarnos una resena.',
        activo TINYINT(1) DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "INSERT IGNORE INTO reputacion_config (id) VALUES (1)"
];
$success = true; $messages = [];
foreach ($queries as $sql) {
    try { $db->exec($sql); preg_match('/(?:CREATE TABLE IF NOT EXISTS|INSERT IGNORE INTO) (\w+)/', $sql, $m); $messages[] = "OK: " . ($m[1] ?? 'query'); }
    catch (PDOException $e) { $success = false; $messages[] = "ERROR: " . $e->getMessage(); }
}
if (php_sapi_name() === 'cli') { foreach ($messages as $m) echo $m."\n"; exit($success?0:1); }
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalacion Reputacion</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-8"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Reputacion</h4></div><div class="card-body"><?php foreach ($messages as $m): ?><div class="alert alert-<?= strpos($m,'ERROR')!==false?'danger':'success' ?> py-2"><?= htmlspecialchars($m) ?></div><?php endforeach; ?><?php if ($success): ?><a href="<?= APP_URL ?>/modules/marketing/reputacion.php" class="btn btn-primary">Ir a Reputacion</a><?php endif; ?></div></div></div></div></div></body></html>
