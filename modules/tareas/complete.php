<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$id = intval($_GET['id'] ?? 0); $csrf = $_GET['csrf'] ?? '';
if (!$id || $csrf !== csrfToken()) { setFlash('danger', 'Solicitud no valida.'); header('Location: index.php'); exit; }
$db = getDB();
$db->prepare("UPDATE tareas SET estado = 'completada', fecha_completada = NOW() WHERE id = ?")->execute([$id]);
registrarActividad('completar', 'tarea', $id);
setFlash('success', 'Tarea completada.');
header('Location: index.php'); exit;
