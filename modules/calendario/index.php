<?php
$pageTitle = 'Calendario';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Mes y anio actual
$mes = intval(get('mes', date('n')));
$anio = intval(get('anio', date('Y')));

// Validar rango
if ($mes < 1 || $mes > 12) $mes = date('n');
if ($anio < 2020 || $anio > 2040) $anio = date('Y');

// Navegacion de meses
$mesPrev = $mes - 1;
$anioPrev = $anio;
if ($mesPrev < 1) { $mesPrev = 12; $anioPrev--; }
$mesNext = $mes + 1;
$anioNext = $anio;
if ($mesNext > 12) { $mesNext = 1; $anioNext++; }

// Datos del mes
$primerDia = mktime(0, 0, 0, $mes, 1, $anio);
$diasEnMes = date('t', $primerDia);
$diaSemanaInicio = date('N', $primerDia); // 1=lunes, 7=domingo
$nombreMes = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
][$mes];

// Obtener eventos del mes
$fechaInicio = "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01 00:00:00";
$fechaFin = "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-$diasEnMes 23:59:59";

$stmt = $db->prepare("
    SELECT ce.*, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
           p.titulo as propiedad_titulo, p.referencia as propiedad_ref
    FROM calendario_eventos ce
    LEFT JOIN clientes c ON ce.cliente_id = c.id
    LEFT JOIN propiedades p ON ce.propiedad_id = p.id
    WHERE ce.usuario_id = ? AND ce.fecha_inicio <= ? AND ce.fecha_fin >= ?
    ORDER BY ce.fecha_inicio ASC
");
$stmt->execute([currentUserId(), $fechaFin, $fechaInicio]);
$eventos = $stmt->fetchAll();

// También obtener visitas del módulo de visitas para mostrarlas en el calendario
$stmtVisitasCal = $db->prepare("
    SELECT v.id, v.fecha, v.hora, v.estado as visita_estado,
           CONCAT('Visita: ', p.referencia, ' - ', c.nombre) as titulo,
           'visita' as tipo, '#10b981' as color, 0 as todo_dia,
           c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
           p.titulo as propiedad_titulo, p.referencia as propiedad_ref,
           v.cliente_id, v.propiedad_id
    FROM visitas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    LEFT JOIN propiedades p ON v.propiedad_id = p.id
    WHERE v.agente_id = ?
      AND v.fecha >= ? AND v.fecha <= ?
    ORDER BY v.fecha, v.hora ASC
");
$stmtVisitasCal->execute([currentUserId(), "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01", "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-$diasEnMes"]);
$visitas = $stmtVisitasCal->fetchAll();

// Convertir visitas al formato de eventos
foreach ($visitas as $v) {
    $v['fecha_inicio'] = $v['fecha'] . ' ' . ($v['hora'] ?? '09:00:00');
    $v['fecha_fin'] = $v['fecha'] . ' ' . ($v['hora'] ? date('H:i:s', strtotime($v['hora'] . ' +1 hour')) : '10:00:00');
    $eventos[] = $v;
}

// También obtener tareas con fecha de vencimiento
$stmtTareasCal = $db->prepare("
    SELECT t.id, DATE(t.fecha_vencimiento) as fecha, TIME(t.fecha_vencimiento) as hora,
           t.estado as tarea_estado, t.prioridad,
           CONCAT('Tarea: ', t.titulo) as titulo,
           'tarea' as tipo,
           CASE t.prioridad WHEN 'urgente' THEN '#ef4444' WHEN 'alta' THEN '#f59e0b' ELSE '#8b5cf6' END as color,
           0 as todo_dia,
           c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
           p.titulo as propiedad_titulo, p.referencia as propiedad_ref,
           t.cliente_id, t.propiedad_id
    FROM tareas t
    LEFT JOIN clientes c ON t.cliente_id = c.id
    LEFT JOIN propiedades p ON t.propiedad_id = p.id
    WHERE (t.asignado_a = ? OR t.creado_por = ?)
      AND t.estado IN ('pendiente','en_progreso')
      AND DATE(t.fecha_vencimiento) >= ? AND DATE(t.fecha_vencimiento) <= ?
    ORDER BY t.fecha_vencimiento ASC
");
$stmtTareasCal->execute([currentUserId(), currentUserId(), "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01", "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-$diasEnMes"]);
$tareasCal = $stmtTareasCal->fetchAll();

foreach ($tareasCal as $t) {
    $t['fecha_inicio'] = $t['fecha'] . ' ' . ($t['hora'] ?? '09:00:00');
    $t['fecha_fin'] = $t['fecha'] . ' ' . ($t['hora'] ? date('H:i:s', strtotime($t['hora'] . ' +1 hour')) : '10:00:00');
    $eventos[] = $t;
}

// Prospectos con fecha de próximo contacto
$stmtProspCal = $db->prepare("
    SELECT p.id, p.fecha_proximo_contacto as fecha, '09:00:00' as hora,
           CONCAT('Contactar: ', p.nombre) as titulo,
           'llamada' as tipo, '#f59e0b' as color, 0 as todo_dia,
           NULL as cliente_nombre, NULL as cliente_apellidos,
           NULL as propiedad_titulo, NULL as propiedad_ref,
           NULL as cliente_id, NULL as propiedad_id
    FROM prospectos p
    WHERE p.agente_id = ?
      AND p.activo = 1 AND p.etapa NOT IN ('captado','descartado')
      AND p.fecha_proximo_contacto >= ? AND p.fecha_proximo_contacto <= ?
    ORDER BY p.fecha_proximo_contacto ASC
");
$stmtProspCal->execute([currentUserId(), "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01", "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-$diasEnMes"]);
$prospCal = $stmtProspCal->fetchAll();

foreach ($prospCal as $pc) {
    $pc['fecha_inicio'] = $pc['fecha'] . ' 09:00:00';
    $pc['fecha_fin'] = $pc['fecha'] . ' 09:30:00';
    $eventos[] = $pc;
}

// Organizar eventos por dia
$eventosPorDia = [];
foreach ($eventos as $evento) {
    $diaInicio = max(1, intval(date('j', strtotime($evento['fecha_inicio']))));
    $diaFin = min($diasEnMes, intval(date('j', strtotime($evento['fecha_fin']))));
    // Si el evento empieza en otro mes
    if (date('n', strtotime($evento['fecha_inicio'])) != $mes) $diaInicio = 1;
    if (date('n', strtotime($evento['fecha_fin'])) != $mes) $diaFin = $diasEnMes;
    for ($d = $diaInicio; $d <= $diaFin; $d++) {
        $eventosPorDia[$d][] = $evento;
    }
}

// Eventos de hoy para panel lateral
$hoy = date('Y-m-d');
$stmtHoy = $db->prepare("
    SELECT ce.*, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
           p.titulo as propiedad_titulo
    FROM calendario_eventos ce
    LEFT JOIN clientes c ON ce.cliente_id = c.id
    LEFT JOIN propiedades p ON ce.propiedad_id = p.id
    WHERE ce.usuario_id = ? AND DATE(ce.fecha_inicio) = ?
    ORDER BY ce.fecha_inicio ASC
");
$stmtHoy->execute([currentUserId(), $hoy]);
$eventosHoy = $stmtHoy->fetchAll();

$tipoColores = [
    'visita' => '#10b981',
    'reunion' => '#3b82f6',
    'llamada' => '#f59e0b',
    'tarea' => '#8b5cf6',
    'personal' => '#6b7280',
    'otro' => '#ef4444',
];

$tipoIconos = [
    'visita' => 'bi-house-door',
    'reunion' => 'bi-people',
    'llamada' => 'bi-telephone',
    'tarea' => 'bi-check2-square',
    'personal' => 'bi-person',
    'otro' => 'bi-calendar-event',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="?mes=<?= $mesPrev ?>&anio=<?= $anioPrev ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i>
        </a>
        <h5 class="mb-0"><?= $nombreMes ?> <?= $anio ?></h5>
        <a href="?mes=<?= $mesNext ?>&anio=<?= $anioNext ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-right"></i>
        </a>
        <?php if ($mes != date('n') || $anio != date('Y')): ?>
        <a href="?" class="btn btn-outline-primary btn-sm">Hoy</a>
        <?php endif; ?>
    </div>
    <a href="form.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nuevo Evento
    </a>
</div>

<div class="row g-3">
    <!-- Calendario -->
    <div class="col-lg-9">
        <div class="card shadow-sm border-0">
            <div class="card-body p-2">
                <div class="calendar-grid">
                    <!-- Cabecera dias -->
                    <div class="calendar-header">
                        <?php foreach (['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'] as $dia): ?>
                        <div class="calendar-day-header"><?= $dia ?></div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Celdas -->
                    <div class="calendar-body">
                        <?php
                        // Celdas vacias antes del primer dia
                        for ($i = 1; $i < $diaSemanaInicio; $i++):
                        ?>
                        <div class="calendar-cell calendar-empty"></div>
                        <?php endfor; ?>

                        <?php for ($dia = 1; $dia <= $diasEnMes; $dia++):
                            $esHoy = ($dia == date('j') && $mes == date('n') && $anio == date('Y'));
                            $tieneEventos = !empty($eventosPorDia[$dia]);
                        ?>
                        <div class="calendar-cell <?= $esHoy ? 'calendar-today' : '' ?>">
                            <div class="calendar-date <?= $esHoy ? 'bg-primary text-white rounded-circle' : '' ?>">
                                <?= $dia ?>
                            </div>
                            <?php if ($tieneEventos): ?>
                                <?php foreach (array_slice($eventosPorDia[$dia], 0, 3) as $ev): ?>
                                <a href="form.php?id=<?= $ev['id'] ?>" class="calendar-event" style="background-color: <?= $ev['color'] ?? $tipoColores[$ev['tipo']] ?? '#10b981' ?>;" title="<?= sanitize($ev['titulo']) ?>">
                                    <small><?= date('H:i', strtotime($ev['fecha_inicio'])) ?> <?= sanitize(mb_strimwidth($ev['titulo'], 0, 15, '...')) ?></small>
                                </a>
                                <?php endforeach; ?>
                                <?php if (count($eventosPorDia[$dia]) > 3): ?>
                                <small class="text-muted d-block text-center">+<?= count($eventosPorDia[$dia]) - 3 ?> mas</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>

                        <?php
                        // Celdas vacias despues del ultimo dia
                        $ultimoDiaSemana = date('N', mktime(0, 0, 0, $mes, $diasEnMes, $anio));
                        for ($i = $ultimoDiaSemana + 1; $i <= 7; $i++):
                        ?>
                        <div class="calendar-cell calendar-empty"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel lateral: Eventos de hoy -->
    <div class="col-lg-3">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent">
                <h6 class="mb-0"><i class="bi bi-calendar-check"></i> Hoy, <?= date('d/m/Y') ?></h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($eventosHoy)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-calendar-x d-block fs-3 mb-2"></i>
                    <small>Sin eventos para hoy</small>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($eventosHoy as $ev): ?>
                    <a href="form.php?id=<?= $ev['id'] ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" style="width: 10px; height: 10px; background: <?= $ev['color'] ?? $tipoColores[$ev['tipo']] ?? '#10b981' ?>;"></span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= sanitize($ev['titulo']) ?></div>
                                <small class="text-muted">
                                    <?php if ($ev['todo_dia']): ?>
                                        Todo el dia
                                    <?php else: ?>
                                        <?= date('H:i', strtotime($ev['fecha_inicio'])) ?> - <?= date('H:i', strtotime($ev['fecha_fin'])) ?>
                                    <?php endif; ?>
                                </small>
                                <?php if (!empty($ev['cliente_nombre'])): ?>
                                <br><small class="text-muted"><i class="bi bi-person"></i> <?= sanitize($ev['cliente_nombre'] . ' ' . $ev['cliente_apellidos']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Proximos eventos -->
        <?php
        $stmtProximos = $db->prepare("
            SELECT ce.*, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos
            FROM calendario_eventos ce
            LEFT JOIN clientes c ON ce.cliente_id = c.id
            WHERE ce.usuario_id = ? AND ce.fecha_inicio > NOW()
            ORDER BY ce.fecha_inicio ASC LIMIT 5
        ");
        $stmtProximos->execute([currentUserId()]);
        $proximos = $stmtProximos->fetchAll();
        ?>
        <?php if (!empty($proximos)): ?>
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-transparent">
                <h6 class="mb-0"><i class="bi bi-clock"></i> Proximos</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($proximos as $ev): ?>
                <a href="form.php?id=<?= $ev['id'] ?>" class="list-group-item list-group-item-action py-2">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi <?= $tipoIconos[$ev['tipo']] ?? 'bi-calendar-event' ?> mt-1" style="color: <?= $ev['color'] ?? $tipoColores[$ev['tipo']] ?? '#10b981' ?>;"></i>
                        <div>
                            <div class="small fw-semibold"><?= sanitize($ev['titulo']) ?></div>
                            <small class="text-muted"><?= formatFechaHora($ev['fecha_inicio']) ?></small>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.calendar-grid { width: 100%; }
.calendar-header { display: grid; grid-template-columns: repeat(7, 1fr); }
.calendar-day-header { text-align: center; font-weight: 600; font-size: 0.85rem; padding: 8px 4px; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.calendar-body { display: grid; grid-template-columns: repeat(7, 1fr); }
.calendar-cell { min-height: 100px; border: 1px solid #f1f5f9; padding: 4px; position: relative; }
.calendar-cell:hover { background: #f8fafc; }
.calendar-empty { background: #fafafa; }
.calendar-today { background: #f0fdf4 !important; }
.calendar-date { display: inline-block; width: 28px; height: 28px; line-height: 28px; text-align: center; font-size: 0.85rem; font-weight: 500; margin-bottom: 2px; }
.calendar-event { display: block; padding: 1px 4px; margin-bottom: 2px; border-radius: 3px; color: #fff; text-decoration: none; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; font-size: 0.7rem; }
.calendar-event:hover { opacity: 0.85; color: #fff; }
@media (max-width: 768px) {
    .calendar-cell { min-height: 60px; }
    .calendar-event small { font-size: 0.6rem; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
