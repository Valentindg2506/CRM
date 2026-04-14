<?php
/**
 * Webhook endpoint para WhatsApp Business API
 * No requiere sesion/login, solo verificacion por token
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();

// GET: Verificacion del webhook
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? '');
    $token = $_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? '');
    $challenge = $_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? '');
    $stmtVerify = $db->prepare("SELECT id FROM whatsapp_config WHERE webhook_verify_token = ? LIMIT 1");
    $stmtVerify->execute([trim((string)$token)]);
    $configVerify = $stmtVerify->fetch();

    if (!$configVerify || $mode !== 'subscribe') {
        http_response_code(400);
        echo json_encode(['error' => 'Verificacion fallida']);
        exit;
    }

    http_response_code(200);
    echo $challenge;
    exit;
}

// POST: Recibir mensajes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $secret = getEnvSecret('WHATSAPP_APP_SECRET', '');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if ($secret !== '') {
        $expected = 'sha256=' . hash_hmac('sha256', $input, $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) {
            http_response_code(403);
            echo json_encode(['error' => 'Firma invalida']);
            exit;
        }
    }

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
            $metadata = $value['metadata'] ?? [];
            $phoneNumberId = trim((string)($metadata['phone_number_id'] ?? ''));

            $config = null;
            if ($phoneNumberId !== '') {
                $stmtConfig = $db->prepare("SELECT * FROM whatsapp_config WHERE activo = 1 AND phone_number_id = ? ORDER BY id DESC LIMIT 1");
                $stmtConfig->execute([$phoneNumberId]);
                $config = $stmtConfig->fetch();
            }

            if (!$config) {
                continue;
            }

            $ownerUserId = isset($config['updated_by']) ? (int)$config['updated_by'] : 0;
            if ($ownerUserId <= 0) {
                continue;
            }

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
                    $stmtPrev = $db->prepare("SELECT cliente_id FROM whatsapp_mensajes WHERE telefono = ? AND created_by = ? AND cliente_id IS NOT NULL LIMIT 1");
                    $stmtPrev->execute([$from, $ownerUserId]);
                    $prev = $stmtPrev->fetch();
                    if ($prev) {
                        $clienteId = $prev['cliente_id'];
                    }
                }

                // Guardar mensaje
                $stmt = $db->prepare("
                    INSERT INTO whatsapp_mensajes (cliente_id, telefono, direccion, mensaje, tipo, wa_message_id, estado, created_by, created_at)
                    VALUES (?, ?, 'entrante', ?, ?, ?, 'recibido', ?, FROM_UNIXTIME(?))
                ");
                $stmt->execute([$clienteId, $from, $contenido, $msgType, $waMessageId, $ownerUserId, $timestamp]);
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
                    $stmtUpdate = $db->prepare("UPDATE whatsapp_mensajes SET estado = ? WHERE wa_message_id = ? AND created_by = ?");
                    $stmtUpdate->execute([$nuevoEstado, $waId, $ownerUserId]);
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
