<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS social_cuentas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plataforma ENUM('facebook','instagram','google_business','linkedin','twitter') NOT NULL,
        nombre VARCHAR(200) NOT NULL,
        access_token TEXT,
        page_id VARCHAR(100) DEFAULT '',
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS social_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cuenta_id INT DEFAULT NULL,
        plataformas JSON DEFAULT '[]',
        contenido TEXT NOT NULL,
        imagen_url VARCHAR(500) DEFAULT '',
        enlace VARCHAR(500) DEFAULT '',
        estado ENUM('borrador','programado','publicado','error') DEFAULT 'borrador',
        programado_para DATETIME DEFAULT NULL,
        publicado_at DATETIME DEFAULT NULL,
        respuesta_api TEXT,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS blog_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(300) NOT NULL,
        slug VARCHAR(300) NOT NULL UNIQUE,
        extracto TEXT,
        contenido LONGTEXT,
        imagen_destacada VARCHAR(500) DEFAULT '',
        categoria VARCHAR(100) DEFAULT '',
        tags VARCHAR(500) DEFAULT '',
        meta_title VARCHAR(200) DEFAULT '',
        meta_description VARCHAR(300) DEFAULT '',
        estado ENUM('borrador','publicado','archivado') DEFAULT 'borrador',
        visitas INT DEFAULT 0,
        propiedad_id INT DEFAULT NULL,
        usuario_id INT,
        publicado_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS medios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(300) NOT NULL,
        archivo VARCHAR(500) NOT NULL,
        tipo ENUM('imagen','documento','video','otro') DEFAULT 'imagen',
        mime_type VARCHAR(100) DEFAULT '',
        tamano INT DEFAULT 0,
        ancho INT DEFAULT NULL,
        alto INT DEFAULT NULL,
        carpeta VARCHAR(200) DEFAULT 'general',
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS contratos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(300) NOT NULL,
        cliente_id INT DEFAULT NULL,
        propiedad_id INT DEFAULT NULL,
        plantilla_id INT DEFAULT NULL,
        contenido LONGTEXT,
        estado ENUM('borrador','enviado','visto','firmado','rechazado','expirado') DEFAULT 'borrador',
        token VARCHAR(64) NOT NULL,
        firmado_at DATETIME DEFAULT NULL,
        firmado_ip VARCHAR(45) DEFAULT NULL,
        firma_imagen LONGTEXT DEFAULT NULL,
        firmante_nombre VARCHAR(200) DEFAULT '',
        fecha_expiracion DATE DEFAULT NULL,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS contrato_plantillas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        tipo ENUM('texto','pdf') NOT NULL DEFAULT 'texto',
        contenido LONGTEXT,
        archivo_path VARCHAR(255) DEFAULT NULL,
        archivo_nombre VARCHAR(255) DEFAULT NULL,
        categoria VARCHAR(100) DEFAULT 'general',
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "INSERT IGNORE INTO contrato_plantillas (id, nombre, contenido, categoria) VALUES
    (1, 'Contrato de Arras', '<h2>CONTRATO DE ARRAS</h2><p>En {{ciudad}}, a {{fecha}}</p><h3>REUNIDOS</h3><p>De una parte, <strong>{{empresa_nombre}}</strong>, con CIF {{empresa_cif}}...</p><p>De otra parte, <strong>{{cliente_nombre}}</strong>, con DNI/NIE {{cliente_dni}}...</p><h3>EXPONEN</h3><p>Que ambas partes han convenido la compraventa del inmueble sito en {{propiedad_direccion}}, referencia {{propiedad_referencia}}, por el precio de {{propiedad_precio}} euros...</p>', 'compraventa'),
    (2, 'Mandato de Venta', '<h2>MANDATO DE VENTA EN EXCLUSIVA</h2><p>En {{ciudad}}, a {{fecha}}</p><p>El propietario {{cliente_nombre}} encarga a {{empresa_nombre}} la venta del inmueble...</p>', 'mandato'),
    (3, 'Contrato de Alquiler', '<h2>CONTRATO DE ARRENDAMIENTO</h2><p>En {{ciudad}}, a {{fecha}}</p><h3>PARTES</h3><p>ARRENDADOR: {{empresa_nombre}}</p><p>ARRENDATARIO: {{cliente_nombre}}, DNI: {{cliente_dni}}</p><h3>CLAUSULAS</h3><p>1. El arrendador cede en arrendamiento al arrendatario la vivienda sita en {{propiedad_direccion}}...</p>', 'alquiler')"
];
$ok=true;$msgs=[];foreach($queries as $sql){try{$db->exec($sql);$msgs[]="OK";}catch(PDOException $e){$ok=false;$msgs[]="ERROR: ".$e->getMessage();}}
?><!DOCTYPE html><html><head><title>Install Social+Blog+Medios+Contratos</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - Social, Blog, Medios, Contratos</h4></div><div class="card-body"><?php foreach($msgs as $m):?><div class="alert alert-<?=strpos($m,'ERROR')!==false?'danger':'success'?> py-2"><?=htmlspecialchars($m)?></div><?php endforeach;?></div></div></div></body></html>
