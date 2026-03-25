<?php
/**
 * Instalador del CRM Inmobiliario
 * Ejecutar una sola vez para crear la base de datos y tablas
 */

// Mostrar todos los errores durante la instalacion
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'crm_inmobiliario';

try {
    // Conectar sin seleccionar base de datos
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Crear base de datos
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // ========================================
    // TABLA: usuarios
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `usuarios` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre` VARCHAR(100) NOT NULL,
        `apellidos` VARCHAR(150) NOT NULL,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `telefono` VARCHAR(20) DEFAULT NULL,
        `rol` ENUM('admin','agente') NOT NULL DEFAULT 'agente',
        `avatar` VARCHAR(255) DEFAULT NULL,
        `activo` TINYINT(1) NOT NULL DEFAULT 1,
        `ultimo_acceso` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // TABLA: propiedades (inmuebles)
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `propiedades` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `referencia` VARCHAR(50) NOT NULL UNIQUE,
        `titulo` VARCHAR(255) NOT NULL,
        `tipo` ENUM('piso','casa','chalet','adosado','atico','duplex','estudio','local','oficina','nave','terreno','garaje','trastero','edificio','otro') NOT NULL,
        `operacion` ENUM('venta','alquiler','alquiler_opcion_compra','traspaso') NOT NULL,
        `estado` ENUM('disponible','reservado','vendido','alquilado','retirado') NOT NULL DEFAULT 'disponible',
        `precio` DECIMAL(12,2) NOT NULL,
        `precio_comunidad` DECIMAL(8,2) DEFAULT NULL,
        `superficie_construida` DECIMAL(10,2) DEFAULT NULL,
        `superficie_util` DECIMAL(10,2) DEFAULT NULL,
        `superficie_parcela` DECIMAL(10,2) DEFAULT NULL,
        `habitaciones` TINYINT DEFAULT NULL,
        `banos` TINYINT DEFAULT NULL,
        `aseos` TINYINT DEFAULT NULL,
        `planta` VARCHAR(20) DEFAULT NULL,
        `ascensor` TINYINT(1) DEFAULT 0,
        `garaje_incluido` TINYINT(1) DEFAULT 0,
        `trastero_incluido` TINYINT(1) DEFAULT 0,
        `terraza` TINYINT(1) DEFAULT 0,
        `balcon` TINYINT(1) DEFAULT 0,
        `jardin` TINYINT(1) DEFAULT 0,
        `piscina` TINYINT(1) DEFAULT 0,
        `aire_acondicionado` TINYINT(1) DEFAULT 0,
        `calefaccion` VARCHAR(50) DEFAULT NULL,
        `orientacion` ENUM('norte','sur','este','oeste','noreste','noroeste','sureste','suroeste') DEFAULT NULL,
        `antiguedad` INT DEFAULT NULL,
        `estado_conservacion` ENUM('a_estrenar','buen_estado','a_reformar','en_construccion') DEFAULT NULL,
        `certificacion_energetica` ENUM('A','B','C','D','E','F','G','en_tramite','exento') DEFAULT NULL,
        `referencia_catastral` VARCHAR(25) DEFAULT NULL,
        `direccion` VARCHAR(255) DEFAULT NULL,
        `numero` VARCHAR(10) DEFAULT NULL,
        `piso_puerta` VARCHAR(20) DEFAULT NULL,
        `codigo_postal` VARCHAR(10) DEFAULT NULL,
        `localidad` VARCHAR(100) DEFAULT NULL,
        `provincia` VARCHAR(100) DEFAULT NULL,
        `comunidad_autonoma` VARCHAR(100) DEFAULT NULL,
        `latitud` DECIMAL(10,8) DEFAULT NULL,
        `longitud` DECIMAL(11,8) DEFAULT NULL,
        `descripcion` TEXT DEFAULT NULL,
        `descripcion_interna` TEXT DEFAULT NULL,
        `propietario_id` INT DEFAULT NULL,
        `agente_id` INT DEFAULT NULL,
        `fecha_captacion` DATE DEFAULT NULL,
        `fecha_disponibilidad` DATE DEFAULT NULL,
        `visitas_count` INT DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_tipo` (`tipo`),
        INDEX `idx_operacion` (`operacion`),
        INDEX `idx_estado` (`estado`),
        INDEX `idx_precio` (`precio`),
        INDEX `idx_provincia` (`provincia`),
        INDEX `idx_localidad` (`localidad`),
        INDEX `idx_agente` (`agente_id`),
        INDEX `idx_propietario` (`propietario_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // TABLA: propiedad_fotos
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `propiedad_fotos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `propiedad_id` INT NOT NULL,
        `archivo` VARCHAR(255) NOT NULL,
        `titulo` VARCHAR(255) DEFAULT NULL,
        `orden` INT DEFAULT 0,
        `es_principal` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // TABLA: clientes
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `clientes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre` VARCHAR(100) NOT NULL,
        `apellidos` VARCHAR(150) DEFAULT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `telefono` VARCHAR(20) DEFAULT NULL,
        `telefono2` VARCHAR(20) DEFAULT NULL,
        `dni_nie_cif` VARCHAR(20) DEFAULT NULL,
        `tipo` SET('comprador','vendedor','inquilino','propietario','inversor') NOT NULL,
        `origen` ENUM('web','telefono','oficina','referido','portal','otro') DEFAULT 'otro',
        `direccion` VARCHAR(255) DEFAULT NULL,
        `codigo_postal` VARCHAR(10) DEFAULT NULL,
        `localidad` VARCHAR(100) DEFAULT NULL,
        `provincia` VARCHAR(100) DEFAULT NULL,
        `notas` TEXT DEFAULT NULL,
        `presupuesto_min` DECIMAL(12,2) DEFAULT NULL,
        `presupuesto_max` DECIMAL(12,2) DEFAULT NULL,
        `zona_interes` VARCHAR(255) DEFAULT NULL,
        `tipo_inmueble_interes` VARCHAR(255) DEFAULT NULL,
        `habitaciones_min` TINYINT DEFAULT NULL,
        `superficie_min` DECIMAL(10,2) DEFAULT NULL,
        `operacion_interes` ENUM('venta','alquiler','ambas') DEFAULT NULL,
        `agente_id` INT DEFAULT NULL,
        `activo` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_tipo` (`tipo`),
        INDEX `idx_agente` (`agente_id`),
        INDEX `idx_provincia` (`provincia`),
        INDEX `idx_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // TABLA: visitas
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `visitas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `propiedad_id` INT NOT NULL,
        `cliente_id` INT NOT NULL,
        `agente_id` INT NOT NULL,
        `fecha` DATE NOT NULL,
        `hora` TIME NOT NULL,
        `duracion_minutos` INT DEFAULT 30,
        `estado` ENUM('programada','realizada','cancelada','no_presentado') NOT NULL DEFAULT 'programada',
        `valoracion` TINYINT DEFAULT NULL,
        `comentarios` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE,
        INDEX `idx_fecha` (`fecha`),
        INDEX `idx_agente` (`agente_id`),
        INDEX `idx_estado` (`estado`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // TABLA: tareas
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tareas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `titulo` VARCHAR(255) NOT NULL,
        `descripcion` TEXT DEFAULT NULL,
        `tipo` ENUM('llamada','email','reunion','visita','gestion','documentacion','otro') NOT NULL DEFAULT 'otro',
        `prioridad` ENUM('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
        `estado` ENUM('pendiente','en_progreso','completada','cancelada') NOT NULL DEFAULT 'pendiente',
        `fecha_vencimiento` DATETIME DEFAULT NULL,
        `fecha_completada` DATETIME DEFAULT NULL,
        `asignado_a` INT DEFAULT NULL,
        `creado_por` INT NOT NULL,
        `propiedad_id` INT DEFAULT NULL,
        `cliente_id` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_estado` (`estado`),
        INDEX `idx_prioridad` (`prioridad`),
        INDEX `idx_asignado` (`asignado_a`),
        INDEX `idx_fecha_vencimiento` (`fecha_vencimiento`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // TABLA: documentos
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `documentos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre` VARCHAR(255) NOT NULL,
        `tipo` ENUM('contrato_arras','contrato_compraventa','contrato_alquiler','escritura','nota_simple','certificado_energetico','cedula_habitabilidad','ite','licencia','factura','presupuesto','mandato','ficha_cliente','otro') NOT NULL,
        `archivo` VARCHAR(255) NOT NULL,
        `tamano` INT DEFAULT NULL,
        `propiedad_id` INT DEFAULT NULL,
        `cliente_id` INT DEFAULT NULL,
        `subido_por` INT NOT NULL,
        `notas` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_tipo` (`tipo`),
        INDEX `idx_propiedad` (`propiedad_id`),
        INDEX `idx_cliente` (`cliente_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // TABLA: finanzas (comisiones y transacciones)
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `finanzas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tipo` ENUM('comision_venta','comision_alquiler','honorarios','gasto','ingreso_otro') NOT NULL,
        `concepto` VARCHAR(255) NOT NULL,
        `importe` DECIMAL(12,2) NOT NULL,
        `iva` DECIMAL(5,2) DEFAULT 21.00,
        `importe_total` DECIMAL(12,2) NOT NULL,
        `fecha` DATE NOT NULL,
        `estado` ENUM('pendiente','cobrado','pagado','anulado') NOT NULL DEFAULT 'pendiente',
        `propiedad_id` INT DEFAULT NULL,
        `cliente_id` INT DEFAULT NULL,
        `agente_id` INT DEFAULT NULL,
        `factura_numero` VARCHAR(50) DEFAULT NULL,
        `notas` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_tipo` (`tipo`),
        INDEX `idx_estado` (`estado`),
        INDEX `idx_fecha` (`fecha`),
        INDEX `idx_agente` (`agente_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // TABLA: portales (publicacion en portales)
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `portales` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre` VARCHAR(100) NOT NULL UNIQUE,
        `url` VARCHAR(255) DEFAULT NULL,
        `activo` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `propiedad_portales` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `propiedad_id` INT NOT NULL,
        `portal_id` INT NOT NULL,
        `estado` ENUM('publicado','pendiente','retirado','error') NOT NULL DEFAULT 'pendiente',
        `url_publicacion` VARCHAR(500) DEFAULT NULL,
        `fecha_publicacion` DATE DEFAULT NULL,
        `fecha_actualizacion` DATE DEFAULT NULL,
        `notas` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`portal_id`) REFERENCES `portales`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `uk_propiedad_portal` (`propiedad_id`, `portal_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // TABLA: actividad_log
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `actividad_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `usuario_id` INT NOT NULL,
        `accion` VARCHAR(50) NOT NULL,
        `entidad` VARCHAR(50) NOT NULL,
        `entidad_id` INT DEFAULT NULL,
        `detalles` TEXT DEFAULT NULL,
        `ip` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_usuario` (`usuario_id`),
        INDEX `idx_entidad` (`entidad`, `entidad_id`),
        INDEX `idx_fecha` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // TABLA: notificaciones
    // ========================================
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notificaciones` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `usuario_id` INT NOT NULL,
        `titulo` VARCHAR(255) NOT NULL,
        `mensaje` TEXT DEFAULT NULL,
        `tipo` ENUM('info','exito','aviso','error') DEFAULT 'info',
        `enlace` VARCHAR(500) DEFAULT NULL,
        `leida` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_usuario` (`usuario_id`),
        INDEX `idx_leida` (`leida`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ========================================
    // DATOS INICIALES
    // ========================================

    // Insertar portales inmobiliarios españoles
    $portales = [
        ['Idealista', 'https://www.idealista.com'],
        ['Fotocasa', 'https://www.fotocasa.es'],
        ['Habitaclia', 'https://www.habitaclia.com'],
        ['Pisos.com', 'https://www.pisos.com'],
        ['Yaencontre', 'https://www.yaencontre.com'],
        ['Milanuncios', 'https://www.milanuncios.com'],
        ['Tucasa.com', 'https://www.tucasa.com'],
        ['Hogaria', 'https://www.hogaria.net'],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `portales` (`nombre`, `url`) VALUES (?, ?)");
    foreach ($portales as $portal) {
        $stmt->execute($portal);
    }

    // Insertar usuario admin por defecto
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO `usuarios` (`nombre`, `apellidos`, `email`, `password`, `rol`) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Admin', 'Sistema', 'admin@inmocrm.es', $adminPass, 'admin']);

    echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Instalacion - InmoCRM</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
    <div class='container mt-5'>
        <div class='row justify-content-center'>
            <div class='col-md-8'>
                <div class='card shadow'>
                    <div class='card-body text-center p-5'>
                        <h1 class='text-success mb-4'>&#10004; Instalacion Completada</h1>
                        <p class='lead'>La base de datos <strong>$dbname</strong> se ha creado correctamente con todas las tablas.</p>
                        <hr>
                        <h5>Datos de acceso:</h5>
                        <p><strong>Email:</strong> admin@inmocrm.es<br>
                        <strong>Password:</strong> admin123</p>
                        <div class='alert alert-warning'>
                            <strong>Importante:</strong> Cambia la contraseña del administrador despues del primer acceso.
                            Tambien elimina o protege este archivo <code>install.php</code> despues de la instalacion.
                        </div>
                        <a href='index.php' class='btn btn-primary btn-lg mt-3'>Acceder al CRM</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";

} catch (PDOException $e) {
    echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Error de Instalacion</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
    <div class='container mt-5'>
        <div class='row justify-content-center'>
            <div class='col-md-8'>
                <div class='card shadow border-danger'>
                    <div class='card-body text-center p-5'>
                        <h1 class='text-danger mb-4'>&#10008; Error de Instalacion</h1>
                        <p class='lead'>No se pudo completar la instalacion.</p>
                        <div class='alert alert-danger'>" . htmlspecialchars($e->getMessage()) . "</div>
                        <p>Verifica la configuracion de la base de datos en <code>config/database.php</code></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";
}
