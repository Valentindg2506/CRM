<?php
$pageTitle = 'Backup Base de Datos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/backup.php';
requireAdmin();

$accion = get('accion');
$db = getDB();

// Crear backup
if ($accion === 'crear') {
    $result = generarBackup();
    if (isset($result['success'])) {
        // Limpiar backups antiguos (mantener 10)
        limpiarBackupsAntiguos(10);
        registrarActividad('crear', 'backup', null, $result['filename']);
        setFlash('success', 'Backup creado correctamente: ' . $result['filename'] . ' (' . round($result['size'] / 1024, 1) . ' KB)');
    } else {
        setFlash('danger', 'Error al crear backup: ' . ($result['error'] ?? 'Error desconocido'));
    }
    header('Location: backup.php');
    exit;
}

// Descargar backup
if ($accion === 'descargar' && get('file')) {
    descargarBackup(get('file'));
    exit;
}

// Eliminar backup
if ($accion === 'eliminar' && get('file') && get('csrf') === csrfToken()) {
    if (eliminarBackup(get('file'))) {
        setFlash('success', 'Backup eliminado.');
    } else {
        setFlash('danger', 'No se pudo eliminar el backup.');
    }
    header('Location: backup.php');
    exit;
}

$backups = listarBackups();

// Info de la base de datos
$dbSize = $db->query("SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetch();
$tablesCount = $db->query("SELECT COUNT(*) as total FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetch();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="text-muted">
            Base de datos: <strong><?= DB_NAME ?></strong> |
            <?= $tablesCount['total'] ?> tablas |
            <?= round(($dbSize['size'] ?? 0) / 1024 / 1024, 2) ?> MB
        </span>
    </div>
    <a href="backup.php?accion=crear" class="btn btn-primary" onclick="this.innerHTML='<i class=\'bi bi-hourglass-split\'></i> Generando...'; this.classList.add('disabled');">
        <i class="bi bi-database-down"></i> Crear Backup Ahora
    </a>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-archive"></i> Backups Disponibles</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Archivo</th><th>Tamaño</th><th>Fecha</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($backups as $b): ?>
            <tr>
                <td><i class="bi bi-file-earmark-code"></i> <strong><?= sanitize($b['filename']) ?></strong></td>
                <td><?= round($b['size'] / 1024, 1) ?> KB</td>
                <td><?= date('d/m/Y H:i:s', $b['date']) ?></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="backup.php?accion=descargar&file=<?= urlencode($b['filename']) ?>" class="btn btn-outline-primary"><i class="bi bi-download"></i> Descargar</a>
                        <a href="backup.php?accion=eliminar&file=<?= urlencode($b['filename']) ?>&csrf=<?= csrfToken() ?>" class="btn btn-outline-danger" data-confirm="Eliminar este backup?"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($backups)): ?>
            <tr><td colspan="4" class="text-center text-muted py-5">
                <i class="bi bi-archive fs-1 d-block mb-2"></i>No hay backups. Crea el primero haciendo clic en el boton de arriba.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info mt-3">
    <i class="bi bi-info-circle"></i> <strong>Recomendacion:</strong> Programa backups regulares. Se mantienen los ultimos 10 backups automaticamente.
    En Hostinger puedes crear un cron job que llame a <code><?= APP_URL ?>/cron/backup.php?key=TU_CLAVE_SECRETA</code>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
