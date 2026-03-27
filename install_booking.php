<?php
/**
 * Instalador de tablas para el modulo de Booking (Reservas publicas)
 * Ejecutar una sola vez: php install_booking.php o acceder via navegador
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

$queries = [
    // Tabla de configuracion de booking
    "CREATE TABLE IF NOT EXISTS booking_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(200) DEFAULT 'Reservar Cita',
        descripcion TEXT,
        duracion_minutos INT DEFAULT 30,
        horario_inicio TIME DEFAULT '09:00:00',
        horario_fin TIME DEFAULT '18:00:00',
        dias_disponibles VARCHAR(50) DEFAULT '1,2,3,4,5',
        dias_anticipacion INT DEFAULT 30,
        agente_id INT,
        activo TINYINT(1) DEFAULT 1,
        color_primario VARCHAR(7) DEFAULT '#10b981',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (agente_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Tabla de reservas
    "CREATE TABLE IF NOT EXISTS booking_reservas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_config_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        telefono VARCHAR(20),
        fecha DATE NOT NULL,
        hora TIME NOT NULL,
        notas TEXT,
        estado ENUM('pendiente','confirmada','cancelada','completada') DEFAULT 'pendiente',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_config_id) REFERENCES booking_config(id) ON DELETE CASCADE
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
    <title>Instalacion Booking - InmoCRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-database-add"></i> Instalacion - Modulo Booking</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= strpos($msg, 'ERROR') !== false ? 'danger' : 'success' ?> py-2">
                                <?= htmlspecialchars($msg) ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-info">
                                <strong>Instalacion completada.</strong> Ya puedes usar el modulo de Booking.
                            </div>
                            <a href="<?= APP_URL ?>/modules/calendario/booking_config.php" class="btn btn-primary">Ir a Configuracion de Booking</a>
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
