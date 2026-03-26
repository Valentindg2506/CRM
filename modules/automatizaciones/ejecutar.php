<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verifyCsrf();

$id = intval(post('id'));
if (!$id) {
    setFlash('danger', 'Automatizacion no especificada.');
    header('Location: index.php');
    exit;
}

$db = getDB();

// Verificar que la automatizacion existe y es de tipo manual
$stmt = $db->prepare("SELECT * FROM automatizaciones WHERE id = ?");
$stmt->execute([$id]);
$auto = $stmt->fetch();

if (!$auto) {
    setFlash('danger', 'Automatizacion no encontrada.');
    header('Location: index.php');
    exit;
}

if ($auto['trigger_tipo'] !== 'manual') {
    setFlash('danger', 'Esta automatizacion no es de ejecucion manual.');
    header('Location: index.php');
    exit;
}

if (!$auto['activo']) {
    setFlash('warning', 'La automatizacion esta desactivada. Activala antes de ejecutarla.');
    header('Location: index.php');
    exit;
}

// Insert log entry
$stmtLog = $db->prepare("INSERT INTO automatizacion_log (automatizacion_id, estado, detalles, entidad_tipo) VALUES (?, 'exito', ?, 'manual')");
$stmtLog->execute([$id, 'Ejecucion manual por ' . currentUserName()]);

// Update execution count and last execution date
$stmtUpd = $db->prepare("UPDATE automatizaciones SET ejecuciones = ejecuciones + 1, ultima_ejecucion = NOW() WHERE id = ?");
$stmtUpd->execute([$id]);

registrarActividad('ejecutar', 'automatizacion', $id, 'Ejecucion manual: ' . $auto['nombre']);
setFlash('success', 'Automatizacion "' . sanitize($auto['nombre']) . '" ejecutada correctamente.');
header('Location: index.php');
exit;
