<?php
$pageTitle = 'Configurar Pipeline';
require_once __DIR__ . '/../../includes/header.php';

$id = intval(get('id'));
if (!$id) {
    setFlash('danger', 'Pipeline no especificada.');
    header('Location: index.php');
    exit;
}

$db = getDB();

function pipelineEtapasHasConversionColumn(PDO $db): bool {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM pipeline_etapas LIKE 'permitir_conversion'");
        return (bool) $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

$hasConversionColumn = pipelineEtapasHasConversionColumn($db);

// Obtener pipeline
$stmt = $db->prepare("SELECT * FROM pipelines WHERE id = ?");
$stmt->execute([$id]);
$pipeline = $stmt->fetch();

if (!$pipeline) {
    setFlash('danger', 'Pipeline no encontrada.');
    header('Location: index.php');
    exit;
}

// Actualizar pipeline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'actualizar_pipeline') {
    verifyCsrf();
    $nombre = post('nombre');
    $descripcion = post('descripcion');
    $color = post('color', '#10b981');

    if (empty($nombre)) {
        setFlash('danger', 'El nombre es obligatorio.');
        header('Location: config.php?id=' . $id);
        exit;
    }

    $stmtUpd = $db->prepare("UPDATE pipelines SET nombre = ?, descripcion = ?, color = ?, updated_at = NOW() WHERE id = ?");
    $stmtUpd->execute([$nombre, $descripcion, $color, $id]);
    registrarActividad('editar', 'pipeline', $id, 'Pipeline: ' . $nombre);
    setFlash('success', 'Pipeline actualizada correctamente.');
    header('Location: config.php?id=' . $id);
    exit;
}

// Anadir etapa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'nueva_etapa') {
    verifyCsrf();
    $nombreEtapa = post('nombre_etapa');
    $colorEtapa = post('color_etapa', '#64748b');

    if (empty($nombreEtapa)) {
        setFlash('danger', 'El nombre de la etapa es obligatorio.');
        header('Location: config.php?id=' . $id);
        exit;
    }

    // Obtener el orden maximo
    $maxOrden = $db->prepare("SELECT COALESCE(MAX(orden), -1) FROM pipeline_etapas WHERE pipeline_id = ?");
    $maxOrden->execute([$id]);
    $nuevoOrden = $maxOrden->fetchColumn() + 1;

    $stmtIns = $db->prepare("INSERT INTO pipeline_etapas (pipeline_id, nombre, color, orden) VALUES (?, ?, ?, ?)");
    $stmtIns->execute([$id, $nombreEtapa, $colorEtapa, $nuevoOrden]);
    registrarActividad('crear', 'pipeline_etapa', $db->lastInsertId(), 'Etapa: ' . $nombreEtapa);
    setFlash('success', 'Etapa anadida correctamente.');
    header('Location: config.php?id=' . $id);
    exit;
}

// Eliminar etapa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'eliminar_etapa') {
    verifyCsrf();
    $etapaId = intval(post('etapa_id'));

    // Verificar que no tenga items
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM pipeline_items WHERE etapa_id = ?");
    $stmtCount->execute([$etapaId]);
    $itemsCount = $stmtCount->fetchColumn();

    if ($itemsCount > 0) {
        setFlash('danger', 'No se puede eliminar una etapa que tiene ' . $itemsCount . ' item(s). Mueve los items primero.');
        header('Location: config.php?id=' . $id);
        exit;
    }

    $db->prepare("DELETE FROM pipeline_etapas WHERE id = ? AND pipeline_id = ?")->execute([$etapaId, $id]);
    registrarActividad('eliminar', 'pipeline_etapa', $etapaId);
    setFlash('success', 'Etapa eliminada correctamente.');
    header('Location: config.php?id=' . $id);
    exit;
}

// Configurar si la etapa permite conversion prospecto->cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'toggle_conversion_etapa') {
    verifyCsrf();

    if (!$hasConversionColumn) {
        setFlash('danger', 'Esta instalacion no soporta configuracion de conversion por etapa. Ejecuta install_pipelines.php.');
        header('Location: config.php?id=' . $id);
        exit;
    }

    $etapaId = intval(post('etapa_id'));
    $permitir = intval(post('permitir_conversion')) === 1 ? 1 : 0;
    $stmtToggle = $db->prepare("UPDATE pipeline_etapas SET permitir_conversion = ? WHERE id = ? AND pipeline_id = ?");
    $stmtToggle->execute([$permitir, $etapaId, $id]);
    setFlash('success', 'Configuracion de conversion actualizada.');
    header('Location: config.php?id=' . $id);
    exit;
}

// Reordenar etapas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'reordenar') {
    verifyCsrf();
    $ordenJson = $_POST['orden'] ?? '';
    $orden = json_decode($ordenJson, true);

    if (is_array($orden)) {
        $stmtOrd = $db->prepare("UPDATE pipeline_etapas SET orden = ? WHERE id = ? AND pipeline_id = ?");
        foreach ($orden as $pos => $etapaId) {
            $stmtOrd->execute([$pos, intval($etapaId), $id]);
        }
        setFlash('success', 'Orden de etapas actualizado.');
    }
    header('Location: config.php?id=' . $id);
    exit;
}

// Recargar pipeline despues de posibles cambios
$stmt->execute([$id]);
$pipeline = $stmt->fetch();

// Obtener etapas
if ($hasConversionColumn) {
    $stmtEtapas = $db->prepare("SELECT e.*, (SELECT COUNT(*) FROM pipeline_items WHERE etapa_id = e.id) as total_items FROM pipeline_etapas e WHERE e.pipeline_id = ? ORDER BY e.orden ASC");
    $stmtEtapas->execute([$id]);
    $etapas = $stmtEtapas->fetchAll();
} else {
    $stmtEtapas = $db->prepare("SELECT e.*, 0 AS permitir_conversion, (SELECT COUNT(*) FROM pipeline_items WHERE etapa_id = e.id) as total_items FROM pipeline_etapas e WHERE e.pipeline_id = ? ORDER BY e.orden ASC");
    $stmtEtapas->execute([$id]);
    $etapas = $stmtEtapas->fetchAll();
}
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0">Configurar: <?= sanitize($pipeline['nombre']) ?></h5>
</div>

<div class="row g-4">
    <!-- Datos de la pipeline -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent">
                <h6 class="mb-0"><i class="bi bi-sliders"></i> Datos de la Pipeline</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="actualizar_pipeline">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="100"
                            value="<?= sanitize($pipeline['nombre']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripcion</label>
                        <textarea name="descripcion" class="form-control" rows="2" maxlength="500"><?= sanitize($pipeline['descripcion']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color" class="form-control form-control-color"
                            value="<?= sanitize($pipeline['color']) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg"></i> Guardar Cambios</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Etapas -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-columns-gap"></i> Etapas</h6>
                <span class="badge bg-secondary"><?= count($etapas) ?> etapas</span>
            </div>
            <div class="card-body">
                <!-- Lista de etapas existentes -->
                <?php if (empty($etapas)): ?>
                <p class="text-muted text-center py-3">No hay etapas configuradas.</p>
                <?php else: ?>
                <div class="list-group mb-3" id="etapasList">
                    <?php foreach ($etapas as $i => $etapa): ?>
                    <div class="list-group-item d-flex align-items-center gap-3" data-etapa-id="<?= $etapa['id'] ?>">
                        <span class="text-muted"><i class="bi bi-grip-vertical"></i></span>
                        <span class="rounded-circle d-inline-block" style="width:16px; height:16px; background:<?= sanitize($etapa['color']) ?>; flex-shrink:0;"></span>
                        <div class="flex-grow-1">
                            <strong><?= sanitize($etapa['nombre']) ?></strong>
                            <small class="text-muted ms-2"><?= $etapa['total_items'] ?> item<?= $etapa['total_items'] != 1 ? 's' : '' ?></small>
                            <?php if ($hasConversionColumn): ?>
                                <div class="small mt-1">
                                    <span class="badge <?= !empty($etapa['permitir_conversion']) ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= !empty($etapa['permitir_conversion']) ? 'Permite conversion' : 'Sin conversion' ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-1">
                            <?php if ($hasConversionColumn): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="accion" value="toggle_conversion_etapa">
                                <input type="hidden" name="etapa_id" value="<?= intval($etapa['id']) ?>">
                                <input type="hidden" name="permitir_conversion" value="<?= !empty($etapa['permitir_conversion']) ? '0' : '1' ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-sm <?= !empty($etapa['permitir_conversion']) ? 'btn-outline-success' : 'btn-outline-secondary' ?>" title="Activar/desactivar conversion prospecto->cliente">
                                    <i class="bi bi-person-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($i > 0): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="accion" value="reordenar">
                                <?= csrfField() ?>
                                <?php
                                    $newOrder = $etapas;
                                    $temp = $newOrder[$i];
                                    $newOrder[$i] = $newOrder[$i - 1];
                                    $newOrder[$i - 1] = $temp;
                                    $orderIds = array_map(fn($e) => $e['id'], $newOrder);
                                ?>
                                <input type="hidden" name="orden" value='<?= json_encode($orderIds) ?>'>
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Subir"><i class="bi bi-arrow-up"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($i < count($etapas) - 1): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="accion" value="reordenar">
                                <?= csrfField() ?>
                                <?php
                                    $newOrder2 = $etapas;
                                    $temp2 = $newOrder2[$i];
                                    $newOrder2[$i] = $newOrder2[$i + 1];
                                    $newOrder2[$i + 1] = $temp2;
                                    $orderIds2 = array_map(fn($e) => $e['id'], $newOrder2);
                                ?>
                                <input type="hidden" name="orden" value='<?= json_encode($orderIds2) ?>'>
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Bajar"><i class="bi bi-arrow-down"></i></button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar esta etapa?')">
                                <input type="hidden" name="accion" value="eliminar_etapa">
                                <input type="hidden" name="etapa_id" value="<?= $etapa['id'] ?>">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Formulario para nueva etapa -->
                <div class="border rounded p-3 bg-light">
                    <h6 class="mb-3"><i class="bi bi-plus-circle"></i> Anadir Etapa</h6>
                    <form method="POST">
                        <input type="hidden" name="accion" value="nueva_etapa">
                        <?= csrfField() ?>
                        <div class="row g-2 align-items-end">
                            <div class="col">
                                <label class="form-label small">Nombre</label>
                                <input type="text" name="nombre_etapa" class="form-control form-control-sm" required maxlength="100" placeholder="Nombre de la etapa">
                            </div>
                            <div class="col-auto">
                                <label class="form-label small">Color</label>
                                <input type="color" name="color_etapa" class="form-control form-control-sm form-control-color" value="#64748b">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Anadir</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
