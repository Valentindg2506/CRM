<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$id = intval(get('id'));
$csrf = get('csrf');

if (!$id || $csrf !== csrfToken()) {
    setFlash('danger', 'Solicitud no valida.');
    header('Location: index.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM calendario_eventos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$id, currentUserId()]);
$evento = $stmt->fetch();

if (!$evento) {
    setFlash('danger', 'Evento no encontrado.');
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("DELETE FROM calendario_eventos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$id, currentUserId()]);

registrarActividad('eliminar', 'calendario_evento', $id, 'Evento eliminado: ' . $evento['titulo']);
setFlash('success', 'Evento eliminado correctamente.');
header('Location: index.php');
exit;
