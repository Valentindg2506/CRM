<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');
    if ($a === 'guardar') {
        $id = intval(post('pid'));
        if ($id) {
            $db->prepare("UPDATE contrato_plantillas SET nombre=?, contenido=?, categoria=? WHERE id=?")
                ->execute([trim(post('nombre')), post('contenido'), trim(post('categoria')), $id]);
        } else {
            $db->prepare("INSERT INTO contrato_plantillas (nombre, contenido, categoria) VALUES (?,?,?)")
                ->execute([trim(post('nombre')), post('contenido'), trim(post('categoria'))]);
        }
        setFlash('success','Plantilla guardada.');
    }
    if ($a === 'eliminar') { $db->prepare("DELETE FROM contrato_plantillas WHERE id=?")->execute([intval(post('pid'))]); setFlash('success','Eliminada.'); }
    header('Location: plantillas.php'); exit;
}

$pageTitle = 'Plantillas de Contratos';
require_once __DIR__ . '/../../includes/header.php';
$plantillas = $db->query("SELECT * FROM contrato_plantillas ORDER BY categoria, nombre")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Contratos</a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPlantilla"><i class="bi bi-plus-lg"></i> Nueva Plantilla</button>
</div>

<div class="row g-3">
    <?php foreach ($plantillas as $pl): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold"><?= sanitize($pl['nombre']) ?></h6>
                <span class="badge bg-light text-dark"><?= sanitize($pl['categoria']) ?></span>
                <div class="small text-muted mt-2"><?= sanitize(mb_strimwidth(strip_tags($pl['contenido']),0,120,'...')) ?></div>
            </div>
            <div class="card-footer bg-white border-0 d-flex justify-content-between">
                <button class="btn btn-sm btn-outline-primary" onclick='editPlantilla(<?= json_encode($pl) ?>)'><i class="bi bi-pencil"></i> Editar</button>
                <form method="POST" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="pid" value="<?= $pl['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="modalPlantilla" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="pid" id="pl_id" value="0">
    <div class="modal-header"><h5 class="modal-title" id="plTitle">Nueva Plantilla</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="nombre" id="pl_nombre" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Categoria</label><input type="text" name="categoria" id="pl_cat" class="form-control" value="general"></div>
        </div>
        <div class="mt-3"><label class="form-label">Contenido HTML</label><textarea name="contenido" id="pl_contenido" class="form-control" rows="15"></textarea>
        <small class="text-muted">Variables: {{cliente_nombre}}, {{cliente_dni}}, {{propiedad_direccion}}, {{propiedad_referencia}}, {{propiedad_precio}}, {{empresa_nombre}}, {{empresa_cif}}, {{fecha}}, {{ciudad}}</small></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Guardar</button></div>
</form></div></div></div>

<script>
function editPlantilla(p) {
    document.getElementById('pl_id').value = p.id;
    document.getElementById('pl_nombre').value = p.nombre;
    document.getElementById('pl_cat').value = p.categoria;
    document.getElementById('pl_contenido').value = p.contenido;
    document.getElementById('plTitle').textContent = 'Editar Plantilla';
    new bootstrap.Modal(document.getElementById('modalPlantilla')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
