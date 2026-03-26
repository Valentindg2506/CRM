<?php
$pageTitle = 'WhatsApp Chat';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

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
            $stmt = $db->prepare("UPDATE whatsapp_mensajes SET cliente_id = ? WHERE telefono = ?");
            $stmt->execute([$clienteId, $telefono]);
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
    $stmtCliente = $db->prepare("SELECT cliente_id FROM whatsapp_mensajes WHERE telefono = ? AND cliente_id IS NOT NULL LIMIT 1");
    $stmtCliente->execute([$telefono]);
    $clienteId = $stmtCliente->fetchColumn() ?: null;

    // Guardar mensaje en BD (en produccion, aqui se llamaria a la API de WhatsApp)
    $stmt = $db->prepare("
        INSERT INTO whatsapp_mensajes (cliente_id, telefono, direccion, mensaje, tipo, estado, created_by, created_at)
        VALUES (?, ?, 'saliente', ?, 'text', 'enviado', ?, NOW())
    ");
    $stmt->execute([$clienteId, $telefono, $mensaje, currentUserId()]);

    registrarActividad('enviar', 'whatsapp_mensaje', $db->lastInsertId(), 'Mensaje a ' . $telefono);

    header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
    exit;
}

// Si se accede por GET sin telefono, redirigir al index
header('Location: ' . APP_URL . '/modules/whatsapp/index.php');
exit;
?>
