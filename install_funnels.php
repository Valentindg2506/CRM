<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS funnels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT,
        activo TINYINT(1) DEFAULT 1,
        visitas_total INT DEFAULT 0,
        conversiones_total INT DEFAULT 0,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS funnel_pasos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funnel_id INT NOT NULL,
        orden INT DEFAULT 0,
        nombre VARCHAR(200) NOT NULL,
        tipo ENUM('landing','formulario','upsell','downsell','gracias','custom') DEFAULT 'landing',
        landing_page_id INT DEFAULT NULL,
        formulario_id INT DEFAULT NULL,
        contenido_html TEXT,
        config JSON DEFAULT NULL,
        visitas INT DEFAULT 0,
        conversiones INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS funnel_sesiones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funnel_id INT NOT NULL,
        visitor_id VARCHAR(64),
        cliente_id INT DEFAULT NULL,
        paso_actual INT DEFAULT 1,
        completado TINYINT(1) DEFAULT 0,
        datos JSON DEFAULT NULL,
        ip VARCHAR(45),
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];
$ok = true; $msgs = [];
foreach ($queries as $sql) {
    try { $db->exec($sql); $msgs[] = "OK"; } catch (PDOException $e) { $ok = false; $msgs[] = "ERROR: ".$e->getMessage(); }
}
?><!DOCTYPE html><html><head><title>Install Funnels</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Funnels</h4></div><div class="card-body"><?php foreach($msgs as $m): ?><div class="alert alert-<?= strpos($m,'ERROR')!==false?'danger':'success' ?> py-2"><?= htmlspecialchars($m) ?></div><?php endforeach; ?><?php if($ok): ?><a href="modules/funnels/index.php" class="btn btn-primary">Ir a Funnels</a><?php endif; ?></div></div></div></body></html>
