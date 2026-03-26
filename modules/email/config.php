<?php
$pageTitle = 'Configuracion Email';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Obtener cuenta existente
$stmtCuenta = $db->prepare("SELECT * FROM email_cuentas WHERE usuario_id = ? LIMIT 1");
$stmtCuenta->execute([currentUserId()]);
$cuenta = $stmtCuenta->fetch();

// Guardar configuracion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'email' => post('email'),
        'nombre_display' => post('nombre_display'),
        'smtp_host' => post('smtp_host'),
        'smtp_port' => intval(post('smtp_port', 587)),
        'smtp_user' => post('smtp_user'),
        'smtp_pass' => post('smtp_pass'),
        'imap_host' => post('imap_host'),
        'imap_port' => intval(post('imap_port', 993)),
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];

    if (empty($data['email'])) {
        setFlash('danger', 'El email es obligatorio.');
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        setFlash('danger', 'El email no es valido.');
    } else {
        if ($cuenta) {
            // No actualizar password si se deja vacio
            $sql = "UPDATE email_cuentas SET email = ?, nombre_display = ?, smtp_host = ?, smtp_port = ?, smtp_user = ?, imap_host = ?, imap_port = ?, activo = ?";
            $params = [$data['email'], $data['nombre_display'], $data['smtp_host'], $data['smtp_port'], $data['smtp_user'], $data['imap_host'], $data['imap_port'], $data['activo']];

            if (!empty($data['smtp_pass'])) {
                $sql .= ", smtp_pass = ?";
                $params[] = $data['smtp_pass'];
            }
            $sql .= " WHERE id = ?";
            $params[] = $cuenta['id'];

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $db->prepare("
                INSERT INTO email_cuentas (usuario_id, email, nombre_display, smtp_host, smtp_port, smtp_user, smtp_pass, imap_host, imap_port, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                currentUserId(), $data['email'], $data['nombre_display'],
                $data['smtp_host'], $data['smtp_port'], $data['smtp_user'], $data['smtp_pass'],
                $data['imap_host'], $data['imap_port'], $data['activo']
            ]);
        }

        registrarActividad('actualizar', 'email_config', currentUserId(), 'Configuracion de email actualizada');
        setFlash('success', 'Configuracion de email guardada correctamente.');
        header('Location: config.php');
        exit;
    }
}

$valores = [
    'email' => $cuenta['email'] ?? '',
    'nombre_display' => $cuenta['nombre_display'] ?? currentUserName(),
    'smtp_host' => $cuenta['smtp_host'] ?? '',
    'smtp_port' => $cuenta['smtp_port'] ?? 587,
    'smtp_user' => $cuenta['smtp_user'] ?? '',
    'imap_host' => $cuenta['imap_host'] ?? '',
    'imap_port' => $cuenta['imap_port'] ?? 993,
    'activo' => $cuenta['activo'] ?? 1,
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver a Email
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-gear"></i> Configuracion de Cuenta Email</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="<?= sanitize($valores['email']) ?>" placeholder="tu@email.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre para mostrar</label>
                            <input type="text" name="nombre_display" class="form-control" value="<?= sanitize($valores['nombre_display']) ?>" placeholder="Tu nombre">
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3"><i class="bi bi-send"></i> Configuracion SMTP (Envio)</h6>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Servidor SMTP</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?= sanitize($valores['smtp_host']) ?>" placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Puerto</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?= intval($valores['smtp_port']) ?>" placeholder="587">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Usuario SMTP</label>
                            <input type="text" name="smtp_user" class="form-control" value="<?= sanitize($valores['smtp_user']) ?>" placeholder="tu@email.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contrasena SMTP</label>
                            <input type="password" name="smtp_pass" class="form-control" placeholder="<?= $cuenta ? '(sin cambios)' : 'Contrasena' ?>">
                            <?php if ($cuenta): ?>
                            <small class="text-muted">Dejar en blanco para mantener la actual.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3"><i class="bi bi-inbox"></i> Configuracion IMAP (Recepcion)</h6>

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Servidor IMAP</label>
                            <input type="text" name="imap_host" class="form-control" value="<?= sanitize($valores['imap_host']) ?>" placeholder="imap.gmail.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Puerto</label>
                            <input type="number" name="imap_port" class="form-control" value="<?= intval($valores['imap_port']) ?>" placeholder="993">
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="activo" class="form-check-input" id="activoSwitch" value="1" <?= $valores['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activoSwitch">Cuenta activa</label>
                        </div>
                    </div>

                    <div class="alert alert-info small">
                        <h6><i class="bi bi-info-circle"></i> Configuraciones comunes</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <thead><tr><th>Proveedor</th><th>SMTP</th><th>Puerto</th><th>IMAP</th><th>Puerto</th></tr></thead>
                            <tbody>
                                <tr><td>Gmail</td><td>smtp.gmail.com</td><td>587</td><td>imap.gmail.com</td><td>993</td></tr>
                                <tr><td>Outlook</td><td>smtp.office365.com</td><td>587</td><td>outlook.office365.com</td><td>993</td></tr>
                                <tr><td>Hostinger</td><td>smtp.hostinger.com</td><td>465</td><td>imap.hostinger.com</td><td>993</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Configuracion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
