<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireAdmin();
$id = intval($_GET['id'] ?? 0); $csrf = $_GET['csrf'] ?? '';
if (!$id || $csrf !== csrfToken() || $id === currentUserId()) {
    setFlash('danger', 'No puedes eliminar tu propia cuenta.'); header('Location: index.php'); exit;
}
$db = getDB();
$db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'usuario', $id);
setFlash('success', 'Usuario eliminado.');
header('Location: index.php'); exit;
