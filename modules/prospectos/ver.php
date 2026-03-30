<?php
$pageTitle = 'Detalle Prospecto';
require_once __DIR__ . '/../../includes/header.php';

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

        // Marcar prospecto como captado
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
    'contactado' => ['label' => 'Contactado', 'color' => '#64748b', 'icon' => 'bi-telephone'],
    'seguimiento' => ['label' => 'En Seguimiento', 'color' => '#3b82f6', 'icon' => 'bi-arrow-repeat'],
    'visita_programada' => ['label' => 'Visita Programada', 'color' => '#8b5cf6', 'icon' => 'bi-calendar-check'],
    'en_negociacion' => ['label' => 'En Negociación', 'color' => '#f59e0b', 'icon' => 'bi-chat-left-dots'],
    'captado' => ['label' => 'Captado', 'color' => '#10b981', 'icon' => 'bi-check-circle'],
    'descartado' => ['label' => 'Descartado', 'color' => '#ef4444', 'icon' => 'bi-x-circle'],
];

$estados = [
    'nuevo' => 'Nuevo',
    'en_proceso' => 'En Proceso',
    'pendiente' => 'Pendiente Respuesta',
    'sin_interes' => 'Sin Interés',
    'captado' => 'Captado',
];

$etapaInfo = $etapas[$p['etapa']] ?? ['label' => $p['etapa'], 'color' => '#64748b', 'icon' => 'bi-circle'];
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <span class="badge fs-6 me-2" style="background: <?= $etapaInfo['color'] ?>;">
            <i class="bi <?= $etapaInfo['icon'] ?>"></i> <?= $etapaInfo['label'] ?>
        </span>
        <span class="badge-estado badge-<?= $p['estado'] ?>"><?= $estados[$p['estado']] ?? $p['estado'] ?></span>
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
        <a href="form.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="delete.php?id=<?= $id ?>&csrf=<?= csrfToken() ?>" class="btn btn-outline-danger" data-confirm="Eliminar este prospecto?"><i class="bi bi-trash"></i> Eliminar</a>
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
                $isFuture = $idx > $currentIdx;
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
        <!-- Info del Prospecto -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person"></i> Datos del Prospecto</div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0"><?= sanitize($p['nombre']) ?></h5>
                    <span class="badge bg-primary"><?= sanitize($p['referencia']) ?></span>
                </div>
                <?php if ($p['email']): ?>
                <p class="mb-2"><i class="bi bi-envelope text-muted"></i> <a href="mailto:<?= sanitize($p['email']) ?>"><?= sanitize($p['email']) ?></a></p>
                <?php endif; ?>
                <?php if ($p['telefono']): ?>
                <p class="mb-2"><i class="bi bi-telephone text-muted"></i> <a href="tel:<?= sanitize($p['telefono']) ?>"><?= sanitize($p['telefono']) ?></a></p>
                <?php endif; ?>
                <?php if ($p['telefono2']): ?>
                <p class="mb-2"><i class="bi bi-telephone text-muted"></i> <?= sanitize($p['telefono2']) ?></p>
                <?php endif; ?>
                <p class="mb-0 text-muted"><small>Alta: <?= formatFecha($p['created_at']) ?></small></p>
            </div>
        </div>

        <!-- Seguimiento -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-calendar-event"></i> Seguimiento</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="detail-label">Primer Contacto</div>
                        <div class="detail-value"><?= $p['fecha_contacto'] ? formatFecha($p['fecha_contacto']) : '-' ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Próx. Contacto</div>
                        <div class="detail-value">
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
                            <?php else: ?>-<?php endif; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Comisión</div>
                        <div class="detail-value"><?= $p['comision'] ? $p['comision'] . '%' : '-' ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Exclusividad</div>
                        <div class="detail-value">
                            <?= $p['exclusividad'] ? '<i class="bi bi-check-circle text-success"></i> Sí' : '<span class="text-muted">No</span>' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agente -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person-badge"></i> Agente Asignado</div>
            <div class="card-body">
                <strong><?= sanitize(($p['agente_nombre'] ?? '') . ' ' . ($p['agente_apellidos'] ?? '')) ?></strong>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Datos de la Propiedad -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-house-door"></i> Datos de la Propiedad</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="detail-label">Tipo</div>
                        <div class="detail-value"><?= sanitize($p['tipo_propiedad'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-label">Precio Estimado</div>
                        <div class="detail-value fw-bold"><?= $p['precio_estimado'] ? formatPrecio($p['precio_estimado']) : '-' ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-label">Precio Propietario</div>
                        <div class="detail-value"><?= $p['precio_propietario'] ? formatPrecio($p['precio_propietario']) : '-' ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-label">Superficie</div>
                        <div class="detail-value"><?= $p['superficie'] ? formatSuperficie($p['superficie']) : '-' ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-label">Habitaciones</div>
                        <div class="detail-value"><?= $p['habitaciones'] ?? '-' ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-label">Dirección</div>
                        <div class="detail-value"><?= sanitize($p['direccion'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-label">Barrio / Zona</div>
                        <div class="detail-value"><?= sanitize($p['barrio'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-label">Localidad</div>
                        <div class="detail-value"><?= sanitize($p['localidad'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-label">Provincia</div>
                        <div class="detail-value"><?= sanitize($p['provincia'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="detail-label">Código Postal</div>
                        <div class="detail-value"><?= sanitize($p['codigo_postal'] ?? '-') ?></div>
                    </div>
                    <?php if ($p['enlace']): ?>
                    <div class="col-12">
                        <div class="detail-label">Enlace</div>
                        <div class="detail-value">
                            <a href="<?= sanitize($p['enlace']) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-box-arrow-up-right"></i> <?= sanitize(mb_substr($p['enlace'], 0, 60)) ?><?= mb_strlen($p['enlace']) > 60 ? '...' : '' ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notas -->
        <?php if ($p['notas']): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-chat-text"></i> Notas</div>
            <div class="card-body"><?= nl2br(sanitize($p['notas'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- Reformas -->
        <?php if ($p['reformas']): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-tools"></i> Reformas</div>
            <div class="card-body"><?= nl2br(sanitize($p['reformas'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- Historial de Contactos -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-clock-history"></i> Historial de Contactos</div>
            <div class="card-body">
                <?php if ($p['historial_contactos']): ?>
                    <?= nl2br(sanitize($p['historial_contactos'])) ?>
                <?php else: ?>
                    <p class="text-muted mb-0">Sin historial de contactos registrado</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
