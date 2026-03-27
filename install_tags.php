<?php
/**
 * Instalador de tablas para el sistema de Tags/Etiquetas
 * Ejecutar una sola vez: php install_tags.php o acceder via navegador
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

$queries = [
    // Tabla de tags
    "CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL UNIQUE,
        color VARCHAR(7) DEFAULT '#6b7280',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Tabla de relacion cliente-tags
    "CREATE TABLE IF NOT EXISTS cliente_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        tag_id INT NOT NULL,
        UNIQUE KEY unique_cliente_tag (cliente_id, tag_id),
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$success = true;
$messages = [];

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $matches);
        $tableName = $matches[1] ?? 'desconocida';
        $messages[] = "Tabla '$tableName' creada correctamente.";
    } catch (PDOException $e) {
        $success = false;
        $messages[] = "ERROR: " . $e->getMessage();
    }
}

// Si se ejecuta desde CLI
if (php_sapi_name() === 'cli') {
    foreach ($messages as $msg) {
        echo $msg . PHP_EOL;
    }
    echo $success ? "\nInstalacion completada con exito.\n" : "\nInstalacion completada con errores.\n";
    exit($success ? 0 : 1);
}

// Si se ejecuta desde navegador
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalacion Tags - InmoCRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-database-add"></i> Instalacion - Sistema de Tags</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= strpos($msg, 'ERROR') !== false ? 'danger' : 'success' ?> py-2">
                                <?= htmlspecialchars($msg) ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-info">
                                <strong>Instalacion completada.</strong> Ya puedes usar el sistema de tags en clientes.
                            </div>
                            <a href="<?= APP_URL ?>/modules/clientes/index.php" class="btn btn-primary">Ir a Clientes</a>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <strong>Hubo errores.</strong> Revisa los mensajes anteriores.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
