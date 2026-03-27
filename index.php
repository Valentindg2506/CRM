<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$userId = currentUserId();
$isAdm = isAdmin();

// KPIs principales
$totalPropiedades = getCount('propiedades', $isAdm ? '1=1' : 'agente_id = ?', $isAdm ? [] : [$userId]);
$propDisponibles = getCount('propiedades', $isAdm ? 'estado = "disponible"' : 'estado = "disponible" AND agente_id = ?', $isAdm ? [] : [$userId]);
$totalClientes = getCount('clientes', $isAdm ? '1=1' : 'agente_id = ?', $isAdm ? [] : [$userId]);
$visitasHoy = getCount('visitas', $isAdm ? 'fecha = CURDATE()' : 'fecha = CURDATE() AND agente_id = ?', $isAdm ? [] : [$userId]);
$visitasMes = getCount('visitas', $isAdm ? 'MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())' : 'MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND agente_id = ?', $isAdm ? [] : [$userId]);
$tareasPendientes = getCount('tareas', $isAdm ? 'estado IN ("pendiente","en_progreso")' : 'estado IN ("pendiente","en_progreso") AND asignado_a = ?', $isAdm ? [] : [$userId]);
$tareasVencidas = getCount('tareas', $isAdm ? 'estado = "pendiente" AND fecha_vencimiento < NOW()' : 'estado = "pendiente" AND fecha_vencimiento < NOW() AND asignado_a = ?', $isAdm ? [] : [$userId]);

// Finanzas del mes
$stmtFin = $db->prepare("SELECT
    COALESCE(SUM(CASE WHEN estado = 'cobrado' AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) THEN importe_total ELSE 0 END), 0) as cobrado_mes,
    COALESCE(SUM(CASE WHEN estado = 'pendiente' THEN importe_total ELSE 0 END), 0) as pendiente_total
    FROM finanzas WHERE tipo IN ('comision_venta','comision_alquiler','honorarios')" .
    ($isAdm ? '' : ' AND agente_id = ?'));
$stmtFin->execute($isAdm ? [] : [$userId]);
$finanzas = $stmtFin->fetch();

// Propiedades por estado
$stmtEstados = $db->query("SELECT estado, COUNT(*) as total FROM propiedades GROUP BY estado");
$estadosProp = $stmtEstados->fetchAll(PDO::FETCH_KEY_PAIR);

// Ultimas propiedades
$stmtUltProp = $db->prepare("SELECT p.*, u.nombre as agente_nombre FROM propiedades p LEFT JOIN usuarios u ON p.agente_id = u.id ORDER BY p.created_at DESC LIMIT 5");
$stmtUltProp->execute();
$ultimasPropiedades = $stmtUltProp->fetchAll();

// Proximas visitas
$stmtVisitas = $db->prepare("SELECT v.*, p.titulo as propiedad, p.referencia, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos
    FROM visitas v
    JOIN propiedades p ON v.propiedad_id = p.id
    JOIN clientes c ON v.cliente_id = c.id
    WHERE v.fecha >= CURDATE() AND v.estado = 'programada'" .
    ($isAdm ? '' : ' AND v.agente_id = ?') .
    " ORDER BY v.fecha, v.hora LIMIT 5");
$stmtVisitas->execute($isAdm ? [] : [$userId]);
$proximasVisitas = $stmtVisitas->fetchAll();

// Tareas urgentes
$stmtTareas = $db->prepare("SELECT t.*, p.referencia as prop_ref, c.nombre as cliente_nombre
    FROM tareas t
    LEFT JOIN propiedades p ON t.propiedad_id = p.id
    LEFT JOIN clientes c ON t.cliente_id = c.id
    WHERE t.estado IN ('pendiente','en_progreso')" .
    ($isAdm ? '' : ' AND t.asignado_a = ?') .
    " ORDER BY FIELD(t.prioridad, 'urgente','alta','media','baja'), t.fecha_vencimiento ASC LIMIT 5");
$stmtTareas->execute($isAdm ? [] : [$userId]);
$tareasUrgentes = $stmtTareas->fetchAll();

// Actividad reciente
$stmtAct = $db->prepare("SELECT a.*, u.nombre as usuario_nombre FROM actividad_log a LEFT JOIN usuarios u ON a.usuario_id = u.id ORDER BY a.created_at DESC LIMIT 8");
$stmtAct->execute();
$actividadReciente = $stmtAct->fetchAll();

// Chart data: Visitas por Mes (last 6 months)
$stmtVisitasMes = $db->query("SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, COUNT(*) as total FROM visitas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY mes ORDER BY mes");
$visitasPorMes = $stmtVisitasMes->fetchAll();
$chartVisitasLabels = array_column($visitasPorMes, 'mes');
$chartVisitasData = array_column($visitasPorMes, 'total');

// Chart data: Propiedades por Tipo
$stmtPropTipo = $db->query("SELECT tipo, COUNT(*) as total FROM propiedades GROUP BY tipo");
$propPorTipo = $stmtPropTipo->fetchAll();
$chartTipoLabels = array_column($propPorTipo, 'tipo');
$chartTipoData = array_column($propPorTipo, 'total');

// Conversion funnel data
$totalLeads = getCount('clientes', '1=1');
$visitasRealizadas = getCount('visitas', "estado = 'realizada'");
$operacionesCerradas = getCount('propiedades', "estado IN ('vendido','alquilado')");
$convVisitas = $totalLeads > 0 ? round(($visitasRealizadas / $totalLeads) * 100) : 0;
$convCierre = $visitasRealizadas > 0 ? round(($operacionesCerradas / $visitasRealizadas) * 100) : 0;
?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= $totalPropiedades ?></div>
                    <div class="stat-label">Propiedades</div>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-house-door"></i></div>
            </div>
            <small class="text-success"><?= $propDisponibles ?> disponibles</small>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= $totalClientes ?></div>
                    <div class="stat-label">Clientes</div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= $visitasMes ?></div>
                    <div class="stat-label">Visitas este mes</div>
                </div>
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-calendar-event"></i></div>
            </div>
            <small class="text-primary"><?= $visitasHoy ?> hoy</small>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value"><?= $tareasPendientes ?></div>
                    <div class="stat-label">Tareas pendientes</div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-check2-square"></i></div>
            </div>
            <?php if ($tareasVencidas > 0): ?>
            <small class="text-danger"><i class="bi bi-exclamation-triangle"></i> <?= $tareasVencidas ?> vencidas</small>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Finanzas resumen -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value text-success"><?= formatPrecio($finanzas['cobrado_mes']) ?></div>
                    <div class="stat-label">Cobrado este mes</div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-value text-warning"><?= formatPrecio($finanzas['pendiente_total']) ?></div>
                    <div class="stat-label">Pendiente de cobro</div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Acciones rapidas -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= APP_URL ?>/modules/propiedades/form.php" class="quick-action">
            <i class="bi bi-plus-circle text-primary"></i>
            <span>Nueva Propiedad</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= APP_URL ?>/modules/clientes/form.php" class="quick-action">
            <i class="bi bi-person-plus text-success"></i>
            <span>Nuevo Cliente</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= APP_URL ?>/modules/visitas/form.php" class="quick-action">
            <i class="bi bi-calendar-plus text-info"></i>
            <span>Nueva Visita</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= APP_URL ?>/modules/tareas/form.php" class="quick-action">
            <i class="bi bi-plus-square text-warning"></i>
            <span>Nueva Tarea</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Proximas Visitas -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-event"></i> Proximas Visitas</span>
                <a href="<?= APP_URL ?>/modules/visitas/index.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($proximasVisitas)): ?>
                <div class="empty-state py-4">
                    <i class="bi bi-calendar-x"></i>
                    <p>No hay visitas programadas</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($proximasVisitas as $v): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?= sanitize($v['referencia']) ?></strong> - <?= sanitize($v['propiedad']) ?><br>
                                <small class="text-muted"><i class="bi bi-person"></i> <?= sanitize($v['cliente_nombre'] . ' ' . $v['cliente_apellidos']) ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary"><?= formatFecha($v['fecha']) ?></span><br>
                                <small><?= substr($v['hora'], 0, 5) ?>h</small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tareas Urgentes -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check2-square"></i> Tareas Prioritarias</span>
                <a href="<?= APP_URL ?>/modules/tareas/index.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($tareasUrgentes)): ?>
                <div class="empty-state py-4">
                    <i class="bi bi-check-circle"></i>
                    <p>No hay tareas pendientes</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($tareasUrgentes as $t): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="prioridad-<?= $t['prioridad'] ?>"><i class="bi bi-circle-fill" style="font-size:0.5rem"></i></span>
                                <strong><?= sanitize($t['titulo']) ?></strong><br>
                                <small class="text-muted">
                                    <?php if ($t['prop_ref']): ?>
                                    <i class="bi bi-house"></i> <?= sanitize($t['prop_ref']) ?>
                                    <?php endif; ?>
                                    <?php if ($t['cliente_nombre']): ?>
                                    <i class="bi bi-person"></i> <?= sanitize($t['cliente_nombre']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <?php if ($t['fecha_vencimiento']): ?>
                                <small class="<?= strtotime($t['fecha_vencimiento']) < time() ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    <?= formatFechaHora($t['fecha_vencimiento']) ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Graficos -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart-line"></i> Visitas por Mes</div>
            <div class="card-body"><canvas id="chartVisitas" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-pie-chart"></i> Propiedades por Tipo</div>
            <div class="card-body"><canvas id="chartTipos" height="200"></canvas></div>
        </div>
    </div>
</div>

<!-- Funnel de Conversion -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-funnel"></i> Embudo de Conversion</div>
            <div class="card-body">
                <div class="row align-items-center text-center">
                    <div class="col-md-3">
                        <div class="p-3 rounded" style="background: rgba(16,185,129,0.1);">
                            <div class="fs-2 fw-bold text-success"><?= $totalLeads ?></div>
                            <div class="text-muted">Contactos</div>
                        </div>
                    </div>
                    <div class="col-md-1 d-none d-md-block">
                        <i class="bi bi-chevron-right fs-3 text-muted"></i>
                        <div class="small text-muted"><?= $convVisitas ?>%</div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 rounded" style="background: rgba(59,130,246,0.1);">
                            <div class="fs-2 fw-bold text-primary"><?= $visitasRealizadas ?></div>
                            <div class="text-muted">Visitas realizadas</div>
                        </div>
                    </div>
                    <div class="col-md-1 d-none d-md-block">
                        <i class="bi bi-chevron-right fs-3 text-muted"></i>
                        <div class="small text-muted"><?= $convCierre ?>%</div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 rounded" style="background: rgba(245,158,11,0.1);">
                            <div class="fs-2 fw-bold text-warning"><?= $operacionesCerradas ?></div>
                            <div class="text-muted">Operaciones cerradas</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ultimas propiedades y actividad -->
<div class="row g-4 mt-2">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-house-door"></i> Ultimas Propiedades</span>
                <a href="<?= APP_URL ?>/modules/propiedades/index.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Ref.</th>
                                <th>Titulo</th>
                                <th>Tipo</th>
                                <th>Precio</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimasPropiedades as $p): ?>
                            <tr style="cursor:pointer" onclick="location.href='<?= APP_URL ?>/modules/propiedades/ver.php?id=<?= $p['id'] ?>'">
                                <td><strong><?= sanitize($p['referencia']) ?></strong></td>
                                <td><?= sanitize(mb_substr($p['titulo'], 0, 40)) ?></td>
                                <td><?= sanitize(getTiposPropiedad()[$p['tipo']] ?? $p['tipo']) ?></td>
                                <td class="text-nowrap"><?= formatPrecio($p['precio']) ?></td>
                                <td><span class="badge-estado badge-<?= $p['estado'] ?>"><?= ucfirst($p['estado']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($ultimasPropiedades)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No hay propiedades registradas</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Actividad Reciente -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> Actividad Reciente</div>
            <div class="card-body">
                <?php if (empty($actividadReciente)): ?>
                <div class="empty-state py-4">
                    <i class="bi bi-clock"></i>
                    <p>Sin actividad reciente</p>
                </div>
                <?php else: ?>
                <div class="ps-2">
                    <?php foreach ($actividadReciente as $act): ?>
                    <div class="timeline-item">
                        <strong><?= sanitize($act['usuario_nombre'] ?? 'Sistema') ?></strong>
                        <span class="text-muted"><?= sanitize($act['accion']) ?></span>
                        <span><?= sanitize($act['entidad']) ?></span>
                        <?php if ($act['detalles']): ?>
                        <br><small class="text-muted"><?= sanitize(mb_substr($act['detalles'], 0, 60)) ?></small>
                        <?php endif; ?>
                        <div class="timeline-date"><?= formatFechaHora($act['created_at']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Resumen por estado de propiedades -->
<div class="row g-4 mt-2 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart"></i> Propiedades por Estado</div>
            <div class="card-body">
                <div class="row text-center">
                    <?php
                    $estadosLabels = ['disponible' => ['Disponibles', 'success'], 'reservado' => ['Reservados', 'warning'], 'vendido' => ['Vendidos', 'primary'], 'alquilado' => ['Alquilados', 'info'], 'retirado' => ['Retirados', 'secondary']];
                    foreach ($estadosLabels as $key => [$label, $color]):
                    ?>
                    <div class="col">
                        <h3 class="text-<?= $color ?>"><?= $estadosProp[$key] ?? 0 ?></h3>
                        <small class="text-muted"><?= $label ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Visitas por Mes - Bar Chart
new Chart(document.getElementById('chartVisitas'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartVisitasLabels) ?>,
        datasets: [{
            label: 'Visitas',
            data: <?= json_encode(array_map('intval', $chartVisitasData)) ?>,
            backgroundColor: 'rgba(16, 185, 129, 0.7)',
            borderColor: '#10b981',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Propiedades por Tipo - Doughnut Chart
new Chart(document.getElementById('chartTipos'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chartTipoLabels) ?>,
        datasets: [{
            data: <?= json_encode(array_map('intval', $chartTipoData)) ?>,
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444', '#6b7280', '#ec4899', '#14b8a6'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'right' }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
