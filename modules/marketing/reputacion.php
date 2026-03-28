<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');

    if ($accion === 'guardar_config') {
        $db->prepare("UPDATE reputacion_config SET google_review_link=?, mensaje_solicitud=?, activo=? WHERE id=1")
            ->execute([trim(post('google_review_link')), trim(post('mensaje_solicitud')), post('activo') ? 1 : 0]);
        setFlash('success', 'Configuracion guardada.');
    }
    if ($accion === 'solicitar') {
        $clienteId = intval(post('cliente_id'));
        $tipo = post('tipo', 'google');
        $cfg = $db->query("SELECT * FROM reputacion_config WHERE id = 1")->fetch();
        $db->prepare("INSERT INTO resenas_solicitudes (cliente_id, tipo, enlace_resena, estado, enviada_at) VALUES (?,?,?,'enviada',NOW())")
            ->execute([$clienteId, $tipo, $cfg['google_review_link'] ?? '']);
        setFlash('success', 'Solicitud de resena creada.');
    }
    if ($accion === 'cambiar_estado') {
        $id = intval(post('solicitud_id'));
        $estado = post('nuevo_estado');
        $val = intval(post('valoracion')) ?: null;
        $db->prepare("UPDATE resenas_solicitudes SET estado=?, valoracion=?, completada_at=IF(?='completada',NOW(),completada_at) WHERE id=?")
            ->execute([$estado, $val, $estado, $id]);
    }
    header('Location: reputacion.php');
    exit;
}

$pageTitle = 'Reputacion';
require_once __DIR__ . '/../../includes/header.php';

$cfg = $db->query("SELECT * FROM reputacion_config WHERE id = 1")->fetch();
$stats = $db->query("SELECT COUNT(*) as total, SUM(estado='enviada') as enviadas, SUM(estado='completada') as completadas, AVG(CASE WHEN valoracion IS NOT NULL THEN valoracion END) as media FROM resenas_solicitudes")->fetch();
$solicitudes = $db->query("SELECT rs.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos FROM resenas_solicitudes rs JOIN clientes c ON rs.cliente_id = c.id ORDER BY rs.created_at DESC LIMIT 50")->fetchAll();
$clientes = $db->query("SELECT id, nombre, apellidos FROM clientes WHERE activo = 1 ORDER BY nombre")->fetchAll();

$estadoClases = ['pendiente'=>'warning','enviada'=>'primary','completada'=>'success','ignorada'=>'secondary'];
?>

<div class="d-flex mb-4"><a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Marketing</a></div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold"><?= $stats['total'] ?? 0 ?></div><small class="text-muted">Total solicitudes</small></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold text-primary"><?= $stats['enviadas'] ?? 0 ?></div><small class="text-muted">Enviadas</small></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold text-success"><?= $stats['completadas'] ?? 0 ?></div><small class="text-muted">Completadas</small></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold text-warning"><?= $stats['media'] ? number_format($stats['media'], 1) . ' <i class="bi bi-star-fill"></i>' : '-' ?></div><small class="text-muted">Valoracion media</small></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-gear"></i> Configuracion</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?><input type="hidden" name="accion" value="guardar_config">
                    <div class="mb-3"><label class="form-label">Enlace Google Reviews</label><input type="url" name="google_review_link" class="form-control" value="<?= sanitize($cfg['google_review_link'] ?? '') ?>" placeholder="https://g.page/..."></div>
                    <div class="mb-3"><label class="form-label">Mensaje solicitud</label><textarea name="mensaje_solicitud" class="form-control" rows="3"><?= sanitize($cfg['mensaje_solicitud'] ?? '') ?></textarea><small class="text-muted">Usa {{nombre}} para el nombre del cliente</small></div>
                    <div class="form-check form-switch mb-3"><input type="checkbox" name="activo" class="form-check-input" value="1" <?= ($cfg['activo'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label">Activo</label></div>
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> Guardar</button>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-send"></i> Nueva solicitud</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?><input type="hidden" name="accion" value="solicitar">
                    <div class="mb-3"><label class="form-label">Cliente</label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['nombre'].' '.$c['apellidos']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Tipo</label>
                        <select name="tipo" class="form-select"><option value="google">Google</option><option value="email">Email</option><option value="whatsapp">WhatsApp</option></select>
                    </div>
                    <button class="btn btn-warning text-white btn-sm w-100"><i class="bi bi-star"></i> Enviar solicitud</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-list-ul"></i> Solicitudes</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Cliente</th><th>Tipo</th><th>Estado</th><th>Valoracion</th><th>Fecha</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach ($solicitudes as $s): ?>
                            <tr>
                                <td><?= sanitize($s['cli_nombre'].' '.$s['cli_apellidos']) ?></td>
                                <td><span class="badge bg-secondary"><?= ucfirst($s['tipo']) ?></span></td>
                                <td><span class="badge bg-<?= $estadoClases[$s['estado']] ?>"><?= ucfirst($s['estado']) ?></span></td>
                                <td><?= $s['valoracion'] ? str_repeat('&#9733;', $s['valoracion']) : '-' ?></td>
                                <td><small><?= formatFecha($s['created_at']) ?></small></td>
                                <td class="text-end">
                                    <form method="POST" class="d-inline">
                                        <?= csrfField() ?><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                        <select name="nuevo_estado" class="form-select form-select-sm d-inline" style="width:auto" onchange="this.form.submit()">
                                            <option value="">...</option>
                                            <?php foreach (['completada','ignorada'] as $e): ?><option value="<?= $e ?>"><?= ucfirst($e) ?></option><?php endforeach; ?>
                                        </select>
                                        <input type="number" name="valoracion" class="form-control form-control-sm d-inline" style="width:60px" min="1" max="5" placeholder="1-5">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($solicitudes)): ?><tr><td colspan="6" class="text-center text-muted py-4">Sin solicitudes</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
