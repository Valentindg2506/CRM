<?php
$pageTitle = 'WhatsApp';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$userId = (int) currentUserId();

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
        (SELECT wm2.mensaje FROM whatsapp_mensajes wm2 WHERE wm2.telefono = wm.telefono AND wm2.created_by = wm.created_by ORDER BY wm2.created_at DESC LIMIT 1) as ultimo_mensaje,
        (SELECT COUNT(*) FROM whatsapp_mensajes wm3 WHERE wm3.telefono = wm.telefono AND wm3.created_by = wm.created_by AND wm3.direccion = 'entrante' AND wm3.estado = 'recibido') as no_leidos
    FROM whatsapp_mensajes wm
    LEFT JOIN clientes c ON wm.cliente_id = c.id
";

$params = [$userId];
$sqlConversaciones .= " WHERE wm.created_by = ?";
if (!empty($busqueda)) {
    $sqlConversaciones .= " AND (wm.telefono LIKE ? OR c.nombre LIKE ? OR c.apellidos LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$sqlConversaciones .= " GROUP BY wm.telefono ORDER BY ultimo_mensaje_fecha DESC";

$stmt = $db->prepare($sqlConversaciones);
$stmt->execute($params);
$conversaciones = $stmt->fetchAll();

// Si hay telefono activo, obtener mensajes
$mensajes = [];
$clienteChat = null;
$latestMessageId = 0;
if (!empty($telefonoActivo)) {
    $stmtMsg = $db->prepare("
        SELECT wm.*, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos
        FROM whatsapp_mensajes wm
        LEFT JOIN clientes c ON wm.cliente_id = c.id
        WHERE wm.telefono = ? AND wm.created_by = ?
        ORDER BY wm.created_at ASC
    ");
    $stmtMsg->execute([$telefonoActivo, $userId]);
    $mensajes = $stmtMsg->fetchAll();
    if (!empty($mensajes)) {
        $ultimo = end($mensajes);
        $latestMessageId = (int)($ultimo['id'] ?? 0);
    }

    // Obtener cliente vinculado
    $stmtCliente = $db->prepare("
        SELECT c.id, c.nombre, c.apellidos
        FROM whatsapp_mensajes wm
        JOIN clientes c ON wm.cliente_id = c.id
        WHERE wm.telefono = ? AND wm.created_by = ? AND wm.cliente_id IS NOT NULL
        LIMIT 1
    ");
    $stmtCliente->execute([$telefonoActivo, $userId]);
    $clienteChat = $stmtCliente->fetch();

    // Marcar mensajes entrantes como leidos
    $stmtLeer = $db->prepare("UPDATE whatsapp_mensajes SET estado = 'leido' WHERE telefono = ? AND created_by = ? AND direccion = 'entrante' AND estado = 'recibido'");
    $stmtLeer->execute([$telefonoActivo, $userId]);
}
?>

<style>
.wa-layout {
    height: calc(100vh - 180px);
    min-height: 520px;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(2, 8, 23, 0.2);
}
.wa-sidebar {
    height: 100%;
    background: linear-gradient(180deg, #0f2543 0%, #102a4d 100%);
    color: #e6efff;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}
.wa-sidebar .wa-top {
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.wa-sidebar .wa-title {
    color: #e9f9ef;
    font-weight: 700;
}
.wa-config-btn {
    border-color: rgba(255, 255, 255, 0.35);
    color: #dbe7ff;
}
.wa-config-btn:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
}
.wa-search {
    background: rgba(3, 17, 43, 0.8);
    border-color: rgba(255, 255, 255, 0.2);
    color: #e5eeff;
}
.wa-search::placeholder { color: #8ea4c7; }
.wa-search-btn {
    border-color: rgba(255, 255, 255, 0.2);
    color: #c7d7f5;
}
.wa-conv-item {
    color: #e4ecff;
    background: transparent;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    transition: all 0.2s ease;
}
.wa-conv-item:hover {
    background: rgba(255, 255, 255, 0.07);
    color: #ffffff;
}
.wa-conv-item.wa-active {
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.18) 0%, rgba(16, 185, 129, 0.06) 100%);
    box-shadow: inset 3px 0 0 #10b981;
}
.wa-muted { color: #9cb4d8 !important; }
.wa-main {
    height: 100%;
    background: #f3f4f8;
}
.wa-empty {
    color: #6f7f96;
}
.wa-chat-header {
    background: #ffffff;
    border-bottom: 1px solid #dbe1ea;
}
.wa-chat-phone {
    color: #22324f;
    font-weight: 700;
}
.wa-chat-messages {
    background:
        radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.85), rgba(255, 255, 255, 0) 40%),
        radial-gradient(circle at 80% 10%, rgba(189, 230, 210, 0.45), rgba(189, 230, 210, 0) 35%),
        linear-gradient(160deg, #efe9df 0%, #ece7e0 100%);
}
.wa-day-badge {
    background: #1f2f49;
    color: #e8f1ff;
    border-radius: 10px;
    font-weight: 600;
}
.wa-bubble {
    max-width: 72%;
    border-radius: 14px;
    padding: 10px 12px;
    box-shadow: 0 4px 14px rgba(15, 35, 63, 0.12);
}
.wa-bubble-in {
    background: #ffffff;
    color: #22324f;
    border-top-left-radius: 6px;
}
.wa-bubble-out {
    background: linear-gradient(180deg, #d7f6cf 0%, #c7f0bc 100%);
    color: #17331f;
    border-top-right-radius: 6px;
}
.wa-input-wrap {
    background: #ffffff;
    border-top: 1px solid #dbe1ea;
}
.wa-input {
    border-radius: 12px;
    border: 1px solid #ced8e5;
}
.wa-send {
    border-radius: 12px;
    padding-inline: 14px;
}
@media (max-width: 991px) {
    .wa-layout { min-height: 680px; }
    .wa-bubble { max-width: 84%; }
}
</style>

<div class="row g-0 wa-layout">
    <!-- Panel izquierdo: Lista de conversaciones -->
    <div class="col-md-4 col-lg-3 d-flex flex-column wa-sidebar">
        <div class="p-3 wa-top">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 wa-title"><i class="bi bi-whatsapp text-success"></i> Conversaciones</h6>
                <a href="<?= APP_URL ?>/modules/whatsapp/config.php" class="btn btn-sm wa-config-btn" title="Configuracion de WhatsApp">
                    <i class="bi bi-gear"></i>
                </a>
            </div>
            <form method="GET" class="input-group input-group-sm">
                <input type="text" name="buscar" class="form-control wa-search" placeholder="Buscar..." value="<?= sanitize($busqueda) ?>">
                <button type="submit" class="btn wa-search-btn"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <div class="overflow-auto flex-grow-1" id="conversationList">
            <?php if (empty($conversaciones)): ?>
                <div class="text-center wa-muted py-5">
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
                       class="d-block text-decoration-none p-3 wa-conv-item <?= $activo ? 'wa-active' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="fw-semibold text-truncate"><?= $nombre ?></div>
                            <small class="wa-muted ms-2 text-nowrap"><?= formatFechaHora($conv['ultimo_mensaje_fecha']) ?></small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="wa-muted text-truncate"><?= sanitize($preview) ?></small>
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
    <div class="col-md-8 col-lg-9 d-flex flex-column wa-main">
        <?php if (empty($telefonoActivo)): ?>
            <div class="d-flex align-items-center justify-content-center flex-grow-1 wa-empty">
                <div class="text-center">
                    <i class="bi bi-whatsapp fs-1 d-block mb-3 text-success"></i>
                    <h5>Selecciona una conversacion</h5>
                    <p>Elige una conversacion del panel izquierdo para ver los mensajes.</p>
                    <a href="<?= APP_URL ?>/modules/whatsapp/config.php" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-gear"></i> Configurar WhatsApp
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Cabecera del chat -->
            <div class="p-3 wa-chat-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 wa-chat-phone">
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
            <div class="flex-grow-1 overflow-auto p-3 wa-chat-messages" id="chatMessages">
                <?php
                $fechaAnterior = '';
                foreach ($mensajes as $msg):
                    $fechaMsg = (new DateTime($msg['created_at']))->format('Y-m-d');
                    if ($fechaMsg !== $fechaAnterior):
                        $fechaAnterior = $fechaMsg;
                ?>
                    <div class="text-center my-3">
                        <span class="badge wa-day-badge shadow-sm px-3 py-2"><?= formatFecha($msg['created_at']) ?></span>
                    </div>
                <?php endif; ?>
                    <div class="d-flex <?= $msg['direccion'] === 'saliente' ? 'justify-content-end' : 'justify-content-start' ?> mb-2">
                        <div class="wa-bubble <?= $msg['direccion'] === 'saliente' ? 'wa-bubble-out' : 'wa-bubble-in' ?>">
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
            <div class="p-3 wa-input-wrap">
                <form method="POST" action="<?= APP_URL ?>/modules/whatsapp/chat.php" class="d-flex gap-2 align-items-center">
                    <?= csrfField() ?>
                    <input type="hidden" name="telefono" value="<?= sanitize($telefonoActivo) ?>">
                    <input type="text" name="mensaje" class="form-control wa-input" placeholder="Escribe un mensaje..." required autocomplete="off">
                    <button type="submit" class="btn btn-success wa-send">
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
function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function truncateText(str, max) {
    var s = String(str || '');
    return s.length > max ? s.slice(0, max) + '...' : s;
}

function formatDateTimeLabel(dateStr) {
    var d = new Date(String(dateStr).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';
    var dd = String(d.getDate()).padStart(2, '0');
    var mm = String(d.getMonth() + 1).padStart(2, '0');
    var yyyy = d.getFullYear();
    var hh = String(d.getHours()).padStart(2, '0');
    var mi = String(d.getMinutes()).padStart(2, '0');
    return dd + '/' + mm + '/' + yyyy + ' ' + hh + ':' + mi;
}

function formatDayLabel(dateStr) {
    var d = new Date(String(dateStr).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';
    var dd = String(d.getDate()).padStart(2, '0');
    var mm = String(d.getMonth() + 1).padStart(2, '0');
    var yyyy = d.getFullYear();
    return dd + '/' + mm + '/' + yyyy;
}

function formatTimeLabel(dateStr) {
    var d = new Date(String(dateStr).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';
    var hh = String(d.getHours()).padStart(2, '0');
    var mi = String(d.getMinutes()).padStart(2, '0');
    return hh + ':' + mi;
}

function statusIconHtml(msg) {
    if (msg.direccion !== 'saliente') {
        return '';
    }
    if (msg.estado === 'leido') {
        return '<i class="bi bi-check-all text-primary"></i>';
    }
    if (msg.estado === 'entregado') {
        return '<i class="bi bi-check-all"></i>';
    }
    if (msg.estado === 'enviado') {
        return '<i class="bi bi-check"></i>';
    }
    if (msg.estado === 'fallido') {
        return '<i class="bi bi-exclamation-circle text-danger"></i>';
    }
    return '';
}

function renderConversationList(conversaciones, telefonoActivo, buscar) {
    var wrap = document.getElementById('conversationList');
    if (!wrap) return;

    if (!Array.isArray(conversaciones) || conversaciones.length === 0) {
        wrap.innerHTML = '' +
            '<div class="text-center wa-muted py-5">' +
                '<i class="bi bi-chat-dots fs-1 d-block mb-2"></i>' +
                '<small>No hay conversaciones</small>' +
            '</div>';
        return;
    }

    var queryBuscar = buscar ? ('&buscar=' + encodeURIComponent(buscar)) : '';
    var html = '';
    conversaciones.forEach(function(conv) {
        var telefono = String(conv.telefono || '');
        var nombre = (conv.cliente_nombre && conv.cliente_nombre.trim() !== '')
            ? (conv.cliente_nombre + ' ' + (conv.cliente_apellidos || '')).trim()
            : telefono;
        var preview = truncateText(conv.ultimo_mensaje || '', 50);
        var activo = (telefonoActivo === telefono);
        var noLeidos = parseInt(conv.no_leidos || 0, 10);

        html += '' +
        '<a href="<?= APP_URL ?>/modules/whatsapp/index.php?telefono=' + encodeURIComponent(telefono) + queryBuscar + '" class="d-block text-decoration-none p-3 wa-conv-item ' + (activo ? 'wa-active' : '') + '">' +
            '<div class="d-flex justify-content-between align-items-start">' +
                '<div class="fw-semibold text-truncate">' + escapeHtml(nombre) + '</div>' +
                '<small class="wa-muted ms-2 text-nowrap">' + escapeHtml(formatDateTimeLabel(conv.ultimo_mensaje_fecha || '')) + '</small>' +
            '</div>' +
            '<div class="d-flex justify-content-between align-items-center mt-1">' +
                '<small class="wa-muted text-truncate">' + escapeHtml(preview) + '</small>' +
                (noLeidos > 0 ? '<span class="badge bg-success rounded-pill ms-2">' + noLeidos + '</span>' : '') +
            '</div>' +
        '</a>';
    });

    wrap.innerHTML = html;
}

function renderMessages(mensajes) {
    var chatDiv = document.getElementById('chatMessages');
    if (!chatDiv) return;

    var nearBottom = (chatDiv.scrollHeight - chatDiv.scrollTop - chatDiv.clientHeight) < 90;
    var html = '';
    var fechaAnterior = '';

    (mensajes || []).forEach(function(msg) {
        var fecha = String(msg.created_at || '').slice(0, 10);
        if (fecha && fecha !== fechaAnterior) {
            fechaAnterior = fecha;
            html += '' +
                '<div class="text-center my-3">' +
                    '<span class="badge wa-day-badge shadow-sm px-3 py-2">' + escapeHtml(formatDayLabel(msg.created_at || '')) + '</span>' +
                '</div>';
        }

        var isOut = msg.direccion === 'saliente';
        html += '' +
            '<div class="d-flex ' + (isOut ? 'justify-content-end' : 'justify-content-start') + ' mb-2">' +
                '<div class="wa-bubble ' + (isOut ? 'wa-bubble-out' : 'wa-bubble-in') + '">' +
                    '<div>' + escapeHtml(msg.mensaje || '') + '</div>' +
                    '<div class="text-end mt-1">' +
                        '<small class="text-muted" style="font-size: 0.7rem;">' + escapeHtml(formatTimeLabel(msg.created_at || '')) + ' ' + statusIconHtml(msg) + '</small>' +
                    '</div>' +
                '</div>' +
            '</div>';
    });

    chatDiv.innerHTML = html;
    if (nearBottom) {
        chatDiv.scrollTop = chatDiv.scrollHeight;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var chatDiv = document.getElementById('chatMessages');
    if (chatDiv) {
        chatDiv.scrollTop = chatDiv.scrollHeight;
    }

    var telefonoActivo = <?= json_encode($telefonoActivo, JSON_UNESCAPED_UNICODE) ?>;
    var buscar = <?= json_encode($busqueda, JSON_UNESCAPED_UNICODE) ?>;
    var latestMessageId = <?= (int)$latestMessageId ?>;
    var streamCursor = latestMessageId;
    var pollBusy = false;
    var pollStopped = false;

    function pollWhatsApp() {
        if (pollBusy || pollStopped) {
            return;
        }
        pollBusy = true;

        var waitSeconds = document.visibilityState === 'visible' ? 22 : 0;
        var url = '<?= APP_URL ?>/modules/whatsapp/poll.php?telefono=' + encodeURIComponent(telefonoActivo || '') + '&buscar=' + encodeURIComponent(buscar || '') + '&since_id=' + encodeURIComponent(streamCursor || 0) + '&wait=' + waitSeconds;
        fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin'
        })
        .then(function(res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.json();
        })
        .then(function(data) {
            if (!data || data.success !== true) {
                return;
            }

            renderConversationList(data.conversaciones || [], telefonoActivo, buscar || '');
            streamCursor = parseInt(data.cursor_id || streamCursor || 0, 10);

            if (telefonoActivo) {
                var newLatest = parseInt(data.latest_message_id || 0, 10);
                if (newLatest !== latestMessageId) {
                    latestMessageId = newLatest;
                    renderMessages(data.mensajes || []);
                }
            }
        })
        .catch(function() {
            // Silencioso para no interrumpir la experiencia del chat.
        })
        .finally(function() {
            pollBusy = false;
            if (!pollStopped) {
                setTimeout(pollWhatsApp, 120);
            }
        });
    }

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible' && !pollBusy && !pollStopped) {
            pollWhatsApp();
        }
    });

    window.addEventListener('beforeunload', function() {
        pollStopped = true;
    });

    pollWhatsApp();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
