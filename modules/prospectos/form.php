<?php
$pageTitle = 'Nuevo Prospecto';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/validators.php';

$db = getDB();
$id = intval(get('id'));
$prospecto = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM prospectos WHERE id = ?");
    $stmt->execute([$id]);
    $prospecto = $stmt->fetch();
    if (!$prospecto) { setFlash('danger', 'Prospecto no encontrado.'); header('Location: index.php'); exit; }
    $pageTitle = 'Editar Prospecto';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'nombre' => post('nombre'),
        'telefono' => post('telefono') ?: null,
        'telefono2' => post('telefono2') ?: null,
        'email' => post('email') ?: null,
        'etapa' => post('etapa', 'contactado'),
        'tipo_propiedad' => post('tipo_propiedad') ?: null,
        'direccion' => post('direccion') ?: null,
        'barrio' => post('barrio') ?: null,
        'localidad' => post('localidad') ?: null,
        'provincia' => post('provincia') ?: null,
        'codigo_postal' => post('codigo_postal') ?: null,
        'precio_estimado' => post('precio_estimado') ? floatval(str_replace(',', '.', post('precio_estimado'))) : null,
        'precio_propietario' => post('precio_propietario') ? floatval(str_replace(',', '.', post('precio_propietario'))) : null,
        'superficie' => post('superficie') ? floatval(str_replace(',', '.', post('superficie'))) : null,
        'habitaciones' => post('habitaciones') ?: null,
        'enlace' => post('enlace') ?: null,
        'fecha_contacto' => post('fecha_contacto') ?: null,
        'fecha_proximo_contacto' => post('fecha_proximo_contacto') ?: null,
        'estado' => post('estado', 'nuevo'),
        'comision' => post('comision') ? floatval(str_replace(',', '.', post('comision'))) : null,
        'exclusividad' => isset($_POST['exclusividad']) ? 1 : 0,
        'notas' => $_POST['notas'] ?? null,
        'reformas' => $_POST['reformas'] ?? null,
        'historial_contactos' => $_POST['historial_contactos'] ?? null,
        'agente_id' => post('agente_id') ?: currentUserId(),
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];

    // Validacion
    $erroresValidacion = validarProspecto($data);

    if (!empty($erroresValidacion)) {
        $error = implode('<br>', $erroresValidacion);
    } elseif (empty($data['nombre'])) {
        $error = 'El nombre es obligatorio.';
    } else {
        try {
            if ($id) {
                $fields = [];
                $values = [];
                foreach ($data as $k => $v) { $fields[] = "`$k` = ?"; $values[] = $v; }
                $values[] = $id;
                $db->prepare("UPDATE prospectos SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                registrarActividad('editar', 'prospecto', $id, $data['nombre']);
            } else {
                // Generar referencia automatica
                $stmtRef = $db->query("SELECT MAX(CAST(SUBSTRING(referencia, 3) AS UNSIGNED)) as max_ref FROM prospectos WHERE referencia LIKE 'PR%'");
                $maxRef = $stmtRef->fetch()['max_ref'] ?? 0;
                $data['referencia'] = 'PR' . str_pad($maxRef + 1, 3, '0', STR_PAD_LEFT);

                $fields = array_keys($data);
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                $db->prepare("INSERT INTO prospectos (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($data));
                $id = $db->lastInsertId();
                registrarActividad('crear', 'prospecto', $id, $data['nombre']);
            }
            setFlash('success', $prospecto ? 'Prospecto actualizado.' : 'Prospecto creado correctamente.');
            header('Location: ver.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$agentes = $db->query("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();
$provincias = getProvincias();
$p = $prospecto ?? [];

$etapas = [
    'contactado' => 'Contactado',
    'seguimiento' => 'En Seguimiento',
    'visita_programada' => 'Visita Programada',
    'en_negociacion' => 'En Negociación',
    'captado' => 'Captado',
    'descartado' => 'Descartado',
];

$estados = [
    'nuevo' => 'Nuevo',
    'en_proceso' => 'En Proceso',
    'pendiente' => 'Pendiente Respuesta',
    'sin_interes' => 'Sin Interés',
    'captado' => 'Captado',
];

$tiposPropiedad = ['Piso', 'Casa', 'Chalet', 'Adosado', 'Atico', 'Duplex', 'Estudio', 'Local', 'Oficina', 'Nave', 'Terreno', 'Garaje', 'Edificio', 'Otro'];
?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-person"></i> Datos del Prospecto</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= sanitize($p['nombre'] ?? post('nombre')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" name="telefono" class="form-control" value="<?= sanitize($p['telefono'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Teléfono 2</label>
                    <input type="tel" name="telefono2" class="form-control" value="<?= sanitize($p['telefono2'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Referencia</label>
                    <?php if ($id): ?>
                    <input type="text" class="form-control" value="<?= sanitize($p['referencia'] ?? '') ?>" readonly>
                    <?php else: ?>
                    <input type="text" class="form-control text-muted" value="Auto" readonly>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($p['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Etapa Pipeline</label>
                    <select name="etapa" class="form-select">
                        <?php foreach ($etapas as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['etapa'] ?? 'contactado') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach ($estados as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['estado'] ?? 'nuevo') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-house-door"></i> Datos de la Propiedad</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tipo de Propiedad</label>
                    <select name="tipo_propiedad" class="form-select">
                        <option value="">-</option>
                        <?php foreach ($tiposPropiedad as $tp): ?>
                        <option value="<?= $tp ?>" <?= ($p['tipo_propiedad'] ?? '') === $tp ? 'selected' : '' ?>><?= $tp ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Dirección</label>
                    <input type="text" name="direccion" class="form-control" value="<?= sanitize($p['direccion'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Barrio / Zona</label>
                    <input type="text" name="barrio" class="form-control" value="<?= sanitize($p['barrio'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Localidad</label>
                    <input type="text" name="localidad" class="form-control" value="<?= sanitize($p['localidad'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Provincia</label>
                    <select name="provincia" class="form-select">
                        <option value="">-</option>
                        <?php foreach ($provincias as $prov): ?>
                        <option value="<?= $prov ?>" <?= ($p['provincia'] ?? '') === $prov ? 'selected' : '' ?>><?= $prov ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Código Postal</label>
                    <input type="text" name="codigo_postal" class="form-control" maxlength="5" value="<?= sanitize($p['codigo_postal'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Enlace (Portal / Anuncio)</label>
                    <input type="url" name="enlace" class="form-control" placeholder="https://..." value="<?= sanitize($p['enlace'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Precio Estimado</label>
                    <div class="input-group">
                        <input type="text" name="precio_estimado" class="form-control format-precio" value="<?= sanitize($p['precio_estimado'] ?? '') ?>">
                        <span class="input-group-text">&euro;</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Precio Propietario</label>
                    <div class="input-group">
                        <input type="text" name="precio_propietario" class="form-control format-precio" value="<?= sanitize($p['precio_propietario'] ?? '') ?>">
                        <span class="input-group-text">&euro;</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Superficie (m²)</label>
                    <input type="number" name="superficie" class="form-control" step="0.01" value="<?= sanitize($p['superficie'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Habitaciones</label>
                    <input type="number" name="habitaciones" class="form-control" min="0" value="<?= sanitize($p['habitaciones'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Comisión (%)</label>
                    <div class="input-group">
                        <input type="text" name="comision" class="form-control" value="<?= sanitize($p['comision'] ?? '') ?>">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-calendar-event"></i> Seguimiento</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Fecha Primer Contacto</label>
                    <input type="date" name="fecha_contacto" class="form-control" value="<?= sanitize($p['fecha_contacto'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Próximo Contacto</label>
                    <input type="date" name="fecha_proximo_contacto" class="form-control" value="<?= sanitize($p['fecha_proximo_contacto'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Agente Asignado</label>
                    <select name="agente_id" class="form-select">
                        <?php foreach ($agentes as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= ($p['agente_id'] ?? currentUserId()) == $ag['id'] ? 'selected' : '' ?>><?= sanitize($ag['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-3 pt-1">
                        <div class="form-check">
                            <input type="checkbox" name="exclusividad" class="form-check-input" id="exclusividad" <?= ($p['exclusividad'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="exclusividad">Exclusividad</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($p['activo'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Activo</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-chat-text"></i> Notas y Comentarios</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Notas Generales</label>
                    <textarea name="notas" class="form-control" rows="4" placeholder="Notas sobre el prospecto..."><?= sanitize($p['notas'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reformas</label>
                    <textarea name="reformas" class="form-control" rows="4" placeholder="Info de reformas necesarias/realizadas..."><?= sanitize($p['reformas'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Historial de Contactos</label>
                    <textarea name="historial_contactos" class="form-control" rows="4" placeholder="Registro de llamadas, emails, visitas..."><?= sanitize($p['historial_contactos'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> <?= $id ? 'Actualizar' : 'Crear' ?> Prospecto</button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
