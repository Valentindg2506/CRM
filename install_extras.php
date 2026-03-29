<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$queries = [
    "CREATE TABLE IF NOT EXISTS ab_tests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        tipo ENUM('email','landing') NOT NULL,
        estado ENUM('borrador','activo','completado') DEFAULT 'borrador',
        variante_a_id INT DEFAULT NULL,
        variante_b_id INT DEFAULT NULL,
        variante_a_config JSON DEFAULT NULL,
        variante_b_config JSON DEFAULT NULL,
        visitas_a INT DEFAULT 0,
        visitas_b INT DEFAULT 0,
        conversiones_a INT DEFAULT 0,
        conversiones_b INT DEFAULT 0,
        ganador ENUM('a','b','empate') DEFAULT NULL,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS ads_cuentas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plataforma ENUM('facebook','google') NOT NULL,
        nombre VARCHAR(200) NOT NULL,
        account_id VARCHAR(100) DEFAULT '',
        access_token TEXT,
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS ads_campanas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cuenta_id INT NOT NULL,
        external_id VARCHAR(100) DEFAULT '',
        nombre VARCHAR(300) NOT NULL,
        plataforma ENUM('facebook','google') NOT NULL,
        estado VARCHAR(50) DEFAULT 'active',
        presupuesto DECIMAL(10,2) DEFAULT 0,
        gasto DECIMAL(10,2) DEFAULT 0,
        impresiones INT DEFAULT 0,
        clicks INT DEFAULT 0,
        conversiones INT DEFAULT 0,
        cpl DECIMAL(10,2) DEFAULT 0,
        roas DECIMAL(10,2) DEFAULT 0,
        fecha_inicio DATE DEFAULT NULL,
        fecha_fin DATE DEFAULT NULL,
        last_sync DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cuenta_id) REFERENCES ads_cuentas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS cursos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(300) NOT NULL,
        slug VARCHAR(300) NOT NULL,
        descripcion TEXT,
        imagen VARCHAR(500) DEFAULT '',
        precio DECIMAL(10,2) DEFAULT 0,
        activo TINYINT(1) DEFAULT 1,
        acceso ENUM('publico','privado','pago') DEFAULT 'privado',
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS curso_lecciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        curso_id INT NOT NULL,
        titulo VARCHAR(300) NOT NULL,
        contenido LONGTEXT,
        tipo ENUM('texto','video','pdf','quiz') DEFAULT 'texto',
        video_url VARCHAR(500) DEFAULT '',
        orden INT DEFAULT 0,
        duracion_min INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS curso_matriculas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        curso_id INT NOT NULL,
        cliente_id INT NOT NULL,
        estado ENUM('activa','completada','cancelada') DEFAULT 'activa',
        progreso INT DEFAULT 0,
        leccion_actual INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS afiliados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        codigo VARCHAR(20) NOT NULL UNIQUE,
        nombre VARCHAR(200) NOT NULL,
        email VARCHAR(200) NOT NULL,
        comision_porcentaje DECIMAL(5,2) DEFAULT 10,
        total_referidos INT DEFAULT 0,
        total_comisiones DECIMAL(12,2) DEFAULT 0,
        saldo_pendiente DECIMAL(12,2) DEFAULT 0,
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS afiliado_referidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        afiliado_id INT NOT NULL,
        cliente_id INT DEFAULT NULL,
        ip VARCHAR(45),
        convertido TINYINT(1) DEFAULT 0,
        comision DECIMAL(10,2) DEFAULT 0,
        pagado TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (afiliado_id) REFERENCES afiliados(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS comunidad_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        canal VARCHAR(100) DEFAULT 'general',
        titulo VARCHAR(300) NOT NULL,
        contenido TEXT NOT NULL,
        tipo ENUM('discusion','pregunta','anuncio') DEFAULT 'discusion',
        fijado TINYINT(1) DEFAULT 0,
        likes INT DEFAULT 0,
        respuestas_count INT DEFAULT 0,
        cliente_id INT DEFAULT NULL,
        usuario_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS comunidad_respuestas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        contenido TEXT NOT NULL,
        likes INT DEFAULT 0,
        cliente_id INT DEFAULT NULL,
        usuario_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES comunidad_posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS ia_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proveedor ENUM('openai','anthropic') DEFAULT 'openai',
        api_key VARCHAR(200) DEFAULT '',
        modelo VARCHAR(100) DEFAULT 'gpt-3.5-turbo',
        prompt_sistema TEXT DEFAULT '',
        activo TINYINT(1) DEFAULT 0,
        max_tokens INT DEFAULT 500,
        temperatura DECIMAL(2,1) DEFAULT 0.7,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "INSERT IGNORE INTO ia_config (id, prompt_sistema) VALUES (1, 'Eres un asistente de una inmobiliaria en Espana. Responde preguntas sobre propiedades, visitas, y procesos de compra/alquiler de forma amable y profesional.')"
];
$ok=true;$msgs=[];foreach($queries as $sql){try{$db->exec($sql);$msgs[]="OK";}catch(PDOException $e){$ok=false;$msgs[]="ERROR: ".$e->getMessage();}}
?><!DOCTYPE html><html><head><title>Install Extras</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="card"><div class="card-header bg-success text-white"><h4 class="mb-0">Instalacion - A/B Testing, Ads, Cursos, Afiliados, Comunidad, IA</h4></div><div class="card-body"><?php foreach($msgs as $m):?><div class="alert alert-<?=strpos($m,'ERROR')!==false?'danger':'success'?> py-2 mb-1" style="font-size:.85rem"><?=htmlspecialchars($m)?></div><?php endforeach;?></div></div></div></body></html>
