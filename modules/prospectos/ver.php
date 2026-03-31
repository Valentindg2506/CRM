<?php
$pageTitle = 'Detalle Prospecto';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/custom_fields_helper.php';

$db = getDB();
$id = intval(get('id'));

$stmt = $db->prepare("SELECT p.*, u.nombre as agente_nombre, u.apellidos as agente_apellidos FROM prospectos p LEFT JOIN usuarios u ON p.agente_id = u.id WHERE p.id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { setFlash('danger', 'Prospecto no encontrado.'); header('Location: index.php'); exit; }

// Convertir a cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'convertir_cliente') {
    verifyCsrf();
    try {
        $clienteData = [
            'nombre' => $p['nombre'],
            'email' => $p['email'],
            'telefono' => $p['telefono'],
            'telefono2' => $p['telefono2'],
            'tipo' => 'propietario',
            'origen' => 'otro',
            'direccion' => $p['direccion'],
            'localidad' => $p['localidad'],
            'provincia' => $p['provincia'],
            'codigo_postal' => $p['codigo_postal'],
            'notas' => 'Convertido desde prospecto ' . $p['referencia'] . '. ' . ($p['notas'] ?? ''),
            'agente_id' => $p['agente_id'],
            'activo' => 1,
        ];
        $fields = array_keys($clienteData);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $db->prepare("INSERT INTO clientes (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($clienteData));
        $clienteId = $db->lastInsertId();
        $db->prepare("UPDATE prospectos SET etapa = 'captado', estado = 'captado' WHERE id = ?")->execute([$id]);
        registrarActividad('convertir', 'prospecto', $id, 'Convertido a cliente #' . $clienteId);
        setFlash('success', 'Prospecto convertido a cliente correctamente. <a href="' . APP_URL . '/modules/clientes/ver.php?id=' . $clienteId . '">Ver cliente</a>');
        header('Location: ver.php?id=' . $id);
        exit;
    } catch (Exception $e) {
        setFlash('danger', 'Error al convertir: ' . $e->getMessage());
    }
}

$etapas = [
    'nuevo_lead' => ['label' => 'Nuevo Lead', 'color' => '#06b6d4', 'icon' => 'bi-star'],
    'contactado' => ['label' => 'Contactado', 'color' => '#64748b', 'icon' => 'bi-telephone'],
    'seguimiento' => ['label' => 'En Seguimiento', 'color' => '#3b82f6', 'icon' => 'bi-arrow-repeat'],
    'visita_programada' => ['label' => 'Visita Programada', 'color' => '#8b5cf6', 'icon' => 'bi-calendar-check'],
    'en_negociacion' => ['label' => 'En Negociación', 'color' => '#f59e0b', 'icon' => 'bi-chat-left-dots'],
    'captado' => ['label' => 'Captado', 'color' => '#10b981', 'icon' => 'bi-check-circle'],
    'descartado' => ['label' => 'Descartado', 'color' => '#ef4444', 'icon' => 'bi-x-circle'],
];

$estados = [
    'nuevo' => 'Nuevo', 'en_proceso' => 'En Proceso', 'pendiente' => 'Pendiente Respuesta',
    'sin_interes' => 'Sin Interés', 'captado' => 'Captado',
];

$temperaturas = ['frio' => ['label' => 'Frío', 'color' => '#3b82f6', 'icon' => 'bi-snow'], 'templado' => ['label' => 'Templado', 'color' => '#f59e0b', 'icon' => 'bi-thermometer-half'], 'caliente' => ['label' => 'Caliente', 'color' => '#ef4444', 'icon' => 'bi-fire']];

$etapaInfo = $etapas[$p['etapa']] ?? ['label' => $p['etapa'], 'color' => '#64748b', 'icon' => 'bi-circle'];
$tempInfo = $temperaturas[$p['temperatura'] ?? 'frio'] ?? $temperaturas['frio'];

// Historial de contactos
$stmtHist = $db->prepare("SELECT h.*, u.nombre as usuario_nombre, u.apellidos as usuario_apellidos 
                           FROM historial_prospectos h 
                           LEFT JOIN usuarios u ON h.usuario_id = u.id 
                           WHERE h.prospecto_id = ? 
                           ORDER BY h.created_at DESC LIMIT 50");
$stmtHist->execute([$id]);
$historial = $stmtHist->fetchAll();

// Custom fields
$customValues = getCustomFieldValues($id, 'prospecto');
$customFields = getCustomFields('prospecto');

// Agentes para select
$agentes = $db->query("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();

$tiposHistorial = [
    'llamada' => ['label' => 'Llamada', 'icon' => 'bi-telephone', 'color' => '#3b82f6'],
    'email' => ['label' => 'Email', 'icon' => 'bi-envelope', 'color' => '#8b5cf6'],
    'visita' => ['label' => 'Visita', 'icon' => 'bi-house-door', 'color' => '#10b981'],
    'nota' => ['label' => 'Nota', 'icon' => 'bi-sticky', 'color' => '#f59e0b'],
    'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'color' => '#25d366'],
    'otro' => ['label' => 'Otro', 'icon' => 'bi-chat-dots', 'color' => '#6b7280'],
];
?>

<style>
/* Inline Edit Styles */
.editable-field { position: relative; cursor: pointer; padding: 2px 4px; border-radius: 4px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
.editable-field:hover { background: var(--primary-light, rgba(16,185,129,0.08)); }
.editable-field .edit-icon { opacity: 0; font-size: 0.75rem; color: var(--primary); transition: opacity 0.2s; }
.editable-field:hover .edit-icon { opacity: 1; }
.editable-field.editing { background: transparent; }
.inline-input { border: 2px solid var(--primary); border-radius: 6px; padding: 4px 8px; font-size: inherit; width: 100%; outline: none; }
.inline-input:focus { box-shadow: 0 0 0 3px var(--primary-light); }
.inline-select { border: 2px solid var(--primary); border-radius: 6px; padding: 4px 8px; font-size: inherit; outline: none; }
.save-indicator { position: absolute; right: -20px; top: 50%; transform: translateY(-50%); }
.save-indicator.success { color: #10b981; }
.save-indicator.error { color: #ef4444; }

/* Timeline Styles */
.timeline { position: relative; padding-left: 0; }
.timeline-entry { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.05); position: relative; }
.timeline-entry:last-child { border-bottom: none; }
.timeline-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; color: #fff; flex-shrink: 0; }
.timeline-content { flex: 1; min-width: 0; }
.timeline-header { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap; }
.timeline-header .name { font-weight: 600; font-size: 0.85rem; }
.timeline-header .date { font-size: 0.75rem; color: #94a3b8; }
.timeline-header .tipo-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 8px; font-weight: 500; }
.timeline-text { font-size: 0.9rem; color: #374151; line-height: 1.5; }
[data-bs-theme="dark"] .timeline-text { color: #d1d5db; }
.timeline-actions { opacity: 0; transition: opacity 0.15s; }
.timeline-entry:hover .timeline-actions { opacity: 1; }

/* Mini Calendar Container */
.mini-cal-wrapper { background: #fff; border-radius: 8px; }
[data-bs-theme="dark"] .mini-cal-wrapper { background: #1e293b; }
.mini-cal-wrapper .flatpickr-calendar { box-shadow: none !important; width: 100% !important; }
.task-list-day { max-height: 200px; overflow-y: auto; }
.task-item { padding: 6px 10px; border-radius: 6px; margin-bottom: 4px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px; }

/* Add contact form */
.add-contact-form { border-top: 1px solid rgba(0,0,0,0.08); padding-top: 12px; }
.contact-type-selector { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px; }
.contact-type-btn { border: 1px solid #e2e8f0; background: transparent; padding: 3px 10px; border-radius: 16px; font-size: 0.75rem; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; gap: 4px; }
.contact-type-btn:hover, .contact-type-btn.active { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }
</style>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-secondary" id="btn-volver">
            <i class="bi bi-arrow-left"></i> Volver al listado
        </a>
        <span class="badge fs-6" style="background: <?= $etapaInfo['color'] ?>;">
            <i class="bi <?= $etapaInfo['icon'] ?>"></i> <?= $etapaInfo['label'] ?>
        </span>
        <span class="editable-field" data-field="etapa" data-type="select" data-options='<?= json_encode(array_map(fn($e) => $e['label'], $etapas)) ?>' data-value="<?= $p['etapa'] ?>">
            <i class="bi bi-pencil edit-icon"></i>
        </span>
        <span class="badge" style="background: <?= $tempInfo['color'] ?>;">
            <i class="bi <?= $tempInfo['icon'] ?>"></i> <?= $tempInfo['label'] ?>
        </span>
        <?php if ($p['exclusividad']): ?><span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i> Exclusiva</span><?php endif; ?>
        <?php if (!$p['activo']): ?><span class="badge bg-secondary ms-1">Inactivo</span><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if ($p['etapa'] !== 'captado' && $p['etapa'] !== 'descartado'): ?>
        <form method="POST" class="d-inline" onsubmit="return confirm('¿Convertir este prospecto en cliente?')">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="convertir_cliente">
            <button type="submit" class="btn btn-success"><i class="bi bi-person-check"></i> Convertir a Cliente</button>
        </form>
        <?php endif; ?>
        <a href="form.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i> Formulario Completo</a>
        <a href="delete.php?id=<?= $id ?>&csrf=<?= csrfToken() ?>" class="btn btn-outline-danger" data-confirm="Eliminar este prospecto?"><i class="bi bi-trash"></i></a>
    </div>
</div>

<!-- Pipeline Progress -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center position-relative" style="z-index: 1;">
            <?php
            $etapaKeys = array_keys($etapas);
            $currentIdx = array_search($p['etapa'], $etapaKeys);
            foreach ($etapas as $eKey => $eData):
                $idx = array_search($eKey, $etapaKeys);
                $isActive = $eKey === $p['etapa'];
                $isPast = $idx < $currentIdx;
            ?>
            <div class="text-center flex-fill">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-1"
                     style="width: 36px; height: 36px; font-size: 0.9rem;
                            background: <?= $isActive ? $eData['color'] : ($isPast ? $eData['color'] . '30' : '#e2e8f030') ?>;
                            color: <?= $isActive ? '#fff' : ($isPast ? $eData['color'] : '#94a3b8') ?>;
                            border: 2px solid <?= $isActive || $isPast ? $eData['color'] : '#e2e8f0' ?>;">
                    <i class="bi <?= $eData['icon'] ?>"></i>
                </div>
                <div class="small <?= $isActive ? 'fw-bold' : '' ?>" style="font-size: 0.7rem; color: <?= $isActive ? $eData['color'] : '#94a3b8' ?>;">
                    <?= $eData['label'] ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <!-- Info del Prospecto (Editable) -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person"></i> Datos del Prospecto</div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0">
                        <span class="editable-field" data-field="nombre" data-type="text" data-value="<?= sanitize($p['nombre']) ?>">
                            <?= sanitize($p['nombre']) ?> <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </h5>
                    <span class="badge bg-primary"><?= sanitize($p['referencia']) ?></span>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block">Email</small>
                    <span class="editable-field" data-field="email" data-type="email" data-value="<?= sanitize($p['email'] ?? '') ?>">
                        <?php if ($p['email']): ?>
                            <a href="mailto:<?= sanitize($p['email']) ?>"><?= sanitize($p['email']) ?></a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                        <i class="bi bi-pencil edit-icon"></i>
                    </span>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block">Teléfono</small>
                    <span class="editable-field" data-field="telefono" data-type="tel" data-value="<?= sanitize($p['telefono'] ?? '') ?>">
                        <?php if ($p['telefono']): ?>
                            <a href="tel:<?= sanitize($p['telefono']) ?>"><?= sanitize($p['telefono']) ?></a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                        <i class="bi bi-pencil edit-icon"></i>
                    </span>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block">Teléfono 2</small>
                    <span class="editable-field" data-field="telefono2" data-type="tel" data-value="<?= sanitize($p['telefono2'] ?? '') ?>">
                        <?= $p['telefono2'] ? sanitize($p['telefono2']) : '<span class="text-muted">-</span>' ?>
                        <i class="bi bi-pencil edit-icon"></i>
                    </span>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block">Estado</small>
                    <span class="editable-field" data-field="estado" data-type="select" data-options='<?= json_encode($estados) ?>' data-value="<?= $p['estado'] ?>">
                        <span class="badge-estado badge-<?= $p['estado'] ?>"><?= $estados[$p['estado']] ?? $p['estado'] ?></span>
                        <i class="bi bi-pencil edit-icon"></i>
                    </span>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block">Temperatura</small>
                    <span class="editable-field" data-field="temperatura" data-type="select" data-options='<?= json_encode(array_map(fn($t) => $t['label'], $temperaturas)) ?>' data-value="<?= $p['temperatura'] ?? 'frio' ?>">
                        <span class="badge" style="background: <?= $tempInfo['color'] ?>"><i class="bi <?= $tempInfo['icon'] ?>"></i> <?= $tempInfo['label'] ?></span>
                        <i class="bi bi-pencil edit-icon"></i>
                    </span>
                </div>

                <p class="mb-0 text-muted mt-3"><small>Alta: <?= formatFecha($p['created_at']) ?></small></p>
            </div>
        </div>

        <!-- Seguimiento con Mini Calendario -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-calendar-event"></i> Seguimiento</div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="detail-label">Primer Contacto</div>
                        <span class="editable-field" data-field="fecha_contacto" data-type="date" data-value="<?= $p['fecha_contacto'] ?? '' ?>">
                            <span class="detail-value"><?= $p['fecha_contacto'] ? formatFecha($p['fecha_contacto']) : '-' ?></span>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Próx. Contacto</div>
                        <span class="editable-field" data-field="fecha_proximo_contacto" data-type="date" data-value="<?= $p['fecha_proximo_contacto'] ?? '' ?>">
                            <?php if ($p['fecha_proximo_contacto']): ?>
                                <?php
                                $proxDate = new DateTime($p['fecha_proximo_contacto']);
                                $today = new DateTime('today');
                                $isPast = $proxDate < $today;
                                $isToday = $proxDate->format('Y-m-d') === $today->format('Y-m-d');
                                ?>
                                <span class="<?= $isPast ? 'text-danger fw-bold' : ($isToday ? 'text-warning fw-bold' : '') ?>">
                                    <?= formatFecha($p['fecha_proximo_contacto']) ?>
                                </span>
                                <?php if ($isPast): ?><br><small class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Vencido</small><?php endif; ?>
                                <?php if ($isToday): ?><br><small class="text-warning"><i class="bi bi-clock"></i> Hoy</small><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Comisión</div>
                        <span class="editable-field" data-field="comision" data-type="number" data-value="<?= $p['comision'] ?? '' ?>">
                            <span class="detail-value"><?= $p['comision'] ? $p['comision'] . '%' : '-' ?></span>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Exclusividad</div>
                        <span class="editable-field" data-field="exclusividad" data-type="toggle" data-value="<?= $p['exclusividad'] ?>">
                            <?= $p['exclusividad'] ? '<i class="bi bi-check-circle text-success"></i> Sí' : '<span class="text-muted">No</span>' ?>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                </div>

                <!-- Mini Calendar -->
                <hr>
                <div class="mini-cal-wrapper">
                    <div id="miniCalendar"></div>
                    <div id="miniCalTasks" class="task-list-day mt-2" style="display:none;">
                        <h6 class="small fw-bold mb-2" id="miniCalDate"></h6>
                        <div id="miniCalTaskList"></div>
                        <button class="btn btn-sm btn-outline-primary w-100 mt-2" id="btnSetProxContacto">
                            <i class="bi bi-calendar-plus"></i> Fijar como próximo contacto
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agente -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person-badge"></i> Agente Asignado</div>
            <div class="card-body">
                <span class="editable-field" data-field="agente_id" data-type="select" data-options='<?= json_encode(array_column($agentes, 'nombre_completo', 'id')) ?>' data-value="<?= $p['agente_id'] ?>">
                    <strong><?= sanitize(($p['agente_nombre'] ?? '') . ' ' . ($p['agente_apellidos'] ?? '')) ?></strong>
                    <i class="bi bi-pencil edit-icon"></i>
                </span>
            </div>
        </div>

        <!-- Custom Fields -->
        <?php if (!empty($customFields)): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-ui-checks-grid"></i> Campos Personalizados</div>
            <div class="card-body">
                <?php foreach ($customFields as $cf): ?>
                    <?php $cfVal = $customValues[$cf['slug']]['valor'] ?? ''; ?>
                    <div class="mb-2">
                        <small class="text-muted d-block"><?= sanitize($cf['nombre']) ?></small>
                        <span class="editable-field" data-field="cf_<?= $cf['id'] ?>" data-type="custom_field" data-custom-field-id="<?= $cf['id'] ?>" data-value="<?= sanitize($cfVal) ?>">
                            <?= $cfVal ?: '<span class="text-muted">-</span>' ?>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <!-- Datos de la Propiedad (Editable) -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-house-door"></i> Datos de la Propiedad</div>
            <div class="card-body">
                <div class="row g-3">
                    <?php
                    $propFields = [
                        ['field' => 'tipo_propiedad', 'label' => 'Tipo', 'type' => 'select', 'col' => 'col-md-4',
                         'options' => json_encode(array_combine(['Piso','Casa','Chalet','Adosado','Atico','Duplex','Estudio','Local','Oficina','Nave','Terreno','Garaje','Edificio','Otro'], ['Piso','Casa','Chalet','Adosado','Atico','Duplex','Estudio','Local','Oficina','Nave','Terreno','Garaje','Edificio','Otro']))],
                        ['field' => 'precio_estimado', 'label' => 'Precio Estimado', 'type' => 'number', 'col' => 'col-md-4', 'format' => 'precio'],
                        ['field' => 'precio_propietario', 'label' => 'Precio Propietario', 'type' => 'number', 'col' => 'col-md-4', 'format' => 'precio'],
                        ['field' => 'superficie', 'label' => 'Superficie', 'type' => 'number', 'col' => 'col-md-3', 'format' => 'superficie'],
                        ['field' => 'habitaciones', 'label' => 'Habitaciones', 'type' => 'number', 'col' => 'col-md-3'],
                        ['field' => 'direccion', 'label' => 'Dirección', 'type' => 'text', 'col' => 'col-md-6'],
                        ['field' => 'barrio', 'label' => 'Barrio / Zona', 'type' => 'text', 'col' => 'col-md-3'],
                        ['field' => 'localidad', 'label' => 'Localidad', 'type' => 'text', 'col' => 'col-md-3'],
                        ['field' => 'provincia', 'label' => 'Provincia', 'type' => 'text', 'col' => 'col-md-3'],
                        ['field' => 'codigo_postal', 'label' => 'Código Postal', 'type' => 'text', 'col' => 'col-md-3'],
                    ];
                    foreach ($propFields as $pf):
                        $val = $p[$pf['field']] ?? '';
                        $displayVal = $val ?: '-';
                        if (($pf['format'] ?? '') === 'precio' && $val) $displayVal = formatPrecio($val);
                        if (($pf['format'] ?? '') === 'superficie' && $val) $displayVal = formatSuperficie($val);
                    ?>
                    <div class="<?= $pf['col'] ?>">
                        <div class="detail-label"><?= $pf['label'] ?></div>
                        <span class="editable-field" data-field="<?= $pf['field'] ?>" data-type="<?= $pf['type'] ?>" data-value="<?= sanitize($val) ?>" <?= isset($pf['options']) ? "data-options='" . $pf['options'] . "'" : '' ?>>
                            <span class="detail-value <?= ($pf['format'] ?? '') === 'precio' ? 'fw-bold' : '' ?>"><?= sanitize($displayVal) ?></span>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($p['enlace']): ?>
                    <div class="col-12">
                        <div class="detail-label">Enlace</div>
                        <span class="editable-field" data-field="enlace" data-type="url" data-value="<?= sanitize($p['enlace']) ?>">
                            <a href="<?= sanitize($p['enlace']) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-box-arrow-up-right"></i> <?= sanitize(mb_substr($p['enlace'], 0, 60)) ?><?= mb_strlen($p['enlace']) > 60 ? '...' : '' ?>
                            </a>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notas (Editable) -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-chat-text"></i> Notas</div>
            <div class="card-body">
                <span class="editable-field d-block" data-field="notas" data-type="textarea" data-value="<?= sanitize($p['notas'] ?? '') ?>">
                    <?= $p['notas'] ? nl2br(sanitize($p['notas'])) : '<span class="text-muted">Sin notas</span>' ?>
                    <i class="bi bi-pencil edit-icon"></i>
                </span>
            </div>
        </div>

        <!-- Reformas (Editable) -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-tools"></i> Reformas</div>
            <div class="card-body">
                <span class="editable-field d-block" data-field="reformas" data-type="textarea" data-value="<?= sanitize($p['reformas'] ?? '') ?>">
                    <?= $p['reformas'] ? nl2br(sanitize($p['reformas'])) : '<span class="text-muted">Sin reformas registradas</span>' ?>
                    <i class="bi bi-pencil edit-icon"></i>
                </span>
            </div>
        </div>

        <!-- Historial de Contactos (Timeline) -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history"></i> Historial de Contactos</span>
                <span class="badge bg-secondary"><?= count($historial) ?></span>
            </div>
            <div class="card-body">
                <!-- Add new contact form -->
                <div class="add-contact-form mb-3">
                    <div class="contact-type-selector">
                        <?php foreach ($tiposHistorial as $tKey => $tData): ?>
                        <button type="button" class="contact-type-btn <?= $tKey === 'nota' ? 'active' : '' ?>" data-tipo="<?= $tKey ?>">
                            <i class="bi <?= $tData['icon'] ?>"></i> <?= $tData['label'] ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="hidden" id="historialTipo" value="nota">
                        <textarea id="historialContenido" class="form-control form-control-sm" rows="2" placeholder="Añadir contacto o nota..." style="resize: none;"></textarea>
                        <button type="button" class="btn btn-primary btn-sm align-self-end" id="btnAddHistorial" style="white-space: nowrap;">
                            <i class="bi bi-plus-lg"></i> Añadir
                        </button>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="timeline" id="timelineContainer">
                    <?php if (empty($historial)): ?>
                        <p class="text-muted mb-0" id="emptyHistorial">Sin historial de contactos registrado</p>
                    <?php else: ?>
                        <?php foreach ($historial as $h):
                            $hTipo = $tiposHistorial[$h['tipo']] ?? $tiposHistorial['otro'];
                            $iniciales = strtoupper(mb_substr($h['usuario_nombre'] ?? '?', 0, 1) . mb_substr($h['usuario_apellidos'] ?? '', 0, 1));
                        ?>
                        <div class="timeline-entry" data-id="<?= $h['id'] ?>">
                            <div class="timeline-avatar" style="background: <?= $hTipo['color'] ?>;"><?= $iniciales ?></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="name"><?= sanitize(($h['usuario_nombre'] ?? '') . ' ' . mb_substr($h['usuario_apellidos'] ?? '', 0, 1)) ?>.</span>
                                    <span class="tipo-badge" style="background: <?= $hTipo['color'] ?>20; color: <?= $hTipo['color'] ?>;">
                                        <i class="bi <?= $hTipo['icon'] ?>"></i> <?= $hTipo['label'] ?>
                                    </span>
                                    <span class="date"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></span>
                                    <div class="timeline-actions ms-auto">
                                        <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteHistorial(<?= $h['id'] ?>)" title="Eliminar">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="timeline-text"><?= nl2br(sanitize($h['contenido'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

<script>
const PROSPECTO_ID = <?= $id ?>;
const CSRF_TOKEN = '<?= csrfToken() ?>';
const API_URL = '<?= APP_URL ?>/api/prospectos.php';

// ─────────────────────────────────
// INLINE EDITING
// ─────────────────────────────────
document.querySelectorAll('.editable-field').forEach(el => {
    el.addEventListener('click', function(e) {
        if (this.classList.contains('editing')) return;
        if (e.target.tagName === 'A') return; // Don't interfere with links

        const field = this.dataset.field;
        const type = this.dataset.type;
        const currentVal = this.dataset.value || '';
        const originalHTML = this.innerHTML;

        this.classList.add('editing');

        let input;
        if (type === 'textarea') {
            input = document.createElement('textarea');
            input.className = 'inline-input';
            input.rows = 3;
            input.value = currentVal;
        } else if (type === 'select') {
            input = document.createElement('select');
            input.className = 'inline-select';
            const options = JSON.parse(this.dataset.options || '{}');
            input.innerHTML = '<option value="">-</option>';
            for (const [key, label] of Object.entries(options)) {
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = label;
                if (key === currentVal) opt.selected = true;
                input.appendChild(opt);
            }
        } else if (type === 'toggle') {
            // Toggle boolean
            const newVal = currentVal === '1' ? '0' : '1';
            saveInlineField(field, newVal, this, originalHTML);
            return;
        } else if (type === 'date') {
            input = document.createElement('input');
            input.type = 'date';
            input.className = 'inline-input';
            input.value = currentVal;
        } else {
            input = document.createElement('input');
            input.type = type === 'number' ? 'number' : (type === 'email' ? 'email' : 'text');
            input.className = 'inline-input';
            input.value = currentVal;
            if (type === 'number') input.step = 'any';
        }

        this.innerHTML = '';
        this.appendChild(input);
        input.focus();
        if (input.select) input.select();

        const save = () => {
            const newVal = input.value;
            if (newVal !== currentVal) {
                saveInlineField(field, newVal, el, originalHTML);
            } else {
                el.innerHTML = originalHTML;
                el.classList.remove('editing');
            }
        };

        input.addEventListener('blur', () => setTimeout(save, 150));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && type !== 'textarea') { e.preventDefault(); save(); }
            if (e.key === 'Escape') { el.innerHTML = originalHTML; el.classList.remove('editing'); }
        });
    });
});

function saveInlineField(field, value, el, originalHTML) {
    // Show saving spinner
    el.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>';

    const isCustomField = el.dataset.type === 'custom_field';
    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);

    if (isCustomField) {
        body.append('accion', 'editar_custom_field');
        body.append('prospecto_id', PROSPECTO_ID);
        body.append('field_id', el.dataset.customFieldId);
        body.append('valor', value);
    } else {
        body.append('accion', 'editar_campo');
        body.append('id', PROSPECTO_ID);
        body.append('campo', field);
        body.append('valor', value);
    }

    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            el.dataset.value = value;
            // Reload to get proper formatting
            location.reload();
        } else {
            el.innerHTML = originalHTML;
            el.classList.remove('editing');
            alert('Error: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(() => {
        el.innerHTML = originalHTML;
        el.classList.remove('editing');
        alert('Error de conexión');
    });
}

// ─────────────────────────────────
// MINI CALENDAR (Flatpickr)
// ─────────────────────────────────
let selectedCalDate = null;

const miniCal = flatpickr('#miniCalendar', {
    inline: true,
    locale: 'es',
    dateFormat: 'Y-m-d',
    defaultDate: '<?= $p['fecha_proximo_contacto'] ?? 'today' ?>',
    onChange: function(selectedDates, dateStr) {
        selectedCalDate = dateStr;
        loadTasksForDay(dateStr);
    }
});

function loadTasksForDay(fecha) {
    document.getElementById('miniCalTasks').style.display = 'block';
    document.getElementById('miniCalDate').textContent = new Date(fecha + 'T12:00:00').toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' });
    document.getElementById('miniCalTaskList').innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-sm"></span></div>';

    fetch(`${API_URL}?accion=tareas_dia&prospecto_id=${PROSPECTO_ID}&fecha=${fecha}`)
    .then(r => r.json())
    .then(data => {
        const list = document.getElementById('miniCalTaskList');
        if (data.tareas && data.tareas.length > 0) {
            list.innerHTML = data.tareas.map(t => {
                const colors = {pendiente: '#f59e0b', en_progreso: '#3b82f6', completada: '#10b981'};
                return `<div class="task-item" style="background: ${colors[t.estado] || '#6b7280'}15; border-left: 3px solid ${colors[t.estado] || '#6b7280'}">
                    <i class="bi bi-check2-square" style="color: ${colors[t.estado] || '#6b7280'}"></i>
                    <span>${t.titulo}</span>
                </div>`;
            }).join('');
        } else {
            list.innerHTML = '<div class="text-muted small text-center py-2"><i class="bi bi-calendar-x"></i> Sin tareas este día</div>';
        }
    });
}

document.getElementById('btnSetProxContacto').addEventListener('click', function() {
    if (!selectedCalDate) return;
    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    body.append('accion', 'set_proximo_contacto');
    body.append('prospecto_id', PROSPECTO_ID);
    body.append('fecha', selectedCalDate);

    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
});

// ─────────────────────────────────
// HISTORIAL DE CONTACTOS
// ─────────────────────────────────
const tipoConfig = <?= json_encode($tiposHistorial) ?>;

// Type selector
document.querySelectorAll('.contact-type-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.contact-type-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('historialTipo').value = this.dataset.tipo;
    });
});

// Add historial entry
document.getElementById('btnAddHistorial').addEventListener('click', addHistorialEntry);
document.getElementById('historialContenido').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        addHistorialEntry();
    }
});

function addHistorialEntry() {
    const contenido = document.getElementById('historialContenido').value.trim();
    const tipo = document.getElementById('historialTipo').value;
    if (!contenido) return;

    const btn = document.getElementById('btnAddHistorial');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    body.append('accion', 'add_historial');
    body.append('prospecto_id', PROSPECTO_ID);
    body.append('contenido', contenido);
    body.append('tipo', tipo);

    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Añadir';

        if (data.success) {
            const e = data.entrada;
            const tc = tipoConfig[e.tipo] || tipoConfig['otro'];
            const empty = document.getElementById('emptyHistorial');
            if (empty) empty.remove();

            const html = `
            <div class="timeline-entry" data-id="${e.id}" style="animation: fadeIn 0.3s ease;">
                <div class="timeline-avatar" style="background: ${tc.color};">${e.usuario_iniciales}</div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="name">${e.usuario}</span>
                        <span class="tipo-badge" style="background: ${tc.color}20; color: ${tc.color};">
                            <i class="bi ${tc.icon}"></i> ${tc.label}
                        </span>
                        <span class="date">${e.fecha}</span>
                        <div class="timeline-actions ms-auto">
                            <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteHistorial(${e.id})" title="Eliminar">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="timeline-text">${e.contenido.replace(/\n/g, '<br>')}</div>
                </div>
            </div>`;

            document.getElementById('timelineContainer').insertAdjacentHTML('afterbegin', html);
            document.getElementById('historialContenido').value = '';

            // Update badge count
            const badge = document.querySelector('.card-header .badge.bg-secondary');
            if (badge) badge.textContent = parseInt(badge.textContent) + 1;
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Añadir';
        alert('Error de conexión');
    });
}

function deleteHistorial(id) {
    if (!confirm('¿Eliminar esta entrada?')) return;

    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    body.append('accion', 'delete_historial');
    body.append('entrada_id', id);

    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.querySelector(`.timeline-entry[data-id="${id}"]`);
            if (el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }
            const badge = document.querySelector('.card-header .badge.bg-secondary');
            if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent) - 1);
        }
    });
}
</script>

<style>
@keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
