<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
requireLogin();
ob_start();

// Contar notificaciones no leidas
$db = getDB();
$stmtNotif = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
$stmtNotif->execute([currentUserId()]);
$notifCount = $stmtNotif->fetchColumn();

// Load whitelabel config (colors, branding)
$_wl = null;
try {
    $_wl = $db->query("SELECT * FROM whitelabel_config WHERE id=1")->fetch();
} catch (Exception $e) {}

$_appName = $_wl['app_nombre'] ?? APP_NAME;
$_colorPrimary = $_wl['color_primario'] ?? '#10b981';
$_colorSecondary = $_wl['color_secundario'] ?? '#1e293b';
$_colorAccent = $_wl['color_acento'] ?? '#f59e0b';
$_logoUrl = $_wl['app_logo_url'] ?? '';
$_faviconUrl = $_wl['app_favicon_url'] ?? '';
$_customCss = $_wl['css_custom'] ?? '';

// Compute color variants from primary
function hexToHsl($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex,0,2))/255;
    $g = hexdec(substr($hex,2,2))/255;
    $b = hexdec(substr($hex,4,2))/255;
    $max = max($r,$g,$b); $min = min($r,$g,$b);
    $l = ($max+$min)/2;
    if ($max == $min) { $h = $s = 0; }
    else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d/(2-$max-$min) : $d/($max+$min);
        if ($max == $r) $h = ($g-$b)/$d + ($g < $b ? 6 : 0);
        elseif ($max == $g) $h = ($b-$r)/$d + 2;
        else $h = ($r-$g)/$d + 4;
        $h /= 6;
    }
    return [round($h*360), round($s*100), round($l*100)];
}

list($ph, $ps, $pl) = hexToHsl($_colorPrimary);
$_primaryDarkHex = $_colorPrimary; // fallback
// Make a darker variant
$pdl = max(0, $pl - 10);
$_primaryLightRgba = "rgba(" . hexdec(substr($_colorPrimary,1,2)) . "," . hexdec(substr($_colorPrimary,3,2)) . "," . hexdec(substr($_colorPrimary,5,2)) . ",0.12)";

// Determinar pagina activa
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentPath = $_SERVER['PHP_SELF'];
$isDashboard = ($currentPath === APP_URL . '/index.php' || $currentPath === '/index.php' || !preg_match('/modules\//', $currentPath));
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'InmoCRM' ?> - <?= htmlspecialchars($_appName) ?></title>
    <?php if ($_faviconUrl): ?><link rel="icon" href="<?= htmlspecialchars($_faviconUrl) ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary: <?= htmlspecialchars($_colorPrimary) ?>;
            --primary-dark: <?= htmlspecialchars($_colorPrimary) ?>;
            --primary-light: <?= $_primaryLightRgba ?>;
            --accent: <?= htmlspecialchars($_colorAccent) ?>;
            --sidebar-active: <?= htmlspecialchars($_colorPrimary) ?>;
            --sidebar-hover: rgba(<?= hexdec(substr($_colorPrimary,1,2)) ?>,<?= hexdec(substr($_colorPrimary,3,2)) ?>,<?= hexdec(substr($_colorPrimary,5,2)) ?>,0.08);
        }
        .btn-primary { background: var(--primary) !important; border-color: var(--primary) !important; }
        .btn-primary:hover { filter: brightness(0.9); box-shadow: 0 4px 12px <?= $_primaryLightRgba ?>; }
        .btn-outline-primary { color: var(--primary) !important; border-color: var(--primary) !important; }
        .btn-outline-primary:hover { background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; }
        .badge.bg-primary { background: var(--primary) !important; }
        .text-primary { color: var(--primary) !important; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px <?= $_primaryLightRgba ?>; }
        a { color: var(--primary); }
        a:hover { color: var(--primary); filter: brightness(0.85); }
        <?= $_customCss ?>
    </style>
    <script>
        // Dark mode: apply before render to prevent flash
        (function(){
            const t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            }
        })();
    </script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <?php if ($_logoUrl): ?>
                <img src="<?= htmlspecialchars($_logoUrl) ?>" alt="Logo" style="max-height:36px;margin-bottom:4px">
            <?php else: ?>
                <h4><i class="bi bi-buildings"></i> <?= htmlspecialchars($_appName) ?></h4>
            <?php endif; ?>
            <small class="text-muted">CRM Inmobiliario</small>
        </div>
        <nav class="sidebar-nav">
            <a href="<?= APP_URL ?>/index.php" class="nav-link <?= $isDashboard && $currentPage === 'index' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="<?= APP_URL ?>/modules/propiedades/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'propiedades') !== false ? 'active' : '' ?>">
                <i class="bi bi-house-door"></i> Propiedades
            </a>
            <a href="<?= APP_URL ?>/modules/clientes/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'clientes') !== false ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Clientes
            </a>
            <a href="<?= APP_URL ?>/modules/prospectos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'prospectos') !== false ? 'active' : '' ?>">
                <i class="bi bi-person-plus"></i> Prospectos
            </a>
            <a href="<?= APP_URL ?>/modules/visitas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'visitas') !== false ? 'active' : '' ?>">
                <i class="bi bi-calendar-event"></i> Visitas
            </a>
            <a href="<?= APP_URL ?>/modules/tareas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'tareas') !== false ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i> Tareas
            </a>
            <a href="<?= APP_URL ?>/modules/documentos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'documentos') !== false ? 'active' : '' ?>">
                <i class="bi bi-folder"></i> Documentos
            </a>
            <a href="<?= APP_URL ?>/modules/finanzas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'finanzas') !== false ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> Finanzas
            </a>
            <a href="<?= APP_URL ?>/modules/portales/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'portales') !== false ? 'active' : '' ?>">
                <i class="bi bi-globe"></i> Portales
            </a>
            <a href="<?= APP_URL ?>/modules/informes/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'informes') !== false ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i> Informes
            </a>
            <a href="<?= APP_URL ?>/modules/pipelines/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pipelines') !== false ? 'active' : '' ?>">
                <i class="bi bi-kanban"></i> Pipelines
            </a>
            <a href="<?= APP_URL ?>/modules/calendario/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'calendario') !== false ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i> Calendario
            </a>
            <a href="<?= APP_URL ?>/modules/pagos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pagos') !== false ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i> Facturacion
            </a>
            <a href="<?= APP_URL ?>/modules/presupuestos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'presupuestos') !== false ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i> Presupuestos
            </a>
            <a href="<?= APP_URL ?>/modules/contratos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'contratos') !== false ? 'active' : '' ?>">
                <i class="bi bi-pen"></i> Contratos
            </a>
            <hr class="mx-3 my-2">
            <small class="text-muted px-3 text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Marketing</small>
            <a href="<?= APP_URL ?>/modules/formularios/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'formularios') !== false ? 'active' : '' ?>">
                <i class="bi bi-ui-checks-grid"></i> Formularios
            </a>
            <a href="<?= APP_URL ?>/modules/encuestas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'encuestas') !== false ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-data"></i> Encuestas
            </a>
            <a href="<?= APP_URL ?>/modules/funnels/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'funnels') !== false ? 'active' : '' ?>">
                <i class="bi bi-funnel"></i> Funnels
            </a>
            <a href="<?= APP_URL ?>/modules/landing/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/landing/') !== false ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-richtext"></i> Landing Pages
            </a>
            <a href="<?= APP_URL ?>/modules/campanas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'campanas') !== false ? 'active' : '' ?>">
                <i class="bi bi-send"></i> Campanas Drip
            </a>
            <a href="<?= APP_URL ?>/modules/marketing/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/marketing/') !== false ? 'active' : '' ?>">
                <i class="bi bi-megaphone"></i> Marketing
            </a>
            <a href="<?= APP_URL ?>/modules/ab-testing/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'ab-testing') !== false ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i> A/B Testing
            </a>
            <a href="<?= APP_URL ?>/modules/ads/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/ads/') !== false ? 'active' : '' ?>">
                <i class="bi bi-badge-ad"></i> Ads Report
            </a>
            <a href="<?= APP_URL ?>/modules/social/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/social/') !== false ? 'active' : '' ?>">
                <i class="bi bi-share"></i> Redes Sociales
            </a>
            <hr class="mx-3 my-2">
            <small class="text-muted px-3 text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Comunicacion</small>
            <a href="<?= APP_URL ?>/modules/conversaciones/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'conversaciones') !== false ? 'active' : '' ?>">
                <i class="bi bi-chat-left-text"></i> Conversaciones
            </a>
            <a href="<?= APP_URL ?>/modules/inbox/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/inbox/') !== false ? 'active' : '' ?>">
                <i class="bi bi-inboxes"></i> Bandeja Unificada
            </a>
            <a href="<?= APP_URL ?>/modules/email/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/email/') !== false ? 'active' : '' ?>">
                <i class="bi bi-envelope"></i> Email
            </a>
            <a href="<?= APP_URL ?>/modules/whatsapp/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'whatsapp') !== false ? 'active' : '' ?>">
                <i class="bi bi-whatsapp"></i> WhatsApp
            </a>
            <a href="<?= APP_URL ?>/modules/sms/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/sms/') !== false ? 'active' : '' ?>">
                <i class="bi bi-phone"></i> SMS
            </a>
            <a href="<?= APP_URL ?>/modules/chat/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/chat/') !== false ? 'active' : '' ?>">
                <i class="bi bi-chat-dots"></i> Chat Web
            </a>
            <hr class="mx-3 my-2">
            <small class="text-muted px-3 text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Contenido</small>
            <a href="<?= APP_URL ?>/modules/blog/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/blog/') !== false ? 'active' : '' ?>">
                <i class="bi bi-journal-richtext"></i> Blog
            </a>
            <a href="<?= APP_URL ?>/modules/cursos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/cursos/') !== false ? 'active' : '' ?>">
                <i class="bi bi-mortarboard"></i> Cursos
            </a>
            <a href="<?= APP_URL ?>/modules/comunidad/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'comunidad') !== false ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Comunidad
            </a>
            <a href="<?= APP_URL ?>/modules/medios/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/medios/') !== false ? 'active' : '' ?>">
                <i class="bi bi-images"></i> Medios
            </a>
            <hr class="mx-3 my-2">
            <small class="text-muted px-3 text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Sistema</small>
            <a href="<?= APP_URL ?>/modules/automatizaciones/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'automatizaciones') !== false ? 'active' : '' ?>">
                <i class="bi bi-robot"></i> Automatizaciones
            </a>
            <a href="<?= APP_URL ?>/modules/ia/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/ia/') !== false ? 'active' : '' ?>">
                <i class="bi bi-cpu"></i> IA Asistente
            </a>
            <a href="<?= APP_URL ?>/modules/afiliados/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'afiliados') !== false ? 'active' : '' ?>">
                <i class="bi bi-link-45deg"></i> Afiliados
            </a>
            <a href="<?= APP_URL ?>/modules/ajustes/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'ajustes') !== false ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i> Ajustes
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?= APP_URL ?>/modules/usuarios/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'usuarios') !== false && strpos($_SERVER['PHP_SELF'], 'backup') === false ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Usuarios
            </a>
            <a href="<?= APP_URL ?>/modules/usuarios/backup.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'backup') !== false ? 'active' : '' ?>">
                <i class="bi bi-database-down"></i> Backups
            </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link me-3 d-lg-none" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h5 class="mb-0 page-title"><?= $pageTitle ?? 'Dashboard' ?></h5>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- Dark mode toggle -->
                <button class="btn btn-link btn-sm" id="themeToggle" title="Cambiar tema">
                    <i class="bi bi-moon-stars fs-5" id="themeIcon"></i>
                </button>
                <!-- Notificaciones -->
                <div class="dropdown">
                    <button class="btn btn-link position-relative" data-bs-toggle="dropdown">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if ($notifCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $notifCount ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                        <h6 class="dropdown-header">Notificaciones</h6>
                        <?php
                        $stmtN = $db->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 5");
                        $stmtN->execute([currentUserId()]);
                        $notifs = $stmtN->fetchAll();
                        if (empty($notifs)):
                        ?>
                        <p class="text-muted text-center py-3 mb-0">Sin notificaciones</p>
                        <?php else:
                            foreach ($notifs as $notif): ?>
                        <a class="dropdown-item <?= $notif['leida'] ? '' : 'fw-bold' ?>" href="<?= $notif['enlace'] ?? '#' ?>">
                            <small class="text-muted"><?= formatFechaHora($notif['created_at']) ?></small><br>
                            <?= sanitize($notif['titulo']) ?>
                        </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <!-- Usuario -->
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= sanitize(currentUserName()) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/usuarios/perfil.php"><i class="bi bi-person"></i> Mi Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesion</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="content-wrapper">
            <?php
            $flash = getFlash();
            if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
