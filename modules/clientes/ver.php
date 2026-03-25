<?php
$pageTitle = 'Detalle Cliente';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = intval(get('id'));

$stmt = $db->prepare("SELECT c.*, u.nombre as agente_nombre, u.apellidos as agente_apellidos FROM clientes c LEFT JOIN usuarios u ON c.agente_id = u.id WHERE c.id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { setFlash('danger', 'Cliente no encontrado.'); header('Location: index.php'); exit; }

// Propiedades como propietario
$propiedades = $db->prepare("SELECT id, referencia, titulo, tipo, operacion, precio, estado FROM propiedades WHERE propietario_id = ? ORDER BY created_at DESC");
$propiedades->execute([$id]);
$propiedades = $propiedades->fetchAll();

// Visitas
$visitas = $db->prepare("SELECT v.*, p.referencia, p.titulo as propiedad FROM visitas v JOIN propiedades p ON v.propiedad_id = p.id WHERE v.cliente_id = ? ORDER BY v.fecha DESC LIMIT 10");
$visitas->execute([$id]);
$visitas = $visitas->fetchAll();

// Documentos
$docs = $db->prepare("SELECT * FROM documentos WHERE cliente_id = ? ORDER BY created_at DESC");
$docs->execute([$id]);
$docs = $docs->fetchAll();

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

$stmtMatch = $db->prepare($matchQuery);
$stmtMatch->execute($matchParams);
$matches = $stmtMatch->fetchAll();

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
        <a href="form.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="rgpd.php?id=<?= $id ?>" class="btn btn-outline-info"><i class="bi bi-shield-lock"></i> RGPD</a>
        <a href="delete.php?id=<?= $id ?>&csrf=<?= csrfToken() ?>" class="btn btn-outline-danger" data-confirm="Eliminar este cliente?"><i class="bi bi-trash"></i> Eliminar</a>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
