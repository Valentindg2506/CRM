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

// Determinar pagina activa
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentPath = $_SERVER['PHP_SELF'];
$isDashboard = ($currentPath === APP_URL . '/index.php' || $currentPath === '/index.php' || !preg_match('/modules\//', $currentPath));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'InmoCRM' ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="bi bi-buildings"></i> InmoCRM</h4>
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
            <hr class="mx-3 my-2">
            <small class="text-muted px-3 text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Comunicacion</small>
            <a href="<?= APP_URL ?>/modules/email/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/email/') !== false ? 'active' : '' ?>">
                <i class="bi bi-envelope"></i> Email
            </a>
            <a href="<?= APP_URL ?>/modules/whatsapp/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'whatsapp') !== false ? 'active' : '' ?>">
                <i class="bi bi-whatsapp"></i> WhatsApp
            </a>
            <hr class="mx-3 my-2">
            <small class="text-muted px-3 text-uppercase" style="font-size: 0.65rem; letter-spacing: 1px;">Sistema</small>
            <a href="<?= APP_URL ?>/modules/automatizaciones/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'automatizaciones') !== false ? 'active' : '' ?>">
                <i class="bi bi-robot"></i> Automatizaciones
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
                <button class="btn btn-link text-dark me-3 d-lg-none" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h5 class="mb-0 page-title"><?= $pageTitle ?? 'Dashboard' ?></h5>
            </div>
            <div class="d-flex align-items-center gap-3">
                <!-- Notificaciones -->
                <div class="dropdown">
                    <button class="btn btn-link text-dark position-relative" data-bs-toggle="dropdown">
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
                    <button class="btn btn-link text-dark dropdown-toggle" data-bs-toggle="dropdown">
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
