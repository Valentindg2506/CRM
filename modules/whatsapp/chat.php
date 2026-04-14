<?php
$pageTitle = 'WhatsApp Chat';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$userId = (int) currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $telefono = post('telefono');
    $accion = post('accion');

    if (empty($telefono)) {
        setFlash('danger', 'Telefono no especificado.');
        header('Location: ' . APP_URL . '/modules/whatsapp/index.php');
        exit;
    }

    // Vincular cliente
    if ($accion === 'vincular') {
        $clienteId = post('cliente_id');
        if (!empty($clienteId)) {
            $stmt = $db->prepare("UPDATE whatsapp_mensajes SET cliente_id = ? WHERE telefono = ? AND created_by = ?");
            $stmt->execute([$clienteId, $telefono, $userId]);
            registrarActividad('vincular', 'whatsapp', $clienteId, 'Vincular telefono ' . $telefono . ' a cliente');
            setFlash('success', 'Cliente vinculado correctamente.');
        } else {
            setFlash('danger', 'Debes seleccionar un cliente.');
        }
        header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
        exit;
    }

    // Enviar mensaje
    $mensaje = post('mensaje');

    if (empty($mensaje)) {
        setFlash('danger', 'El mensaje no puede estar vacio.');
        header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
        exit;
    }

    // Obtener cliente vinculado a este telefono
    $stmtCliente = $db->prepare("SELECT cliente_id FROM whatsapp_mensajes WHERE telefono = ? AND created_by = ? AND cliente_id IS NOT NULL LIMIT 1");
    $stmtCliente->execute([$telefono, $userId]);
    $clienteId = $stmtCliente->fetchColumn() ?: null;

    // Cargar configuracion activa de WhatsApp Cloud API
    $stmtConfig = $db->prepare("SELECT phone_number_id, access_token, activo FROM whatsapp_config WHERE activo = 1 AND updated_by = ? ORDER BY id DESC LIMIT 1");
    $stmtConfig->execute([$userId]);
    $waConfig = $stmtConfig->fetch();

    if (!$waConfig || empty($waConfig['phone_number_id']) || empty($waConfig['access_token'])) {
        header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
        exit;
    }

    $telefonoDestino = preg_replace('/[^0-9]/', '', (string)$telefono);
    if ($telefonoDestino === '') {
        header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
        exit;
    }

    $waEstado = 'fallido';
    $waMessageId = null;
    $errorEnvio = '';

    if (!function_exists('curl_init')) {
        $errorEnvio = 'cURL no esta habilitado en el servidor.';
    } else {
        $endpoint = 'https://graph.facebook.com/v21.0/' . rawurlencode((string)$waConfig['phone_number_id']) . '/messages';
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $telefonoDestino,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $mensaje,
            ],
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $waConfig['access_token'],
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = is_string($responseBody) ? json_decode($responseBody, true) : null;

        if ($curlError !== '') {
            $errorEnvio = 'Error de conexion: ' . $curlError;
        } elseif ($httpCode >= 200 && $httpCode < 300 && !empty($responseData['messages'][0]['id'])) {
            $waEstado = 'enviado';
            $waMessageId = $responseData['messages'][0]['id'];
        } else {
            $apiMessage = $responseData['error']['message'] ?? '';
            $apiCode = isset($responseData['error']['code']) ? (string)$responseData['error']['code'] : '';
            $detalle = trim($apiCode !== '' ? ('Codigo ' . $apiCode . ': ' . $apiMessage) : $apiMessage);
            $errorEnvio = $detalle !== '' ? $detalle : ('Respuesta HTTP ' . $httpCode . ' no valida desde Meta.');
        }
    }

    // Guardar siempre en BD para mantener el historial y estado real de envio
    $stmt = $db->prepare("
        INSERT INTO whatsapp_mensajes (cliente_id, telefono, direccion, mensaje, tipo, wa_message_id, estado, created_by, created_at)
        VALUES (?, ?, 'saliente', ?, 'text', ?, ?, ?, NOW())
    ");
    $stmt->execute([$clienteId, $telefono, $mensaje, $waMessageId, $waEstado, $userId]);

    registrarActividad('enviar', 'whatsapp_mensaje', $db->lastInsertId(), 'Mensaje a ' . $telefono);

    header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
    exit;
}

// Si se accede por GET sin telefono, redirigir al index
header('Location: ' . APP_URL . '/modules/whatsapp/index.php');
exit;
?>
