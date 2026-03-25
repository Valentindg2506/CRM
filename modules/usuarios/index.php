<?php
$pageTitle = 'Gestion de Usuarios';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$db = getDB();
$usuarios = $db->query("SELECT * FROM usuarios ORDER BY nombre")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($usuarios) ?> usuarios</span>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Usuario</a>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Nombre</th><th>Email</th><th>Telefono</th><th>Rol</th><th>Estado</th><th>Ultimo Acceso</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><strong><?= sanitize($u['nombre'] . ' ' . $u['apellidos']) ?></strong></td>
                <td><?= sanitize($u['email']) ?></td>
                <td><?= sanitize($u['telefono'] ?? '-') ?></td>
                <td><span class="badge bg-<?= $u['rol'] === 'admin' ? 'danger' : 'primary' ?>"><?= ucfirst($u['rol']) ?></span></td>
                <td><?= $u['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
                <td><?= $u['ultimo_acceso'] ? formatFechaHora($u['ultimo_acceso']) : 'Nunca' ?></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="form.php?id=<?= $u['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        <?php if ($u['id'] !== currentUserId()): ?>
                        <a href="delete.php?id=<?= $u['id'] ?>&csrf=<?= csrfToken() ?>" class="btn btn-outline-danger" data-confirm="Eliminar este usuario?"><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
