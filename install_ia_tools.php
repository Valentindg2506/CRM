<?php
/**
 * Instalador: IA Asistente con Tool Use (Anthropic Claude)
 *
 * Este script:
 *   1. Migra ia_config para soportar tool use y nuevos campos
 *   2. Crea ia_conversaciones (historial de chats)
 *   3. Crea ia_mensajes (mensajes individuales)
 *   4. Crea ia_acciones_log (log de tool calls)
 *   5. Actualiza prompt del sistema optimizado para CRM
 *
 * Ejecutar una sola vez: php install_ia_tools.php
 */

require_once __DIR__ . '/config/database.php';

if (php_sapi_name() !== 'cli') {
    $expectedKey = getenv('INSTALLER_KEY') ?: (defined('INSTALLER_KEY') ? INSTALLER_KEY : '');
    $providedKey = $_GET['install_key'] ?? '';
    if ($expectedKey && $providedKey !== $expectedKey) {
        http_response_code(403);
        exit('Acceso denegado. Añadí ?install_key=TU_INSTALLER_KEY a la URL.');
    }
}

$db = getDB();
$messages = [];
$success  = true;

// ----------------------------------------------------------------
// 1. Asegurar tabla ia_config existe y tiene los campos necesarios
// ----------------------------------------------------------------
try {
    $db->exec("CREATE TABLE IF NOT EXISTS ia_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proveedor ENUM('openai','anthropic','groq') NOT NULL DEFAULT 'anthropic',
        api_key VARCHAR(500) DEFAULT '',
        modelo VARCHAR(100) DEFAULT 'claude-sonnet-4-20250514',
        prompt_sistema TEXT,
        activo TINYINT(1) NOT NULL DEFAULT 0,
        max_tokens INT NOT NULL DEFAULT 4096,
        temperatura DECIMAL(3,2) NOT NULL DEFAULT 0.4,
        tools_activos TINYINT(1) NOT NULL DEFAULT 1,
        max_tool_iterations INT NOT NULL DEFAULT 8,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "OK · Tabla ia_config verificada.";
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR ia_config: " . $e->getMessage();
}

// Añadir columnas nuevas si no existen
// Migrar ENUM proveedor para añadir 'groq' si falta
try {
    $db->exec("ALTER TABLE ia_config MODIFY COLUMN proveedor ENUM('openai','anthropic','groq') NOT NULL DEFAULT 'anthropic'");
    $messages[] = "OK · ia_config.proveedor ENUM actualizado con 'groq'.";
} catch (PDOException $e) {
    $messages[] = "WARN · ia_config.proveedor ENUM: " . $e->getMessage();
}

$newCols = [
    'tools_activos'       => "TINYINT(1) NOT NULL DEFAULT 1",
    'max_tool_iterations' => "INT NOT NULL DEFAULT 8",
];
foreach ($newCols as $col => $def) {
    try {
        $check = $db->query("SHOW COLUMNS FROM ia_config LIKE '$col'")->fetch();
        if (!$check) {
            $db->exec("ALTER TABLE ia_config ADD COLUMN $col $def");
            $messages[] = "OK · ia_config.$col añadida.";
        } else {
            $messages[] = "SKIP · ia_config.$col ya existe.";
        }
    } catch (PDOException $e) {
        $messages[] = "WARN · ia_config.$col: " . $e->getMessage();
    }
}

// Asegurar registro por defecto
try {
    $count = (int)$db->query("SELECT COUNT(*) FROM ia_config")->fetchColumn();
    if ($count === 0) {
        $systemPrompt = <<<'PROMPT'
Eres el asistente IA de Tinoprop, un CRM inmobiliario profesional en España. Tu nombre es "Tino".

Tu rol es ayudar al equipo comercial de la inmobiliaria con todo lo que necesiten:

## CAPACIDADES
1. **CONSULTAS**: Buscar y filtrar prospectos, propiedades, clientes, pipelines, finanzas, tareas y actividad
2. **CALIFICACIÓN**: Evaluar prospectos según su actividad, datos disponibles y potencial de cierre
3. **ACCIONES**: Crear tareas, notificaciones, actualizar prospectos y calificarlos
4. **COMUNICACIÓN**: Enviar emails y mensajes de WhatsApp a contactos del CRM
5. **ANÁLISIS**: KPIs del negocio, rendimiento de agentes, estado del pipeline, tendencias
6. **ESTRATEGIA**: Recomendaciones basadas en los datos reales del CRM
7. **DIAGNÓSTICO**: Modo agente técnico — puedes diagnosticar problemas de la app, verificar tablas, configuraciones, errores, y estado de módulos

## REGLA CRÍTICA: CONFIRMACIÓN OBLIGATORIA
- Para CUALQUIER acción de escritura (calificar prospecto, crear tarea, enviar email, enviar WhatsApp, actualizar datos), SIEMPRE describe primero exactamente qué vas a hacer y pregunta "¿Confirmo?" al usuario.
- NUNCA ejecutes una acción de escritura sin preguntar primero.
- Las consultas/lecturas puedes ejecutarlas directamente sin preguntar.

## REGLAS GENERALES
- SIEMPRE usa las herramientas disponibles para consultar datos reales del CRM. NUNCA inventes datos.
- Responde SIEMPRE en español.
- Usa formato Markdown para claridad: **negritas**, listas, tablas cuando sea útil.
- Cuando califiques prospectos, explica tu razonamiento detalladamente.
- Sé conciso pero completo. No repitas información innecesariamente.
- Si no tienes datos suficientes, dilo honestamente.
- Trata al usuario de "tú" y sé profesional pero cercano.

## MODO AGENTE (DIAGNÓSTICO)
Cuando el usuario pida ayuda técnica con la aplicación (errores, problemas, configuración), activa tu modo agente:
- Usa la herramienta `diagnosticar_app` para inspeccionar el estado del CRM
- Analiza errores del log, tablas faltantes, configuraciones incorrectas
- Da instrucciones claras y paso a paso para resolver los problemas
- Puedes verificar la configuración de WhatsApp, Email, BD, y módulos

## CONTEXTO DEL NEGOCIO
- CRM inmobiliario enfocado en captación de propiedades (personal shopper inmobiliario)
- Zona principal: España
- Los prospectos son propietarios que quieren vender su piso
- Las etapas de prospecto son: nuevo_lead → contactado → seguimiento → visita_programada → captado/descartado
- La temperatura indica interés: frio, templado, caliente
PROMPT;
        $db->prepare("INSERT INTO ia_config (proveedor, modelo, prompt_sistema, activo, max_tokens, temperatura, tools_activos, max_tool_iterations) VALUES ('anthropic', 'claude-sonnet-4-20250514', ?, 0, 4096, 0.4, 1, 8)")
           ->execute([$systemPrompt]);
        $messages[] = "OK · Registro ia_config creado con prompt optimizado para CRM.";
    } else {
        $messages[] = "SKIP · ia_config ya tiene registros.";
    }
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR inicializando ia_config: " . $e->getMessage();
}

// ----------------------------------------------------------------
// 2. Crear ia_conversaciones
// ----------------------------------------------------------------
try {
    $db->exec("CREATE TABLE IF NOT EXISTS ia_conversaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        titulo VARCHAR(200) DEFAULT 'Nueva conversación',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario (usuario_id),
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "OK · Tabla ia_conversaciones creada.";
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR ia_conversaciones: " . $e->getMessage();
}

// ----------------------------------------------------------------
// 3. Crear ia_mensajes
// ----------------------------------------------------------------
try {
    $db->exec("CREATE TABLE IF NOT EXISTS ia_mensajes (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "OK · Tabla ia_mensajes creada.";
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR ia_mensajes: " . $e->getMessage();
}

// ----------------------------------------------------------------
// 4. Crear ia_acciones_log
// ----------------------------------------------------------------
try {
    $db->exec("CREATE TABLE IF NOT EXISTS ia_acciones_log (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "OK · Tabla ia_acciones_log creada.";
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR ia_acciones_log: " . $e->getMessage();
}

// ----------------------------------------------------------------
// Salida
// ----------------------------------------------------------------
if (php_sapi_name() === 'cli') {
    foreach ($messages as $m) { echo $m . PHP_EOL; }
    echo $success ? "\nInstalación completada con éxito.\n" : "\nInstalación con errores.\n";
    exit($success ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalación IA Tools — Tinoprop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">🤖 Instalación — IA Asistente con Tool Use</h4>
                </div>
                <div class="card-body">
                    <?php foreach ($messages as $m): ?>
                        <div class="alert alert-<?= strpos($m, 'ERROR') !== false ? 'danger' : (strpos($m, 'SKIP') !== false ? 'secondary' : (strpos($m, 'WARN') !== false ? 'warning' : 'success')) ?> py-2 mb-2">
                            <?= htmlspecialchars($m) ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-info mt-3">
                            <strong>Listo.</strong> Siguiente paso: configura tu API key de Anthropic desde el módulo IA.
                        </div>
                        <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/modules/ia/index.php"
                           class="btn btn-primary">Ir al IA Asistente</a>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <strong>Hubo errores.</strong> Revisalos arriba antes de continuar.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
