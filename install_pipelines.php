<?php
/**
 * Instalador de tablas para el modulo de Pipelines/Kanban
 * Ejecutar una sola vez: php install_pipelines.php o acceder via navegador
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

$queries = [
    // Tabla de pipelines
    "CREATE TABLE IF NOT EXISTS pipelines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT NULL,
        color VARCHAR(7) NOT NULL DEFAULT '#10b981',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Tabla de etapas de pipeline
    "CREATE TABLE IF NOT EXISTS pipeline_etapas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pipeline_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        color VARCHAR(7) NOT NULL DEFAULT '#64748b',
        orden INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Tabla de items del pipeline
    "CREATE TABLE IF NOT EXISTS pipeline_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pipeline_id INT NOT NULL,
        etapa_id INT NOT NULL,
        titulo VARCHAR(200) NOT NULL,
        propiedad_id INT NULL,
        cliente_id INT NULL,
        valor DECIMAL(12,2) NULL,
        notas TEXT NULL,
        prioridad ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE CASCADE,
        FOREIGN KEY (etapa_id) REFERENCES pipeline_etapas(id) ON DELETE CASCADE,
        FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE SET NULL,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_pipeline_etapa (pipeline_id, etapa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$success = true;
$messages = [];

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        // Extraer nombre de tabla del CREATE TABLE
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
    <title>Instalacion Pipelines - InmoCRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-database-add"></i> Instalacion - Modulo Pipelines</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= strpos($msg, 'ERROR') !== false ? 'danger' : 'success' ?> py-2">
                                <?= htmlspecialchars($msg) ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-info">
                                <strong>Instalacion completada.</strong> Ya puedes usar el modulo de Pipelines.
                            </div>
                            <a href="<?= APP_URL ?>/modules/pipelines/index.php" class="btn btn-primary">Ir a Pipelines</a>
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
