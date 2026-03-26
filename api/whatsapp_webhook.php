<?php
/**
 * Webhook endpoint para WhatsApp Business API
 * No requiere sesion/login, solo verificacion por token
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();

// Obtener configuracion
$config = $db->query("SELECT * FROM whatsapp_config WHERE activo = 1 LIMIT 1")->fetch();

// GET: Verificacion del webhook
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $config && $token === $config['webhook_verify_token']) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo json_encode(['error' => 'Verificacion fallida']);
    exit;
}

// POST: Recibir mensajes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$config) {
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Integracion no activa']);
        exit;
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }

    // Parsear payload de WhatsApp Cloud API
    $entries = $data['entry'] ?? [];
    foreach ($entries as $entry) {
        $changes = $entry['changes'] ?? [];
        foreach ($changes as $change) {
            $value = $change['value'] ?? [];
            $messages = $value['messages'] ?? [];
            $contacts = $value['contacts'] ?? [];

            foreach ($messages as $message) {
                $from = $message['from'] ?? '';
                $msgType = $message['type'] ?? 'text';
                $waMessageId = $message['id'] ?? null;
                $timestamp = $message['timestamp'] ?? time();

                // Obtener contenido del mensaje segun tipo
                $contenido = '';
                switch ($msgType) {
                    case 'text':
                        $contenido = $message['text']['body'] ?? '';
                        break;
                    case 'image':
                        $contenido = '[Imagen]' . (!empty($message['image']['caption']) ? ' ' . $message['image']['caption'] : '');
                        $msgType = 'image';
                        break;
                    case 'document':
                        $contenido = '[Documento]' . (!empty($message['document']['filename']) ? ' ' . $message['document']['filename'] : '');
                        $msgType = 'document';
                        break;
                    default:
                        $contenido = '[' . ucfirst($msgType) . ']';
                        $msgType = 'text';
                        break;
                }

                if (empty($from) || empty($contenido)) {
                    continue;
                }

                // Intentar vincular con cliente por telefono
                $clienteId = null;
                $stmtCliente = $db->prepare("SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '+', ''), '-', '') LIKE ? LIMIT 1");
                $telefonoLimpio = preg_replace('/[^0-9]/', '', $from);
                $stmtCliente->execute(['%' . substr($telefonoLimpio, -9) . '%']);
                $cliente = $stmtCliente->fetch();
                if ($cliente) {
                    $clienteId = $cliente['id'];
                }

                // Tambien verificar si ya hay mensajes previos vinculados con este telefono
                if (!$clienteId) {
                    $stmtPrev = $db->prepare("SELECT cliente_id FROM whatsapp_mensajes WHERE telefono = ? AND cliente_id IS NOT NULL LIMIT 1");
                    $stmtPrev->execute([$from]);
                    $prev = $stmtPrev->fetch();
                    if ($prev) {
                        $clienteId = $prev['cliente_id'];
                    }
                }

                // Guardar mensaje
                $stmt = $db->prepare("
                    INSERT INTO whatsapp_mensajes (cliente_id, telefono, direccion, mensaje, tipo, wa_message_id, estado, created_by, created_at)
                    VALUES (?, ?, 'entrante', ?, ?, ?, 'recibido', NULL, FROM_UNIXTIME(?))
                ");
                $stmt->execute([$clienteId, $from, $contenido, $msgType, $waMessageId, $timestamp]);
            }

            // Procesar actualizaciones de estado
            $statuses = $value['statuses'] ?? [];
            foreach ($statuses as $status) {
                $waId = $status['id'] ?? '';
                $statusValue = $status['status'] ?? '';

                $estadoMap = [
                    'sent' => 'enviado',
                    'delivered' => 'entregado',
                    'read' => 'leido',
                    'failed' => 'fallido',
                ];

                $nuevoEstado = $estadoMap[$statusValue] ?? null;
                if ($nuevoEstado && !empty($waId)) {
                    $stmtUpdate = $db->prepare("UPDATE whatsapp_mensajes SET estado = ? WHERE wa_message_id = ?");
                    $stmtUpdate->execute([$nuevoEstado, $waId]);
                }
            }
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Metodo no permitido']);
