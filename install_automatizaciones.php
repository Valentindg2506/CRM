<?php
/**
 * Instalador de tablas para el modulo de Automatizaciones/Workflows
 * Ejecutar una sola vez: php install_automatizaciones.php o acceder via navegador
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

$queries = [
    // Tabla de automatizaciones
    "CREATE TABLE IF NOT EXISTS automatizaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT NULL,
        activo TINYINT(1) DEFAULT 1,
        trigger_tipo ENUM('nuevo_cliente','nueva_propiedad','nueva_visita','visita_realizada','tarea_vencida','pipeline_etapa_cambiada','nuevo_documento','manual') NOT NULL,
        trigger_condiciones JSON NULL,
        created_by INT NOT NULL,
        ejecuciones INT DEFAULT 0,
        ultima_ejecucion DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Tabla de acciones de automatizacion
    "CREATE TABLE IF NOT EXISTS automatizacion_acciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        automatizacion_id INT NOT NULL,
        orden INT NOT NULL DEFAULT 0,
        tipo ENUM('enviar_email','enviar_whatsapp','crear_tarea','cambiar_estado_propiedad','asignar_agente','mover_pipeline','notificar','esperar') NOT NULL,
        configuracion JSON NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (automatizacion_id) REFERENCES automatizaciones(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Tabla de log de ejecuciones
    "CREATE TABLE IF NOT EXISTS automatizacion_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        automatizacion_id INT NOT NULL,
        accion_id INT NULL,
        estado ENUM('exito','error','pendiente') DEFAULT 'pendiente',
        detalles TEXT NULL,
        entidad_tipo VARCHAR(50) NULL,
        entidad_id INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (automatizacion_id) REFERENCES automatizaciones(id) ON DELETE CASCADE,
        INDEX idx_fecha (created_at)
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
    <title>Instalacion Automatizaciones - InmoCRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-database-add"></i> Instalacion - Modulo Automatizaciones</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= strpos($msg, 'ERROR') !== false ? 'danger' : 'success' ?> py-2">
                                <?= htmlspecialchars($msg) ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-info">
                                <strong>Instalacion completada.</strong> Ya puedes usar el modulo de Automatizaciones.
                            </div>
                            <a href="<?= APP_URL ?>/modules/automatizaciones/index.php" class="btn btn-primary">Ir a Automatizaciones</a>
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
