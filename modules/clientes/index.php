<?php
$pageTitle = 'Clientes';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/export.php';

// Exportar CSV
if (get('accion') === 'exportar') {
    $db = getDB();
    $exportStmt = $db->query("SELECT c.*, u.nombre as agente_nombre FROM clientes c LEFT JOIN usuarios u ON c.agente_id = u.id ORDER BY c.created_at DESC");
    exportarClientes($exportStmt->fetchAll());
    exit;
}

$db = getDB();
$isAdm = isAdmin();

// Filtros
$filtroTipo = get('tipo');
$filtroOrigen = get('origen');
$filtroProvincia = get('provincia');
$filtroBusqueda = get('q');
$filtroActivo = get('activo', '1');
$page = max(1, intval(get('page', 1)));

$where = [];
$params = [];

if (!$isAdm) {
    $where[] = 'c.agente_id = ?';
    $params[] = currentUserId();
}
if ($filtroTipo) { $where[] = 'FIND_IN_SET(?, c.tipo)'; $params[] = $filtroTipo; }
if ($filtroOrigen) { $where[] = 'c.origen = ?'; $params[] = $filtroOrigen; }
if ($filtroProvincia) { $where[] = 'c.provincia = ?'; $params[] = $filtroProvincia; }
if ($filtroActivo !== '') { $where[] = 'c.activo = ?'; $params[] = $filtroActivo; }
if ($filtroBusqueda) {
    $where[] = '(c.nombre LIKE ? OR c.apellidos LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.dni_nie_cif LIKE ?)';
    $busq = '%' . $filtroBusqueda . '%';
    $params = array_merge($params, [$busq, $busq, $busq, $busq, $busq]);
}

$whereStr = empty($where) ? '1=1' : implode(' AND ', $where);

$stmtCount = $db->prepare("SELECT COUNT(*) FROM clientes c WHERE $whereStr");
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$pagination = paginate($total, 20, $page);

$stmtCli = $db->prepare("SELECT c.*, u.nombre as agente_nombre
    FROM clientes c
    LEFT JOIN usuarios u ON c.agente_id = u.id
    WHERE $whereStr
    ORDER BY c.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmtCli->execute($params);
$clientes = $stmtCli->fetchAll();

$provincias = getProvincias();
$baseUrl = 'index.php?tipo=' . urlencode($filtroTipo) . '&origen=' . urlencode($filtroOrigen) . '&provincia=' . urlencode($filtroProvincia) . '&q=' . urlencode($filtroBusqueda) . '&activo=' . urlencode($filtroActivo);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= $total ?> clientes encontrados</span>
    <div class="d-flex gap-2">
        <a href="index.php?accion=exportar" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</a>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Cliente</a>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Nombre, email, telefono, DNI..." value="<?= sanitize($filtroBusqueda) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="comprador" <?= $filtroTipo === 'comprador' ? 'selected' : '' ?>>Comprador</option>
                <option value="vendedor" <?= $filtroTipo === 'vendedor' ? 'selected' : '' ?>>Vendedor</option>
                <option value="inquilino" <?= $filtroTipo === 'inquilino' ? 'selected' : '' ?>>Inquilino</option>
                <option value="propietario" <?= $filtroTipo === 'propietario' ? 'selected' : '' ?>>Propietario</option>
                <option value="inversor" <?= $filtroTipo === 'inversor' ? 'selected' : '' ?>>Inversor</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Origen</label>
            <select name="origen" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="web" <?= $filtroOrigen === 'web' ? 'selected' : '' ?>>Web</option>
                <option value="telefono" <?= $filtroOrigen === 'telefono' ? 'selected' : '' ?>>Telefono</option>
                <option value="oficina" <?= $filtroOrigen === 'oficina' ? 'selected' : '' ?>>Oficina</option>
                <option value="referido" <?= $filtroOrigen === 'referido' ? 'selected' : '' ?>>Referido</option>
                <option value="portal" <?= $filtroOrigen === 'portal' ? 'selected' : '' ?>>Portal</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Provincia</label>
            <select name="provincia" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($provincias as $prov): ?>
                <option value="<?= $prov ?>" <?= $filtroProvincia === $prov ? 'selected' : '' ?>><?= $prov ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">Activo</label>
            <select name="activo" class="form-select form-select-sm">
                <option value="1" <?= $filtroActivo === '1' ? 'selected' : '' ?>>Si</option>
                <option value="0" <?= $filtroActivo === '0' ? 'selected' : '' ?>>No</option>
                <option value="" <?= $filtroActivo === '' ? 'selected' : '' ?>>Todos</option>
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
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Email</th>
                    <th>Telefono</th>
                    <th>DNI/NIE/CIF</th>
                    <th>Provincia</th>
                    <th>Agente</th>
                    <th>Fecha Alta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td>
                        <a href="ver.php?id=<?= $c['id'] ?>">
                            <strong><?= sanitize($c['nombre'] . ' ' . $c['apellidos']) ?></strong>
                        </a>
                        <?php if (!$c['activo']): ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
                    </td>
                    <td>
                        <?php foreach (explode(',', $c['tipo']) as $t): ?>
                        <span class="badge bg-outline-primary border"><?= ucfirst(trim($t)) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><?= sanitize($c['email'] ?? '-') ?></td>
                    <td><?= sanitize($c['telefono'] ?? '-') ?></td>
                    <td><?= sanitize($c['dni_nie_cif'] ?? '-') ?></td>
                    <td><?= sanitize($c['provincia'] ?? '-') ?></td>
                    <td><?= sanitize($c['agente_nombre'] ?? '-') ?></td>
                    <td><?= formatFecha($c['created_at']) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="ver.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
                            <a href="form.php?id=<?= $c['id'] ?>" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <a href="delete.php?id=<?= $c['id'] ?>&csrf=<?= csrfToken() ?>" class="btn btn-outline-danger" title="Eliminar" data-confirm="Seguro que deseas eliminar este cliente?"><i class="bi bi-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($clientes)): ?>
                <tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="bi bi-people fs-1 d-block mb-2"></i>No se encontraron clientes
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($pagination, $baseUrl) ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
