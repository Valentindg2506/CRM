<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$id = intval(get('id'));
$co = null;
if ($id) { $co = $db->prepare("SELECT * FROM contratos WHERE id=?"); $co->execute([$id]); $co=$co->fetch(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $titulo = trim(post('titulo'));
    $clienteId = intval(post('cliente_id')) ?: null;
    $propiedadId = intval(post('propiedad_id')) ?: null;
    $contenido = $_POST['contenido'] ?? '';
    $fechaExp = post('fecha_expiracion') ?: null;

    // Replace variables
    if ($clienteId) {
        $cl = $db->prepare("SELECT * FROM clientes WHERE id=?"); $cl->execute([$clienteId]); $cl=$cl->fetch();
        if ($cl) {
            $contenido = str_replace(['{{cliente_nombre}}','{{cliente_dni}}','{{cliente_email}}','{{cliente_telefono}}'],
                [($cl['nombre']??'').' '.($cl['apellidos']??''), $cl['dni_nie_cif']??'', $cl['email']??'', $cl['telefono']??''], $contenido);
        }
    }
    if ($propiedadId) {
        $pr = $db->prepare("SELECT * FROM propiedades WHERE id=?"); $pr->execute([$propiedadId]); $pr=$pr->fetch();
        if ($pr) {
            $contenido = str_replace(['{{propiedad_direccion}}','{{propiedad_referencia}}','{{propiedad_precio}}','{{propiedad_titulo}}'],
                [$pr['direccion']??'', $pr['referencia']??'', number_format($pr['precio']??0,2,',','.'), $pr['titulo']??''], $contenido);
        }
    }
    $config = $db->query("SELECT * FROM configuracion_pagos LIMIT 1")->fetch();
    $contenido = str_replace(['{{empresa_nombre}}','{{empresa_cif}}','{{fecha}}','{{ciudad}}'],
        [$config['empresa_nombre']??'', $config['empresa_cif']??'', date('d/m/Y'), ''], $contenido);

    if ($id) {
        $db->prepare("UPDATE contratos SET titulo=?, cliente_id=?, propiedad_id=?, contenido=?, fecha_expiracion=? WHERE id=?")
            ->execute([$titulo, $clienteId, $propiedadId, $contenido, $fechaExp, $id]);
    } else {
        $token = bin2hex(random_bytes(32));
        $db->prepare("INSERT INTO contratos (titulo, cliente_id, propiedad_id, contenido, token, fecha_expiracion, usuario_id) VALUES (?,?,?,?,?,?,?)")
            ->execute([$titulo, $clienteId, $propiedadId, $contenido, $token, $fechaExp, currentUserId()]);
    }
    setFlash('success','Contrato guardado.');
    header('Location: index.php'); exit;
}

$pageTitle = $id ? 'Editar Contrato' : 'Nuevo Contrato';
require_once __DIR__ . '/../../includes/header.php';

$clientes = $db->query("SELECT id, nombre, apellidos FROM clientes WHERE activo=1 ORDER BY nombre")->fetchAll();
$propiedades = $db->query("SELECT id, titulo, referencia FROM propiedades WHERE estado != 'retirado' ORDER BY titulo")->fetchAll();
$plantillas = $db->query("SELECT * FROM contrato_plantillas WHERE activo=1 ORDER BY nombre")->fetchAll();
?>

<a href="index.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Volver</a>

<form method="POST">
    <?= csrfField() ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Titulo</label><input type="text" name="titulo" class="form-control" value="<?= sanitize($co['titulo']??'') ?>" required></div>
                <div class="col-md-3"><label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-select"><option value="">--</option>
                    <?php foreach($clientes as $c): ?><option value="<?= $c['id'] ?>" <?= ($co['cliente_id']??'')==$c['id']?'selected':'' ?>><?= sanitize($c['nombre'].' '.$c['apellidos']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Propiedad</label>
                    <select name="propiedad_id" class="form-select"><option value="">--</option>
                    <?php foreach($propiedades as $p): ?><option value="<?= $p['id'] ?>" <?= ($co['propiedad_id']??'')==$p['id']?'selected':'' ?>><?= sanitize($p['referencia'].' - '.$p['titulo']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">Expira</label><input type="date" name="fecha_expiracion" class="form-control" value="<?= $co['fecha_expiracion']??'' ?>"></div>
            </div>
        </div>
    </div>

    <?php if (!$id && !empty($plantillas)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><h6 class="mb-0">Usar plantilla</h6></div>
        <div class="card-body">
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($plantillas as $pl): ?>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('contenido').value=this.dataset.content" data-content="<?= htmlspecialchars($pl['contenido']) ?>"><?= sanitize($pl['nombre']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between">
            <h6 class="mb-0">Contenido del Contrato</h6>
            <small class="text-muted">Variables: {{cliente_nombre}}, {{cliente_dni}}, {{propiedad_direccion}}, {{propiedad_precio}}, {{empresa_nombre}}, {{fecha}}</small>
        </div>
        <div class="card-body"><textarea name="contenido" id="contenido" class="form-control" rows="25"><?= htmlspecialchars($co['contenido']??'') ?></textarea></div>
    </div>

    <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
