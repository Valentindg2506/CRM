<?php
/**
 * Instalador / migrador de tablas para el módulo de WhatsApp (Meta Cloud API)
 * Ejecutar una sola vez: php install_whatsapp.php o acceder via navegador
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

$queries = [
    // Tabla de configuración (Meta Cloud API)
    "CREATE TABLE IF NOT EXISTS whatsapp_config (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        access_token        TEXT,
        phone_number_id     VARCHAR(80),
        business_account_id VARCHAR(80),
        webhook_verify_token VARCHAR(120),
        phone_display       VARCHAR(30),
        activo              TINYINT(1) DEFAULT 1,
        updated_by          INT,
        updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Tabla de mensajes
    "CREATE TABLE IF NOT EXISTS whatsapp_mensajes (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id    INT NULL,
        telefono      VARCHAR(30) NOT NULL,
        direccion     ENUM('entrante','saliente') NOT NULL,
        mensaje       TEXT NOT NULL,
        tipo          ENUM('text','image','document','audio','video','template') DEFAULT 'text',
        wa_message_id VARCHAR(120) NULL,
        estado        ENUM('enviado','entregado','leido','fallido','recibido') DEFAULT 'enviado',
        created_by    INT NULL,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
        INDEX idx_telefono  (telefono),
        INDEX idx_cliente   (cliente_id),
        INDEX idx_fecha     (created_at),
        INDEX idx_wa_msg_id (wa_message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

// Migraciones para instalaciones existentes
$migrations = [
    "ALTER TABLE whatsapp_config ADD COLUMN IF NOT EXISTS business_account_id VARCHAR(80) AFTER phone_number_id",
    "ALTER TABLE whatsapp_config ADD COLUMN IF NOT EXISTS phone_display VARCHAR(30) AFTER business_account_id",
];

$success  = true;
$messages = [];

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
        $messages[] = ['ok', "Tabla '{$m[1]}' lista."];
    } catch (PDOException $e) {
        $success    = false;
        $messages[] = ['error', "Error: " . $e->getMessage()];
    }
}

foreach ($migrations as $sql) {
    try {
        $db->exec($sql);
    } catch (PDOException $e) {
        // Ignorar si la columna ya existe (MySQL < 8 no soporta IF NOT EXISTS en ALTER)
    }
}

if (php_sapi_name() === 'cli') {
    foreach ($messages as [$type, $msg]) echo ($type === 'error' ? '✗ ' : '✓ ') . $msg . PHP_EOL;
    echo $success ? "\nInstalación completada.\n" : "\nInstalación con errores.\n";
    exit($success ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalación WhatsApp Meta API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-sm border-0">
                <div class="card-header" style="background:#25D366;">
                    <h5 class="mb-0 text-white"><i class="bi bi-whatsapp"></i> Instalación — WhatsApp Meta Cloud API</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($messages as [$type, $msg]): ?>
                        <div class="alert alert-<?= $type === 'error' ? 'danger' : 'success' ?> py-2 mb-2">
                            <i class="bi bi-<?= $type === 'error' ? 'x-circle' : 'check-circle' ?>"></i> <?= htmlspecialchars($msg) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle"></i> <strong>Instalación completada.</strong>
                            Ahora configura tus credenciales de Meta.
                        </div>
                        <div class="mt-3">
                            <a href="<?= APP_URL ?>/modules/whatsapp/config.php" class="btn btn-success me-2">
                                <i class="bi bi-gear"></i> Ir a Configuración
                            </a>
                            <a href="<?= APP_URL ?>/modules/whatsapp/index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-whatsapp"></i> Ir a WhatsApp
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
