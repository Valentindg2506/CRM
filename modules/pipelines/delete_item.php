<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$id = intval($_GET['id'] ?? 0);
$pipelineId = intval($_GET['pipeline_id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!$id || $csrf !== csrfToken()) {
    setFlash('danger', 'Solicitud no valida.');
    header('Location: index.php');
    exit;
}

$db = getDB();

// Verificar que el item existe
$stmt = $db->prepare("SELECT titulo, pipeline_id FROM pipeline_items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('danger', 'Item no encontrado.');
    header('Location: index.php');
    exit;
}

$pipelineId = $item['pipeline_id'];

$db->prepare("DELETE FROM pipeline_items WHERE id = ?")->execute([$id]);

registrarActividad('eliminar', 'pipeline_item', $id, 'Item: ' . $item['titulo']);
setFlash('success', 'Item eliminado correctamente.');
header('Location: ver.php?id=' . $pipelineId);
exit;
