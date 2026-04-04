<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$id = intval($_POST['id'] ?? 0);
$csrf = $_POST['csrf_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { setFlash('danger', 'Metodo no permitido.'); header('Location: index.php'); exit; }
if (!$id || $csrf !== csrfToken()) { setFlash('danger', 'Solicitud no valida.'); header('Location: index.php'); exit; }
$db = getDB();
$docMeta = $db->prepare("SELECT subido_por FROM documentos WHERE id = ? LIMIT 1");
$docMeta->execute([$id]);
$subidoPor = $docMeta->fetchColumn();
if ($subidoPor === false) { setFlash('danger', 'Documento no encontrado.'); header('Location: index.php'); exit; }
if (!isAdmin() && intval($subidoPor) !== intval(currentUserId())) { setFlash('danger', 'No tienes permisos para eliminar este documento.'); header('Location: index.php'); exit; }
$doc = $db->prepare("SELECT archivo FROM documentos WHERE id = ?"); $doc->execute([$id]); $doc = $doc->fetch();
if ($doc) { deleteUpload($doc['archivo']); }
$db->prepare("DELETE FROM documentos WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'documento', $id);
setFlash('success', 'Documento eliminado.');
header('Location: index.php'); exit;
