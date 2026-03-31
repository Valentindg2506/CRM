<?php
/**
 * Instalador de mejoras CRM - Fase 1
 * - Tabla historial_prospectos
 * - Columna temperatura en prospectos
 * - Extensión custom_fields ENUM para prospectos
 * - Etapa 'nuevo_lead' añadida
 * - Campos de propiedad completos
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

$queries = [
    // 1. Tabla historial_prospectos
    "CREATE TABLE IF NOT EXISTS historial_prospectos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prospecto_id INT NOT NULL,
        usuario_id INT NOT NULL,
        contenido TEXT NOT NULL,
        tipo ENUM('llamada','email','visita','nota','whatsapp','otro') NOT NULL DEFAULT 'nota',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (prospecto_id) REFERENCES prospectos(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
        INDEX idx_prospecto (prospecto_id),
        INDEX idx_fecha (created_at DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // 2. Columna temperatura
    "ALTER TABLE prospectos ADD COLUMN temperatura ENUM('frio','templado','caliente') DEFAULT 'frio' AFTER estado",

    // 3. custom_fields ENUM
    "ALTER TABLE custom_fields MODIFY COLUMN entidad ENUM('cliente','propiedad','visita','prospecto') NOT NULL DEFAULT 'cliente'",

    // 4. Etapa 'nuevo_lead'
    "ALTER TABLE prospectos MODIFY COLUMN etapa ENUM('nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado') NOT NULL DEFAULT 'nuevo_lead'",

    // 5. Campos de propiedad completos
    "ALTER TABLE prospectos ADD COLUMN operacion ENUM('venta','alquiler','alquiler_opcion_compra','traspaso') DEFAULT NULL AFTER tipo_propiedad",
    "ALTER TABLE prospectos ADD COLUMN precio_comunidad DECIMAL(8,2) DEFAULT NULL AFTER precio_propietario",
    "ALTER TABLE prospectos ADD COLUMN superficie_construida DECIMAL(10,2) DEFAULT NULL AFTER superficie",
    "ALTER TABLE prospectos ADD COLUMN superficie_util DECIMAL(10,2) DEFAULT NULL AFTER superficie_construida",
    "ALTER TABLE prospectos ADD COLUMN superficie_parcela DECIMAL(10,2) DEFAULT NULL AFTER superficie_util",
    "ALTER TABLE prospectos ADD COLUMN banos TINYINT DEFAULT NULL AFTER habitaciones",
    "ALTER TABLE prospectos ADD COLUMN aseos TINYINT DEFAULT NULL AFTER banos",
    "ALTER TABLE prospectos ADD COLUMN planta VARCHAR(20) DEFAULT NULL AFTER aseos",
    "ALTER TABLE prospectos ADD COLUMN ascensor TINYINT(1) DEFAULT 0 AFTER planta",
    "ALTER TABLE prospectos ADD COLUMN garaje_incluido TINYINT(1) DEFAULT 0 AFTER ascensor",
    "ALTER TABLE prospectos ADD COLUMN trastero_incluido TINYINT(1) DEFAULT 0 AFTER garaje_incluido",
    "ALTER TABLE prospectos ADD COLUMN terraza TINYINT(1) DEFAULT 0 AFTER trastero_incluido",
    "ALTER TABLE prospectos ADD COLUMN balcon TINYINT(1) DEFAULT 0 AFTER terraza",
    "ALTER TABLE prospectos ADD COLUMN jardin TINYINT(1) DEFAULT 0 AFTER balcon",
    "ALTER TABLE prospectos ADD COLUMN piscina TINYINT(1) DEFAULT 0 AFTER jardin",
    "ALTER TABLE prospectos ADD COLUMN aire_acondicionado TINYINT(1) DEFAULT 0 AFTER piscina",
    "ALTER TABLE prospectos ADD COLUMN calefaccion VARCHAR(50) DEFAULT NULL AFTER aire_acondicionado",
    "ALTER TABLE prospectos ADD COLUMN orientacion ENUM('norte','sur','este','oeste','noreste','noroeste','sureste','suroeste') DEFAULT NULL AFTER calefaccion",
    "ALTER TABLE prospectos ADD COLUMN antiguedad INT DEFAULT NULL AFTER orientacion",
    "ALTER TABLE prospectos ADD COLUMN estado_conservacion ENUM('a_estrenar','buen_estado','a_reformar','en_construccion') DEFAULT NULL AFTER antiguedad",
    "ALTER TABLE prospectos ADD COLUMN certificacion_energetica ENUM('A','B','C','D','E','F','G','en_tramite','exento') DEFAULT NULL AFTER estado_conservacion",
    "ALTER TABLE prospectos ADD COLUMN referencia_catastral VARCHAR(25) DEFAULT NULL AFTER certificacion_energetica",
    "ALTER TABLE prospectos ADD COLUMN numero VARCHAR(10) DEFAULT NULL AFTER direccion",
    "ALTER TABLE prospectos ADD COLUMN piso_puerta VARCHAR(20) DEFAULT NULL AFTER numero",
    "ALTER TABLE prospectos ADD COLUMN comunidad_autonoma VARCHAR(100) DEFAULT NULL AFTER provincia",
    "ALTER TABLE prospectos ADD COLUMN descripcion TEXT DEFAULT NULL AFTER enlace",
    "ALTER TABLE prospectos ADD COLUMN descripcion_interna TEXT DEFAULT NULL AFTER descripcion",
];

$success = true;
$messages = [];

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $matches)) {
            $messages[] = "✅ Tabla '{$matches[1]}' creada.";
        } elseif (preg_match('/ADD COLUMN (\w+)/', $sql, $m)) {
            $messages[] = "✅ Columna '{$m[1]}' añadida.";
        } elseif (strpos($sql, 'MODIFY') !== false) {
            $messages[] = "✅ ENUM actualizado.";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            preg_match('/ADD COLUMN (\w+)/', $sql, $m);
            $messages[] = "ℹ️ Columna '{$m[1]}' ya existe.";
        } else {
            $success = false;
            $messages[] = "❌ ERROR: " . $e->getMessage();
        }
    }
}

// Migrar historial existente
try {
    $stmt = $db->query("SELECT id, historial_contactos, agente_id FROM prospectos WHERE historial_contactos IS NOT NULL AND historial_contactos != ''");
    $migrados = 0;
    while ($row = $stmt->fetch()) {
        $lineas = preg_split('/\r?\n/', trim($row['historial_contactos']));
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (empty($linea)) continue;
            $userId = $row['agente_id'] ?: 1;
            $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?, ?, ?, 'nota')")
               ->execute([$row['id'], $userId, $linea]);
            $migrados++;
        }
    }
    if ($migrados > 0) $messages[] = "✅ Migradas $migrados entradas de historial.";
} catch (Exception $e) {
    $messages[] = "⚠️ Migración historial: " . $e->getMessage();
}

if (php_sapi_name() === 'cli') {
    foreach ($messages as $msg) echo $msg . PHP_EOL;
    echo $success ? "\nCompletado.\n" : "\nCompletado con errores.\n";
    exit($success ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalación Mejoras CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-database-add"></i> Instalación Mejoras CRM v1.3</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= strpos($msg, '❌') !== false ? 'danger' : (strpos($msg, '⚠️') !== false ? 'warning' : 'success') ?> py-2 mb-1">
                                <?= htmlspecialchars($msg) ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($success): ?>
                            <a href="<?= APP_URL ?>/index.php" class="btn btn-primary mt-3">Ir al Dashboard</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
