<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

// Toggle activo/inactivo (antes del header para poder redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'toggle') {
    verifyCsrf();
    $id = intval(post('id'));
    $stmt = $db->prepare("UPDATE automatizaciones SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    registrarActividad('actualizar', 'automatizacion', $id, 'Toggle estado automatizacion');
    setFlash('success', 'Estado de la automatizacion actualizado.');
    header('Location: index.php');
    exit;
}

$pageTitle = 'Automatizaciones';
require_once __DIR__ . '/../../includes/header.php';

// Obtener automatizaciones
$automatizaciones = $db->query("
    SELECT a.*, u.nombre as creador_nombre
    FROM automatizaciones a
    LEFT JOIN usuarios u ON a.created_by = u.id
    ORDER BY a.created_at DESC
")->fetchAll();

// Contar acciones por automatizacion
$accionesCount = [];
if (!empty($automatizaciones)) {
    $ids = array_column($automatizaciones, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT automatizacion_id, COUNT(*) as total FROM automatizacion_acciones WHERE automatizacion_id IN ($placeholders) GROUP BY automatizacion_id");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $accionesCount[$row['automatizacion_id']] = $row['total'];
    }
}

$triggerLabels = [
    'nuevo_cliente' => 'Nuevo cliente creado',
    'nueva_propiedad' => 'Nueva propiedad captada',
    'nueva_visita' => 'Visita programada',
    'visita_realizada' => 'Visita realizada',
    'tarea_vencida' => 'Tarea vencida',
    'pipeline_etapa_cambiada' => 'Cambio de etapa en pipeline',
    'nuevo_documento' => 'Documento subido',
    'manual' => 'Ejecucion manual',
];

$triggerIcons = [
    'nuevo_cliente' => 'bi-person-plus',
    'nueva_propiedad' => 'bi-house-add',
    'nueva_visita' => 'bi-calendar-plus',
    'visita_realizada' => 'bi-calendar-check',
    'tarea_vencida' => 'bi-exclamation-triangle',
    'pipeline_etapa_cambiada' => 'bi-arrow-right-circle',
    'nuevo_documento' => 'bi-file-earmark-arrow-up',
    'manual' => 'bi-hand-index',
];

$totalAutomatizaciones = count($automatizaciones);
?>

<!-- Barra de acciones -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="text-muted"><?= $totalAutomatizaciones ?> automatizacion<?= $totalAutomatizaciones !== 1 ? 'es' : '' ?></span>
    </div>
    <div>
        <a href="form.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nueva Automatizacion
        </a>
    </div>
</div>

<?php if (empty($automatizaciones)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-robot fs-1 d-block mb-3"></i>
    <h5>No hay automatizaciones creadas</h5>
    <p>Crea tu primera automatizacion para agilizar tus flujos de trabajo.</p>
    <a href="form.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Crear Automatizacion
    </a>
</div>
<?php else: ?>
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Trigger</th>
                    <th>Acciones</th>
                    <th>Estado</th>
                    <th>Ejecuciones</th>
                    <th>Ultima ejecucion</th>
                    <th class="text-end">Opciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($automatizaciones as $auto): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= sanitize($auto['nombre']) ?></div>
                        <?php if (!empty($auto['descripcion'])): ?>
                            <small class="text-muted"><?= sanitize(mb_strimwidth($auto['descripcion'], 0, 80, '...')) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <i class="bi <?= $triggerIcons[$auto['trigger_tipo']] ?? 'bi-lightning' ?>"></i>
                        <?= $triggerLabels[$auto['trigger_tipo']] ?? $auto['trigger_tipo'] ?>
                    </td>
                    <td>
                        <span class="badge bg-secondary"><?= $accionesCount[$auto['id']] ?? 0 ?> acciones</span>
                    </td>
                    <td>
                        <?php if ($auto['activo']): ?>
                            <span class="badge bg-success">Activa</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactiva</span>
                        <?php endif; ?>
                    </td>
                    <td><?= intval($auto['ejecuciones']) ?></td>
                    <td><?= $auto['ultima_ejecucion'] ? formatFechaHora($auto['ultima_ejecucion']) : '<span class="text-muted">Nunca</span>' ?></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="form.php?id=<?= $auto['id'] ?>"><i class="bi bi-pencil"></i> Editar</a></li>
                                <li><a class="dropdown-item" href="log.php?id=<?= $auto['id'] ?>"><i class="bi bi-list-check"></i> Ver log</a></li>
                                <li>
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="accion" value="toggle">
                                        <input type="hidden" name="id" value="<?= $auto['id'] ?>">
                                        <button type="submit" class="dropdown-item">
                                            <i class="bi bi-toggle-<?= $auto['activo'] ? 'on' : 'off' ?>"></i>
                                            <?= $auto['activo'] ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                </li>
                                <?php if ($auto['trigger_tipo'] === 'manual'): ?>
                                <li>
                                    <form method="POST" action="ejecutar.php" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $auto['id'] ?>">
                                        <button type="submit" class="dropdown-item">
                                            <i class="bi bi-play-fill"></i> Ejecutar
                                        </button>
                                    </form>
                                </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="delete.php?id=<?= $auto['id'] ?>&csrf=<?= csrfToken() ?>" data-confirm="Seguro que deseas eliminar esta automatizacion?"><i class="bi bi-trash"></i> Eliminar</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
