<?php
$pageTitle = 'Item de Pipeline';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$itemId = intval(get('id'));
$pipelineId = intval(get('pipeline_id'));
$item = null;

// Si estamos editando, cargar item
if ($itemId) {
    $stmt = $db->prepare("SELECT * FROM pipeline_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        setFlash('danger', 'Item no encontrado.');
        header('Location: index.php');
        exit;
    }
    $pipelineId = $item['pipeline_id'];
}

if (!$pipelineId) {
    setFlash('danger', 'Pipeline no especificada.');
    header('Location: index.php');
    exit;
}

// Verificar que la pipeline existe
$stmtP = $db->prepare("SELECT * FROM pipelines WHERE id = ?");
$stmtP->execute([$pipelineId]);
$pipeline = $stmtP->fetch();
if (!$pipeline) {
    setFlash('danger', 'Pipeline no encontrada.');
    header('Location: index.php');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $titulo = post('titulo');
    $etapaId = intval(post('etapa_id'));
    $propiedadId = intval(post('propiedad_id')) ?: null;
    $clienteId = intval(post('cliente_id')) ?: null;
    $valor = post('valor') !== '' ? floatval(str_replace(',', '.', post('valor'))) : null;
    $notas = post('notas');
    $prioridad = post('prioridad', 'media');

    if (empty($titulo) || !$etapaId) {
        setFlash('danger', 'El titulo y la etapa son obligatorios.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Validar prioridad
    if (!in_array($prioridad, ['baja', 'media', 'alta'])) {
        $prioridad = 'media';
    }

    if ($itemId) {
        // Actualizar
        $stmt = $db->prepare("UPDATE pipeline_items SET titulo = ?, etapa_id = ?, propiedad_id = ?, cliente_id = ?, valor = ?, notas = ?, prioridad = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$titulo, $etapaId, $propiedadId, $clienteId, $valor, $notas, $prioridad, $itemId]);
        registrarActividad('editar', 'pipeline_item', $itemId, 'Item: ' . $titulo);
        setFlash('success', 'Item actualizado correctamente.');
    } else {
        // Crear
        $stmt = $db->prepare("INSERT INTO pipeline_items (pipeline_id, etapa_id, titulo, propiedad_id, cliente_id, valor, notas, prioridad, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$pipelineId, $etapaId, $titulo, $propiedadId, $clienteId, $valor, $notas, $prioridad, currentUserId()]);
        registrarActividad('crear', 'pipeline_item', $db->lastInsertId(), 'Item: ' . $titulo);
        setFlash('success', 'Item creado correctamente.');
    }

    header('Location: ver.php?id=' . $pipelineId);
    exit;
}

// Obtener etapas de la pipeline
$stmtEtapas = $db->prepare("SELECT * FROM pipeline_etapas WHERE pipeline_id = ? ORDER BY orden ASC");
$stmtEtapas->execute([$pipelineId]);
$etapas = $stmtEtapas->fetchAll();

// Obtener clientes para el select
$clientes = $db->query("SELECT id, nombre, apellidos FROM clientes ORDER BY nombre ASC")->fetchAll();

// Obtener propiedades para el select
$isAdm = isAdmin();
if ($isAdm) {
    $propiedades = $db->query("SELECT id, referencia, titulo FROM propiedades WHERE estado = 'disponible' ORDER BY referencia ASC")->fetchAll();
} else {
    $stmtProp = $db->prepare("SELECT id, referencia, titulo FROM propiedades WHERE estado = 'disponible' AND agente_id = ? ORDER BY referencia ASC");
    $stmtProp->execute([currentUserId()]);
    $propiedades = $stmtProp->fetchAll();
}

// Pre-seleccionar etapa si viene por GET
$preEtapa = intval(get('etapa_id'));
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="ver.php?id=<?= $pipelineId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0"><?= $itemId ? 'Editar' : 'Nuevo' ?> Item - <?= sanitize($pipeline['nombre']) ?></h5>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Titulo <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" required maxlength="200"
                            value="<?= $item ? sanitize($item['titulo']) : '' ?>"
                            placeholder="Titulo del item">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Etapa <span class="text-danger">*</span></label>
                            <select name="etapa_id" class="form-select" required>
                                <option value="">-- Seleccionar etapa --</option>
                                <?php foreach ($etapas as $etapa): ?>
                                <option value="<?= $etapa['id'] ?>"
                                    <?= ($item && $item['etapa_id'] == $etapa['id']) || $preEtapa == $etapa['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($etapa['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prioridad</label>
                            <select name="prioridad" class="form-select">
                                <option value="baja" <?= ($item && $item['prioridad'] === 'baja') ? 'selected' : '' ?>>Baja</option>
                                <option value="media" <?= (!$item || $item['prioridad'] === 'media') ? 'selected' : '' ?>>Media</option>
                                <option value="alta" <?= ($item && $item['prioridad'] === 'alta') ? 'selected' : '' ?>>Alta</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Cliente <small class="text-muted">(opcional)</small></label>
                            <select name="cliente_id" class="form-select">
                                <option value="">-- Sin cliente --</option>
                                <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($item && $item['cliente_id'] == $c['id']) ? 'selected' : '' ?>>
                                    <?= sanitize($c['nombre'] . ' ' . $c['apellidos']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Propiedad <small class="text-muted">(opcional)</small></label>
                            <select name="propiedad_id" class="form-select">
                                <option value="">-- Sin propiedad --</option>
                                <?php foreach ($propiedades as $prop): ?>
                                <option value="<?= $prop['id'] ?>" <?= ($item && $item['propiedad_id'] == $prop['id']) ? 'selected' : '' ?>>
                                    <?= sanitize($prop['referencia'] . ' - ' . mb_substr($prop['titulo'], 0, 40)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label">Valor <small class="text-muted">(opcional)</small></label>
                        <div class="input-group">
                            <input type="text" name="valor" class="form-control"
                                value="<?= $item && $item['valor'] ? number_format($item['valor'], 2, ',', '.') : '' ?>"
                                placeholder="0,00">
                            <span class="input-group-text">&euro;</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notas <small class="text-muted">(opcional)</small></label>
                        <textarea name="notas" class="form-control" rows="3" placeholder="Notas adicionales..."><?= $item ? sanitize($item['notas']) : '' ?></textarea>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="ver.php?id=<?= $pipelineId ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-<?= $itemId ? 'check-lg' : 'plus-lg' ?>"></i>
                            <?= $itemId ? 'Guardar Cambios' : 'Crear Item' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
