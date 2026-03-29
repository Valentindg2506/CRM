<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config/database.php';
$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'config':
            $cfg = $db->query("SELECT titulo, subtitulo, color_primario, posicion, mensaje_bienvenida, pedir_datos, activo, horario_inicio, horario_fin, mensaje_fuera_horario FROM chat_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'config' => $cfg ?: []]);
            break;

        case 'init':
            $visitorId = trim($_POST['visitor_id'] ?? '');
            if (!$visitorId) { echo json_encode(['error' => 'visitor_id requerido']); exit; }
            $stmt = $db->prepare("SELECT id FROM chat_conversaciones WHERE visitor_id = ? AND estado != 'cerrada' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$visitorId]);
            $conv = $stmt->fetch();
            if (!$conv) {
                $db->prepare("INSERT INTO chat_conversaciones (visitor_id, nombre, email, telefono, pagina_origen, ip) VALUES (?,?,?,?,?,?)")->execute([
                    $visitorId, trim($_POST['nombre'] ?? 'Visitante'), trim($_POST['email'] ?? ''), trim($_POST['telefono'] ?? ''),
                    trim($_POST['pagina'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? ''
                ]);
                $convId = $db->lastInsertId();
                $cfg = $db->query("SELECT mensaje_bienvenida FROM chat_config WHERE id = 1")->fetch();
                if ($cfg && $cfg['mensaje_bienvenida']) {
                    $db->prepare("INSERT INTO chat_mensajes (conversacion_id, emisor, mensaje) VALUES (?, 'sistema', ?)")->execute([$convId, $cfg['mensaje_bienvenida']]);
                }
            } else {
                $convId = $conv['id'];
            }
            echo json_encode(['success' => true, 'conversacion_id' => intval($convId)]);
            break;

        case 'send':
            $visitorId = trim($_POST['visitor_id'] ?? '');
            $mensaje = trim($_POST['mensaje'] ?? '');
            if (!$visitorId || !$mensaje) { echo json_encode(['error' => 'Datos incompletos']); exit; }
            $stmt = $db->prepare("SELECT id FROM chat_conversaciones WHERE visitor_id = ? AND estado != 'cerrada' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$visitorId]);
            $conv = $stmt->fetch();
            if (!$conv) { echo json_encode(['error' => 'Conversacion no encontrada']); exit; }
            $db->prepare("INSERT INTO chat_mensajes (conversacion_id, emisor, mensaje) VALUES (?, 'visitante', ?)")->execute([$conv['id'], $mensaje]);
            $db->prepare("UPDATE chat_conversaciones SET ultimo_mensaje = NOW(), estado = 'esperando' WHERE id = ?")->execute([$conv['id']]);
            echo json_encode(['success' => true]);
            break;

        case 'messages':
            $visitorId = trim($_GET['visitor_id'] ?? '');
            if (!$visitorId) { echo json_encode(['error' => 'visitor_id requerido']); exit; }
            $stmt = $db->prepare("SELECT id FROM chat_conversaciones WHERE visitor_id = ? AND estado != 'cerrada' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$visitorId]);
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
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
