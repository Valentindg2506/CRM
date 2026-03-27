<?php
$pageTitle = 'Ajustes';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/ajustes_helper.php';

$db = getDB();

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Apariencia
    setUserSetting('tema', post('tema', 'claro'));
    setUserSetting('color_primario', post('color_primario', 'emerald'));
    setUserSetting('sidebar_compacta', isset($_POST['sidebar_compacta']) ? '1' : '0');

    // Dashboard
    $widgets = $_POST['dashboard_widgets'] ?? [];
    setUserSetting('dashboard_widgets', implode(',', array_map('sanitize', $widgets)));
    setUserSetting('items_por_pagina', post('items_por_pagina', '20'));

    // Notificaciones
    setUserSetting('notif_email_visitas', isset($_POST['notif_email_visitas']) ? '1' : '0');
    setUserSetting('notif_email_tareas', isset($_POST['notif_email_tareas']) ? '1' : '0');
    setUserSetting('notif_email_semanal', isset($_POST['notif_email_semanal']) ? '1' : '0');
    setUserSetting('notif_browser', isset($_POST['notif_browser']) ? '1' : '0');

    // Datos empresa (solo admin)
    if (isAdmin()) {
        setUserSetting('empresa_nombre', post('empresa_nombre'));
        setUserSetting('empresa_cif', post('empresa_cif'));
        setUserSetting('empresa_direccion', post('empresa_direccion'));
        setUserSetting('empresa_email_dpd', post('empresa_email_dpd'));

        if (!empty($_FILES['empresa_logo']['name'])) {
            $logo = uploadImage($_FILES['empresa_logo'], 'empresa');
            if (isset($logo['success'])) {
                setUserSetting('empresa_logo', $logo['filename']);
            }
        }
    }

    registrarActividad('actualizar', 'ajustes', currentUserId(), 'Ajustes de usuario actualizados');
    setFlash('success', 'Ajustes guardados correctamente.');
    header('Location: index.php');
    exit;
}

// Load current settings
$settings = getUserSettings();
$tema = $settings['tema'] ?? 'claro';
$colorPrimario = $settings['color_primario'] ?? 'emerald';
$sidebarCompacta = ($settings['sidebar_compacta'] ?? '0') === '1';
$dashboardWidgets = explode(',', $settings['dashboard_widgets'] ?? 'kpis,finanzas,proximas_visitas,tareas,ultimas_propiedades,actividad');
$itemsPorPagina = $settings['items_por_pagina'] ?? '20';
$notifEmailVisitas = ($settings['notif_email_visitas'] ?? '1') === '1';
$notifEmailTareas = ($settings['notif_email_tareas'] ?? '1') === '1';
$notifEmailSemanal = ($settings['notif_email_semanal'] ?? '0') === '1';
$notifBrowser = ($settings['notif_browser'] ?? '0') === '1';

$colores = [
    'emerald' => ['label' => 'Esmeralda', 'hex' => '#10b981'],
    'blue' => ['label' => 'Azul', 'hex' => '#3b82f6'],
    'purple' => ['label' => 'Purpura', 'hex' => '#8b5cf6'],
    'orange' => ['label' => 'Naranja', 'hex' => '#f97316'],
    'rose' => ['label' => 'Rosa', 'hex' => '#f43f5e'],
    'cyan' => ['label' => 'Cian', 'hex' => '#06b6d4'],
];

$widgetOptions = [
    'kpis' => 'KPIs',
    'finanzas' => 'Finanzas',
    'proximas_visitas' => 'Proximas Visitas',
    'tareas' => 'Tareas',
    'ultimas_propiedades' => 'Ultimas Propiedades',
    'actividad' => 'Actividad',
];
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <h5 class="mb-0"><i class="bi bi-sliders"></i> Preferencias</h5>
</div>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>

    <div class="row g-4">
        <!-- Card 1: Apariencia -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0"><i class="bi bi-palette"></i> Apariencia</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Tema</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tema" id="temaClaro" value="claro" <?= $tema === 'claro' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="temaClaro"><i class="bi bi-sun"></i> Claro</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tema" id="temaOscuro" value="oscuro" <?= $tema === 'oscuro' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="temaOscuro"><i class="bi bi-moon"></i> Oscuro</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Color primario</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach ($colores as $key => $color): ?>
                            <label class="color-option position-relative" style="cursor: pointer;">
                                <input type="radio" name="color_primario" value="<?= $key ?>" class="d-none" <?= $colorPrimario === $key ? 'checked' : '' ?>>
                                <span class="d-inline-block rounded-circle border border-2" style="width: 36px; height: 36px; background-color: <?= $color['hex'] ?>; <?= $colorPrimario === $key ? 'border-color: #000 !important;' : '' ?>" title="<?= $color['label'] ?>"></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="sidebar_compacta" id="sidebarCompacta" value="1" <?= $sidebarCompacta ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sidebarCompacta">Sidebar compacta</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Dashboard -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0"><i class="bi bi-speedometer2"></i> Dashboard</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Widgets visibles</label>
                        <?php foreach ($widgetOptions as $key => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="dashboard_widgets[]" value="<?= $key ?>" id="widget_<?= $key ?>" <?= in_array($key, $dashboardWidgets) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="widget_<?= $key ?>"><?= $label ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Propiedades por pagina</label>
                        <select name="items_por_pagina" class="form-select">
                            <option value="10" <?= $itemsPorPagina === '10' ? 'selected' : '' ?>>10</option>
                            <option value="20" <?= $itemsPorPagina === '20' ? 'selected' : '' ?>>20</option>
                            <option value="50" <?= $itemsPorPagina === '50' ? 'selected' : '' ?>>50</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Notificaciones -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0"><i class="bi bi-bell"></i> Notificaciones</h6>
                </div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="notif_email_visitas" id="notifVisitas" value="1" <?= $notifEmailVisitas ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifVisitas">Email nuevas visitas</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="notif_email_tareas" id="notifTareas" value="1" <?= $notifEmailTareas ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifTareas">Email nuevas tareas</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="notif_email_semanal" id="notifSemanal" value="1" <?= $notifEmailSemanal ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifSemanal">Email informe semanal</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="notif_browser" id="notifBrowser" value="1" <?= $notifBrowser ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifBrowser">Notificaciones en navegador</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4: Datos Empresa (admin only) -->
        <?php if (isAdmin()): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0"><i class="bi bi-building"></i> Datos Empresa</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre empresa</label>
                        <input type="text" name="empresa_nombre" class="form-control" value="<?= sanitize($settings['empresa_nombre'] ?? '') ?>" maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CIF</label>
                        <input type="text" name="empresa_cif" class="form-control" value="<?= sanitize($settings['empresa_cif'] ?? '') ?>" maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Direccion</label>
                        <input type="text" name="empresa_direccion" class="form-control" value="<?= sanitize($settings['empresa_direccion'] ?? '') ?>" maxlength="300">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email DPD</label>
                        <input type="email" name="empresa_email_dpd" class="form-control" value="<?= sanitize($settings['empresa_email_dpd'] ?? '') ?>" maxlength="200">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Logo</label>
                        <input type="file" name="empresa_logo" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($settings['empresa_logo'])): ?>
                            <small class="text-muted mt-1 d-block">Logo actual: <?= sanitize($settings['empresa_logo']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar Ajustes</button>
    </div>
</form>

<!-- Acceso rapido a herramientas -->
<div class="card mt-4 shadow-sm border-0">
    <div class="card-header"><i class="bi bi-tools"></i> Herramientas</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <a href="custom_fields.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-ui-checks-grid"></i> Campos Personalizados
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= APP_URL ?>/modules/calendario/booking_config.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-calendar-check"></i> Configurar Booking
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= APP_URL ?>/modules/automatizaciones/index.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-robot"></i> Automatizaciones
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.color-option input:checked + span {
    border-color: #000 !important;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.2);
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
