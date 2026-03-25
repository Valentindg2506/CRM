<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$id = intval($_GET['id'] ?? 0); $csrf = $_GET['csrf'] ?? '';
if (!$id || $csrf !== csrfToken()) { setFlash('danger', 'Solicitud no valida.'); header('Location: index.php'); exit; }
$db = getDB();
$doc = $db->prepare("SELECT archivo FROM documentos WHERE id = ?"); $doc->execute([$id]); $doc = $doc->fetch();
if ($doc) { deleteUpload($doc['archivo']); }
$db->prepare("DELETE FROM documentos WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'documento', $id);
setFlash('success', 'Documento eliminado.');
header('Location: index.php'); exit;
