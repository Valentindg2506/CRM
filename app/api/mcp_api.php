<?php
/**
 * API REST para el conector MCP del CRM
 * Autenticación: Bearer token generado en Ajustes → Conector Claude (MCP)
 * El token se almacena en usuario_ajustes con clave = 'mcp_token'
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/email.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Autenticar por Bearer token ────────────────────────────────────────────
function getMcpUser(PDO $db): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) return null;
    $token = trim($m[1]);
    if (strlen($token) < 32) return null;
    $stmt = $db->prepare(
        "SELECT u.* FROM usuarios u
         JOIN usuario_ajustes ua ON ua.usuario_id = u.id
         WHERE ua.clave = 'mcp_token' AND ua.valor = ? AND u.activo = 1 LIMIT 1"
    );
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$db      = getDB();
$user    = getMcpUser($db);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Token MCP inválido o expirado. Genera uno en Ajustes → Conector Claude (MCP).']);
    exit;
}

$isAdmin = ($user['rol'] ?? '') === 'admin';
$userId  = (int)$user['id'];
$action  = $_GET['action'] ?? '';

$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
}

// ── Helpers ────────────────────────────────────────────────────────────────
function agentFilter(bool $isAdmin, int $userId, string $alias = 'p'): array {
    if ($isAdmin) return ['', []];
    return [" AND {$alias}.agente_id = ?", [$userId]];
}

function ok($data): void {
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
}

// ── Acciones ───────────────────────────────────────────────────────────────
try {
    switch ($action) {

        // ══════════════════════════════════════════════════════════════════════
        // DASHBOARD
        // ══════════════════════════════════════════════════════════════════════

        case 'resumen': {
            $af  = $isAdmin ? '' : " AND agente_id = $userId";
            $afP = $isAdmin ? '' : " AND p.agente_id = $userId";

            $stmt = $db->query("SELECT etapa, COUNT(*) as total FROM prospectos WHERE activo = 1 $af GROUP BY etapa ORDER BY FIELD(etapa,'nuevo_lead','contactado','seguimiento','visita_programada','captado','descartado')");
            $porEtapa = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->query("SELECT COUNT(*) FROM clientes WHERE activo = 1 $af");
            $totalClientes = (int)$stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM tareas WHERE estado = 'pendiente' AND DATE(fecha_vencimiento) = CURDATE() AND (asignado_a = ? OR creado_por = ?)");
            $stmt->execute([$userId, $userId]);
            $tareasPendientesHoy = (int)$stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM prospectos p WHERE p.activo = 1 AND p.fecha_proximo_contacto = CURDATE() $afP");
            $contactosHoy = (int)$stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM prospectos WHERE activo = 1 AND etapa NOT IN ('captado','descartado') $af");
            $prospectosPipeline = (int)$stmt->fetchColumn();

            // Tareas vencidas sin completar
            $stmt = $db->prepare("SELECT COUNT(*) FROM tareas WHERE estado = 'pendiente' AND fecha_vencimiento < NOW() AND (asignado_a = ? OR creado_por = ?)");
            $stmt->execute([$userId, $userId]);
            $tareasVencidas = (int)$stmt->fetchColumn();

            ok([
                'resumen' => [
                    'prospectos_activos_pipeline' => $prospectosPipeline,
                    'prospectos_por_etapa'        => $porEtapa,
                    'total_clientes'              => $totalClientes,
                    'tareas_pendientes_hoy'       => $tareasPendientesHoy,
                    'tareas_vencidas'             => $tareasVencidas,
                    'prospectos_a_contactar_hoy'  => $contactosHoy,
                ]
            ]);
            break;
        }

        case 'estadisticas': {
            $periodo = $_GET['periodo'] ?? 'mes';
            $af = $isAdmin ? '' : " AND usuario_id = $userId";

            switch ($periodo) {
                case 'hoy':       $desde = 'CURDATE()'; $hasta = 'CURDATE()'; break;
                case 'semana':    $desde = 'DATE_SUB(CURDATE(), INTERVAL 7 DAY)'; $hasta = 'CURDATE()'; break;
                case 'trimestre': $desde = 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)'; $hasta = 'CURDATE()'; break;
                case 'anio':      $desde = 'DATE_FORMAT(CURDATE(), "%Y-01-01")'; $hasta = 'CURDATE()'; break;
                default:          $desde = 'DATE_FORMAT(CURDATE(), "%Y-%m-01")'; $hasta = 'CURDATE()';
            }

            $stmt = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(total),0) as importe FROM facturas WHERE estado = 'pagada' AND DATE(fecha_emision) BETWEEN $desde AND $hasta $af");
            $facturas = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->query("SELECT COUNT(*) as total, COALESCE(SUM(total),0) as importe FROM presupuestos WHERE estado = 'aceptado' AND DATE(fecha_emision) BETWEEN $desde AND $hasta $af");
            $presupuestos = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->query("SELECT COUNT(*) FROM contratos WHERE estado = 'firmado' AND DATE(created_at) BETWEEN $desde AND $hasta $af");
            $contratosFirmados = (int)$stmt->fetchColumn();

            $afP = $isAdmin ? '' : " AND agente_id = $userId";
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos WHERE DATE(created_at) BETWEEN $desde AND $hasta $afP");
            $prospectoNuevos = (int)$stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM prospectos WHERE etapa = 'captado' AND DATE(updated_at) BETWEEN $desde AND $hasta $afP");
            $captados = (int)$stmt->fetchColumn();

            ok([
                'estadisticas' => [
                    'periodo'                => $periodo,
                    'facturas_cobradas'      => (int)$facturas['total'],
                    'importe_cobrado'        => round((float)$facturas['importe'], 2),
                    'presupuestos_aceptados' => (int)$presupuestos['total'],
                    'importe_presupuestado'  => round((float)$presupuestos['importe'], 2),
                    'contratos_firmados'     => $contratosFirmados,
                    'prospectos_nuevos'      => $prospectoNuevos,
                    'prospectos_captados'    => $captados,
                ]
            ]);
            break;
        }

        case 'buscar': {
            $q = trim($_GET['q'] ?? '');
            if (!$q) { err('Parámetro q requerido'); break; }
            $like = "%$q%";
            $af = $isAdmin ? '' : " AND agente_id = $userId";

            $stmt = $db->prepare("SELECT id, nombre, telefono, email, etapa, temperatura FROM prospectos WHERE activo = 1 AND (nombre LIKE ? OR telefono LIKE ? OR email LIKE ?) $af LIMIT 10");
            $stmt->execute([$like, $like, $like]);
            $prospectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT id, CONCAT(nombre,' ',COALESCE(apellidos,'')) as nombre, tipo, email, telefono FROM clientes WHERE activo = 1 AND (nombre LIKE ? OR apellidos LIKE ? OR email LIKE ? OR telefono LIKE ?) $af LIMIT 10");
            $stmt->execute([$like, $like, $like, $like]);
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT id, referencia, titulo, tipo, estado, precio FROM propiedades WHERE (titulo LIKE ? OR referencia LIKE ? OR localidad LIKE ?) $af LIMIT 10");
            $stmt->execute([$like, $like, $like]);
            $propiedades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ok(['prospectos' => $prospectos, 'clientes' => $clientes, 'propiedades' => $propiedades]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // PROSPECTOS
        // ══════════════════════════════════════════════════════════════════════

        case 'prospectos': {
            $q            = '%' . ($_GET['q'] ?? '') . '%';
            $etapa        = $_GET['etapa'] ?? '';
            $temp         = $_GET['temperatura'] ?? '';
            $contactarHoy = ($_GET['contactar_hoy'] ?? '') === '1';
            $limit        = min(100, max(1, (int)($_GET['limit'] ?? 20)));

            $where  = ['p.activo = 1'];
            $params = [];
            if (!$isAdmin)     { $where[] = 'p.agente_id = ?';   $params[] = $userId; }
            if ($_GET['q'] ?? '') {
                $where[]  = '(p.nombre LIKE ? OR p.telefono LIKE ? OR p.email LIKE ?)';
                $params   = array_merge($params, [$q, $q, $q]);
            }
            if ($etapa)        { $where[] = 'p.etapa = ?';        $params[] = $etapa; }
            if ($temp)         { $where[] = 'p.temperatura = ?';  $params[] = $temp; }
            if ($contactarHoy) { $where[] = 'p.fecha_proximo_contacto = CURDATE()'; }

            $sql  = "SELECT p.id, p.referencia, p.nombre, p.telefono, p.email,
                            p.etapa, p.temperatura, p.tipo_propiedad,
                            p.precio_estimado, p.localidad, p.provincia,
                            DATE_FORMAT(p.fecha_proximo_contacto,'%d/%m/%Y') as fecha_contacto,
                            p.proxima_accion
                     FROM prospectos p
                     WHERE " . implode(' AND ', $where) .
                    " ORDER BY p.fecha_proximo_contacto ASC, p.created_at DESC LIMIT $limit";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['total' => count($rows = $stmt->fetchAll(PDO::FETCH_ASSOC)), 'prospectos' => $rows]);
            break;
        }

        case 'prospecto': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }
            [$af, $ap] = agentFilter($isAdmin, $userId);
            $stmt = $db->prepare(
                "SELECT p.*, u.nombre as agente_nombre FROM prospectos p
                 LEFT JOIN usuarios u ON p.agente_id = u.id
                 WHERE p.id = ? $af LIMIT 1"
            );
            $stmt->execute(array_merge([$id], $ap));
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { err('Prospecto no encontrado', 404); break; }

            $h = $db->prepare(
                "SELECT tipo, contenido, DATE_FORMAT(COALESCE(fecha_evento,created_at),'%d/%m/%Y %H:%i') as fecha
                 FROM historial_prospectos WHERE prospecto_id = ?
                 ORDER BY COALESCE(fecha_evento,created_at) DESC LIMIT 10"
            );
            $h->execute([$id]);
            $p['historial_reciente'] = $h->fetchAll(PDO::FETCH_ASSOC);

            // Tareas relacionadas
            $t = $db->prepare("SELECT id, titulo, tipo, estado, DATE_FORMAT(fecha_vencimiento,'%d/%m/%Y') as vence FROM tareas WHERE prospecto_id = ? AND estado != 'completada' ORDER BY fecha_vencimiento ASC LIMIT 5");
            $t->execute([$id]);
            $p['tareas_pendientes'] = $t->fetchAll(PDO::FETCH_ASSOC);

            unset($p['propietarios_json']);
            ok(['prospecto' => $p]);
            break;
        }

        case 'crear_prospecto': {
            $nombre = trim($body['nombre'] ?? '');
            if (!$nombre) { err('El nombre es obligatorio'); break; }

            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(referencia,3) AS UNSIGNED)) FROM prospectos WHERE referencia LIKE 'PR%'");
            $ref  = 'PR' . str_pad((int)$stmt->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);

            $ins = $db->prepare(
                "INSERT INTO prospectos
                 (referencia, nombre, telefono, email, tipo_propiedad, precio_estimado,
                  localidad, provincia, notas, etapa, temperatura, agente_id, activo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)"
            );
            $ins->execute([
                $ref, $nombre,
                $body['telefono']        ?? null,
                $body['email']           ?? null,
                $body['tipo_propiedad']  ?? null,
                isset($body['precio_estimado']) ? (float)$body['precio_estimado'] : null,
                $body['localidad']       ?? null,
                $body['provincia']       ?? null,
                $body['notas']           ?? null,
                $body['etapa']           ?? 'nuevo_lead',
                $body['temperatura']     ?? 'frio',
                $userId,
            ]);
            $newId = (int)$db->lastInsertId();
            if (function_exists('registrarActividad')) registrarActividad('crear', 'prospecto', $newId, "Prospecto $ref creado por IA");
            ok(['id' => $newId, 'referencia' => $ref, 'mensaje' => "Prospecto $ref creado correctamente"]);
            break;
        }

        case 'actualizar_prospecto': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId);
            $chk = $db->prepare("SELECT id FROM prospectos WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$id], $ap));
            if (!$chk->fetch()) { err('Prospecto no encontrado o sin acceso', 403); break; }

            $campos = [];
            $params = [];
            $etapasOk = ['nuevo_lead','contactado','seguimiento','visita_programada','negociacion','captado','descartado'];
            $tempsOk  = ['frio','templado','caliente'];

            if (!empty($body['etapa']) && in_array($body['etapa'], $etapasOk)) {
                $campos[] = 'etapa = ?'; $params[] = $body['etapa'];
            }
            if (!empty($body['temperatura']) && in_array($body['temperatura'], $tempsOk)) {
                $campos[] = 'temperatura = ?'; $params[] = $body['temperatura'];
            }
            if (isset($body['notas_internas'])) {
                $campos[] = 'notas = ?'; $params[] = $body['notas_internas'];
            }
            if (isset($body['fecha_proximo_contacto'])) {
                $campos[] = 'fecha_proximo_contacto = ?'; $params[] = $body['fecha_proximo_contacto'];
            }
            if (isset($body['proxima_accion'])) {
                $campos[] = 'proxima_accion = ?'; $params[] = $body['proxima_accion'];
            }
            if (empty($campos)) { err('Nada que actualizar'); break; }

            $params[] = $id;
            $db->prepare("UPDATE prospectos SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            if (function_exists('registrarActividad')) registrarActividad('editar', 'prospecto', $id, 'Actualizado por IA');
            ok(['mensaje' => 'Prospecto actualizado correctamente']);
            break;
        }

        case 'anadir_nota': {
            $pid      = (int)($body['prospecto_id'] ?? 0);
            $contenido = trim($body['contenido'] ?? '');
            $tiposOk  = ['nota','llamada','email','visita','whatsapp','otro'];
            $tipo     = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'nota';

            if (!$pid || !$contenido) { err('prospecto_id y contenido son obligatorios'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId);
            $chk = $db->prepare("SELECT id FROM prospectos WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$pid], $ap));
            if (!$chk->fetch()) { err('Prospecto no encontrado o sin acceso', 403); break; }

            $stmt = $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?,?,?,?)");
            $stmt->execute([$pid, $userId, $contenido, $tipo]);

            // Actualizar fecha de último contacto
            $db->prepare("UPDATE prospectos SET updated_at = NOW() WHERE id = ?")->execute([$pid]);

            ok(['mensaje' => 'Nota añadida correctamente', 'id' => (int)$db->lastInsertId()]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // CLIENTES
        // ══════════════════════════════════════════════════════════════════════

        case 'clientes': {
            $q     = '%' . ($_GET['q'] ?? '') . '%';
            $tipo  = $_GET['tipo'] ?? '';
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

            $where  = ['c.activo = 1'];
            $params = [];
            if (!$isAdmin) { $where[] = 'c.agente_id = ?'; $params[] = $userId; }
            if ($_GET['q'] ?? '') {
                $where[]  = '(c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.apellidos LIKE ?)';
                $params   = array_merge($params, [$q, $q, $q, $q]);
            }
            if ($tipo) { $where[] = 'FIND_IN_SET(?, c.tipo)'; $params[] = $tipo; }

            $sql  = "SELECT c.id, c.nombre, c.apellidos, c.tipo, c.email,
                            c.telefono, c.localidad, c.provincia,
                            DATE_FORMAT(c.created_at,'%d/%m/%Y') as alta
                     FROM clientes c
                     WHERE " . implode(' AND ', $where) .
                    " ORDER BY c.created_at DESC LIMIT $limit";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'cliente': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId, 'c');
            $stmt = $db->prepare(
                "SELECT c.*, u.nombre as agente_nombre FROM clientes c
                 LEFT JOIN usuarios u ON c.agente_id = u.id
                 WHERE c.id = ? $af LIMIT 1"
            );
            $stmt->execute(array_merge([$id], $ap));
            $c = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$c) { err('Cliente no encontrado', 404); break; }

            // Presupuestos del cliente
            $stmt2 = $db->prepare("SELECT id, titulo, total, estado, DATE_FORMAT(fecha_emision,'%d/%m/%Y') as fecha FROM presupuestos WHERE cliente_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt2->execute([$id]);
            $c['presupuestos_recientes'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            ok(['cliente' => $c]);
            break;
        }

        case 'crear_cliente': {
            $nombre = trim($body['nombre'] ?? '');
            if (!$nombre) { err('El nombre es obligatorio'); break; }

            $ins = $db->prepare(
                "INSERT INTO clientes (nombre, apellidos, tipo, email, telefono, localidad, provincia, notas, agente_id, activo)
                 VALUES (?,?,?,?,?,?,?,?,?,1)"
            );
            $ins->execute([
                $nombre,
                $body['apellidos'] ?? null,
                $body['tipo']      ?? 'comprador',
                $body['email']     ?? null,
                $body['telefono']  ?? null,
                $body['localidad'] ?? null,
                $body['provincia'] ?? null,
                $body['notas']     ?? null,
                $userId,
            ]);
            $newId = (int)$db->lastInsertId();
            if (function_exists('registrarActividad')) registrarActividad('crear', 'cliente', $newId, 'Cliente creado por IA');
            ok(['id' => $newId, 'mensaje' => 'Cliente creado correctamente']);
            break;
        }

        case 'actualizar_cliente': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId, 'c');
            $chk = $db->prepare("SELECT id FROM clientes c WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$id], $ap));
            if (!$chk->fetch()) { err('Cliente no encontrado o sin acceso', 403); break; }

            $campos = []; $params = [];
            foreach (['nombre','apellidos','tipo','email','telefono','localidad','provincia','notas'] as $f) {
                if (isset($body[$f])) { $campos[] = "$f = ?"; $params[] = $body[$f]; }
            }
            if (empty($campos)) { err('Nada que actualizar'); break; }

            $params[] = $id;
            $db->prepare("UPDATE clientes SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            ok(['mensaje' => 'Cliente actualizado correctamente']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // TAREAS
        // ══════════════════════════════════════════════════════════════════════

        case 'tareas': {
            $estado  = $_GET['estado'] ?? '';
            $soloHoy = ($_GET['solo_hoy'] ?? '') === '1';

            $where  = ['(t.asignado_a = ? OR t.creado_por = ?)'];
            $params = [$userId, $userId];
            if ($estado)  { $where[] = 't.estado = ?';                         $params[] = $estado; }
            if ($soloHoy) { $where[] = 'DATE(t.fecha_vencimiento) = CURDATE()'; }

            $sql  = "SELECT t.id, t.titulo, t.tipo, t.estado, t.prioridad,
                            DATE_FORMAT(t.fecha_vencimiento,'%d/%m/%Y %H:%i') as vencimiento,
                            p.nombre as prospecto_nombre
                     FROM tareas t
                     LEFT JOIN prospectos p ON t.prospecto_id = p.id
                     WHERE " . implode(' AND ', $where) .
                    " ORDER BY t.fecha_vencimiento ASC LIMIT 50";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['tareas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'crear_tarea': {
            $titulo = trim($body['titulo'] ?? '');
            if (!$titulo) { err('El título es obligatorio'); break; }

            $tiposOk = ['llamada','email','reunion','visita','otro'];
            $tipo    = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'otro';

            $ins = $db->prepare(
                "INSERT INTO tareas (titulo, tipo, descripcion, prioridad, estado, fecha_vencimiento, asignado_a, creado_por, prospecto_id, cliente_id, propiedad_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([
                $titulo, $tipo,
                $body['descripcion']       ?? null,
                $body['prioridad']         ?? 'media',
                'pendiente',
                $body['fecha_vencimiento'] ?? null,
                $userId,
                $userId,
                isset($body['prospecto_id']) ? (int)$body['prospecto_id'] : null,
                isset($body['cliente_id'])   ? (int)$body['cliente_id']   : null,
                isset($body['propiedad_id']) ? (int)$body['propiedad_id'] : null,
            ]);
            ok(['id' => (int)$db->lastInsertId(), 'mensaje' => 'Tarea creada correctamente']);
            break;
        }

        case 'completar_tarea': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            $stmt = $db->prepare("UPDATE tareas SET estado = 'completada', updated_at = NOW() WHERE id = ? AND (asignado_a = ? OR creado_por = ?)");
            $stmt->execute([$id, $userId, $userId]);
            if (!$stmt->rowCount()) { err('Tarea no encontrada o sin acceso', 403); break; }
            ok(['mensaje' => 'Tarea marcada como completada']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // PROPIEDADES
        // ══════════════════════════════════════════════════════════════════════

        case 'propiedades': {
            $q     = '%' . ($_GET['q'] ?? '') . '%';
            $est   = $_GET['estado'] ?? '';
            $max   = isset($_GET['max']) ? (float)$_GET['max'] : null;
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

            $where  = ['p.activo = 1'];
            $params = [];
            if (!$isAdmin) { $where[] = 'p.agente_id = ?'; $params[] = $userId; }
            if ($_GET['q'] ?? '') {
                $where[] = '(p.titulo LIKE ? OR p.referencia LIKE ? OR p.localidad LIKE ?)';
                $params  = array_merge($params, [$q, $q, $q]);
            }
            if ($est) { $where[] = 'p.estado = ?'; $params[] = $est; }
            if ($max) { $where[] = 'p.precio <= ?'; $params[] = $max; }

            $sql  = "SELECT p.id, p.referencia, p.titulo, p.tipo, p.estado,
                            p.precio, p.localidad, p.provincia,
                            p.habitaciones, p.banos, p.superficie_construida, p.superficie_util
                     FROM propiedades p
                     WHERE " . implode(' AND ', $where) .
                    " ORDER BY p.created_at DESC LIMIT $limit";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['propiedades' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'propiedad': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId, 'p');
            $stmt = $db->prepare(
                "SELECT p.*, u.nombre as agente_nombre FROM propiedades p
                 LEFT JOIN usuarios u ON p.agente_id = u.id
                 WHERE p.id = ? $af LIMIT 1"
            );
            $stmt->execute(array_merge([$id], $ap));
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { err('Propiedad no encontrada', 404); break; }

            // Fotos
            $f = $db->prepare("SELECT url FROM propiedad_fotos WHERE propiedad_id = ? ORDER BY orden ASC LIMIT 5");
            $f->execute([$id]);
            $p['fotos'] = $f->fetchAll(PDO::FETCH_COLUMN);

            ok(['propiedad' => $p]);
            break;
        }

        case 'crear_propiedad': {
            $titulo = trim($body['titulo'] ?? '');
            if (!$titulo) { err('El título es obligatorio'); break; }

            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(referencia,3) AS UNSIGNED)) FROM propiedades WHERE referencia LIKE 'IM%'");
            $ref  = 'IM' . str_pad((int)$stmt->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);

            $ins = $db->prepare(
                "INSERT INTO propiedades (referencia, titulo, tipo, estado, precio, localidad, provincia, descripcion, habitaciones, banos, superficie_construida, agente_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([
                $ref, $titulo,
                $body['tipo']         ?? 'piso',
                $body['estado']       ?? 'disponible',
                isset($body['precio'])       ? (float)$body['precio']       : null,
                $body['localidad']    ?? null,
                $body['provincia']    ?? null,
                $body['descripcion']  ?? null,
                isset($body['habitaciones']) ? (int)$body['habitaciones']   : null,
                isset($body['banos'])        ? (int)$body['banos']          : null,
                isset($body['metros'])       ? (float)$body['metros']       : null,
                $userId,
            ]);
            $newId = (int)$db->lastInsertId();
            if (function_exists('registrarActividad')) registrarActividad('crear', 'propiedad', $newId, "Propiedad $ref creada por IA");
            ok(['id' => $newId, 'referencia' => $ref, 'mensaje' => "Propiedad $ref creada correctamente"]);
            break;
        }

        case 'actualizar_propiedad': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId, 'p');
            $chk = $db->prepare("SELECT id FROM propiedades p WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$id], $ap));
            if (!$chk->fetch()) { err('Propiedad no encontrada o sin acceso', 403); break; }

            $campos = []; $params = [];
            $estadosOk = ['disponible','reservado','vendido','alquilado','retirado'];
            if (!empty($body['estado']) && in_array($body['estado'], $estadosOk)) {
                $campos[] = 'estado = ?'; $params[] = $body['estado'];
            }
            if (isset($body['precio'])) { $campos[] = 'precio = ?'; $params[] = (float)$body['precio']; }
            if (isset($body['notas']))  { $campos[] = 'descripcion = ?'; $params[] = $body['notas']; }
            if (empty($campos)) { err('Nada que actualizar'); break; }

            $params[] = $id;
            $db->prepare("UPDATE propiedades SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            ok(['mensaje' => 'Propiedad actualizada correctamente']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // PRESUPUESTOS
        // ══════════════════════════════════════════════════════════════════════

        case 'presupuestos': {
            $estado = $_GET['estado'] ?? '';
            $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND pr.usuario_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado) { $where[] = 'pr.estado = ?'; $params[] = $estado; }

            $sql = "SELECT pr.id, pr.titulo, pr.total, pr.estado,
                           c.nombre as cliente_nombre,
                           DATE_FORMAT(pr.fecha_emision,'%d/%m/%Y') as fecha,
                           DATE_FORMAT(pr.fecha_expiracion,'%d/%m/%Y') as expira
                    FROM presupuestos pr
                    LEFT JOIN clientes c ON pr.cliente_id = c.id
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY pr.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['presupuestos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'presupuesto': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            $af = $isAdmin ? '' : " AND pr.usuario_id = $userId";
            $stmt = $db->prepare(
                "SELECT pr.*, c.nombre as cliente_nombre, c.email as cliente_email
                 FROM presupuestos pr
                 LEFT JOIN clientes c ON pr.cliente_id = c.id
                 WHERE pr.id = ? $af LIMIT 1"
            );
            $stmt->execute([$id]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { err('Presupuesto no encontrado', 404); break; }
            if (!empty($p['lineas'])) $p['lineas'] = json_decode($p['lineas'], true) ?? [];
            ok(['presupuesto' => $p]);
            break;
        }

        case 'crear_presupuesto': {
            $total = isset($body['total']) ? (float)$body['total'] : null;
            if ($total === null) { err('El total es obligatorio'); break; }

            $titulo = trim($body['titulo'] ?? 'Presupuesto');
            if (!$titulo) $titulo = 'Presupuesto';

            // Número de presupuesto
            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(numero,'-',-1) AS UNSIGNED)) FROM presupuestos");
            $num  = (int)$stmt->fetchColumn() + 1;
            $numero = 'P-' . date('Y') . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);

            $iva     = round($total * 0.21, 2);
            $subtotal = round($total - $iva, 2);
            $lineas  = $body['detalles'] ? [['concepto' => $body['detalles'], 'cantidad' => 1, 'precio' => $subtotal]] : [];
            $validez = (int)($body['validez_dias'] ?? 30);

            $ins = $db->prepare(
                "INSERT INTO presupuestos (numero, cliente_id, titulo, descripcion, lineas, subtotal, iva_total, total, validez_dias, fecha_emision, fecha_expiracion, notas, estado, usuario_id)
                 VALUES (?,?,?,?,?,?,?,?,?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY),?,?,?)"
            );
            $ins->execute([
                $numero,
                isset($body['cliente_id']) ? (int)$body['cliente_id'] : null,
                $titulo,
                $body['detalles']  ?? null,
                json_encode($lineas),
                $subtotal, $iva, $total,
                $validez,
                $body['notas'] ?? null,
                'borrador',
                $userId,
            ]);
            $newId = (int)$db->lastInsertId();
            ok(['id' => $newId, 'numero' => $numero, 'mensaje' => "Presupuesto $numero creado correctamente"]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // FACTURAS
        // ══════════════════════════════════════════════════════════════════════

        case 'facturas': {
            $estado = $_GET['estado'] ?? '';
            $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND f.usuario_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado) { $where[] = 'f.estado = ?'; $params[] = $estado; }

            $sql = "SELECT f.id, f.numero, f.concepto, f.total, f.estado,
                           c.nombre as cliente_nombre,
                           DATE_FORMAT(f.fecha_emision,'%d/%m/%Y') as fecha,
                           DATE_FORMAT(f.fecha_vencimiento,'%d/%m/%Y') as vence
                    FROM facturas f
                    LEFT JOIN clientes c ON f.cliente_id = c.id
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY f.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['facturas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'factura': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            $af = $isAdmin ? '' : " AND f.usuario_id = $userId";
            $stmt = $db->prepare(
                "SELECT f.*, c.nombre as cliente_nombre, c.email as cliente_email, c.dni_nie_cif
                 FROM facturas f
                 LEFT JOIN clientes c ON f.cliente_id = c.id
                 WHERE f.id = ? $af LIMIT 1"
            );
            $stmt->execute([$id]);
            $f = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$f) { err('Factura no encontrada', 404); break; }
            if (!empty($f['lineas'])) $f['lineas'] = json_decode($f['lineas'], true) ?? [];
            ok(['factura' => $f]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // CONTRATOS
        // ══════════════════════════════════════════════════════════════════════

        case 'contratos': {
            $estado = $_GET['estado'] ?? '';
            $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND ct.usuario_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado) { $where[] = 'ct.estado = ?'; $params[] = $estado; }

            $sql = "SELECT ct.id, ct.titulo, ct.estado, ct.firmante_nombre,
                           c.nombre as cliente_nombre,
                           DATE_FORMAT(ct.created_at,'%d/%m/%Y') as creado,
                           DATE_FORMAT(ct.firmado_at,'%d/%m/%Y') as firmado
                    FROM contratos ct
                    LEFT JOIN clientes c ON ct.cliente_id = c.id
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY ct.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['contratos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'contrato': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            $af = $isAdmin ? '' : " AND ct.usuario_id = $userId";
            $stmt = $db->prepare(
                "SELECT ct.id, ct.titulo, ct.estado, ct.firmante_nombre,
                        ct.firmado_at, ct.firmado_ip,
                        DATE_FORMAT(ct.fecha_expiracion,'%d/%m/%Y') as expira,
                        c.nombre as cliente_nombre, c.email as cliente_email,
                        p.titulo as propiedad_titulo
                 FROM contratos ct
                 LEFT JOIN clientes c   ON ct.cliente_id   = c.id
                 LEFT JOIN propiedades p ON ct.propiedad_id = p.id
                 WHERE ct.id = ? $af LIMIT 1"
            );
            $stmt->execute([$id]);
            $ct = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ct) { err('Contrato no encontrado', 404); break; }
            ok(['contrato' => $ct]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // VISITAS
        // ══════════════════════════════════════════════════════════════════════

        case 'visitas': {
            $estado  = $_GET['estado'] ?? '';
            $soloHoy = ($_GET['solo_hoy'] ?? '') === '1';
            $limit   = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $af      = $isAdmin ? '' : " AND v.agente_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado)  { $where[] = 'v.estado = ?';                 $params[] = $estado; }
            if ($soloHoy) { $where[] = 'v.fecha = CURDATE()'; }

            $sql = "SELECT v.id, v.estado,
                           CONCAT(DATE_FORMAT(v.fecha,'%d/%m/%Y'),' ',COALESCE(TIME_FORMAT(v.hora,'%H:%i'),'')) as fecha,
                           v.duracion_minutos, v.comentarios,
                           p.titulo as propiedad, p.localidad,
                           c.nombre as contacto
                    FROM visitas v
                    LEFT JOIN propiedades p ON v.propiedad_id = p.id
                    LEFT JOIN clientes c    ON v.cliente_id   = c.id
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY v.fecha ASC, v.hora ASC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['visitas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'crear_visita': {
            $propId = (int)($body['propiedad_id'] ?? 0);
            $fecha  = trim($body['fecha'] ?? '');
            if (!$propId || !$fecha) { err('propiedad_id y fecha son obligatorios'); break; }

            // Separar fecha y hora (acepta "YYYY-MM-DD HH:MM" o "YYYY-MM-DD")
            $ts    = strtotime($fecha);
            if (!$ts) { err('Formato de fecha inválido. Usa YYYY-MM-DD HH:MM'); break; }
            $fechaDate = date('Y-m-d', $ts);
            $fechaHora = date('H:i:s', $ts);

            $ins = $db->prepare(
                "INSERT INTO visitas (propiedad_id, cliente_id, fecha, hora, duracion_minutos, comentarios, estado, agente_id)
                 VALUES (?,?,?,?,?,?,'programada',?)"
            );
            $ins->execute([
                $propId,
                isset($body['cliente_id']) ? (int)$body['cliente_id'] : null,
                $fechaDate,
                $fechaHora,
                (int)($body['duracion_min'] ?? 60),
                $body['notas'] ?? null,
                $userId,
            ]);
            $newId = (int)$db->lastInsertId();

            // Crear tarea recordatorio automática
            $tituloTarea = 'Visita programada - ' . date('d/m/Y H:i', $ts);
            $db->prepare("INSERT INTO tareas (titulo, tipo, estado, fecha_vencimiento, asignado_a, creado_por, propiedad_id) VALUES (?,?,?,?,?,?,?)")
               ->execute([$tituloTarea, 'visita', 'pendiente', $fechaDt, $userId, $userId, $propId]);

            ok(['id' => $newId, 'mensaje' => "Visita programada para el $fecha"]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // AUTOMATIZACIONES
        // ══════════════════════════════════════════════════════════════════════

        case 'automatizaciones': {
            $af = $isAdmin ? '' : " AND created_by = $userId";
            $stmt = $db->query(
                "SELECT id, nombre, trigger_tipo, activo, ejecuciones,
                        DATE_FORMAT(ultima_ejecucion,'%d/%m/%Y %H:%i') as ultima_ejecucion
                 FROM automatizaciones WHERE activo = 1 $af ORDER BY nombre ASC"
            );
            ok(['automatizaciones' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'iniciar_automatizacion': {
            $autoId      = (int)($body['automatizacion_id'] ?? 0);
            $prospectoId = (int)($body['prospecto_id'] ?? 0);
            if (!$autoId || !$prospectoId) { err('automatizacion_id y prospecto_id son obligatorios'); break; }

            // Verificar que la automatización existe y tiene acceso
            $af = $isAdmin ? '' : " AND created_by = $userId";
            $stmt = $db->prepare("SELECT * FROM automatizaciones WHERE id = ? AND activo = 1 $af LIMIT 1");
            $stmt->execute([$autoId]);
            if (!$stmt->fetch()) { err('Automatización no encontrada o sin acceso', 403); break; }

            // Obtener datos del prospecto para contexto
            $stmtP = $db->prepare("SELECT agente_id FROM prospectos WHERE id = ? LIMIT 1");
            $stmtP->execute([$prospectoId]);
            $prospecto = $stmtP->fetch(PDO::FETCH_ASSOC);

            require_once __DIR__ . '/../includes/automatizaciones_engine.php';
            $resultado = automatizacionesEjecutarTrigger('manual_ia', [
                'entidad_tipo'   => 'prospecto',
                'entidad_id'     => $prospectoId,
                'prospecto_id'   => $prospectoId,
                'agente_id'      => $prospecto['agente_id'] ?? $userId,
                'actor_user_id'  => $userId,
                'owner_user_id'  => $userId,
            ]);

            ok(['resultado' => $resultado, 'mensaje' => 'Automatización iniciada']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // COMUNICACIÓN
        // ══════════════════════════════════════════════════════════════════════

        case 'enviar_whatsapp': {
            $prospectoId = (int)($body['prospecto_id'] ?? 0);
            $mensaje     = trim($body['mensaje'] ?? '');
            if (!$prospectoId || !$mensaje) { err('prospecto_id y mensaje son obligatorios'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId);
            $stmt = $db->prepare("SELECT * FROM prospectos WHERE id = ? $af LIMIT 1");
            $stmt->execute(array_merge([$prospectoId], $ap));
            $prospecto = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$prospecto) { err('Prospecto no encontrado o sin acceso', 403); break; }

            $telefono = preg_replace('/[^0-9]/', '', $prospecto['telefono'] ?? '');
            if (!$telefono) { err('El prospecto no tiene teléfono registrado'); break; }

            // Obtener configuración Twilio
            $twCfg = null;
            $sid   = getenv('TWILIO_ACCOUNT_SID') ?: '';
            $token = getenv('TWILIO_AUTH_TOKEN')  ?: '';
            $from  = getenv('TWILIO_WHATSAPP_FROM') ?: '';
            if (!$sid || !$token || !$from) {
                $stmtCfg = $db->query("SELECT account_sid, auth_token, phone_number FROM twilio_config WHERE activo = 1 ORDER BY id DESC LIMIT 1");
                $twCfg   = $stmtCfg ? $stmtCfg->fetch(PDO::FETCH_ASSOC) : null;
                if ($twCfg) { $sid = $sid ?: $twCfg['account_sid']; $token = $token ?: $twCfg['auth_token']; $from = $from ?: $twCfg['phone_number']; }
            }
            if (!$sid || !$token || !$from) { err('WhatsApp (Twilio) no configurado en el CRM'); break; }

            if (strpos($from, 'whatsapp:') !== 0) $from = 'whatsapp:' . $from;
            $to  = 'whatsapp:+' . $telefono;
            $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => $from, 'Body' => $mensaje]),
                CURLOPT_USERPWD => "$sid:$token", CURLOPT_TIMEOUT => 20,
            ]);
            $resp     = json_decode(curl_exec($ch), true);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300 && !empty($resp['sid'])) {
                // Registrar en historial
                $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?,?,?,?)")
                   ->execute([$prospectoId, $userId, "WhatsApp enviado: $mensaje", 'whatsapp']);
                ok(['mensaje' => 'WhatsApp enviado correctamente', 'sid' => $resp['sid']]);
            } else {
                err('Error al enviar WhatsApp: ' . ($resp['message'] ?? "HTTP $httpCode"));
            }
            break;
        }

        case 'enviar_email': {
            $prospectoId = (int)($body['prospecto_id'] ?? 0);
            $asunto      = trim($body['asunto'] ?? '');
            $cuerpo      = trim($body['cuerpo_html'] ?? '');
            if (!$prospectoId || !$asunto || !$cuerpo) { err('prospecto_id, asunto y cuerpo_html son obligatorios'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId);
            $stmt = $db->prepare("SELECT * FROM prospectos WHERE id = ? $af LIMIT 1");
            $stmt->execute(array_merge([$prospectoId], $ap));
            $prospecto = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$prospecto) { err('Prospecto no encontrado o sin acceso', 403); break; }

            $emailDest = trim($prospecto['email'] ?? '');
            if (!$emailDest) { err('El prospecto no tiene email registrado'); break; }

            $enviado = enviarEmail($emailDest, $asunto, $cuerpo, true, $userId);
            if ($enviado) {
                $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?,?,?,?)")
                   ->execute([$prospectoId, $userId, "Email enviado: $asunto", 'email']);
                ok(['mensaje' => "Email enviado correctamente a $emailDest"]);
            } else {
                err('Error al enviar email: ' . (getLastEmailError() ?? 'Error desconocido'));
            }
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // CAMPAÑAS
        // ══════════════════════════════════════════════════════════════════════

        case 'campanas': {
            $estado = $_GET['estado'] ?? '';
            $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND ca.usuario_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado) { $where[] = 'ca.estado = ?'; $params[] = $estado; }

            $sql = "SELECT ca.id, ca.nombre, ca.tipo, ca.estado,
                           ca.total_contactos, ca.enviados, ca.abiertos, ca.clicks,
                           DATE_FORMAT(ca.created_at,'%d/%m/%Y') as creada
                    FROM campanas ca
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY ca.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['campanas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        // ── DEBUG temporal: solo accesible con token válido ────────────────────
        case 'schema': {
            $tabla = preg_replace('/[^a-z_]/', '', strtolower($_GET['tabla'] ?? ''));
            if (!$tabla) { err('Parámetro tabla requerido'); break; }
            $stmt = $db->prepare("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
            $stmt->execute([$tabla]);
            ok(['tabla' => $tabla, 'columnas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        default:
            err("Acción desconocida: '$action'");
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
