<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');

    if ($a === 'nueva_conversacion') {
        $clienteId = intval(post('cliente_id'));
        $canal = post('canal', 'email');
        $asunto = trim(post('asunto'));
        $mensaje = trim(post('mensaje'));
        if ($clienteId && $mensaje) {
            $db->prepare("INSERT INTO conversaciones (cliente_id, canal, asunto, ultimo_mensaje, ultimo_mensaje_at, asignado_a) VALUES (?,?,?,?,NOW(),?)")
                ->execute([$clienteId, $canal, $asunto, mb_strimwidth($mensaje, 0, 200), currentUserId()]);
            $convId = $db->lastInsertId();
            $db->prepare("INSERT INTO conversacion_mensajes (conversacion_id, direccion, contenido, usuario_id) VALUES (?,'saliente',?,?)")
                ->execute([$convId, $mensaje, currentUserId()]);

            // Actually send
            $cliente = $db->prepare("SELECT * FROM clientes WHERE id=?"); $cliente->execute([$clienteId]); $cliente=$cliente->fetch();
            if ($canal === 'email' && $cliente['email']) {
                $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
                @mail($cliente['email'], $asunto ?: 'Mensaje', nl2br(htmlspecialchars($mensaje)), $headers);
            }
            setFlash('success', 'Conversacion iniciada.');
            header('Location: index.php?conv='.$convId); exit;
        }
    }

    if ($a === 'responder') {
        $convId = intval(post('conv_id'));
        $mensaje = trim(post('mensaje'));
        if ($convId && $mensaje) {
            $db->prepare("INSERT INTO conversacion_mensajes (conversacion_id, direccion, contenido, usuario_id) VALUES (?,'saliente',?,?)")
                ->execute([$convId, $mensaje, currentUserId()]);
            $db->prepare("UPDATE conversaciones SET ultimo_mensaje=?, ultimo_mensaje_at=NOW() WHERE id=?")
                ->execute([mb_strimwidth($mensaje, 0, 200), $convId]);

            // Send via channel
            $conv = $db->prepare("SELECT c.*, cl.email, cl.telefono FROM conversaciones c JOIN clientes cl ON c.cliente_id=cl.id WHERE c.id=?");
            $conv->execute([$convId]); $conv=$conv->fetch();
            if ($conv['canal'] === 'email' && $conv['email']) {
                $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
                @mail($conv['email'], 'Re: '.($conv['asunto']?:'Mensaje'), nl2br(htmlspecialchars($mensaje)), $headers);
            } elseif ($conv['canal'] === 'sms' && $conv['telefono']) {
                $smsConfig = $db->query("SELECT * FROM sms_config WHERE activo=1 LIMIT 1")->fetch();
                if ($smsConfig && $smsConfig['proveedor'] === 'twilio') {
                    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$smsConfig['api_sid']}/Messages.json");
                    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
                        CURLOPT_USERPWD=>$smsConfig['api_sid'].':'.$smsConfig['api_secret'],
                        CURLOPT_POSTFIELDS=>http_build_query(['To'=>$conv['telefono'],'From'=>$smsConfig['telefono_remitente'],'Body'=>$mensaje])]);
                    curl_exec($ch); curl_close($ch);
                }
            }
            header('Location: index.php?conv='.$convId); exit;
        }
    }

    if ($a === 'cerrar') {
        $db->prepare("UPDATE conversaciones SET estado='cerrada' WHERE id=?")->execute([intval(post('conv_id'))]);
        header('Location: index.php'); exit;
    }
    if ($a === 'archivar') {
        $db->prepare("UPDATE conversaciones SET estado='archivada' WHERE id=?")->execute([intval(post('conv_id'))]);
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'Conversaciones';
require_once __DIR__ . '/../../includes/header.php';

$filtro = get('filtro', 'abierta');
$canal = get('canal', '');
$where = "WHERE cv.estado = ?";
$params = [$filtro];
if ($canal) { $where .= " AND cv.canal = ?"; $params[] = $canal; }

$convs = $db->prepare("SELECT cv.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.email as cli_email, c.telefono as cli_telefono, u.nombre as agente_nombre
    FROM conversaciones cv
    LEFT JOIN clientes c ON cv.cliente_id = c.id
    LEFT JOIN usuarios u ON cv.asignado_a = u.id
    $where ORDER BY cv.ultimo_mensaje_at DESC");
$convs->execute($params); $convs = $convs->fetchAll();

$activeConv = null; $mensajes = [];
$convId = intval(get('conv'));
if ($convId) {
    $activeConv = $db->prepare("SELECT cv.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.email as cli_email, c.telefono as cli_telefono
        FROM conversaciones cv LEFT JOIN clientes c ON cv.cliente_id=c.id WHERE cv.id=?");
    $activeConv->execute([$convId]); $activeConv=$activeConv->fetch();
    if ($activeConv) {
        $mensajes = $db->prepare("SELECT m.*, u.nombre as usuario_nombre FROM conversacion_mensajes m LEFT JOIN usuarios u ON m.usuario_id=u.id WHERE m.conversacion_id=? ORDER BY m.created_at ASC");
        $mensajes->execute([$convId]); $mensajes=$mensajes->fetchAll();
        $db->prepare("UPDATE conversacion_mensajes SET leido=1 WHERE conversacion_id=? AND direccion='entrante' AND leido=0")->execute([$convId]);
        $db->prepare("UPDATE conversaciones SET no_leidos=0 WHERE id=?")->execute([$convId]);
    }
}

$canalIcons = ['email'=>'bi-envelope text-primary','sms'=>'bi-phone text-success','whatsapp'=>'bi-whatsapp text-success','chat'=>'bi-chat-dots text-info'];
$clientes = $db->query("SELECT id, nombre, apellidos, email, telefono FROM clientes WHERE activo=1 ORDER BY nombre LIMIT 500")->fetchAll();
?>

<style>
.conv-list{max-height:calc(100vh - 250px);overflow-y:auto}
.conv-item{padding:12px;border-bottom:1px solid #f0f0f0;cursor:pointer;transition:background .2s}
.conv-item:hover,.conv-item.active{background:#f0fdf4}
.msg-container{max-height:calc(100vh - 350px);overflow-y:auto;padding:20px}
.msg-bubble{max-width:75%;padding:10px 14px;border-radius:12px;margin-bottom:8px}
.msg-out{background:#10b981;color:#fff;margin-left:auto;border-bottom-right-radius:4px}
.msg-in{background:#f1f5f9;border-bottom-left-radius:4px}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex gap-2">
        <a href="?filtro=abierta" class="btn btn-sm <?= $filtro==='abierta'?'btn-primary':'btn-outline-secondary' ?>">Abiertas</a>
        <a href="?filtro=cerrada" class="btn btn-sm <?= $filtro==='cerrada'?'btn-primary':'btn-outline-secondary' ?>">Cerradas</a>
        <a href="?filtro=archivada" class="btn btn-sm <?= $filtro==='archivada'?'btn-primary':'btn-outline-secondary' ?>">Archivadas</a>
    </div>
    <div class="d-flex gap-2">
        <a href="?filtro=<?= $filtro ?>&canal=email" class="btn btn-sm <?= $canal==='email'?'btn-outline-primary':'btn-outline-secondary' ?>"><i class="bi bi-envelope"></i></a>
        <a href="?filtro=<?= $filtro ?>&canal=sms" class="btn btn-sm <?= $canal==='sms'?'btn-outline-success':'btn-outline-secondary' ?>"><i class="bi bi-phone"></i></a>
        <a href="?filtro=<?= $filtro ?>&canal=whatsapp" class="btn btn-sm <?= $canal==='whatsapp'?'btn-outline-success':'btn-outline-secondary' ?>"><i class="bi bi-whatsapp"></i></a>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNueva"><i class="bi bi-plus"></i> Nueva</button>
    </div>
</div>

<div class="row g-3">
    <!-- Lista -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="conv-list">
                <?php if (empty($convs)): ?>
                <div class="text-center text-muted py-5"><i class="bi bi-chat-square-text fs-1 d-block mb-2"></i><small>Sin conversaciones</small></div>
                <?php else: foreach ($convs as $cv): ?>
                <a href="?filtro=<?= $filtro ?>&conv=<?= $cv['id'] ?>" class="conv-item d-block text-decoration-none text-dark <?= $convId===$cv['id']?'active':'' ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <i class="bi <?= $canalIcons[$cv['canal']]??'bi-chat' ?>"></i>
                            <strong class="small"><?= sanitize(($cv['cli_nombre']??'').' '.($cv['cli_apellidos']??'')) ?></strong>
                            <?php if ($cv['no_leidos'] > 0): ?><span class="badge bg-danger rounded-pill ms-1"><?= $cv['no_leidos'] ?></span><?php endif; ?>
                        </div>
                        <small class="text-muted"><?= $cv['ultimo_mensaje_at']?date('d/m H:i',strtotime($cv['ultimo_mensaje_at'])):'' ?></small>
                    </div>
                    <?php if ($cv['asunto']): ?><div class="small fw-semibold"><?= sanitize($cv['asunto']) ?></div><?php endif; ?>
                    <div class="small text-muted text-truncate"><?= sanitize($cv['ultimo_mensaje'] ?? '') ?></div>
                </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Chat -->
    <div class="col-md-8">
        <?php if ($activeConv): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi <?= $canalIcons[$activeConv['canal']]??'bi-chat' ?>"></i>
                    <strong><?= sanitize(($activeConv['cli_nombre']??'').' '.($activeConv['cli_apellidos']??'')) ?></strong>
                    <small class="text-muted ms-2"><?= $activeConv['cli_email'] ?? $activeConv['cli_telefono'] ?? '' ?></small>
                    <?php if ($activeConv['asunto']): ?><br><small class="text-muted"><?= sanitize($activeConv['asunto']) ?></small><?php endif; ?>
                </div>
                <div class="d-flex gap-1">
                    <?php if ($activeConv['estado'] === 'abierta'): ?>
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="cerrar"><input type="hidden" name="conv_id" value="<?= $activeConv['id'] ?>"><button class="btn btn-sm btn-outline-warning"><i class="bi bi-x-circle"></i> Cerrar</button></form>
                    <?php endif; ?>
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="archivar"><input type="hidden" name="conv_id" value="<?= $activeConv['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-archive"></i></button></form>
                </div>
            </div>

            <div class="msg-container" id="msgContainer">
                <?php foreach ($mensajes as $m): ?>
                <div class="d-flex <?= $m['direccion']==='saliente'?'justify-content-end':'' ?>">
                    <div class="msg-bubble <?= $m['direccion']==='saliente'?'msg-out':'msg-in' ?>">
                        <?php if ($m['direccion']==='saliente' && $m['usuario_nombre']): ?><div class="small opacity-75 mb-1"><?= sanitize($m['usuario_nombre']) ?></div><?php endif; ?>
                        <div><?= nl2br(sanitize($m['contenido'])) ?></div>
                        <div class="small <?= $m['direccion']==='saliente'?'text-white-50':'text-muted' ?> mt-1"><?= date('d/m H:i', strtotime($m['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($activeConv['estado'] === 'abierta'): ?>
            <div class="card-footer bg-white">
                <form method="POST" class="d-flex gap-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="accion" value="responder">
                    <input type="hidden" name="conv_id" value="<?= $activeConv['id'] ?>">
                    <textarea name="mensaje" class="form-control" rows="2" placeholder="Escribe tu mensaje..." required></textarea>
                    <button class="btn btn-primary align-self-end"><i class="bi bi-send"></i></button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-chat-square-text fs-1 d-block mb-3"></i>
                <h5>Selecciona una conversacion</h5>
                <p>O inicia una nueva con el boton +</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal nueva conversacion -->
<div class="modal fade" id="modalNueva" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="nueva_conversacion">
    <div class="modal-header"><h5 class="modal-title">Nueva Conversacion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Cliente</label>
            <select name="cliente_id" class="form-select" required>
                <option value="">Seleccionar...</option>
                <?php foreach($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['nombre'].' '.$c['apellidos']) ?> - <?= sanitize($c['email']??$c['telefono']??'') ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Canal</label>
            <select name="canal" class="form-select"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select>
        </div>
        <div class="mb-3"><label class="form-label">Asunto</label><input type="text" name="asunto" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Mensaje</label><textarea name="mensaje" class="form-control" rows="4" required></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-send"></i> Enviar</button></div>
</form></div></div></div>

<script>
const mc = document.getElementById('msgContainer');
if (mc) mc.scrollTop = mc.scrollHeight;
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
