<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');
    if ($a === 'crear_post') {
        $db->prepare("INSERT INTO comunidad_posts (canal, titulo, contenido, tipo, usuario_id) VALUES (?,?,?,?,?)")
            ->execute([trim(post('canal','general')), trim(post('titulo')), trim(post('contenido')), post('tipo','discusion'), currentUserId()]);
        setFlash('success','Publicado.');
        header('Location: index.php'); exit;
    }
    if ($a === 'responder') {
        $postId = intval(post('post_id'));
        $db->prepare("INSERT INTO comunidad_respuestas (post_id, contenido, usuario_id) VALUES (?,?,?)")
            ->execute([$postId, trim(post('contenido')), currentUserId()]);
        $db->prepare("UPDATE comunidad_posts SET respuestas_count=respuestas_count+1 WHERE id=?")->execute([$postId]);
        header('Location: index.php?post='.$postId); exit;
    }
    if ($a === 'fijar') { $db->prepare("UPDATE comunidad_posts SET fijado=NOT fijado WHERE id=?")->execute([intval(post('pid'))]); header('Location: index.php'); exit; }
    if ($a === 'eliminar') { $db->prepare("DELETE FROM comunidad_posts WHERE id=?")->execute([intval(post('pid'))]); setFlash('success','Eliminado.'); header('Location: index.php'); exit; }
}

$pageTitle = 'Comunidad';
require_once __DIR__ . '/../../includes/header.php';

$canal = get('canal', '');
$postId = intval(get('post'));
$where = $canal ? "WHERE cp.canal = ?" : "";
$params = $canal ? [$canal] : [];

$posts = $db->prepare("SELECT cp.*, u.nombre as autor FROM comunidad_posts cp LEFT JOIN usuarios u ON cp.usuario_id=u.id $where ORDER BY cp.fijado DESC, cp.created_at DESC LIMIT 50");
$posts->execute($params); $posts=$posts->fetchAll();

$canales = $db->query("SELECT DISTINCT canal FROM comunidad_posts ORDER BY canal")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('general', $canales)) array_unshift($canales, 'general');

$activePost = null; $respuestas = [];
if ($postId) {
    $activePost = $db->prepare("SELECT cp.*, u.nombre as autor FROM comunidad_posts cp LEFT JOIN usuarios u ON cp.usuario_id=u.id WHERE cp.id=?");
    $activePost->execute([$postId]); $activePost=$activePost->fetch();
    if ($activePost) {
        $respuestas = $db->prepare("SELECT r.*, u.nombre as autor FROM comunidad_respuestas r LEFT JOIN usuarios u ON r.usuario_id=u.id WHERE r.post_id=? ORDER BY r.created_at ASC");
        $respuestas->execute([$postId]); $respuestas=$respuestas->fetchAll();
    }
}

$tipoIcons = ['discusion'=>'bi-chat-text text-primary','pregunta'=>'bi-question-circle text-warning','anuncio'=>'bi-megaphone text-danger'];
?>

<div class="row g-3">
    <!-- Sidebar canales -->
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-2">
                <h6 class="small text-uppercase text-muted px-2 mb-2">Canales</h6>
                <a href="index.php" class="d-block small px-2 py-1 rounded <?= !$canal?'bg-primary text-white':'text-dark' ?> text-decoration-none mb-1"># Todos</a>
                <?php foreach ($canales as $c): ?>
                <a href="?canal=<?= urlencode($c) ?>" class="d-block small px-2 py-1 rounded <?= $canal===$c?'bg-primary text-white':'text-dark' ?> text-decoration-none mb-1"># <?= sanitize($c) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <button class="btn btn-primary btn-sm w-100 mt-2" data-bs-toggle="modal" data-bs-target="#modalPost"><i class="bi bi-plus"></i> Nuevo Post</button>
    </div>

    <!-- Feed o post detail -->
    <div class="col-md-10">
        <?php if ($activePost): ?>
        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Volver</a>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div><i class="bi <?= $tipoIcons[$activePost['tipo']]??'bi-chat' ?>"></i> <span class="badge bg-light text-dark"><?= ucfirst($activePost['tipo']) ?></span> <span class="badge bg-light text-dark"># <?= sanitize($activePost['canal']) ?></span></div>
                    <small class="text-muted"><?= formatFechaHora($activePost['created_at']) ?></small>
                </div>
                <h4 class="fw-bold mt-2"><?= sanitize($activePost['titulo']) ?></h4>
                <div class="mt-2"><?= nl2br(sanitize($activePost['contenido'])) ?></div>
                <small class="text-muted">Por <?= sanitize($activePost['autor']??'Anonimo') ?></small>
            </div>
        </div>

        <!-- Respuestas -->
        <?php foreach ($respuestas as $r): ?>
        <div class="card border-0 shadow-sm mb-2">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-between"><strong class="small"><?= sanitize($r['autor']??'Anonimo') ?></strong><small class="text-muted"><?= formatFechaHora($r['created_at']) ?></small></div>
                <div class="mt-1"><?= nl2br(sanitize($r['contenido'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?><input type="hidden" name="accion" value="responder"><input type="hidden" name="post_id" value="<?= $activePost['id'] ?>">
                    <textarea name="contenido" class="form-control mb-2" rows="3" placeholder="Escribe tu respuesta..." required></textarea>
                    <button class="btn btn-primary btn-sm"><i class="bi bi-reply"></i> Responder</button>
                </form>
            </div>
        </div>

        <?php else: ?>
        <?php foreach ($posts as $p): ?>
        <div class="card border-0 shadow-sm mb-2">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <?php if ($p['fijado']): ?><span class="badge bg-warning text-dark me-1"><i class="bi bi-pin"></i></span><?php endif; ?>
                        <i class="bi <?= $tipoIcons[$p['tipo']]??'bi-chat' ?>"></i>
                        <a href="?post=<?= $p['id'] ?>" class="fw-bold text-decoration-none text-dark"><?= sanitize($p['titulo']) ?></a>
                        <span class="badge bg-light text-dark ms-1"># <?= sanitize($p['canal']) ?></span>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <small class="text-muted"><i class="bi bi-chat"></i> <?= $p['respuestas_count'] ?></small>
                        <?php if (isAdmin()): ?>
                        <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="accion" value="fijar"><input type="hidden" name="pid" value="<?= $p['id'] ?>"><button class="btn btn-xs btn-outline-warning"><i class="bi bi-pin"></i></button></form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="pid" value="<?= $p['id'] ?>"><button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="small text-muted mt-1"><?= sanitize(mb_strimwidth($p['contenido'],0,150,'...')) ?></div>
                <small class="text-muted"><?= sanitize($p['autor']??'Anonimo') ?> &middot; <?= formatFechaHora($p['created_at']) ?></small>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalPost" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="crear_post">
    <div class="modal-header"><h5 class="modal-title">Nuevo Post</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Titulo</label><input type="text" name="titulo" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Canal</label><input type="text" name="canal" class="form-control" value="<?= sanitize($canal?:'general') ?>" list="canalesList">
                <datalist id="canalesList"><?php foreach($canales as $c):?><option value="<?= sanitize($c) ?>"><?php endforeach;?></datalist></div>
        </div>
        <div class="mb-3 mt-3"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><option value="discusion">Discusion</option><option value="pregunta">Pregunta</option><option value="anuncio">Anuncio</option></select></div>
        <div class="mb-3"><label class="form-label">Contenido</label><textarea name="contenido" class="form-control" rows="5" required></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Publicar</button></div>
</form></div></div></div>

<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
