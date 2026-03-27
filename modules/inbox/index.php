<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();
$pageTitle = 'Bandeja Unificada';
require_once __DIR__ . '/../../includes/header.php';

$userId = currentUserId();
$filtro = get('filtro', 'todos');
$busqueda = get('buscar');
$page = max(1, intval(get('page', 1)));

// ── Fetch emails (wrapped in try/catch in case tables don't exist) ──
$emailRows = [];
try {
    $sqlEmail = "
        SELECT em.id,
               em.de_email AS remitente,
               em.asunto AS asunto_o_mensaje,
               em.created_at AS fecha,
               em.leido,
               'email' AS tipo
        FROM email_mensajes em
        JOIN email_cuentas ec ON em.cuenta_id = ec.id
        WHERE ec.usuario_id = ?
    ";
    $paramsEmail = [$userId];

    if (!empty($busqueda)) {
        $sqlEmail .= " AND (em.asunto LIKE ? OR em.de_email LIKE ? OR em.cuerpo LIKE ?)";
        $paramsEmail[] = "%$busqueda%";
        $paramsEmail[] = "%$busqueda%";
        $paramsEmail[] = "%$busqueda%";
    }

    $stmtEmail = $db->prepare($sqlEmail);
    $stmtEmail->execute($paramsEmail);
    $emailRows = $stmtEmail->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $emailRows = [];
}

// ── Fetch WhatsApp messages (wrapped in try/catch) ──
$waRows = [];
try {
    $sqlWa = "
        SELECT wm.id,
               wm.telefono AS remitente,
               wm.mensaje AS asunto_o_mensaje,
               wm.created_at AS fecha,
               CASE WHEN wm.estado = 'recibido' AND wm.direccion = 'entrante' THEN 0 ELSE 1 END AS leido,
               'whatsapp' AS tipo
        FROM whatsapp_mensajes wm
        JOIN whatsapp_cuentas wc ON wm.cuenta_id = wc.id
        WHERE wc.usuario_id = ?
    ";
    $paramsWa = [$userId];

    if (!empty($busqueda)) {
        $sqlWa .= " AND (wm.mensaje LIKE ? OR wm.telefono LIKE ?)";
        $paramsWa[] = "%$busqueda%";
        $paramsWa[] = "%$busqueda%";
    }

    $stmtWa = $db->prepare($sqlWa);
    $stmtWa->execute($paramsWa);
    $waRows = $stmtWa->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If whatsapp_cuentas doesn't exist, try without account join
    try {
        $sqlWaFallback = "
            SELECT wm.id,
                   wm.telefono AS remitente,
                   wm.mensaje AS asunto_o_mensaje,
                   wm.created_at AS fecha,
                   CASE WHEN wm.estado = 'recibido' AND wm.direccion = 'entrante' THEN 0 ELSE 1 END AS leido,
                   'whatsapp' AS tipo
            FROM whatsapp_mensajes wm
        ";
        $paramsWaFb = [];

        if (!empty($busqueda)) {
            $sqlWaFallback .= " WHERE (wm.mensaje LIKE ? OR wm.telefono LIKE ?)";
            $paramsWaFb[] = "%$busqueda%";
            $paramsWaFb[] = "%$busqueda%";
        }

        $stmtWaFb = $db->prepare($sqlWaFallback);
        $stmtWaFb->execute($paramsWaFb);
        $waRows = $stmtWaFb->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $waRows = [];
    }
}

// ── Combine & build unified array ──
$allMessages = array_merge($emailRows, $waRows);

// Add url_ver to each row
foreach ($allMessages as &$msg) {
    if ($msg['tipo'] === 'email') {
        $msg['url_ver'] = APP_URL . '/modules/email/ver.php?id=' . $msg['id'];
    } else {
        $msg['url_ver'] = APP_URL . '/modules/whatsapp/chat.php?telefono=' . urlencode($msg['remitente']);
    }
}
unset($msg);

// ── Apply filter ──
if ($filtro === 'email') {
    $allMessages = array_filter($allMessages, fn($m) => $m['tipo'] === 'email');
} elseif ($filtro === 'whatsapp') {
    $allMessages = array_filter($allMessages, fn($m) => $m['tipo'] === 'whatsapp');
} elseif ($filtro === 'no_leidos') {
    $allMessages = array_filter($allMessages, fn($m) => !$m['leido']);
}
$allMessages = array_values($allMessages);

// ── Sort by fecha DESC ──
usort($allMessages, function ($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

// ── Stats ──
$totalMessages = count($allMessages);
$unreadCount = count(array_filter($allMessages, fn($m) => !$m['leido']));
$emailCount = count(array_filter($allMessages, fn($m) => $m['tipo'] === 'email'));
$whatsappCount = count(array_filter($allMessages, fn($m) => $m['tipo'] === 'whatsapp'));

// ── Pagination ──
$pagination = paginate($totalMessages, 20, $page);
$pagedMessages = array_slice($allMessages, $pagination['offset'], $pagination['per_page']);

// Base URL for pagination links
$baseUrlParams = ['filtro' => $filtro];
if (!empty($busqueda)) {
    $baseUrlParams['buscar'] = $busqueda;
}
$baseUrl = '?' . http_build_query($baseUrlParams);
?>

<!-- Stats Bar -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold text-primary"><?= $totalMessages ?></div>
                <small class="text-muted">Total mensajes</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold text-danger"><?= $unreadCount ?></div>
                <small class="text-muted">No leidos</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold text-info"><?= $emailCount ?></div>
                <small class="text-muted">Emails</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold text-success"><?= $whatsappCount ?></div>
                <small class="text-muted">WhatsApp</small>
            </div>
        </div>
    </div>
</div>

<!-- Filter Tabs & Search -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom-0 pb-0">
        <div class="row align-items-center">
            <div class="col-md-8">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?= $filtro === 'todos' ? 'active' : '' ?>" href="?filtro=todos<?= !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : '' ?>">
                            <i class="bi bi-inbox"></i> Todos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $filtro === 'email' ? 'active' : '' ?>" href="?filtro=email<?= !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : '' ?>">
                            <i class="bi bi-envelope"></i> Email
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $filtro === 'whatsapp' ? 'active' : '' ?>" href="?filtro=whatsapp<?= !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : '' ?>">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $filtro === 'no_leidos' ? 'active' : '' ?>" href="?filtro=no_leidos<?= !empty($busqueda) ? '&buscar=' . urlencode($busqueda) : '' ?>">
                            <i class="bi bi-eye-slash"></i> No leidos
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="col-md-4 mt-2 mt-md-0">
                <form method="GET" class="input-group input-group-sm">
                    <input type="hidden" name="filtro" value="<?= sanitize($filtro) ?>">
                    <input type="text" name="buscar" class="form-control" placeholder="Buscar mensajes..." value="<?= sanitize($busqueda) ?>">
                    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="?filtro=<?= urlencode($filtro) ?>" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (empty($pagedMessages)): ?>
            <!-- Empty State -->
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 d-block mb-3 text-muted"></i>
                <h5 class="text-muted">No hay mensajes</h5>
                <p class="text-muted mb-0">
                    <?php if (!empty($busqueda)): ?>
                        No se encontraron mensajes que coincidan con "<?= sanitize($busqueda) ?>".
                    <?php elseif ($filtro === 'no_leidos'): ?>
                        No tienes mensajes sin leer.
                    <?php elseif ($filtro === 'email'): ?>
                        No hay correos electr&oacute;nicos en tu bandeja.
                    <?php elseif ($filtro === 'whatsapp'): ?>
                        No hay mensajes de WhatsApp.
                    <?php else: ?>
                        Tu bandeja unificada est&aacute; vac&iacute;a.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Remitente</th>
                            <th>Asunto / Mensaje</th>
                            <th style="width: 160px;">Fecha</th>
                            <th style="width: 80px;" class="text-center">Estado</th>
                            <th style="width: 60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagedMessages as $msg): ?>
                            <tr class="<?= !$msg['leido'] ? 'fw-bold' : '' ?>">
                                <td class="text-center">
                                    <?php if ($msg['tipo'] === 'email'): ?>
                                        <i class="bi bi-envelope text-info" title="Email"></i>
                                    <?php else: ?>
                                        <i class="bi bi-whatsapp text-success" title="WhatsApp"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= sanitize($msg['remitente']) ?>">
                                        <?= sanitize($msg['remitente']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= sanitize($msg['url_ver']) ?>" class="text-decoration-none text-dark <?= !$msg['leido'] ? 'fw-bold' : '' ?>">
                                        <?= sanitize(mb_strimwidth($msg['asunto_o_mensaje'] ?? '', 0, 80, '...')) ?>
                                    </a>
                                </td>
                                <td class="text-muted small">
                                    <?= formatFechaHora($msg['fecha']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!$msg['leido']): ?>
                                        <span class="badge bg-primary rounded-pill">Nuevo</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Le&iacute;do</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?= sanitize($msg['url_ver']) ?>" class="btn btn-sm btn-outline-primary" title="Ver">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer bg-white">
            <?= renderPagination($pagination, $baseUrl) ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
