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

// Verificar que la automatizacion existe
$stmt = $db->prepare("SELECT nombre FROM automatizaciones WHERE id = ?");
$stmt->execute([$id]);
$auto = $stmt->fetch();

if (!$auto) {
    setFlash('danger', 'Automatizacion no encontrada.');
    header('Location: index.php');
    exit;
}

// Eliminar automatizacion (acciones y log se eliminan por CASCADE)
$db->prepare("DELETE FROM automatizaciones WHERE id = ?")->execute([$id]);

registrarActividad('eliminar', 'automatizacion', $id, 'Automatizacion: ' . $auto['nombre']);
setFlash('success', 'Automatizacion "' . $auto['nombre'] . '" eliminada correctamente.');
header('Location: index.php');
exit;
