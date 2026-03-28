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

    if ($accion === 'crear' || $accion === 'editar') {
        $nombre = trim(post('nombre'));
        $url_destino = trim(post('url_destino'));
        $accion_tipo = post('accion_tipo', 'ninguna');
        $accion_valor = trim(post('accion_valor'));

        if (empty($nombre) || empty($url_destino)) {
            setFlash('danger', 'Nombre y URL destino son obligatorios.');
        } else {
            if ($accion === 'crear') {
                $codigo = substr(bin2hex(random_bytes(4)), 0, 8);
                $db->prepare("INSERT INTO trigger_links (nombre, codigo, url_destino, accion_tipo, accion_valor, usuario_id) VALUES (?,?,?,?,?,?)")
                    ->execute([$nombre, $codigo, $url_destino, $accion_tipo, $accion_valor, currentUserId()]);
                setFlash('success', 'Trigger Link creado.');
            } else {
                $linkId = intval(post('link_id'));
                $db->prepare("UPDATE trigger_links SET nombre=?, url_destino=?, accion_tipo=?, accion_valor=? WHERE id=?")
                    ->execute([$nombre, $url_destino, $accion_tipo, $accion_valor, $linkId]);
                setFlash('success', 'Trigger Link actualizado.');
            }
        }
    }
    if ($accion === 'eliminar') {
        $db->prepare("DELETE FROM trigger_links WHERE id = ?")->execute([intval(post('link_id'))]);
        setFlash('success', 'Eliminado.');
    }
    if ($accion === 'toggle') {
        $db->prepare("UPDATE trigger_links SET activo = NOT activo WHERE id = ?")->execute([intval(post('link_id'))]);
    }
    header('Location: trigger_links.php');
    exit;
}

$pageTitle = 'Trigger Links';
require_once __DIR__ . '/../../includes/header.php';

$links = $db->query("SELECT * FROM trigger_links ORDER BY created_at DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Marketing</a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear"><i class="bi bi-plus-lg"></i> Nuevo Trigger Link</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Nombre</th><th>URL Corta</th><th>Destino</th><th>Accion</th><th>Clicks</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($links as $l): ?>
                <tr>
                    <td><strong><?= sanitize($l['nombre']) ?></strong></td>
                    <td>
                        <code class="small"><?= APP_URL ?>/t.php?c=<?= sanitize($l['codigo']) ?></code>
                        <button class="btn btn-sm border-0 p-0 ms-1" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/t.php?c=<?= $l['codigo'] ?>')"><i class="bi bi-clipboard text-primary"></i></button>
                    </td>
                    <td><small class="text-muted"><?= sanitize(mb_strimwidth($l['url_destino'],0,40,'...')) ?></small></td>
                    <td><span class="badge bg-secondary"><?= $l['accion_tipo'] ?></span></td>
                    <td><strong><?= $l['total_clicks'] ?></strong></td>
                    <td>
                        <form method="POST" class="d-inline">
                            <?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="link_id" value="<?= $l['id'] ?>">
                            <button class="badge border-0 bg-<?= $l['activo'] ? 'success' : 'secondary' ?>" style="cursor:pointer"><?= $l['activo'] ? 'Activo' : 'Inactivo' ?></button>
                        </form>
                    </td>
                    <td class="text-end">
                        <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar?')">
                            <?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="link_id" value="<?= $l['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($links)): ?><tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-link-45deg fs-1 d-block mb-2"></i>No hay trigger links</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header"><h6 class="modal-title"><i class="bi bi-link-45deg"></i> Nuevo Trigger Link</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" name="nombre" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">URL destino *</label><input type="url" name="url_destino" class="form-control" required placeholder="https://..."></div>
                    <div class="mb-3">
                        <label class="form-label">Accion al hacer clic</label>
                        <select name="accion_tipo" class="form-select">
                            <option value="ninguna">Ninguna</option>
                            <option value="tag">Asignar tag</option>
                            <option value="notificacion">Enviar notificacion</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Valor de la accion</label><input type="text" name="accion_valor" class="form-control" placeholder="Ej: ID del tag"><small class="text-muted">Para tag: ID del tag. Para notificacion: mensaje.</small></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Crear</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
