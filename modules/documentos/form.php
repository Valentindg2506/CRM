<?php
$pageTitle = 'Subir Documento';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/automatizaciones_engine.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (empty($_FILES['archivo']['name'])) {
        $error = 'Selecciona un archivo para subir.';
    } else {
        $result = uploadDocument($_FILES['archivo']);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $db->prepare("INSERT INTO documentos (nombre, tipo, archivo, tamano, propiedad_id, cliente_id, subido_por, notas) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([
                    post('nombre') ?: $_FILES['archivo']['name'],
                    post('tipo', 'otro'),
                    $result['filename'],
                    $result['size'],
                    post('propiedad_id') ?: null,
                    post('cliente_id') ?: null,
                    currentUserId(),
                    $_POST['notas'] ?? null,
                ]);
            $docId = intval($db->lastInsertId());
            registrarActividad('crear', 'documento', $docId, post('nombre'));

            try {
                automatizacionesEjecutarTrigger('nuevo_documento', [
                    'entidad_tipo' => 'documento',
                    'entidad_id' => $docId,
                    'documento_id' => $docId,
                    'cliente_id' => intval(post('cliente_id') ?: 0),
                    'propiedad_id' => intval(post('propiedad_id') ?: 0),
                    'actor_user_id' => intval(currentUserId()),
                    'owner_user_id' => intval(currentUserId()),
                ]);
            } catch (Throwable $e) {
                if (function_exists('logError')) {
                    logError('Error trigger nuevo_documento: ' . $e->getMessage());
                }
            }

            setFlash('success', 'Documento subido correctamente.');
            header('Location: index.php');
            exit;
        }
    }
}

$propiedades = $db->query("SELECT id, CONCAT(referencia, ' - ', titulo) as nombre FROM propiedades ORDER BY referencia")->fetchAll();
$clientes = $db->query("SELECT id, CONCAT(nombre, ' ', COALESCE(apellidos,'')) as nombre_completo FROM clientes WHERE activo = 1 ORDER BY nombre")->fetchAll();

$tiposDoc = [
    'contrato_arras'=>'Contrato de arras','contrato_compraventa'=>'Contrato compraventa','contrato_alquiler'=>'Contrato alquiler',
    'escritura'=>'Escritura','nota_simple'=>'Nota simple','certificado_energetico'=>'Certificado energetico',
    'cedula_habitabilidad'=>'Cedula de habitabilidad','ite'=>'ITE','licencia'=>'Licencia',
    'factura'=>'Factura','presupuesto'=>'Presupuesto','mandato'=>'Mandato de venta','ficha_cliente'=>'Ficha cliente','otro'=>'Otro'
];
?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-folder-plus"></i> Subir Documento</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre del documento</label>
                    <input type="text" name="nombre" class="form-control" placeholder="Se usara el nombre del archivo si se deja vacio">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo *</label>
                    <select name="tipo" class="form-select" required>
                        <?php foreach ($tiposDoc as $k => $v): ?>
                        <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Archivo *</label>
                    <input type="file" name="archivo" class="form-control" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Propiedad relacionada</label>
                    <select name="propiedad_id" class="form-select">
                        <option value="">Ninguna</option>
                        <?php foreach ($propiedades as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= get('propiedad_id') == $pr['id'] ? 'selected' : '' ?>><?= sanitize($pr['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cliente relacionado</label>
                    <select name="cliente_id" class="form-select">
                        <option value="">Ninguno</option>
                        <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= get('cliente_id') == $cl['id'] ? 'selected' : '' ?>><?= sanitize($cl['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Notas</label>
                    <textarea name="notas" class="form-control" rows="3"></textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-upload"></i> Subir Documento</button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
