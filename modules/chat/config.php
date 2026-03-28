<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $db->prepare("UPDATE chat_config SET titulo=?, subtitulo=?, color_primario=?, posicion=?, mensaje_bienvenida=?, pedir_datos=?, activo=?, horario_inicio=?, horario_fin=?, mensaje_fuera_horario=? WHERE id=1")->execute([
        trim(post('titulo')), trim(post('subtitulo')), post('color_primario','#10b981'), post('posicion','bottom-right'),
        trim(post('mensaje_bienvenida')), post('pedir_datos') ? 1 : 0, post('activo') ? 1 : 0,
        post('horario_inicio','09:00'), post('horario_fin','20:00'), trim(post('mensaje_fuera_horario'))
    ]);
    setFlash('success', 'Configuracion guardada.');
    header('Location: config.php');
    exit;
}

$pageTitle = 'Configuracion Chat';
require_once __DIR__ . '/../../includes/header.php';
$cfg = $db->query("SELECT * FROM chat_config WHERE id = 1")->fetch();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-chat-dots"></i> Configuracion del Widget</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Titulo</label>
                            <input type="text" name="titulo" class="form-control" value="<?= sanitize($cfg['titulo'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color</label>
                            <input type="color" name="color_primario" class="form-control form-control-color w-100" value="<?= sanitize($cfg['color_primario'] ?? '#10b981') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subtitulo</label>
                            <input type="text" name="subtitulo" class="form-control" value="<?= sanitize($cfg['subtitulo'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mensaje de bienvenida</label>
                            <textarea name="mensaje_bienvenida" class="form-control" rows="2"><?= sanitize($cfg['mensaje_bienvenida'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Posicion</label>
                            <select name="posicion" class="form-select">
                                <option value="bottom-right" <?= ($cfg['posicion'] ?? '') === 'bottom-right' ? 'selected' : '' ?>>Abajo derecha</option>
                                <option value="bottom-left" <?= ($cfg['posicion'] ?? '') === 'bottom-left' ? 'selected' : '' ?>>Abajo izquierda</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Horario inicio</label>
                            <input type="time" name="horario_inicio" class="form-control" value="<?= substr($cfg['horario_inicio'] ?? '09:00', 0, 5) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Horario fin</label>
                            <input type="time" name="horario_fin" class="form-control" value="<?= substr($cfg['horario_fin'] ?? '20:00', 0, 5) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mensaje fuera de horario</label>
                            <textarea name="mensaje_fuera_horario" class="form-control" rows="2"><?= sanitize($cfg['mensaje_fuera_horario'] ?? '') ?></textarea>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="pedir_datos" class="form-check-input" value="1" <?= ($cfg['pedir_datos'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label">Pedir datos antes del chat</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="activo" class="form-check-input" value="1" <?= ($cfg['activo'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label">Chat activo</label>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-code-slash"></i> Codigo para tu web</h6></div>
                <div class="card-body">
                    <p class="small text-muted">Copia este codigo y pegalo antes del <code>&lt;/body&gt;</code> en tu sitio web:</p>
                    <textarea class="form-control font-monospace" rows="3" readonly onclick="this.select()">&lt;script src="<?= APP_URL ?>/assets/js/chat-widget.js" data-crm-url="<?= APP_URL ?>"&gt;&lt;/script&gt;</textarea>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
