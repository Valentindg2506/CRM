<?php
/**
 * Instalador: Secuencia de Captación WhatsApp para Prospectos
 *
 * Este script:
 *   1. Añade prospecto_id a whatsapp_mensajes (para loguear mensajes a prospectos)
 *   2. Añade columna `modo` a whatsapp_config (simulacion | real)
 *   3. Crea secuencia_captacion_plantillas (los 7 mensajes)
 *   4. Crea secuencia_captacion_tracking (estado de cada prospecto en la secuencia)
 *   5. Pobla las 7 plantillas iniciales
 *
 * Ejecutar una sola vez: php install_secuencia_captacion.php
 * o acceder vía navegador con el INSTALLER_KEY.
 */

require_once __DIR__ . '/config/database.php';

// Protección mínima vía navegador (ajustá la key si lo ejecutás por web)
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
// 1. ALTERAR whatsapp_mensajes: añadir prospecto_id si no existe
// ----------------------------------------------------------------
try {
    $check = $db->query("SHOW COLUMNS FROM whatsapp_mensajes LIKE 'prospecto_id'")->fetch();
    if (!$check) {
        $db->exec("ALTER TABLE whatsapp_mensajes
                   ADD COLUMN prospecto_id INT NULL AFTER cliente_id,
                   ADD INDEX idx_prospecto (prospecto_id)");
        $messages[] = "OK · whatsapp_mensajes: columna prospecto_id añadida.";
    } else {
        $messages[] = "SKIP · whatsapp_mensajes.prospecto_id ya existe.";
    }
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR alterando whatsapp_mensajes: " . $e->getMessage();
}

// ----------------------------------------------------------------
// 2. Ampliar ENUM estado de whatsapp_mensajes para aceptar 'simulado'
// ----------------------------------------------------------------
try {
    $db->exec("ALTER TABLE whatsapp_mensajes
               MODIFY COLUMN estado ENUM('enviado','entregado','leido','fallido','recibido','simulado')
               DEFAULT 'enviado'");
    $messages[] = "OK · whatsapp_mensajes.estado admite ahora 'simulado'.";
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR ampliando estado: " . $e->getMessage();
}

// ----------------------------------------------------------------
// 3. ALTERAR whatsapp_config: añadir modo simulacion/real
// ----------------------------------------------------------------
try {
    $check = $db->query("SHOW COLUMNS FROM whatsapp_config LIKE 'modo'")->fetch();
    if (!$check) {
        $db->exec("ALTER TABLE whatsapp_config
                   ADD COLUMN modo ENUM('simulacion','real') NOT NULL DEFAULT 'simulacion' AFTER activo");
        $messages[] = "OK · whatsapp_config: columna modo añadida (default: simulacion).";
    } else {
        $messages[] = "SKIP · whatsapp_config.modo ya existe.";
    }
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR alterando whatsapp_config: " . $e->getMessage();
}

// Aseguramos que exista al menos un registro en whatsapp_config
try {
    $count = (int) $db->query("SELECT COUNT(*) FROM whatsapp_config")->fetchColumn();
    if ($count === 0) {
        $db->exec("INSERT INTO whatsapp_config (activo, modo) VALUES (0, 'simulacion')");
        $messages[] = "OK · whatsapp_config: registro inicial creado en modo simulacion.";
    }
} catch (PDOException $e) {
    $messages[] = "WARN · no se pudo inicializar whatsapp_config: " . $e->getMessage();
}

// ----------------------------------------------------------------
// 4. Crear secuencia_captacion_plantillas
// ----------------------------------------------------------------
try {
    $db->exec("CREATE TABLE IF NOT EXISTS secuencia_captacion_plantillas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        orden TINYINT NOT NULL,
        titulo VARCHAR(150) NOT NULL,
        mensaje TEXT NOT NULL,
        dias_espera SMALLINT NOT NULL DEFAULT 0 COMMENT 'Dias desde el inicio de la secuencia',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_orden (orden)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "OK · Tabla secuencia_captacion_plantillas creada.";
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR creando plantillas: " . $e->getMessage();
}

// ----------------------------------------------------------------
// 5. Crear secuencia_captacion_tracking
// ----------------------------------------------------------------
try {
    $db->exec("CREATE TABLE IF NOT EXISTS secuencia_captacion_tracking (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $messages[] = "OK · Tabla secuencia_captacion_tracking creada.";
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR creando tracking: " . $e->getMessage();
}

// ----------------------------------------------------------------
// 6. Poblar las 7 plantillas iniciales (solo si la tabla está vacía)
// ----------------------------------------------------------------
try {
    $count = (int) $db->query("SELECT COUNT(*) FROM secuencia_captacion_plantillas")->fetchColumn();
    if ($count === 0) {
        $plantillas = [
            [
                'orden' => 1,
                'dias'  => 0,
                'titulo'=> 'Apertura — confirmación del contacto',
                'mensaje' =>
"Hola {nombre}, soy {agente_nombre} de CAS RS.

Te escribo por tu piso en {barrio}. Vi que lo tenés publicado y quería presentarme antes de molestarte con cosas genéricas que no sirven.

No te voy a pedir nada hoy. Solo que sepas que estoy acá si en algún momento querés que te comparta cómo se está moviendo el mercado en tu zona. La info es gratis y sin compromiso.

Un saludo."
            ],
            [
                'orden' => 2,
                'dias'  => 2,
                'titulo'=> 'Valor — dato del mercado en su zona',
                'mensaje' =>
"{nombre}, algo que quizá te interese.

Esta semana cerramos una operación en {barrio} con 14% por encima del precio que el propietario pensaba pedir al principio. No por inflar el precio, sino porque sabíamos quién estaba comprando y cómo posicionarlo.

Ese dato aplica a tu piso también. Si querés que te pase un informe corto de qué se está vendiendo hoy en tu zona y a qué precio real, te lo mando sin problema.

¿Te sirve?"
            ],
            [
                'orden' => 3,
                'dias'  => 5,
                'titulo'=> 'Historia — propietario que vendió bien con nosotros',
                'mensaje' =>
"Te cuento una historia corta, {nombre}.

Hace unos meses contactó un señor de Ruzafa. Tenía el piso publicado hacía 7 meses, llamadas pocas, ofertas muy por debajo. Estaba por bajar el precio.

Le propusimos algo distinto: no bajar el precio, cambiar el comprador al que apuntábamos. Reposicionamos el anuncio, armamos otra estrategia de visitas, y cerró en 23 días al precio que él quería desde el principio.

Lo traigo porque tu situación puede parecerse más de lo que crees. ¿Te animás a que revisemos juntos cómo está tu operación?"
            ],
            [
                'orden' => 4,
                'dias'  => 9,
                'titulo'=> 'Objeción — "no tengo prisa"',
                'mensaje' =>
"{nombre}, sé que seguramente pensás que no tenés prisa. Es la respuesta más común que recibo y la entiendo.

Pero dejame plantearte algo: cada mes que tu piso está publicado sin moverse, pierde atractivo para el comprador. Se convierte en un piso que lleva tiempo, y el comprador empieza a preguntarse qué le pasa. Ahí es donde llegan las ofertas bajas.

No se trata de correr, se trata de no perder valor por estar quieto. ¿Hablamos 15 minutos sin compromiso esta semana?"
            ],
            [
                'orden' => 5,
                'dias'  => 14,
                'titulo'=> 'Valor — error que comete el 80% de propietarios',
                'mensaje' =>
"Una cosa que veo repetirse, {nombre}:

El 80% de propietarios que venden por su cuenta o con una agencia que no los cuida, comete el mismo error. Ponen todo el foco en el precio y ninguno en el comprador.

El precio correcto sin el comprador correcto es un piso que se quema en el mercado. El comprador correcto paga lo que cuesta porque ve lo que vos ves en tu piso.

Si querés, te paso un audio corto de 3 minutos explicando cómo diferenciamos un tipo de comprador del otro. Decime y te lo mando."
            ],
            [
                'orden' => 6,
                'dias'  => 20,
                'titulo'=> 'Urgencia — ventana de oportunidad',
                'mensaje' =>
"{nombre}, te escribo por algo concreto.

El mercado en {localidad} está en una ventana particular: tipos de interés estabilizándose, compradores con dinero esperando encontrar el piso correcto, y todavía poco stock de calidad publicado.

Esto no dura para siempre. En los próximos meses es probable que entren más propiedades y la foto cambie.

Si te planteaste vender alguna vez, este es un buen momento para al menos saber a cuánto podrías salir. ¿Lo miramos juntos?"
            ],
            [
                'orden' => 7,
                'dias'  => 28,
                'titulo'=> 'Cierre — propuesta de valoración gratuita',
                'mensaje' =>
"Último mensaje de mi parte, {nombre}, y lo hago breve.

No quiero ser pesado. Si no es el momento, te dejo tranquilo.

Pero si en algún momento te pica la curiosidad, la oferta es simple: una valoración real de tu piso, con datos concretos de la zona, sin compromiso y sin que tengas que firmar nada. 45 minutos, un café, y te vas con información útil decidas lo que decidas.

Si no, sin rencores. Te deseo lo mejor con el piso y si en el futuro nos cruzamos, con gusto."
            ],
        ];

        $stmt = $db->prepare("INSERT INTO secuencia_captacion_plantillas
            (orden, titulo, mensaje, dias_espera, activo)
            VALUES (:orden, :titulo, :mensaje, :dias, 1)");

        foreach ($plantillas as $p) {
            $stmt->execute([
                ':orden'  => $p['orden'],
                ':titulo' => $p['titulo'],
                ':mensaje'=> $p['mensaje'],
                ':dias'   => $p['dias'],
            ]);
        }
        $messages[] = "OK · 7 plantillas iniciales insertadas.";
    } else {
        $messages[] = "SKIP · secuencia_captacion_plantillas ya tiene $count filas.";
    }
} catch (PDOException $e) {
    $success = false;
    $messages[] = "ERROR insertando plantillas: " . $e->getMessage();
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
    <title>Instalación Secuencia Captación — Tinoprop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">🚀 Instalación — Secuencia de Captación WhatsApp</h4>
                </div>
                <div class="card-body">
                    <?php foreach ($messages as $m): ?>
                        <div class="alert alert-<?= strpos($m, 'ERROR') !== false ? 'danger' : (strpos($m, 'SKIP') !== false ? 'secondary' : (strpos($m, 'WARN') !== false ? 'warning' : 'success')) ?> py-2 mb-2">
                            <?= htmlspecialchars($m) ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-info mt-3">
                            <strong>Listo.</strong> Siguiente paso: ejecutar el agente manualmente o vía cron.
                        </div>
                        <code class="d-block p-2 bg-dark text-white mb-2">php cron/secuencia_captacion.php</code>
                        <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/modules/secuencia_captacion/index.php"
                           class="btn btn-primary">Ir al dashboard</a>
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