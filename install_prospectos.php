<?php
/**
 * Instalador de tabla para el modulo de Prospectos
 * Ejecutar una sola vez: acceder via navegador o CLI
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

$queries = [
    // Tabla de prospectos
    "CREATE TABLE IF NOT EXISTS prospectos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referencia VARCHAR(20) NOT NULL UNIQUE,
        nombre VARCHAR(150) NOT NULL,
        telefono VARCHAR(20) DEFAULT NULL,
        telefono2 VARCHAR(20) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        etapa ENUM('nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado') NOT NULL DEFAULT 'nuevo_lead',
        tipo_propiedad VARCHAR(100) DEFAULT NULL,
        operacion ENUM('venta','alquiler','alquiler_opcion_compra','traspaso') DEFAULT NULL,
        direccion VARCHAR(255) DEFAULT NULL,
        numero VARCHAR(10) DEFAULT NULL,
        piso_puerta VARCHAR(20) DEFAULT NULL,
        barrio VARCHAR(100) DEFAULT NULL,
        localidad VARCHAR(100) DEFAULT NULL,
        provincia VARCHAR(100) DEFAULT NULL,
        comunidad_autonoma VARCHAR(100) DEFAULT NULL,
        codigo_postal VARCHAR(10) DEFAULT NULL,
        precio_estimado DECIMAL(12,2) DEFAULT NULL,
        precio_propietario DECIMAL(12,2) DEFAULT NULL,
        precio_comunidad DECIMAL(8,2) DEFAULT NULL,
        superficie DECIMAL(10,2) DEFAULT NULL,
        superficie_construida DECIMAL(10,2) DEFAULT NULL,
        superficie_util DECIMAL(10,2) DEFAULT NULL,
        superficie_parcela DECIMAL(10,2) DEFAULT NULL,
        habitaciones TINYINT DEFAULT NULL,
        banos TINYINT DEFAULT NULL,
        aseos TINYINT DEFAULT NULL,
        planta VARCHAR(20) DEFAULT NULL,
        ascensor TINYINT(1) DEFAULT 0,
        garaje_incluido TINYINT(1) DEFAULT 0,
        trastero_incluido TINYINT(1) DEFAULT 0,
        terraza TINYINT(1) DEFAULT 0,
        balcon TINYINT(1) DEFAULT 0,
        jardin TINYINT(1) DEFAULT 0,
        piscina TINYINT(1) DEFAULT 0,
        aire_acondicionado TINYINT(1) DEFAULT 0,
        calefaccion VARCHAR(50) DEFAULT NULL,
        orientacion ENUM('norte','sur','este','oeste','noreste','noroeste','sureste','suroeste') DEFAULT NULL,
        antiguedad INT DEFAULT NULL,
        estado_conservacion ENUM('a_estrenar','buen_estado','a_reformar','en_construccion') DEFAULT NULL,
        certificacion_energetica ENUM('A','B','C','D','E','F','G','en_tramite','exento') DEFAULT NULL,
        referencia_catastral VARCHAR(25) DEFAULT NULL,
        enlace VARCHAR(500) DEFAULT NULL,
        descripcion TEXT DEFAULT NULL,
        descripcion_interna TEXT DEFAULT NULL,
        fecha_contacto DATE DEFAULT NULL,
        fecha_proximo_contacto DATE DEFAULT NULL,
        estado ENUM('nuevo','en_proceso','pendiente','sin_interes','captado') NOT NULL DEFAULT 'nuevo',
        temperatura ENUM('frio','templado','caliente') DEFAULT 'frio',
        comision DECIMAL(5,2) DEFAULT NULL,
        exclusividad TINYINT(1) NOT NULL DEFAULT 0,
        notas TEXT DEFAULT NULL,
        reformas TEXT DEFAULT NULL,
        historial_contactos TEXT DEFAULT NULL,
        agente_id INT DEFAULT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_etapa (etapa),
        INDEX idx_estado (estado),
        INDEX idx_agente (agente_id),
        INDEX idx_provincia (provincia),
        INDEX idx_fecha_contacto (fecha_contacto),
        INDEX idx_fecha_proximo (fecha_proximo_contacto),
        INDEX idx_referencia (referencia)
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
    <title>Instalacion Prospectos - InmoCRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-database-add"></i> Instalacion - Modulo Prospectos</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= strpos($msg, 'ERROR') !== false ? 'danger' : 'success' ?> py-2">
                                <?= htmlspecialchars($msg) ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-info">
                                <strong>Instalacion completada.</strong> Ya puedes usar el modulo de Prospectos.
                            </div>
                            <a href="<?= APP_URL ?>/modules/prospectos/index.php" class="btn btn-primary">Ir a Prospectos</a>
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
