<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS marketing_utm (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prospecto_id INT DEFAULT NULL,
        cliente_id INT DEFAULT NULL,
        utm_source VARCHAR(200) DEFAULT '',
        utm_medium VARCHAR(200) DEFAULT '',
        utm_campaign VARCHAR(200) DEFAULT '',
        utm_term VARCHAR(200) DEFAULT '',
        utm_content VARCHAR(200) DEFAULT '',
        landing_url VARCHAR(500) DEFAULT '',
        referrer VARCHAR(500) DEFAULT '',
        ip VARCHAR(45) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_source (utm_source),
        INDEX idx_campaign (utm_campaign),
        INDEX idx_medium (utm_medium),
        INDEX idx_fecha (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
$success = true; $messages = [];
foreach ($queries as $sql) {
    try { $db->exec($sql); preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m); $messages[] = "OK: " . ($m[1] ?? 'query'); }
    catch (PDOException $e) { $success = false; $messages[] = "ERROR: " . $e->getMessage(); }
}
if (php_sapi_name() === 'cli') { foreach ($messages as $m) echo $m."\n"; exit($success?0:1); }
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalacion Marketing UTM</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-8"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Marketing UTM</h4></div><div class="card-body"><?php foreach ($messages as $m): ?><div class="alert alert-<?= strpos($m,'ERROR')!==false?'danger':'success' ?> py-2"><?= htmlspecialchars($m) ?></div><?php endforeach; ?><?php if ($success): ?><a href="<?= APP_URL ?>/modules/marketing/analytics.php" class="btn btn-primary">Ir a Analytics</a><?php endif; ?></div></div></div></div></div></body></html>
