<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$id = intval($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

if (!$id || $csrf !== csrfToken()) {
    setFlash('danger', 'Solicitud no valida.');
    header('Location: index.php');
    exit;
}

$db = getDB();

// Eliminar fotos del disco
$fotos = $db->prepare("SELECT archivo FROM propiedad_fotos WHERE propiedad_id = ?");
$fotos->execute([$id]);
foreach ($fotos->fetchAll() as $foto) {
    deleteUpload($foto['archivo']);
}

$db->prepare("DELETE FROM propiedades WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'propiedad', $id);
setFlash('success', 'Propiedad eliminada correctamente.');
header('Location: index.php');
exit;
