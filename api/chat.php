<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

function buildAllowedChatOrigins() {
    $allowed = [];

    $app = parse_url(APP_URL);
    if (!empty($app['scheme']) && !empty($app['host'])) {
        $origin = $app['scheme'] . '://' . $app['host'];
        if (!empty($app['port'])) {
            $origin .= ':' . intval($app['port']);
        }
        $allowed[] = strtolower($origin);
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        $allowed[] = strtolower($scheme . '://' . $host);
    }

    return array_values(array_unique($allowed));
}

function applyChatCorsHeaders() {
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    $allowedOrigins = buildAllowedChatOrigins();

    if ($origin !== '' && in_array(strtolower($origin), $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } elseif (!empty($allowedOrigins)) {
        // Fallback seguro para peticiones sin Origin (mismo sitio).
        header('Access-Control-Allow-Origin: ' . $allowedOrigins[0]);
    }

    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

applyChatCorsHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function chatClientIp() {
    return trim($_SERVER['REMOTE_ADDR'] ?? '');
}

function chatSigningSecret() {
    return getEnvSecret('CHAT_SIGNING_KEY', hash('sha256', DB_NAME . '|' . DB_USER . '|' . DB_PASS));
}

function buildChatToken($visitorId, $ip) {
    return hash_hmac('sha256', $visitorId . '|' . $ip, chatSigningSecret());
}

function isValidVisitorId($visitorId) {
    return (bool) preg_match('/^[a-zA-Z0-9_\-]{8,80}$/', $visitorId);
}

function chatRateLimitExceeded($action, $ip, $visitorId = '') {
    $action = trim((string)$action);
    $ip = trim((string)$ip);
    if ($action === '' || $ip === '') {
        return false;
    }

    $limits = [
        'init' => 90,
        'send' => 120,
        'messages' => 240,
        'config' => 120,
    ];
    $max = $limits[$action] ?? 120;

    $windowSeconds = 60;
    $key = hash('sha256', $action . '|' . $ip . '|' . $visitorId);
    $file = rtrim(sys_get_temp_dir(), '/\\') . '/crm_chat_rl_' . $key . '.json';
    $now = time();

    try {
        $data = ['window_start' => $now, 'count' => 0];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = json_decode((string)$raw, true);
            if (is_array($decoded) && isset($decoded['window_start'], $decoded['count'])) {
                $data = $decoded;
            }
        }

        $windowStart = intval($data['window_start'] ?? $now);
        $count = intval($data['count'] ?? 0);

        if (($now - $windowStart) >= $windowSeconds) {
            $windowStart = $now;
            $count = 0;
        }

        $count++;
        @file_put_contents($file, json_encode(['window_start' => $windowStart, 'count' => $count]), LOCK_EX);

        return $count > $max;
    } catch (Throwable $e) {
        // Fail-open para no afectar el flujo de chat ante errores de I/O.
        return false;
    }
}

try {
    switch ($action) {
        case 'config':
            if (chatRateLimitExceeded('config', chatClientIp(), '')) {
                http_response_code(429);
                echo json_encode(['error' => 'Demasiadas solicitudes']);
                break;
            }
            $cfg = $db->query("SELECT titulo, subtitulo, color_primario, posicion, mensaje_bienvenida, pedir_datos, activo, horario_inicio, horario_fin, mensaje_fuera_horario FROM chat_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'config' => $cfg ?: []]);
            break;

        case 'init':
            $visitorId = trim($_POST['visitor_id'] ?? '');
            if (!$visitorId || !isValidVisitorId($visitorId)) { echo json_encode(['error' => 'visitor_id invalido']); exit; }
            $ip = chatClientIp();
            if (chatRateLimitExceeded('init', $ip, $visitorId)) {
                http_response_code(429);
                echo json_encode(['error' => 'Demasiadas solicitudes']);
                break;
            }
            $stmt = $db->prepare("SELECT id FROM chat_conversaciones WHERE visitor_id = ? AND ip = ? AND estado != 'cerrada' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$visitorId, $ip]);
            $conv = $stmt->fetch();
            if (!$conv) {
                $db->prepare("INSERT INTO chat_conversaciones (visitor_id, nombre, email, telefono, pagina_origen, ip) VALUES (?,?,?,?,?,?)")->execute([
                    $visitorId, trim($_POST['nombre'] ?? 'Visitante'), trim($_POST['email'] ?? ''), trim($_POST['telefono'] ?? ''),
                    trim($_POST['pagina'] ?? ''), $ip
                ]);
                $convId = $db->lastInsertId();
                $cfg = $db->query("SELECT mensaje_bienvenida FROM chat_config WHERE id = 1")->fetch();
                if ($cfg && $cfg['mensaje_bienvenida']) {
                    $db->prepare("INSERT INTO chat_mensajes (conversacion_id, emisor, mensaje) VALUES (?, 'sistema', ?)")->execute([$convId, $cfg['mensaje_bienvenida']]);
                }
            } else {
                $convId = $conv['id'];
            }
            echo json_encode(['success' => true, 'conversacion_id' => intval($convId), 'token' => buildChatToken($visitorId, $ip)]);
            break;

        case 'send':
            $visitorId = trim($_POST['visitor_id'] ?? '');
            $mensaje = trim($_POST['mensaje'] ?? '');
            $token = trim($_POST['token'] ?? '');
            if (!$visitorId || !$mensaje || !$token || !isValidVisitorId($visitorId)) { echo json_encode(['error' => 'Datos incompletos']); exit; }
            if (mb_strlen($mensaje) > 2000) { echo json_encode(['error' => 'Mensaje demasiado largo']); exit; }
            $ip = chatClientIp();
            if (chatRateLimitExceeded('send', $ip, $visitorId)) {
                http_response_code(429);
                echo json_encode(['error' => 'Demasiadas solicitudes']);
                break;
            }
            if (!hash_equals(buildChatToken($visitorId, $ip), $token)) { echo json_encode(['error' => 'Token invalido']); exit; }
            $stmt = $db->prepare("SELECT id FROM chat_conversaciones WHERE visitor_id = ? AND ip = ? AND estado != 'cerrada' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$visitorId, $ip]);
            $conv = $stmt->fetch();
            if (!$conv) { echo json_encode(['error' => 'Conversacion no encontrada']); exit; }
            $db->prepare("INSERT INTO chat_mensajes (conversacion_id, emisor, mensaje) VALUES (?, 'visitante', ?)")->execute([$conv['id'], $mensaje]);
            $db->prepare("UPDATE chat_conversaciones SET ultimo_mensaje = NOW(), estado = 'esperando' WHERE id = ?")->execute([$conv['id']]);
            echo json_encode(['success' => true]);
            break;

        case 'messages':
            $visitorId = trim($_GET['visitor_id'] ?? '');
            $token = trim($_GET['token'] ?? '');
            if (!$visitorId || !$token || !isValidVisitorId($visitorId)) { echo json_encode(['error' => 'visitor_id requerido']); exit; }
            $ip = chatClientIp();
            if (chatRateLimitExceeded('messages', $ip, $visitorId)) {
                http_response_code(429);
                echo json_encode(['error' => 'Demasiadas solicitudes']);
                break;
            }
            if (!hash_equals(buildChatToken($visitorId, $ip), $token)) { echo json_encode(['error' => 'Token invalido']); exit; }
            $stmt = $db->prepare("SELECT id FROM chat_conversaciones WHERE visitor_id = ? AND ip = ? AND estado != 'cerrada' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$visitorId, $ip]);
            $conv = $stmt->fetch();
            if (!$conv) { echo json_encode(['success' => true, 'messages' => []]); exit; }
            $msgs = $db->prepare("SELECT emisor, mensaje, created_at FROM chat_mensajes WHERE conversacion_id = ? ORDER BY created_at ASC");
            $msgs->execute([$conv['id']]);
            echo json_encode(['success' => true, 'messages' => $msgs->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            echo json_encode(['error' => 'Accion no valida']);
    }
} catch (Exception $e) {
    if (function_exists('logError')) {
        logError('Chat API error: ' . $e->getMessage());
    } else {
        error_log($e->getMessage());
    }
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
