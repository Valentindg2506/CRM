<?php
$pageTitle = 'Documentos';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$filtroTipo = get('tipo');
$filtroBusqueda = get('q');
$page = max(1, intval(get('page', 1)));

$where = [];
$params = [];
if ($filtroTipo) { $where[] = 'd.tipo = ?'; $params[] = $filtroTipo; }
if ($filtroBusqueda) {
    $where[] = '(d.nombre LIKE ? OR p.referencia LIKE ? OR c.nombre LIKE ?)';
    $busq = '%' . $filtroBusqueda . '%';
    $params = array_merge($params, [$busq, $busq, $busq]);
}
$whereStr = empty($where) ? '1=1' : implode(' AND ', $where);

$stmtCount = $db->prepare("SELECT COUNT(*) FROM documentos d LEFT JOIN propiedades p ON d.propiedad_id = p.id LEFT JOIN clientes c ON d.cliente_id = c.id WHERE $whereStr");
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$pagination = paginate($total, 20, $page);

$stmtDoc = $db->prepare("SELECT d.*, p.referencia as prop_ref, p.titulo as prop_titulo, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos, u.nombre as subido_nombre
    FROM documentos d
    LEFT JOIN propiedades p ON d.propiedad_id = p.id
    LEFT JOIN clientes c ON d.cliente_id = c.id
    LEFT JOIN usuarios u ON d.subido_por = u.id
    WHERE $whereStr ORDER BY d.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmtDoc->execute($params);
$documentos = $stmtDoc->fetchAll();

$tiposDoc = [
    'contrato_arras'=>'Contrato arras','contrato_compraventa'=>'Compraventa','contrato_alquiler'=>'Contrato alquiler',
    'escritura'=>'Escritura','nota_simple'=>'Nota simple','certificado_energetico'=>'Cert. Energetico',
    'cedula_habitabilidad'=>'Cedula habitabilidad','ite'=>'ITE','licencia'=>'Licencia',
    'factura'=>'Factura','presupuesto'=>'Presupuesto','mandato'=>'Mandato','ficha_cliente'=>'Ficha cliente','otro'=>'Otro'
];
$baseUrl = 'index.php?tipo=' . urlencode($filtroTipo) . '&q=' . urlencode($filtroBusqueda);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= $total ?> documentos</span>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Subir Documento</a>
</div>

<div class="filter-bar">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Nombre, propiedad, cliente..." value="<?= sanitize($filtroBusqueda) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($tiposDoc as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filtroTipo === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Nombre</th><th>Tipo</th><th>Propiedad</th><th>Cliente</th><th>Subido por</th><th>Fecha</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($documentos as $d): ?>
            <tr>
                <td><i class="bi bi-file-earmark-text"></i> <strong><?= sanitize($d['nombre']) ?></strong></td>
                <td><?= $tiposDoc[$d['tipo']] ?? ucfirst(str_replace('_',' ',$d['tipo'])) ?></td>
                <td><?= $d['prop_ref'] ? '<a href="' . APP_URL . '/modules/propiedades/ver.php?id=' . $d['propiedad_id'] . '">' . sanitize($d['prop_ref']) . '</a>' : '-' ?></td>
                <td><?= $d['cliente_nombre'] ? '<a href="' . APP_URL . '/modules/clientes/ver.php?id=' . $d['cliente_id'] . '">' . sanitize($d['cliente_nombre'] . ' ' . $d['cliente_apellidos']) . '</a>' : '-' ?></td>
                <td><?= sanitize($d['subido_nombre'] ?? '-') ?></td>
                <td><?= formatFecha($d['created_at']) ?></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="<?= APP_URL ?>/assets/uploads/<?= sanitize($d['archivo']) ?>" class="btn btn-outline-primary" target="_blank"><i class="bi bi-download"></i></a>
                        <a href="delete.php?id=<?= $d['id'] ?>&csrf=<?= csrfToken() ?>" class="btn btn-outline-danger" data-confirm="Eliminar este documento?"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($documentos)): ?>
            <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-folder fs-1 d-block mb-2"></i>No hay documentos</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($pagination, $baseUrl) ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
