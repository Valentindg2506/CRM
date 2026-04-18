-- ============================================================
-- TinoProp CRM — Esquema completo de base de datos
-- Copyright (c) 2024-2026 Valentín De Gennaro. Todos los derechos reservados.
-- ============================================================
-- Ejecutar en una BD MySQL 8 / MariaDB 10.6+ vacía.
-- Juego de caracteres: utf8mb4 / utf8mb4_unicode_ci
-- Zona horaria: Europe/Madrid
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS `usuarios` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `propiedades` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `propiedad_fotos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `propiedad_id` INT NOT NULL,
        `archivo` VARCHAR(255) NOT NULL,
        `titulo` VARCHAR(255) DEFAULT NULL,
        `orden` INT DEFAULT 0,
        `es_principal` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clientes` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visitas` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tareas` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documentos` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `finanzas` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portales` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre` VARCHAR(100) NOT NULL UNIQUE,
        `url` VARCHAR(255) DEFAULT NULL,
        `activo` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `propiedad_portales` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `actividad_log` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notificaciones` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prospectos (
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
        fecha_publicacion_propiedad DATE DEFAULT NULL,
        fecha_contacto DATE DEFAULT NULL,
        hora_contacto TIME DEFAULT NULL,
        mejor_horario_contacto VARCHAR(100) DEFAULT NULL,
        fecha_proximo_contacto DATE DEFAULT NULL,
        estado ENUM('nuevo_lead','contactado','en_seguimiento','visita_programada','captado','descartado') NOT NULL DEFAULT 'nuevo_lead',
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
        INDEX idx_fecha_publicacion_propiedad (fecha_publicacion_propiedad),
        INDEX idx_fecha_contacto (fecha_contacto),
        INDEX idx_hora_contacto (hora_contacto),
        INDEX idx_fecha_proximo (fecha_proximo_contacto),
        INDEX idx_referencia (referencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_ajustes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        clave VARCHAR(100) NOT NULL,
        valor TEXT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_usuario_clave (usuario_id, clave),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historial_prospectos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prospecto_id INT NOT NULL,
        usuario_id INT NOT NULL,
        contenido TEXT NOT NULL,
        tipo ENUM('llamada','email','visita','nota','whatsapp','otro') NOT NULL DEFAULT 'nota',
        fecha_evento DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (prospecto_id) REFERENCES prospectos(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
        INDEX idx_prospecto (prospecto_id),
        INDEX idx_fecha_evento (fecha_evento),
        INDEX idx_fecha (created_at DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automatizaciones (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automatizacion_acciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        automatizacion_id INT NOT NULL,
        orden INT NOT NULL DEFAULT 0,
        tipo ENUM('enviar_email','enviar_whatsapp','crear_tarea','cambiar_estado_propiedad','asignar_agente','mover_pipeline','notificar','esperar') NOT NULL,
        configuracion JSON NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (automatizacion_id) REFERENCES automatizaciones(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automatizacion_log (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_config (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_reservas (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendario_eventos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        descripcion TEXT NULL,
        tipo ENUM('visita','reunion','llamada','tarea','personal','otro') DEFAULT 'otro',
        color VARCHAR(7) DEFAULT '#10b981',
        fecha_inicio DATETIME NOT NULL,
        fecha_fin DATETIME NOT NULL,
        todo_dia TINYINT(1) DEFAULT 0,
        ubicacion VARCHAR(255) NULL,
        propiedad_id INT NULL,
        cliente_id INT NULL,
        visita_id INT NULL,
        usuario_id INT NOT NULL,
        recordatorio_minutos INT NULL,
        recurrente ENUM('ninguno','diario','semanal','mensual') DEFAULT 'ninguno',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE SET NULL,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_fecha (fecha_inicio, fecha_fin),
        INDEX idx_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campanas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT,
        tipo ENUM('email','sms','mixta') DEFAULT 'email',
        estado ENUM('borrador','activa','pausada','completada') DEFAULT 'borrador',
        filtro_tags JSON DEFAULT NULL,
        total_contactos INT DEFAULT 0,
        enviados INT DEFAULT 0,
        abiertos INT DEFAULT 0,
        clicks INT DEFAULT 0,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campana_pasos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campana_id INT NOT NULL,
        orden INT DEFAULT 1,
        tipo ENUM('email','sms','esperar','condicion') DEFAULT 'email',
        asunto VARCHAR(300) DEFAULT '',
        contenido TEXT,
        plantilla_id INT DEFAULT NULL,
        esperar_minutos INT DEFAULT 0,
        condicion_campo VARCHAR(100) DEFAULT '',
        condicion_valor VARCHAR(200) DEFAULT '',
        enviados INT DEFAULT 0,
        abiertos INT DEFAULT 0,
        clicks INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campana_contactos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campana_id INT NOT NULL,
        cliente_id INT NOT NULL,
        paso_actual INT DEFAULT 0,
        estado ENUM('pendiente','activo','completado','cancelado') DEFAULT 'pendiente',
        proximo_envio DATETIME DEFAULT NULL,
        datos JSON DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (campana_id) REFERENCES campanas(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_fields (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entidad ENUM('cliente','propiedad','visita') NOT NULL DEFAULT 'cliente',
        nombre VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        tipo ENUM('texto','numero','fecha','select','checkbox','textarea','email','telefono') NOT NULL DEFAULT 'texto',
        opciones TEXT COMMENT 'Opciones separadas por coma para tipo select',
        obligatorio TINYINT(1) DEFAULT 0,
        orden INT DEFAULT 0,
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_slug_entidad (slug, entidad)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_field_values (
        id INT AUTO_INCREMENT PRIMARY KEY,
        field_id INT NOT NULL,
        entidad_id INT NOT NULL,
        valor TEXT,
        UNIQUE KEY unique_field_entity (field_id, entidad_id),
        FOREIGN KEY (field_id) REFERENCES custom_fields(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_cuentas (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_mensajes (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_plantillas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        asunto VARCHAR(300) NOT NULL,
        contenido TEXT NOT NULL,
        categoria ENUM('general','seguimiento','bienvenida','oferta','visita','factura','recordatorio','personalizada') DEFAULT 'general',
        activa TINYINT(1) DEFAULT 1,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS encuestas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT,
        preguntas JSON NOT NULL DEFAULT '[]',
        logica_condicional JSON DEFAULT NULL,
        color_primario VARCHAR(7) DEFAULT '#10b981',
        activo TINYINT(1) DEFAULT 1,
        crear_cliente TINYINT(1) DEFAULT 0,
        total_respuestas INT DEFAULT 0,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS encuesta_respuestas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        encuesta_id INT NOT NULL,
        cliente_id INT DEFAULT NULL,
        respuestas JSON NOT NULL,
        puntuacion DECIMAL(5,2) DEFAULT 0,
        ip VARCHAR(45),
        completada TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ab_tests (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ads_cuentas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plataforma ENUM('facebook','google') NOT NULL,
        nombre VARCHAR(200) NOT NULL,
        account_id VARCHAR(100) DEFAULT '',
        access_token TEXT,
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ads_campanas (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cursos (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curso_lecciones (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS curso_matriculas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        curso_id INT NOT NULL,
        cliente_id INT NOT NULL,
        estado ENUM('activa','completada','cancelada') DEFAULT 'activa',
        progreso INT DEFAULT 0,
        leccion_actual INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS afiliados (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS afiliado_referidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        afiliado_id INT NOT NULL,
        cliente_id INT DEFAULT NULL,
        ip VARCHAR(45),
        convertido TINYINT(1) DEFAULT 0,
        comision DECIMAL(10,2) DEFAULT 0,
        pagado TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (afiliado_id) REFERENCES afiliados(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comunidad_posts (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comunidad_respuestas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        contenido TEXT NOT NULL,
        likes INT DEFAULT 0,
        cliente_id INT DEFAULT NULL,
        usuario_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES comunidad_posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proveedor ENUM('openai','anthropic') DEFAULT 'openai',
        api_key VARCHAR(200) DEFAULT '',
        modelo VARCHAR(100) DEFAULT 'gpt-3.5-turbo',
        prompt_sistema TEXT DEFAULT '',
        activo TINYINT(1) DEFAULT 0,
        max_tokens INT DEFAULT 500,
        temperatura DECIMAL(2,1) DEFAULT 0.7,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_conversaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        titulo VARCHAR(200) DEFAULT 'Nueva conversación',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario (usuario_id),
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_mensajes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversacion_id INT NOT NULL,
        role ENUM('user','assistant') NOT NULL,
        content TEXT NOT NULL,
        tool_calls TEXT NULL COMMENT 'JSON de tool calls ejecutados',
        tokens_in INT NOT NULL DEFAULT 0,
        tokens_out INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversacion_id) REFERENCES ia_conversaciones(id) ON DELETE CASCADE,
        INDEX idx_conv (conversacion_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_acciones_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversacion_id INT NULL,
        usuario_id INT NOT NULL,
        tool_name VARCHAR(50) NOT NULL,
        tool_input TEXT NULL COMMENT 'JSON input',
        tool_result TEXT NULL COMMENT 'JSON result',
        entidad_tipo VARCHAR(50) NULL,
        entidad_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conv (conversacion_id),
        INDEX idx_usuario (usuario_id),
        INDEX idx_tool (tool_name),
        INDEX idx_fecha (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formularios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT,
        campos JSON NOT NULL,
        redirect_url VARCHAR(500) DEFAULT '',
        email_notificacion VARCHAR(200) DEFAULT '',
        crear_cliente TINYINT(1) DEFAULT 1 COMMENT 'Auto-crear cliente al recibir envio',
        activo TINYINT(1) DEFAULT 1,
        color_primario VARCHAR(7) DEFAULT '#10b981',
        texto_boton VARCHAR(100) DEFAULT 'Enviar',
        mensaje_exito TEXT DEFAULT 'Formulario enviado correctamente. Nos pondremos en contacto.',
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formulario_envios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        formulario_id INT NOT NULL,
        datos JSON NOT NULL,
        ip VARCHAR(45),
        user_agent TEXT,
        cliente_id INT DEFAULT NULL,
        leido TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (formulario_id) REFERENCES formularios(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS funnels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT,
        activo TINYINT(1) DEFAULT 1,
        visitas_total INT DEFAULT 0,
        conversiones_total INT DEFAULT 0,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS funnel_pasos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funnel_id INT NOT NULL,
        orden INT DEFAULT 0,
        nombre VARCHAR(200) NOT NULL,
        tipo ENUM('landing','formulario','upsell','downsell','gracias','custom') DEFAULT 'landing',
        landing_page_id INT DEFAULT NULL,
        formulario_id INT DEFAULT NULL,
        contenido_html TEXT,
        config JSON DEFAULT NULL,
        visitas INT DEFAULT 0,
        conversiones INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS funnel_sesiones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funnel_id INT NOT NULL,
        visitor_id VARCHAR(64),
        cliente_id INT DEFAULT NULL,
        paso_actual INT DEFAULT 1,
        completado TINYINT(1) DEFAULT 0,
        datos JSON DEFAULT NULL,
        ip VARCHAR(45),
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS landing_pages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(200) NOT NULL,
        slug VARCHAR(200) NOT NULL UNIQUE,
        secciones JSON NOT NULL,
        meta_titulo VARCHAR(200) DEFAULT '',
        meta_descripcion VARCHAR(300) DEFAULT '',
        formulario_id INT DEFAULT NULL,
        color_primario VARCHAR(7) DEFAULT '#10b981',
        color_fondo VARCHAR(7) DEFAULT '#ffffff',
        custom_css TEXT DEFAULT '',
        activa TINYINT(1) DEFAULT 1,
        visitas INT DEFAULT 0,
        conversiones INT DEFAULT 0,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_utm (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prospecto_id INT DEFAULT NULL,
        cliente_id INT DEFAULT NULL,
        utm_source VARCHAR(200) DEFAULT '',
        utm_medium VARCHAR(200) DEFAULT '',
        utm_campaign VARCHAR(200) DEFAULT '',
        utm_term VARCHAR(200) DEFAULT '',
        utm_content VARCHAR(200) DEFAULT '',
        landing_url VARCHAR(500) DEFAULT '',
        referrer VARCHAR(500) DEFAULT '',
        ip VARCHAR(45) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_source (utm_source),
        INDEX idx_campaign (utm_campaign),
        INDEX idx_medium (utm_medium),
        INDEX idx_fecha (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facturas` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `configuracion_pagos` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pipelines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        descripcion TEXT NULL,
        color VARCHAR(7) NOT NULL DEFAULT '#10b981',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pipeline_etapas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pipeline_id INT NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        color VARCHAR(7) NOT NULL DEFAULT '#64748b',
        orden INT NOT NULL DEFAULT 0,
        permitir_conversion TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pipeline_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pipeline_id INT NOT NULL,
        etapa_id INT NOT NULL,
        titulo VARCHAR(200) NOT NULL,
        propiedad_id INT NULL,
        cliente_id INT NULL,
        prospecto_id INT NULL,
        valor DECIMAL(12,2) NULL,
        notas TEXT NULL,
        prioridad ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE CASCADE,
        FOREIGN KEY (etapa_id) REFERENCES pipeline_etapas(id) ON DELETE CASCADE,
        FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE SET NULL,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
        FOREIGN KEY (prospecto_id) REFERENCES prospectos(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_pipeline_etapa (pipeline_id, etapa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS presupuestos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero VARCHAR(20) NOT NULL,
        cliente_id INT DEFAULT NULL,
        propiedad_id INT DEFAULT NULL,
        titulo VARCHAR(300) NOT NULL,
        descripcion TEXT,
        lineas JSON NOT NULL DEFAULT '[]',
        subtotal DECIMAL(12,2) DEFAULT 0,
        iva_total DECIMAL(12,2) DEFAULT 0,
        total DECIMAL(12,2) DEFAULT 0,
        estado ENUM('borrador','enviado','aceptado','rechazado','expirado','convertido') DEFAULT 'borrador',
        validez_dias INT DEFAULT 30,
        fecha_emision DATE NOT NULL,
        fecha_expiracion DATE DEFAULT NULL,
        notas TEXT,
        condiciones TEXT,
        token VARCHAR(64) DEFAULT NULL,
        aceptado_at DATETIME DEFAULT NULL,
        aceptado_ip VARCHAR(45) DEFAULT NULL,
        factura_id INT DEFAULT NULL,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resenas_solicitudes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        tipo ENUM('google','email','whatsapp') NOT NULL DEFAULT 'google',
        enlace_resena VARCHAR(500) DEFAULT '',
        estado ENUM('pendiente','enviada','completada','ignorada') DEFAULT 'pendiente',
        valoracion INT DEFAULT NULL,
        comentario TEXT,
        enviada_at DATETIME DEFAULT NULL,
        completada_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reputacion_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        google_review_link VARCHAR(500) DEFAULT '',
        mensaje_solicitud TEXT DEFAULT 'Hola {{nombre}}, gracias por confiar en nosotros. Nos ayudaria mucho si pudieras dejarnos una resena.',
        activo TINYINT(1) DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proveedor ENUM('twilio','vonage') DEFAULT 'twilio',
        api_sid VARCHAR(200) DEFAULT '',
        api_token VARCHAR(200) DEFAULT '',
        telefono_remitente VARCHAR(20) DEFAULT '',
        activo TINYINT(1) DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sms_mensajes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT DEFAULT NULL,
        telefono_destino VARCHAR(20) NOT NULL,
        mensaje TEXT NOT NULL,
        estado ENUM('pendiente','enviado','fallido','entregado') DEFAULT 'pendiente',
        proveedor_id VARCHAR(100) DEFAULT NULL,
        error_mensaje TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_cuentas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plataforma ENUM('facebook','instagram','google_business','linkedin','twitter') NOT NULL,
        nombre VARCHAR(200) NOT NULL,
        access_token TEXT,
        page_id VARCHAR(100) DEFAULT '',
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_posts (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medios (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contratos (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contrato_plantillas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        tipo ENUM('texto','pdf') NOT NULL DEFAULT 'texto',
        contenido LONGTEXT,
        archivo_path VARCHAR(255) DEFAULT NULL,
        archivo_nombre VARCHAR(255) DEFAULT NULL,
        categoria VARCHAR(100) DEFAULT 'general',
        activo TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(50) NOT NULL UNIQUE,
        color VARCHAR(7) DEFAULT '#6b7280',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cliente_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cliente_id INT NOT NULL,
        tag_id INT NOT NULL,
        UNIQUE KEY unique_cliente_tag (cliente_id, tag_id),
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trigger_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        codigo VARCHAR(20) NOT NULL UNIQUE,
        url_destino VARCHAR(500) NOT NULL,
        accion_tipo ENUM('ninguna','tag','notificacion') DEFAULT 'ninguna',
        accion_valor VARCHAR(200) DEFAULT '',
        activo TINYINT(1) DEFAULT 1,
        total_clicks INT DEFAULT 0,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trigger_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        link_id INT NOT NULL,
        cliente_id INT DEFAULT NULL,
        ip VARCHAR(45),
        user_agent TEXT,
        referer VARCHAR(500),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (link_id) REFERENCES trigger_links(id) ON DELETE CASCADE,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_config (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        access_token        TEXT,
        phone_number_id     VARCHAR(80),
        business_account_id VARCHAR(80),
        webhook_verify_token VARCHAR(120),
        phone_display       VARCHAR(30),
        activo              TINYINT(1) DEFAULT 1,
        updated_by          INT,
        updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_mensajes (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(200) NOT NULL,
        descripcion TEXT DEFAULT '',
        trigger_tipo VARCHAR(50) NOT NULL DEFAULT 'manual',
        trigger_config JSON DEFAULT NULL,
        nodos JSON NOT NULL,
        conexiones JSON NOT NULL,
        activo TINYINT(1) DEFAULT 0,
        ejecuciones INT DEFAULT 0,
        ultima_ejecucion DATETIME DEFAULT NULL,
        usuario_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workflow_ejecuciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        workflow_id INT NOT NULL,
        estado ENUM('corriendo','completado','error') DEFAULT 'corriendo',
        nodo_actual VARCHAR(50) DEFAULT NULL,
        log JSON DEFAULT NULL,
        datos JSON DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME DEFAULT NULL,
        FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS secuencia_captacion_plantillas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        orden TINYINT NOT NULL,
        titulo VARCHAR(150) NOT NULL,
        mensaje TEXT NOT NULL,
        dias_espera SMALLINT NOT NULL DEFAULT 0 COMMENT 'Dias desde el inicio de la secuencia',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_orden (orden)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS secuencia_captacion_tracking (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prospecto_id INT NOT NULL,
        plantilla_id INT NULL,
        paso_actual TINYINT NOT NULL DEFAULT 0 COMMENT '0 = aun no enviado el primero',
        estado ENUM('activo','pausado','completado','descartado','respondido') NOT NULL DEFAULT 'activo',
        programado_para DATETIME NULL,
        ultimo_envio DATETIME NULL,
        intentos SMALLINT NOT NULL DEFAULT 0,
        error_ultimo TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_prospecto (prospecto_id),
        FOREIGN KEY (prospecto_id) REFERENCES prospectos(id) ON DELETE CASCADE,
        FOREIGN KEY (plantilla_id) REFERENCES secuencia_captacion_plantillas(id) ON DELETE SET NULL,
        INDEX idx_estado (estado),
        INDEX idx_programado (programado_para)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historial_propiedad_prospecto (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prospecto_id INT NOT NULL,
        usuario_id INT DEFAULT NULL,
        tipo ENUM('subida_precio','bajada_precio','modificacion','publicacion','retirada','otro') NOT NULL DEFAULT 'otro',
        descripcion TEXT DEFAULT NULL,
        precio_anterior DECIMAL(12,2) DEFAULT NULL,
        precio_nuevo DECIMAL(12,2) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_prospecto (prospecto_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS google_calendar_tokens (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id        INT NOT NULL,
        access_token      TEXT NOT NULL,
        refresh_token     TEXT DEFAULT NULL,
        expires_at        INT NOT NULL DEFAULT 0,
        google_email      VARCHAR(255) DEFAULT NULL,
        google_calendar_id VARCHAR(255) DEFAULT 'primary',
        created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_usuario (usuario_id),
        INDEX idx_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS google_calendar_event_map (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id        INT NOT NULL,
        entidad_tipo      ENUM('tarea','visita','prospecto','calendario') NOT NULL,
        entidad_id        INT NOT NULL,
        google_event_id   VARCHAR(255) NOT NULL,
        google_calendar_id VARCHAR(255) DEFAULT 'primary',
        created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_entidad (usuario_id, entidad_tipo, entidad_id),
        INDEX idx_usuario (usuario_id),
        INDEX idx_google_event (google_event_id(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ============================================================
-- Datos iniciales
-- ============================================================

INSERT IGNORE INTO portales (nombre, activo) VALUES
  ('Idealista', 1), ('Fotocasa', 1), ('Habitaclia', 1),
  ('Pisos.com', 1), ('Infocasa', 1), ('Milanuncios', 1);

-- Usuario administrador por defecto (contraseña: Admin1234! — cambiar tras instalar)
INSERT IGNORE INTO usuarios (id, nombre, apellidos, email, password, rol, activo)
  VALUES (1, 'Admin', 'CRM', 'admin@crm.local',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXpHmvdO6', 'admin', 1);

INSERT IGNORE INTO reputacion_config (id) VALUES (1);
INSERT IGNORE INTO sms_config (id) VALUES (1);
INSERT IGNORE INTO configuracion_pagos (id) VALUES (1);
INSERT IGNORE INTO ia_config (id, prompt_sistema) VALUES
  (1, 'Eres un asistente de una inmobiliaria en España. Responde preguntas sobre propiedades, visitas, y procesos de compra/alquiler de forma amable y profesional.');

SET FOREIGN_KEY_CHECKS = 1;