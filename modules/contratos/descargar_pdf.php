<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(404);
    exit('Plantilla no encontrada.');
}

try {
    $stmt = $db->prepare("SELECT nombre, tipo, archivo_path, archivo_nombre FROM contrato_plantillas WHERE id = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$id]);
    $tpl = $stmt->fetch();
} catch (Throwable $e) {
    http_response_code(500);
    exit('No se pudo cargar la plantilla.');
}

if (!$tpl || ($tpl['tipo'] ?? 'texto') !== 'pdf' || empty($tpl['archivo_path'])) {
    http_response_code(404);
    exit('Plantilla PDF no disponible.');
}

$absolutePath = __DIR__ . '/../../' . ltrim($tpl['archivo_path'], '/');
if (!is_file($absolutePath)) {
    http_response_code(404);
    exit('Archivo PDF no encontrado.');
}

$filename = $tpl['archivo_nombre'] ?: ($tpl['nombre'] . '.pdf');
$filename = preg_replace('/[\r\n]+/', ' ', $filename);

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
