<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS workflows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT DEFAULT '',
        trigger_tipo VARCHAR(50) NOT NULL DEFAULT 'manual',
        trigger_config JSON DEFAULT NULL,
        nodos JSON NOT NULL,
        conexiones JSON NOT NULL,
        activo TINYINT(1) DEFAULT 0,
        ejecuciones INT DEFAULT 0,
        ultima_ejecucion DATETIME DEFAULT NULL,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS workflow_ejecuciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        workflow_id INT NOT NULL,
        estado ENUM('corriendo','completado','error') DEFAULT 'corriendo',
        nodo_actual VARCHAR(50) DEFAULT NULL,
        log JSON DEFAULT NULL,
        datos JSON DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME DEFAULT NULL,
        FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
$success = true; $messages = [];
foreach ($queries as $sql) {
    try { $db->exec($sql); $messages[] = "OK: tabla creada"; }
    catch (PDOException $e) { $success = false; $messages[] = "ERROR: " . $e->getMessage(); }
}
if (php_sapi_name() === 'cli') { foreach ($messages as $m) echo $m."\n"; exit($success?0:1); }
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Instalacion Workflows</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-8"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Workflows Visuales</h4></div><div class="card-body"><?php foreach ($messages as $m): ?><div class="alert alert-<?= strpos($m,'ERROR')!==false?'danger':'success' ?> py-2"><?= htmlspecialchars($m) ?></div><?php endforeach; ?><?php if ($success): ?><a href="<?= APP_URL ?>/modules/automatizaciones/workflows.php" class="btn btn-primary">Ir a Workflows</a><?php endif; ?></div></div></div></div></div></body></html>
