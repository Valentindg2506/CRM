<?php
$pageTitle = 'Detalle Cliente';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = intval(get('id'));

function clienteSafeFetchAll(PDO $db, $sql, array $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

$stmt = $db->prepare("SELECT c.*, u.nombre as agente_nombre, u.apellidos as agente_apellidos FROM clientes c LEFT JOIN usuarios u ON c.agente_id = u.id WHERE c.id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { setFlash('danger', 'Cliente no encontrado.'); header('Location: index.php'); exit; }

// Propiedades como propietario
$propiedades = clienteSafeFetchAll($db, "SELECT id, referencia, titulo, tipo, operacion, precio, estado FROM propiedades WHERE propietario_id = ? ORDER BY created_at DESC", [$id]);

// Visitas
$visitas = clienteSafeFetchAll($db, "SELECT v.*, p.referencia, p.titulo as propiedad FROM visitas v JOIN propiedades p ON v.propiedad_id = p.id WHERE v.cliente_id = ? ORDER BY v.fecha DESC LIMIT 10", [$id]);

// Documentos
$docs = clienteSafeFetchAll($db, "SELECT * FROM documentos WHERE cliente_id = ? ORDER BY created_at DESC", [$id]);

// Propiedades compatibles (matching)
$matchQuery = "SELECT p.id, p.referencia, p.titulo, p.tipo, p.precio, p.localidad, p.provincia, p.habitaciones, p.superficie_construida
    FROM propiedades p WHERE p.estado = 'disponible'";
$matchParams = [];

if ($c['operacion_interes'] && $c['operacion_interes'] !== 'ambas') {
    $matchQuery .= " AND p.operacion = ?";
    $matchParams[] = $c['operacion_interes'];
}
if ($c['presupuesto_max']) {
    $matchQuery .= " AND p.precio <= ?";
    $matchParams[] = $c['presupuesto_max'];
}
if ($c['presupuesto_min']) {
    $matchQuery .= " AND p.precio >= ?";
    $matchParams[] = $c['presupuesto_min'];
}
if ($c['habitaciones_min']) {
    $matchQuery .= " AND p.habitaciones >= ?";
    $matchParams[] = $c['habitaciones_min'];
}
if ($c['superficie_min']) {
    $matchQuery .= " AND p.superficie_construida >= ?";
    $matchParams[] = $c['superficie_min'];
}
$matchQuery .= " ORDER BY p.created_at DESC LIMIT 10";

$matches = clienteSafeFetchAll($db, $matchQuery, $matchParams);

// Tags (opcional)
$clienteTags = clienteSafeFetchAll($db, "SELECT t.id, t.nombre, t.color FROM tags t JOIN cliente_tags ct ON t.id = ct.tag_id WHERE ct.cliente_id = ? ORDER BY t.nombre", [$id]);

// Campos personalizados (opcional)
$customFields = clienteSafeFetchAll($db, "SELECT cf.*, cfv.valor FROM custom_fields cf LEFT JOIN custom_field_values cfv ON cf.id = cfv.field_id AND cfv.entidad_id = ? WHERE cf.entidad = 'cliente' AND cf.activo = 1 ORDER BY cf.orden", [$id]);

$tipos = getTiposPropiedad();
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <?php foreach (explode(',', $c['tipo']) as $t): ?>
        <span class="badge bg-primary"><?= ucfirst(trim($t)) ?></span>
        <?php endforeach; ?>
        <?php if (!$c['activo']): ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <a href="timeline.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> Timeline</a>
        <a href="form.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="rgpd.php?id=<?= $id ?>" class="btn btn-outline-info"><i class="bi bi-shield-lock"></i> RGPD</a>
        <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Eliminar este cliente?')">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= intval($id) ?>">
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Eliminar</button>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <!-- Info personal -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person"></i> Datos Personales</div>
            <div class="card-body">
                <h5><?= sanitize($c['nombre'] . ' ' . $c['apellidos']) ?></h5>
                <?php if ($c['dni_nie_cif']): ?>
                <p class="mb-2"><i class="bi bi-card-text"></i> <?= sanitize($c['dni_nie_cif']) ?></p>
                <?php endif; ?>
                <?php if ($c['email']): ?>
                <p class="mb-2"><i class="bi bi-envelope"></i> <a href="mailto:<?= sanitize($c['email']) ?>"><?= sanitize($c['email']) ?></a></p>
                <?php endif; ?>
                <?php if ($c['telefono']): ?>
                <p class="mb-2"><i class="bi bi-telephone"></i> <a href="tel:<?= sanitize($c['telefono']) ?>"><?= sanitize($c['telefono']) ?></a></p>
                <?php endif; ?>
                <?php if ($c['telefono2']): ?>
                <p class="mb-2"><i class="bi bi-telephone"></i> <?= sanitize($c['telefono2']) ?></p>
                <?php endif; ?>
                <?php if ($c['direccion']): ?>
                <p class="mb-2"><i class="bi bi-geo-alt"></i> <?= sanitize($c['direccion']) ?>, <?= sanitize($c['localidad'] ?? '') ?> <?= sanitize($c['provincia'] ?? '') ?></p>
                <?php endif; ?>
                <p class="mb-0 text-muted"><small>Origen: <?= ucfirst($c['origen']) ?> | Alta: <?= formatFecha($c['created_at']) ?></small></p>
            </div>
        </div>

        <!-- Preferencias -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-search"></i> Preferencias</div>
            <div class="card-body">
                <div class="row g-2">
                    <?php if ($c['operacion_interes']): ?>
                    <div class="col-6"><div class="detail-label">Operacion</div><div class="detail-value"><?= ucfirst($c['operacion_interes']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($c['tipo_inmueble_interes']): ?>
                    <div class="col-6"><div class="detail-label">Tipo</div><div class="detail-value"><?= sanitize($c['tipo_inmueble_interes']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($c['presupuesto_min'] || $c['presupuesto_max']): ?>
                    <div class="col-12"><div class="detail-label">Presupuesto</div><div class="detail-value"><?= $c['presupuesto_min'] ? formatPrecio($c['presupuesto_min']) : '0' ?> - <?= $c['presupuesto_max'] ? formatPrecio($c['presupuesto_max']) : 'sin limite' ?></div></div>
                    <?php endif; ?>
                    <?php if ($c['zona_interes']): ?>
                    <div class="col-12"><div class="detail-label">Zona</div><div class="detail-value"><?= sanitize($c['zona_interes']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($c['habitaciones_min']): ?>
                    <div class="col-6"><div class="detail-label">Hab. min</div><div class="detail-value"><?= $c['habitaciones_min'] ?></div></div>
                    <?php endif; ?>
                    <?php if ($c['superficie_min']): ?>
                    <div class="col-6"><div class="detail-label">Sup. min</div><div class="detail-value"><?= formatSuperficie($c['superficie_min']) ?></div></div>
                    <?php endif; ?>
                </div>
                <?php if (!$c['operacion_interes'] && !$c['zona_interes']): ?>
                <p class="text-muted mb-0">Sin preferencias definidas</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notas -->
        <?php if ($c['notas']): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-chat-text"></i> Notas</div>
            <div class="card-body"><?= nl2br(sanitize($c['notas'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- Tags -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-tags"></i> Etiquetas</span>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTags"><i class="bi bi-plus"></i></button>
            </div>
            <div class="card-body" id="tagsContainer">
                <?php
                if (empty($clienteTags)): ?>
                <span class="text-muted" id="noTagsMsg">Sin etiquetas</span>
                <?php else:
                    foreach ($clienteTags as $tag): ?>
                <span class="badge me-1 mb-1" style="background:<?= sanitize($tag['color']) ?>; font-size: 0.8rem;">
                    <?= sanitize($tag['nombre']) ?>
                    <a href="#" class="text-white ms-1" onclick="quitarTag(<?= $tag['id'] ?>); return false;" title="Quitar">&times;</a>
                </span>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Campos Personalizados -->
        <?php
        if (!empty($customFields)): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-ui-checks-grid"></i> Campos Personalizados</div>
            <div class="card-body">
                <?php foreach ($customFields as $cf): ?>
                <div class="mb-2">
                    <div class="detail-label"><?= sanitize($cf['nombre']) ?></div>
                    <div class="detail-value">
                        <?php if ($cf['tipo'] === 'checkbox'): ?>
                            <?= $cf['valor'] ? '<i class="bi bi-check-circle text-success"></i> Si' : '<span class="text-muted">No</span>' ?>
                        <?php else: ?>
                            <?= sanitize($cf['valor'] ?? '-') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Agente -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person-badge"></i> Agente Asignado</div>
            <div class="card-body">
                <strong><?= sanitize(($c['agente_nombre'] ?? '') . ' ' . ($c['agente_apellidos'] ?? '')) ?></strong>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Propiedades compatibles -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-house-heart"></i> Propiedades Compatibles (<?= count($matches) ?>)</div>
            <div class="card-body p-0">
                <?php if (empty($matches)): ?>
                <p class="text-muted text-center py-4">No se encontraron propiedades compatibles</p>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead><tr><th>Ref.</th><th>Titulo</th><th>Tipo</th><th>Precio</th><th>Ubicacion</th><th>Hab.</th></tr></thead>
                    <tbody>
                    <?php foreach ($matches as $m): ?>
                    <tr onclick="location.href='<?= APP_URL ?>/modules/propiedades/ver.php?id=<?= $m['id'] ?>'" style="cursor:pointer">
                        <td><strong><?= sanitize($m['referencia']) ?></strong></td>
                        <td><?= sanitize(mb_substr($m['titulo'], 0, 30)) ?></td>
                        <td><?= $tipos[$m['tipo']] ?? $m['tipo'] ?></td>
                        <td class="fw-bold"><?= formatPrecio($m['precio']) ?></td>
                        <td><?= sanitize($m['localidad']) ?>, <?= sanitize($m['provincia']) ?></td>
                        <td><?= $m['habitaciones'] ?? '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Propiedades como propietario -->
        <?php if (!empty($propiedades)): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-house-door"></i> Propiedades (Propietario)</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Ref.</th><th>Titulo</th><th>Operacion</th><th>Precio</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($propiedades as $pr): ?>
                    <tr onclick="location.href='<?= APP_URL ?>/modules/propiedades/ver.php?id=<?= $pr['id'] ?>'" style="cursor:pointer">
                        <td><strong><?= sanitize($pr['referencia']) ?></strong></td>
                        <td><?= sanitize($pr['titulo']) ?></td>
                        <td><?= ucfirst($pr['operacion']) ?></td>
                        <td><?= formatPrecio($pr['precio']) ?></td>
                        <td><span class="badge-estado badge-<?= $pr['estado'] ?>"><?= ucfirst($pr['estado']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Visitas -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-event"></i> Visitas (<?= count($visitas) ?>)</span>
                <a href="<?= APP_URL ?>/modules/visitas/form.php?cliente_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">+ Programar</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($visitas)): ?>
                <p class="text-muted text-center py-3">Sin visitas registradas</p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Fecha</th><th>Propiedad</th><th>Estado</th><th>Valoracion</th></tr></thead>
                    <tbody>
                    <?php foreach ($visitas as $v): ?>
                    <tr>
                        <td><?= formatFecha($v['fecha']) ?> <?= substr($v['hora'], 0, 5) ?></td>
                        <td><a href="<?= APP_URL ?>/modules/propiedades/ver.php?id=<?= $v['propiedad_id'] ?>"><?= sanitize($v['referencia']) ?> - <?= sanitize($v['propiedad']) ?></a></td>
                        <td><span class="badge-estado badge-<?= $v['estado'] ?>"><?= ucfirst(str_replace('_',' ',$v['estado'])) ?></span></td>
                        <td><?= $v['valoracion'] ? str_repeat('&#9733;', $v['valoracion']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tags -->
<div class="modal fade" id="modalTags" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-tags"></i> Gestionar Tags</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="tagsList" class="mb-3">Cargando...</div>
                <hr>
                <h6 class="small text-muted">Crear nuevo tag</h6>
                <div class="input-group input-group-sm">
                    <input type="text" id="nuevoTagNombre" class="form-control" placeholder="Nombre" maxlength="50">
                    <input type="color" id="nuevoTagColor" class="form-control form-control-color" value="#10b981" style="max-width:40px">
                    <button class="btn btn-primary" onclick="crearTag()"><i class="bi bi-plus"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const clienteId = <?= $id ?>;
const csrf = '<?= csrfToken() ?>';

function quitarTag(tagId) {
    fetch('tags.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=quitar_tag&cliente_id=' + clienteId + '&tag_id=' + tagId + '&csrf_token=' + csrf
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
}

function agregarTag(tagId) {
    fetch('tags.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=agregar_tag&cliente_id=' + clienteId + '&tag_id=' + tagId + '&csrf_token=' + csrf
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
}

function crearTag() {
    const nombre = document.getElementById('nuevoTagNombre').value.trim();
    const color = document.getElementById('nuevoTagColor').value;
    if (!nombre) return;
    fetch('tags.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=crear_tag&nombre=' + encodeURIComponent(nombre) + '&color=' + encodeURIComponent(color) + '&csrf_token=' + csrf
    }).then(r => r.json()).then(d => {
        if (d.success && d.tag) agregarTag(d.tag.id);
    });
}

// Cargar tags en modal
document.getElementById('modalTags').addEventListener('show.bs.modal', function() {
    fetch('tags.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=listar_tags&csrf_token=' + csrf
    }).then(r => r.json()).then(data => {
        const list = document.getElementById('tagsList');
        if (data.tags && data.tags.length) {
            list.innerHTML = data.tags.map(t =>
                '<a href="#" class="badge me-1 mb-1 text-decoration-none" style="background:' + t.color + '; font-size:0.8rem;" onclick="agregarTag(' + t.id + ');return false;">' +
                t.nombre + ' <i class="bi bi-plus-circle"></i></a>'
            ).join('');
        } else {
            list.innerHTML = '<span class="text-muted">No hay tags creados</span>';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
