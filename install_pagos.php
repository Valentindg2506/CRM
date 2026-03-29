<?php
/**
 * Instalador del modulo de Pagos/Facturacion
 * Ejecutar una sola vez para crear las tablas necesarias
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDB();

    // ========================================
    // TABLA: facturas
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `facturas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `numero` VARCHAR(20) NOT NULL UNIQUE,
        `cliente_id` INT DEFAULT NULL,
        `propiedad_id` INT DEFAULT NULL,
        `concepto` VARCHAR(300) NOT NULL,
        `lineas` JSON NOT NULL COMMENT '[{\"descripcion\":\"...\",\"cantidad\":1,\"precio_unitario\":100,\"iva\":21}]',
        `subtotal` DECIMAL(12,2) DEFAULT 0,
        `iva_total` DECIMAL(12,2) DEFAULT 0,
        `total` DECIMAL(12,2) DEFAULT 0,
        `estado` ENUM('borrador','enviada','pagada','vencida','cancelada') DEFAULT 'borrador',
        `fecha_emision` DATE NOT NULL,
        `fecha_vencimiento` DATE DEFAULT NULL,
        `notas` TEXT,
        `metodo_pago` VARCHAR(50) DEFAULT '',
        `stripe_payment_id` VARCHAR(200) DEFAULT NULL,
        `token_pago` VARCHAR(64) DEFAULT NULL,
        `fecha_pago` DATETIME DEFAULT NULL,
        `usuario_id` INT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "<p style='color:green;'>Tabla <strong>facturas</strong> creada correctamente.</p>";

    // ========================================
    // TABLA: configuracion_pagos
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `configuracion_pagos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `empresa_nombre` VARCHAR(200) DEFAULT '',
        `empresa_cif` VARCHAR(20) DEFAULT '',
        `empresa_direccion` TEXT,
        `empresa_email` VARCHAR(200) DEFAULT '',
        `empresa_telefono` VARCHAR(20) DEFAULT '',
        `empresa_logo_url` VARCHAR(500) DEFAULT '',
        `stripe_public_key` VARCHAR(200) DEFAULT '',
        `stripe_secret_key` VARCHAR(200) DEFAULT '',
        `stripe_webhook_secret` VARCHAR(200) DEFAULT '',
        `moneda` VARCHAR(3) DEFAULT 'EUR',
        `iva_defecto` DECIMAL(4,2) DEFAULT 21.00,
        `prefijo_factura` VARCHAR(10) DEFAULT 'FAC-',
        `siguiente_numero` INT DEFAULT 1,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "<p style='color:green;'>Tabla <strong>configuracion_pagos</strong> creada correctamente.</p>";

    // Insertar registro por defecto
    $pdo->exec("INSERT IGNORE INTO `configuracion_pagos` (`id`) VALUES (1)");

    echo "<p style='color:green;'>Registro de configuracion por defecto insertado.</p>";

    echo "<hr>";
    echo "<h3 style='color:green;'>Modulo de Pagos instalado correctamente!</h3>";
    echo "<p><a href='modules/pagos/index.php'>Ir al modulo de Pagos</a> | <a href='modules/pagos/config.php'>Configurar Pagos</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
