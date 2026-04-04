<?php
/**
 * Instalador de tablas para Campos Personalizados
 * Ejecutar una sola vez
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

$queries = [
    "CREATE TABLE IF NOT EXISTS custom_fields (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entidad ENUM('cliente','propiedad','visita') NOT NULL DEFAULT 'cliente',
        nombre VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        tipo ENUM('texto','numero','fecha','select','checkbox','textarea','email','telefono') NOT NULL DEFAULT 'texto',
        opciones TEXT COMMENT 'Opciones separadas por coma para tipo select',
        obligatorio TINYINT(1) DEFAULT 0,
        orden INT DEFAULT 0,
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_slug_entidad (slug, entidad)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS custom_field_values (
        id INT AUTO_INCREMENT PRIMARY KEY,
        field_id INT NOT NULL,
        entidad_id INT NOT NULL,
        valor TEXT,
        UNIQUE KEY unique_field_entity (field_id, entidad_id),
        FOREIGN KEY (field_id) REFERENCES custom_fields(id) ON DELETE CASCADE
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

if (php_sapi_name() === 'cli') {
    foreach ($messages as $msg) echo $msg . PHP_EOL;
    echo $success ? "\nInstalacion completada.\n" : "\nInstalacion con errores.\n";
    exit($success ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalacion Campos Personalizados - Tinoprop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Instalacion - Campos Personalizados</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= strpos($msg, 'ERROR') !== false ? 'danger' : 'success' ?> py-2">
                                <?= htmlspecialchars($msg) ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($success): ?>
                            <a href="<?= APP_URL ?>/modules/ajustes/custom_fields.php" class="btn btn-primary">Gestionar Campos</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
