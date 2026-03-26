<?php
/**
 * Instalador de tablas para el modulo de Email
 * Ejecutar una sola vez: php install_email.php o acceder via navegador
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

$queries = [
    // Tabla de cuentas de email
    "CREATE TABLE IF NOT EXISTS email_cuentas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        nombre_display VARCHAR(100),
        smtp_host VARCHAR(255),
        smtp_port INT DEFAULT 587,
        smtp_user VARCHAR(255),
        smtp_pass VARCHAR(255),
        imap_host VARCHAR(255),
        imap_port INT DEFAULT 993,
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Tabla de mensajes de email
    "CREATE TABLE IF NOT EXISTS email_mensajes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cuenta_id INT NOT NULL,
        message_id VARCHAR(255) NULL,
        direccion ENUM('entrante','saliente') NOT NULL,
        de_email VARCHAR(255) NOT NULL,
        para_email VARCHAR(255) NOT NULL,
        cc VARCHAR(500) NULL,
        asunto VARCHAR(500) NOT NULL,
        cuerpo TEXT NOT NULL,
        cuerpo_html TEXT NULL,
        cliente_id INT NULL,
        propiedad_id INT NULL,
        leido TINYINT(1) DEFAULT 0,
        destacado TINYINT(1) DEFAULT 0,
        carpeta ENUM('inbox','sent','draft','trash') DEFAULT 'inbox',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cuenta_id) REFERENCES email_cuentas(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
        INDEX idx_cuenta (cuenta_id),
        INDEX idx_carpeta (carpeta),
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
    <title>Instalacion Email - InmoCRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-database-add"></i> Instalacion - Modulo Email</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($messages as $msg): ?>
                            <div class="alert alert-<?= strpos($msg, 'ERROR') !== false ? 'danger' : 'success' ?> py-2">
                                <?= htmlspecialchars($msg) ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-info">
                                <strong>Instalacion completada.</strong> Ya puedes usar el modulo de Email.
                            </div>
                            <a href="<?= APP_URL ?>/modules/email/index.php" class="btn btn-primary">Ir a Email</a>
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
