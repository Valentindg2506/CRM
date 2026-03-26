<?php
$pageTitle = 'WhatsApp';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Obtener telefono seleccionado
$telefonoActivo = get('telefono');

// Busqueda
$busqueda = get('buscar');

// Obtener conversaciones agrupadas por telefono
$sqlConversaciones = "
    SELECT
        wm.telefono,
        c.nombre as cliente_nombre,
        c.apellidos as cliente_apellidos,
        c.id as cliente_id,
        MAX(wm.created_at) as ultimo_mensaje_fecha,
        (SELECT wm2.mensaje FROM whatsapp_mensajes wm2 WHERE wm2.telefono = wm.telefono ORDER BY wm2.created_at DESC LIMIT 1) as ultimo_mensaje,
        (SELECT COUNT(*) FROM whatsapp_mensajes wm3 WHERE wm3.telefono = wm.telefono AND wm3.direccion = 'entrante' AND wm3.estado = 'recibido') as no_leidos
    FROM whatsapp_mensajes wm
    LEFT JOIN clientes c ON wm.cliente_id = c.id
";

$params = [];
if (!empty($busqueda)) {
    $sqlConversaciones .= " WHERE (wm.telefono LIKE ? OR c.nombre LIKE ? OR c.apellidos LIKE ?)";
    $params = ["%$busqueda%", "%$busqueda%", "%$busqueda%"];
}

$sqlConversaciones .= " GROUP BY wm.telefono ORDER BY ultimo_mensaje_fecha DESC";

$stmt = $db->prepare($sqlConversaciones);
$stmt->execute($params);
$conversaciones = $stmt->fetchAll();

// Si hay telefono activo, obtener mensajes
$mensajes = [];
$clienteChat = null;
if (!empty($telefonoActivo)) {
    $stmtMsg = $db->prepare("
        SELECT wm.*, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos
        FROM whatsapp_mensajes wm
        LEFT JOIN clientes c ON wm.cliente_id = c.id
        WHERE wm.telefono = ?
        ORDER BY wm.created_at ASC
    ");
    $stmtMsg->execute([$telefonoActivo]);
    $mensajes = $stmtMsg->fetchAll();

    // Obtener cliente vinculado
    $stmtCliente = $db->prepare("
        SELECT c.id, c.nombre, c.apellidos
        FROM whatsapp_mensajes wm
        JOIN clientes c ON wm.cliente_id = c.id
        WHERE wm.telefono = ? AND wm.cliente_id IS NOT NULL
        LIMIT 1
    ");
    $stmtCliente->execute([$telefonoActivo]);
    $clienteChat = $stmtCliente->fetch();

    // Marcar mensajes entrantes como leidos
    $stmtLeer = $db->prepare("UPDATE whatsapp_mensajes SET estado = 'leido' WHERE telefono = ? AND direccion = 'entrante' AND estado = 'recibido'");
    $stmtLeer->execute([$telefonoActivo]);
}
?>

<div class="row g-0" style="height: calc(100vh - 180px); min-height: 500px;">
    <!-- Panel izquierdo: Lista de conversaciones -->
    <div class="col-md-4 col-lg-3 border-end bg-white d-flex flex-column" style="height: 100%;">
        <div class="p-3 border-bottom">
            <h6 class="mb-2"><i class="bi bi-whatsapp text-success"></i> Conversaciones</h6>
            <form method="GET" class="input-group input-group-sm">
                <input type="text" name="buscar" class="form-control" placeholder="Buscar..." value="<?= sanitize($busqueda) ?>">
                <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <div class="overflow-auto flex-grow-1">
            <?php if (empty($conversaciones)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-chat-dots fs-1 d-block mb-2"></i>
                    <small>No hay conversaciones</small>
                </div>
            <?php else: ?>
                <?php foreach ($conversaciones as $conv): ?>
                    <?php
                    $nombre = !empty($conv['cliente_nombre'])
                        ? sanitize($conv['cliente_nombre'] . ' ' . $conv['cliente_apellidos'])
                        : sanitize($conv['telefono']);
                    $preview = mb_strlen($conv['ultimo_mensaje']) > 50
                        ? mb_substr($conv['ultimo_mensaje'], 0, 50) . '...'
                        : $conv['ultimo_mensaje'];
                    $activo = ($telefonoActivo === $conv['telefono']);
                    ?>
                    <a href="<?= APP_URL ?>/modules/whatsapp/index.php?telefono=<?= urlencode($conv['telefono']) ?>"
                       class="d-block text-decoration-none text-dark p-3 border-bottom <?= $activo ? 'bg-light' : '' ?>"
                       style="<?= $activo ? 'border-left: 3px solid #10b981;' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="fw-semibold text-truncate"><?= $nombre ?></div>
                            <small class="text-muted ms-2 text-nowrap"><?= formatFechaHora($conv['ultimo_mensaje_fecha']) ?></small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted text-truncate"><?= sanitize($preview) ?></small>
                            <?php if ($conv['no_leidos'] > 0): ?>
                                <span class="badge bg-success rounded-pill ms-2"><?= $conv['no_leidos'] ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Panel derecho: Chat -->
    <div class="col-md-8 col-lg-9 d-flex flex-column bg-light" style="height: 100%;">
        <?php if (empty($telefonoActivo)): ?>
            <div class="d-flex align-items-center justify-content-center flex-grow-1 text-muted">
                <div class="text-center">
                    <i class="bi bi-whatsapp fs-1 d-block mb-3 text-success"></i>
                    <h5>Selecciona una conversacion</h5>
                    <p>Elige una conversacion del panel izquierdo para ver los mensajes.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Cabecera del chat -->
            <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">
                        <i class="bi bi-whatsapp text-success"></i>
                        <?= sanitize($telefonoActivo) ?>
                    </h6>
                    <?php if ($clienteChat): ?>
                        <small>
                            <a href="<?= APP_URL ?>/modules/clientes/ver.php?id=<?= $clienteChat['id'] ?>" class="text-decoration-none">
                                <i class="bi bi-person"></i> <?= sanitize($clienteChat['nombre'] . ' ' . $clienteChat['apellidos']) ?>
                            </a>
                        </small>
                    <?php else: ?>
                        <small class="text-muted">Cliente no vinculado</small>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!$clienteChat): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalVincular">
                            <i class="bi bi-link-45deg"></i> Vincular cliente
                        </button>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/modules/whatsapp/config.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-gear"></i>
                    </a>
                </div>
            </div>

            <!-- Mensajes -->
            <div class="flex-grow-1 overflow-auto p-3" id="chatMessages" style="background: #e5ddd5;">
                <?php
                $fechaAnterior = '';
                foreach ($mensajes as $msg):
                    $fechaMsg = (new DateTime($msg['created_at']))->format('Y-m-d');
                    if ($fechaMsg !== $fechaAnterior):
                        $fechaAnterior = $fechaMsg;
                ?>
                    <div class="text-center my-3">
                        <span class="badge bg-white text-muted shadow-sm px-3 py-2"><?= formatFecha($msg['created_at']) ?></span>
                    </div>
                <?php endif; ?>
                    <div class="d-flex <?= $msg['direccion'] === 'saliente' ? 'justify-content-end' : 'justify-content-start' ?> mb-2">
                        <div class="rounded-3 px-3 py-2 shadow-sm" style="max-width: 70%; <?= $msg['direccion'] === 'saliente' ? 'background: #dcf8c6;' : 'background: #fff;' ?>">
                            <div><?= sanitize($msg['mensaje']) ?></div>
                            <div class="text-end mt-1">
                                <small class="text-muted" style="font-size: 0.7rem;">
                                    <?= (new DateTime($msg['created_at']))->format('H:i') ?>
                                    <?php if ($msg['direccion'] === 'saliente'): ?>
                                        <?php if ($msg['estado'] === 'leido'): ?>
                                            <i class="bi bi-check-all text-primary"></i>
                                        <?php elseif ($msg['estado'] === 'entregado'): ?>
                                            <i class="bi bi-check-all"></i>
                                        <?php elseif ($msg['estado'] === 'enviado'): ?>
                                            <i class="bi bi-check"></i>
                                        <?php elseif ($msg['estado'] === 'fallido'): ?>
                                            <i class="bi bi-exclamation-circle text-danger"></i>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Input de mensaje -->
            <div class="p-3 bg-white border-top">
                <form method="POST" action="<?= APP_URL ?>/modules/whatsapp/chat.php" class="d-flex gap-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="telefono" value="<?= sanitize($telefonoActivo) ?>">
                    <input type="text" name="mensaje" class="form-control" placeholder="Escribe un mensaje..." required autocomplete="off">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-send"></i>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($telefonoActivo) && !$clienteChat): ?>
<!-- Modal Vincular Cliente -->
<div class="modal fade" id="modalVincular" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= APP_URL ?>/modules/whatsapp/chat.php">
                <?= csrfField() ?>
                <input type="hidden" name="accion" value="vincular">
                <input type="hidden" name="telefono" value="<?= sanitize($telefonoActivo) ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-link-45deg"></i> Vincular a Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Cliente</label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">-- Seleccionar --</option>
                            <?php
                            $clientes = $db->query("SELECT id, nombre, apellidos, telefono FROM clientes ORDER BY nombre ASC")->fetchAll();
                            foreach ($clientes as $cli):
                            ?>
                                <option value="<?= $cli['id'] ?>"><?= sanitize($cli['nombre'] . ' ' . $cli['apellidos']) ?> (<?= sanitize($cli['telefono']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-link-45deg"></i> Vincular</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Auto-scroll al final del chat
document.addEventListener('DOMContentLoaded', function() {
    var chatDiv = document.getElementById('chatMessages');
    if (chatDiv) {
        chatDiv.scrollTop = chatDiv.scrollHeight;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
