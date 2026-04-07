<?php
$pageTitle = 'Prospectos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/export.php';

// Exportar CSV
if (get('accion') === 'exportar') {
    $db = getDB();
    $exportStmt = $db->query("SELECT p.*, u.nombre as agente_nombre FROM prospectos p LEFT JOIN usuarios u ON p.agente_id = u.id ORDER BY p.created_at DESC");
    exportarProspectos($exportStmt->fetchAll());
    exit;
}

$db = getDB();
$isAdm = isAdmin();

// Filtros
$filtroEtapa = get('etapa');
$filtroProvincia = get('provincia');
$filtroBusqueda = get('q');
$filtroActivo = get('activo', '1');
$filtroContacto = get('contacto');
$filtroPerPage = intval(get('per_page', 20));
$perPageOptions = [10, 20, 50, 100];
if (!in_array($filtroPerPage, $perPageOptions, true)) {
    $filtroPerPage = 20;
}
$page = max(1, intval(get('page', 1)));

$where = [];
$params = [];

if (!$isAdm) {
    $where[] = 'p.agente_id = ?';
    $params[] = currentUserId();
}
if ($filtroEtapa) {
    // Compatibilidad con etapas antiguas para no dejar fuera registros existentes.
    if ($filtroEtapa === 'en_seguimiento') {
        $where[] = 'p.etapa IN (?,?,?)';
        $params[] = 'en_seguimiento';
        $params[] = 'seguimiento';
        $params[] = 'en_negociacion';
    } else {
        $where[] = 'p.etapa = ?';
        $params[] = $filtroEtapa;
    }
}
if ($filtroProvincia) { $where[] = 'p.provincia = ?'; $params[] = $filtroProvincia; }
if ($filtroActivo !== '') { $where[] = 'p.activo = ?'; $params[] = $filtroActivo; }
if ($filtroBusqueda) {
    $where[] = '(p.nombre LIKE ? OR p.email LIKE ? OR p.telefono LIKE ? OR p.referencia LIKE ? OR p.direccion LIKE ?)';
    $busq = '%' . $filtroBusqueda . '%';
    $params = array_merge($params, [$busq, $busq, $busq, $busq, $busq]);
}

$orderBy = 'p.created_at DESC';
if ($filtroContacto === 'hoy') {
    $where[] = 'p.fecha_proximo_contacto = CURDATE()';
    $orderBy = "COALESCE(p.hora_contacto, '23:59:59') ASC, p.created_at DESC";
} elseif ($filtroContacto === 'vencidos') {
    $where[] = 'p.fecha_proximo_contacto < CURDATE()';
    $orderBy = "p.fecha_proximo_contacto ASC, COALESCE(p.hora_contacto, '23:59:59') ASC";
} elseif ($filtroContacto === 'semana') {
    $where[] = 'p.fecha_proximo_contacto BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
    $orderBy = "p.fecha_proximo_contacto ASC, COALESCE(p.hora_contacto, '23:59:59') ASC";
} elseif ($filtroContacto === 'sin_fecha') {
    $where[] = 'p.fecha_proximo_contacto IS NULL';
} elseif ($filtroContacto === 'proximos') {
    $where[] = 'p.fecha_proximo_contacto IS NOT NULL';
    $orderBy = "p.fecha_proximo_contacto ASC, COALESCE(p.hora_contacto, '23:59:59') ASC";
}

$whereStr = empty($where) ? '1=1' : implode(' AND ', $where);

$stmtCount = $db->prepare("SELECT COUNT(*) FROM prospectos p WHERE $whereStr");
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$pagination = paginate($total, $filtroPerPage, $page);

$stmtPros = $db->prepare("SELECT p.*, u.nombre as agente_nombre
    FROM prospectos p
    LEFT JOIN usuarios u ON p.agente_id = u.id
    WHERE $whereStr
    ORDER BY $orderBy
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmtPros->execute($params);
$prospectos = $stmtPros->fetchAll();

$provincias = getProvincias();
$baseUrl = 'index.php?etapa=' . urlencode($filtroEtapa) . '&provincia=' . urlencode($filtroProvincia) . '&q=' . urlencode($filtroBusqueda) . '&activo=' . urlencode($filtroActivo) . '&contacto=' . urlencode($filtroContacto) . '&per_page=' . urlencode((string)$filtroPerPage);

$etapas = [
    'nuevo_lead' => ['label' => 'Nuevo Lead', 'color' => '#06b6d4'],
    'contactado' => ['label' => 'Contactado', 'color' => '#64748b'],
    'en_seguimiento' => ['label' => 'En Seguimiento', 'color' => '#3b82f6'],
    'visita_programada' => ['label' => 'Visita Programada', 'color' => '#8b5cf6'],
    'captado' => ['label' => 'Captado', 'color' => '#10b981'],
    'descartado' => ['label' => 'Descartado', 'color' => '#ef4444'],
];

?>

<style>
@media (max-width: 767.98px) {
    .prospectos-head {
        align-items: stretch !important;
        gap: 10px;
    }

    .prospectos-head-count {
        font-size: 0.95rem;
    }

    .prospectos-head-actions {
        width: 100%;
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }

    .prospectos-head-actions .btn {
        width: 100%;
        padding: 0.5rem 0.4rem;
        font-size: 0.82rem;
        white-space: normal;
        line-height: 1.2;
    }

    .prospectos-head-actions .btn i {
        margin-right: 4px;
    }
}

@media (max-width: 575.98px) {
    .prospectos-head-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Stats rápidas por etapa -->
<div class="row g-3 mb-4">
    <?php
    $stmtStats = $db->query("SELECT
        CASE
            WHEN etapa IN ('seguimiento', 'en_negociacion') THEN 'en_seguimiento'
            ELSE etapa
        END AS etapa_normalizada,
        COUNT(*) as total
        FROM prospectos
        WHERE activo = 1
        GROUP BY etapa_normalizada");
    $statsByEtapa = [];
    while ($row = $stmtStats->fetch()) { $statsByEtapa[$row['etapa_normalizada']] = $row['total']; }
    foreach ($etapas as $eKey => $eData):
        $count = $statsByEtapa[$eKey] ?? 0;
    ?>
    <div class="col-6 col-md-2">
        <a href="index.php?etapa=<?= $eKey ?>&activo=1" class="stat-card d-block text-decoration-none" style="border-left: 3px solid <?= $eData['color'] ?>;">
            <div class="stat-value" style="font-size: 1.3rem;"><?= $count ?></div>
            <div class="stat-label" style="font-size: 0.7rem;"><?= $eData['label'] ?></div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="d-flex justify-content-between align-items-center flex-column flex-md-row mb-4 prospectos-head">
    <span class="text-muted prospectos-head-count"><?= $total ?> prospectos encontrados</span>
    <div class="d-flex gap-2 flex-wrap justify-content-end prospectos-head-actions">
        <a href="import.php" class="btn btn-outline-info"><i class="bi bi-upload"></i> Importar CSV</a>
        <a href="index.php?accion=exportar" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</a>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Prospecto</a>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Nombre, email, telefono, ref..." value="<?= sanitize($filtroBusqueda) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Etapa</label>
            <select name="etapa" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($etapas as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filtroEtapa === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                <?php endforeach; ?>
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
        <div class="col-md-2">
            <label class="form-label">Contacto</label>
            <select name="contacto" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="proximos" <?= $filtroContacto === 'proximos' ? 'selected' : '' ?>>Más próximo</option>
                <option value="hoy" <?= $filtroContacto === 'hoy' ? 'selected' : '' ?>>Hoy</option>
                <option value="vencidos" <?= $filtroContacto === 'vencidos' ? 'selected' : '' ?>>Vencidos</option>
                <option value="semana" <?= $filtroContacto === 'semana' ? 'selected' : '' ?>>Próx. 7 días</option>
                <option value="sin_fecha" <?= $filtroContacto === 'sin_fecha' ? 'selected' : '' ?>>Sin fecha</option>
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
        <div class="col-md-1">
            <label class="form-label">Por hoja</label>
            <select name="per_page" class="form-select form-select-sm">
                <?php foreach ($perPageOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $filtroPerPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
        </div>
    </form>
</div>

<!-- Bulk Actions Bar -->
<div id="bulkBar" class="alert alert-primary d-none align-items-center justify-content-between mb-3">
    <span><strong id="bulkCount">0</strong> prospectos seleccionados</span>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-arrow-repeat"></i> Cambiar Etapa
            </button>
            <ul class="dropdown-menu" id="bulkEtapaMenu">
                <?php foreach ($etapas as $k => $v): ?>
                <li><a class="dropdown-item" href="#" onclick="bulkChangeEtapa('<?= $k ?>');return false;">
                    <span class="badge me-1" style="background:<?= $v['color'] ?>">&nbsp;</span> <?= $v['label'] ?>
                </a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <button class="btn btn-sm btn-outline-danger" onclick="bulkDelete()">
            <i class="bi bi-trash"></i> Eliminar
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="bulkToggleActive(0)">
            <i class="bi bi-eye-slash"></i> Desactivar
        </button>
        <button class="btn btn-sm btn-outline-success" onclick="bulkToggleActive(1)">
            <i class="bi bi-eye"></i> Activar
        </button>
    </div>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th>Ref.</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Etapa</th>
                    <th>Tipo</th>
                    <th>Enlace</th>
                    <th>Dirección</th>
                    <th>Precio Est.</th>
                    <th>m²</th>
                    <th>Publicación</th>
                    <th>Próx. Contacto</th>
                    <?php if ($isAdm): ?>
                    <th>Agente</th>
                    <?php endif; ?>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prospectos as $pr): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input bulk-check" value="<?= $pr['id'] ?>"></td>
                    <td><strong class="text-primary"><?= sanitize($pr['referencia']) ?></strong></td>
                    <td>
                        <a href="ver.php?id=<?= $pr['id'] ?>">
                            <strong><?= sanitize($pr['nombre']) ?></strong>
                        </a>
                        <?php if (!$pr['activo']): ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pr['telefono']): ?>
                        <a href="tel:<?= sanitize($pr['telefono']) ?>"><?= sanitize($pr['telefono']) ?></a>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $etapaVista = $pr['etapa'];
                        if (in_array($etapaVista, ['seguimiento', 'en_negociacion'], true)) {
                            $etapaVista = 'en_seguimiento';
                        }
                        $etapaInfo = $etapas[$etapaVista] ?? ['label' => $pr['etapa'], 'color' => '#64748b'];
                        $etapaLabelTabla = $etapaInfo['label'] === 'En Seguimiento' ? 'Seguimiento' : $etapaInfo['label'];
                        ?>
                        <span class="badge-estado" style="background: <?= $etapaInfo['color'] ?>20; color: <?= $etapaInfo['color'] ?>; white-space: nowrap;">
                            <?= $etapaLabelTabla ?>
                        </span>
                    </td>
                    <td><?= sanitize($pr['tipo_propiedad'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($pr['enlace'])): ?>
                            <a href="<?= sanitize($pr['enlace']) ?>" target="_blank" rel="noopener" title="<?= sanitize($pr['enlace']) ?>" aria-label="Abrir enlace de propiedad">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        <?php else: ?>-
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize(mb_substr($pr['direccion'] ?? '-', 0, 25)) ?><?= mb_strlen($pr['direccion'] ?? '') > 25 ? '...' : '' ?></td>
                    <td class="fw-bold"><?= $pr['precio_estimado'] ? formatPrecio($pr['precio_estimado']) : '-' ?></td>
                    <td><?= $pr['superficie'] ? $pr['superficie'] . ' m²' : '-' ?></td>
                    <td>
                        <?php if (!empty($pr['fecha_publicacion_propiedad'])): ?>
                            <?php
                            $fechaPublicacion = new DateTime($pr['fecha_publicacion_propiedad']);
                            $hoyPublicacion = new DateTime('today');
                            $diasPublicada = max(0, intval($fechaPublicacion->diff($hoyPublicacion)->format('%a')));
                            ?>
                            <div><?= formatFecha($pr['fecha_publicacion_propiedad']) ?></div>
                            <small class="text-muted"><?= $diasPublicada ?> días</small>
                        <?php else: ?>-
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pr['fecha_proximo_contacto']): ?>
                            <?php
                            $proxDate = new DateTime($pr['fecha_proximo_contacto']);
                            $today = new DateTime('today');
                            $isPast = $proxDate < $today;
                            $isToday = $proxDate->format('Y-m-d') === $today->format('Y-m-d');
                            $horaProxContacto = !empty($pr['hora_contacto']) ? substr($pr['hora_contacto'], 0, 5) : '--:--';
                            ?>
                            <span class="<?= $isPast ? 'text-danger fw-bold' : ($isToday ? 'text-warning fw-bold' : '') ?>">
                                <?= formatFecha($pr['fecha_proximo_contacto']) ?>
                            </span>
                            <br><small class="text-muted"><i class="bi bi-clock"></i> <?= $horaProxContacto ?></small>
                            <?php if ($isPast): ?><i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Vencido"></i><?php endif; ?>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <?php if ($isAdm): ?>
                    <td><?= sanitize($pr['agente_nombre'] ?? '-') ?></td>
                    <?php endif; ?>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="ver.php?id=<?= $pr['id'] ?>" class="btn btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
                            <a href="form.php?id=<?= $pr['id'] ?>" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="delete.php" onsubmit="return confirm('Seguro que deseas eliminar este prospecto?')" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="id" value="<?= intval($pr['id']) ?>">
                                <button type="submit" class="btn btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($prospectos)): ?>
                <tr><td colspan="<?= $isAdm ? 14 : 13 ?>" class="text-center text-muted py-5">
                    <i class="bi bi-person-plus fs-1 d-block mb-2"></i>No se encontraron prospectos
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($pagination, $baseUrl) ?>

<script>
const selectAll = document.getElementById('selectAll');
const bulkBar = document.getElementById('bulkBar');
const bulkCount = document.getElementById('bulkCount');
const checks = document.querySelectorAll('.bulk-check');
const csrfToken = '<?= csrfToken() ?>';

function updateBulkBar() {
    const selected = document.querySelectorAll('.bulk-check:checked');
    if (selected.length > 0) {
        bulkBar.classList.remove('d-none');
        bulkBar.classList.add('d-flex');
        bulkCount.textContent = selected.length;
    } else {
        bulkBar.classList.add('d-none');
        bulkBar.classList.remove('d-flex');
    }
}

selectAll.addEventListener('change', function() {
    checks.forEach(c => c.checked = this.checked);
    updateBulkBar();
});
checks.forEach(c => c.addEventListener('change', updateBulkBar));

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.bulk-check:checked')).map(c => c.value);
}

function bulkAction(accion, extra = {}) {
    const ids = getSelectedIds();
    if (!ids.length) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'bulk.php';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
        '<input type="hidden" name="accion" value="' + accion + '">' +
        '<input type="hidden" name="ids" value="' + ids.join(',') + '">';
    for (const [k, v] of Object.entries(extra)) {
        form.innerHTML += '<input type="hidden" name="' + k + '" value="' + v + '">';
    }
    document.body.appendChild(form);
    form.submit();
}

function bulkDelete() {
    if (confirm('Seguro que deseas eliminar los prospectos seleccionados?')) {
        bulkAction('eliminar');
    }
}

function bulkToggleActive(val) {
    bulkAction('toggle_activo', { activo: val });
}

function bulkChangeEtapa(etapa) {
    bulkAction('cambiar_etapa', { etapa: etapa });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
