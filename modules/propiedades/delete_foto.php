<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$id = intval($_GET['id'] ?? 0);
$propId = intval($_GET['prop'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!$id || $csrf !== csrfToken()) {
    setFlash('danger', 'Solicitud no valida.');
    header('Location: form.php?id=' . $propId);
    exit;
}

$db = getDB();
$foto = $db->prepare("SELECT archivo FROM propiedad_fotos WHERE id = ?");
$foto->execute([$id]);
$foto = $foto->fetch();

if ($foto) {
    deleteUpload($foto['archivo']);
    $db->prepare("DELETE FROM propiedad_fotos WHERE id = ?")->execute([$id]);
}

setFlash('success', 'Foto eliminada.');
header('Location: form.php?id=' . $propId);
exit;
