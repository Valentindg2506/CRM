<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS landing_pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(200) NOT NULL,
        slug VARCHAR(200) NOT NULL UNIQUE,
        secciones JSON NOT NULL,
        meta_titulo VARCHAR(200) DEFAULT '',
        meta_descripcion VARCHAR(300) DEFAULT '',
        formulario_id INT DEFAULT NULL,
        color_primario VARCHAR(7) DEFAULT '#10b981',
        color_fondo VARCHAR(7) DEFAULT '#ffffff',
        custom_css TEXT DEFAULT '',
        activa TINYINT(1) DEFAULT 1,
        visitas INT DEFAULT 0,
        conversiones INT DEFAULT 0,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
$success = true; $messages = [];
foreach ($queries as $sql) {
    try { $db->exec($sql); $messages[] = "OK: landing_pages"; }
    catch (PDOException $e) { $success = false; $messages[] = "ERROR: " . $e->getMessage(); }
}
if (php_sapi_name() === 'cli') { foreach ($messages as $m) echo $m."\n"; exit($success?0:1); }
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalacion Landing Pages</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-8"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Landing Pages</h4></div><div class="card-body"><?php foreach ($messages as $m): ?><div class="alert alert-<?= strpos($m,'ERROR')!==false?'danger':'success' ?> py-2"><?= htmlspecialchars($m) ?></div><?php endforeach; ?><?php if ($success): ?><a href="<?= APP_URL ?>/modules/landing/index.php" class="btn btn-primary">Ir a Landing Pages</a><?php endif; ?></div></div></div></div></div></body></html>
