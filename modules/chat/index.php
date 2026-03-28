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
    $convId = intval(post('conversacion_id'));

    if ($accion === 'enviar' && $convId) {
        $msg = trim(post('mensaje'));
        if ($msg) {
            $db->prepare("INSERT INTO chat_mensajes (conversacion_id, emisor, mensaje) VALUES (?, 'agente', ?)")->execute([$convId, $msg]);
            $db->prepare("UPDATE chat_conversaciones SET ultimo_mensaje = NOW(), estado = 'activa', agente_id = ? WHERE id = ?")->execute([currentUserId(), $convId]);
        }
    }
    if ($accion === 'cerrar' && $convId) {
        $db->prepare("UPDATE chat_conversaciones SET estado = 'cerrada' WHERE id = ?")->execute([$convId]);
        setFlash('success', 'Conversacion cerrada.');
    }
    if ($accion === 'vincular_cliente' && $convId) {
        $clienteId = intval(post('cliente_id'));
        $db->prepare("UPDATE chat_conversaciones SET cliente_id = ? WHERE id = ?")->execute([$clienteId, $convId]);
    }
    header('Location: index.php?id=' . $convId);
    exit;
}

$pageTitle = 'Chat en Vivo';
require_once __DIR__ . '/../../includes/header.php';

$convs = $db->query("SELECT cc.*, (SELECT COUNT(*) FROM chat_mensajes cm WHERE cm.conversacion_id = cc.id AND cm.leido = 0 AND cm.emisor = 'visitante') as no_leidos, (SELECT cm2.mensaje FROM chat_mensajes cm2 WHERE cm2.conversacion_id = cc.id ORDER BY cm2.id DESC LIMIT 1) as ultimo_msg FROM chat_conversaciones cc ORDER BY cc.ultimo_mensaje DESC LIMIT 50")->fetchAll();

$selectedId = intval(get('id'));
$mensajes = [];
$convActual = null;
if ($selectedId) {
    $stmt = $db->prepare("SELECT * FROM chat_conversaciones WHERE id = ?");
    $stmt->execute([$selectedId]);
    $convActual = $stmt->fetch();
    if ($convActual) {
        $db->prepare("UPDATE chat_mensajes SET leido = 1 WHERE conversacion_id = ? AND emisor = 'visitante'")->execute([$selectedId]);
        $mensajes = $db->prepare("SELECT * FROM chat_mensajes WHERE conversacion_id = ? ORDER BY created_at ASC");
        $mensajes->execute([$selectedId]);
        $mensajes = $mensajes->fetchAll();
    }
}

$clientes = $db->query("SELECT id, nombre, apellidos FROM clientes WHERE activo = 1 ORDER BY nombre LIMIT 100")->fetchAll();
$estadoColores = ['activa'=>'success', 'esperando'=>'warning', 'cerrada'=>'secondary'];
?>

<style>
.chat-layout { display: flex; gap: 1rem; height: calc(100vh - 180px); min-height: 400px; }
.chat-list { width: 320px; overflow-y: auto; flex-shrink: 0; }
.chat-main { flex: 1; display: flex; flex-direction: column; }
.chat-messages { flex: 1; overflow-y: auto; padding: 1rem; background: #f8fafc; border-radius: 8px; }
.chat-bubble { max-width: 75%; padding: 0.6rem 1rem; border-radius: 12px; margin-bottom: 0.5rem; font-size: 0.9rem; }
.chat-bubble.visitante { background: #e2e8f0; align-self: flex-start; }
.chat-bubble.agente { background: #10b981; color: #fff; align-self: flex-end; margin-left: auto; }
.chat-bubble.sistema { background: #fef3c7; text-align: center; font-size: 0.8rem; align-self: center; }
.conv-item { cursor: pointer; transition: background 0.15s; }
.conv-item:hover, .conv-item.active { background: rgba(16,185,129,0.08); }
</style>

<div class="chat-layout">
    <div class="chat-list">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-chat-dots"></i> Conversaciones</h6></div>
            <div class="card-body p-0" style="overflow-y:auto">
                <?php if (empty($convs)): ?>
                <p class="text-muted text-center py-4">Sin conversaciones</p>
                <?php endif; ?>
                <?php foreach ($convs as $cv): ?>
                <a href="?id=<?= $cv['id'] ?>" class="d-block p-3 border-bottom text-decoration-none text-dark conv-item <?= $selectedId == $cv['id'] ? 'active' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= sanitize($cv['nombre'] ?: 'Visitante') ?></strong>
                            <?php if ($cv['no_leidos'] > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1"><?= $cv['no_leidos'] ?></span>
                            <?php endif; ?>
                            <br><small class="text-muted"><?= sanitize(mb_strimwidth($cv['ultimo_msg'] ?? '', 0, 40, '...')) ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-<?= $estadoColores[$cv['estado']] ?> rounded-pill" style="font-size:0.65rem"><?= $cv['estado'] ?></span>
                            <br><small class="text-muted"><?= date('H:i', strtotime($cv['ultimo_mensaje'])) ?></small>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="chat-main">
        <?php if ($convActual): ?>
        <div class="card border-0 shadow-sm mb-2">
            <div class="card-body py-2 d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= sanitize($convActual['nombre']) ?></strong>
                    <?php if ($convActual['email']): ?><small class="text-muted ms-2"><?= sanitize($convActual['email']) ?></small><?php endif; ?>
                    <?php if ($convActual['telefono']): ?><small class="text-muted ms-2"><?= sanitize($convActual['telefono']) ?></small><?php endif; ?>
                    <small class="text-muted ms-2">IP: <?= sanitize($convActual['ip'] ?? '') ?></small>
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" class="d-flex gap-1">
                        <?= csrfField() ?>
                        <input type="hidden" name="conversacion_id" value="<?= $convActual['id'] ?>">
                        <input type="hidden" name="accion" value="vincular_cliente">
                        <select name="cliente_id" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
                            <option value="">Vincular cliente...</option>
                            <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $cl['id'] ?>" <?= $convActual['cliente_id'] == $cl['id'] ? 'selected' : '' ?>><?= sanitize($cl['nombre'] . ' ' . $cl['apellidos']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="conversacion_id" value="<?= $convActual['id'] ?>">
                        <input type="hidden" name="accion" value="cerrar">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Cerrar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="chat-messages d-flex flex-column" id="chatMessages">
            <?php foreach ($mensajes as $m): ?>
            <div class="chat-bubble <?= $m['emisor'] ?>">
                <?= nl2br(sanitize($m['mensaje'])) ?>
                <div class="small opacity-75 mt-1" style="font-size:0.7rem"><?= date('H:i', strtotime($m['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($convActual['estado'] !== 'cerrada'): ?>
        <form method="POST" class="mt-2">
            <?= csrfField() ?>
            <input type="hidden" name="conversacion_id" value="<?= $convActual['id'] ?>">
            <input type="hidden" name="accion" value="enviar">
            <div class="input-group">
                <input type="text" name="mensaje" class="form-control" placeholder="Escribe un mensaje..." autocomplete="off" required>
                <button class="btn btn-primary"><i class="bi bi-send"></i></button>
            </div>
        </form>
        <?php endif; ?>

        <?php else: ?>
        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
            <div class="text-center">
                <i class="bi bi-chat-dots fs-1 d-block mb-3"></i>
                <h5>Selecciona una conversacion</h5>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const msgs = document.getElementById('chatMessages');
if (msgs) msgs.scrollTop = msgs.scrollHeight;
<?php if ($selectedId): ?>
setTimeout(() => location.reload(), 15000);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
