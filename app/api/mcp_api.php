<?php
/**
 * API REST para el conector MCP del CRM
 * Autenticación: Bearer token generado en Ajustes → Conector Claude (MCP)
 * El token se almacena en usuario_ajustes con clave = 'mcp_token'
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Autenticar por Bearer token ────────────────────────────────────────────
function getMcpUser(PDO $db): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        return null;
    }
    $token = trim($m[1]);
    if (strlen($token) < 32) return null;

    $stmt = $db->prepare(
        "SELECT u.* FROM usuarios u
         JOIN usuario_ajustes ua ON ua.usuario_id = u.id
         WHERE ua.clave = 'mcp_token' AND ua.valor = ? AND u.activo = 1
         LIMIT 1"
    );
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$db = getDB();
$user = getMcpUser($db);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Token MCP inválido o expirado. Genera uno nuevo en Ajustes → Conector Claude (MCP).']);
    exit;
}

$isAdmin = ($user['rol'] ?? '') === 'admin';
$userId  = (int)$user['id'];
$action  = $_GET['action'] ?? '';

$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
}

// ── Helpers ────────────────────────────────────────────────────────────────
function agentFilter(bool $isAdmin, int $userId, string $alias = 'p'): array {
    if ($isAdmin) return ['', []];
    return [" AND {$alias}.agente_id = ?", [$userId]];
}

function ok($data): void {
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
}

function err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
}

// ── Acciones ───────────────────────────────────────────────────────────────
try {
    switch ($action) {

        // ── RESUMEN DASHBOARD ──────────────────────────────────────────────
        case 'resumen': {
            $af = $isAdmin ? '' : " AND agente_id = $userId";
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

            ok([
                'resumen' => [
                    'prospectos_activos_pipeline' => $prospectosPipeline,
                    'prospectos_por_etapa'        => $porEtapa,
                    'total_clientes'              => $totalClientes,
                    'tareas_pendientes_hoy'       => $tareasPendientesHoy,
                    'prospectos_a_contactar_hoy'  => $contactosHoy,
                ]
            ]);
            break;
        }

        // ── LISTAR PROSPECTOS ──────────────────────────────────────────────
        case 'prospectos': {
            $q            = '%' . ($_GET['q'] ?? '') . '%';
            $etapa        = $_GET['etapa'] ?? '';
            $temp         = $_GET['temperatura'] ?? '';
            $contactarHoy = ($_GET['contactar_hoy'] ?? '') === '1';
            $limit        = min(100, max(1, (int)($_GET['limit'] ?? 20)));

            $where  = ['p.activo = 1'];
            $params = [];

            if (!$isAdmin) { $where[] = 'p.agente_id = ?'; $params[] = $userId; }
            if ($_GET['q'] ?? '') {
                $where[]  = '(p.nombre LIKE ? OR p.telefono LIKE ? OR p.email LIKE ?)';
                $params   = array_merge($params, [$q, $q, $q]);
            }
            if ($etapa)        { $where[] = 'p.etapa = ?';         $params[] = $etapa; }
            if ($temp)         { $where[] = 'p.temperatura = ?';   $params[] = $temp; }
            if ($contactarHoy) { $where[] = 'p.fecha_proximo_contacto = CURDATE()'; }

            $sql  = "SELECT p.id, p.referencia, p.nombre, p.telefono, p.email,
                            p.etapa, p.temperatura, p.tipo_propiedad,
                            p.precio_estimado, p.localidad, p.provincia,
                            p.fecha_proximo_contacto, p.proxima_accion
                     FROM prospectos p
                     WHERE " . implode(' AND ', $where) .
                    " ORDER BY p.fecha_proximo_contacto ASC, p.created_at DESC LIMIT $limit";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ok(['total' => count($rows), 'prospectos' => $rows]);
            break;
        }

        // ── VER PROSPECTO ──────────────────────────────────────────────────
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

            // Historial reciente (5 entradas)
            $h = $db->prepare(
                "SELECT tipo, contenido, DATE_FORMAT(COALESCE(fecha_evento,created_at),'%d/%m/%Y %H:%i') as fecha
                 FROM historial_prospectos WHERE prospecto_id = ?
                 ORDER BY COALESCE(fecha_evento,created_at) DESC LIMIT 5"
            );
            $h->execute([$id]);
            $p['historial_reciente'] = $h->fetchAll(PDO::FETCH_ASSOC);

            // Limpiar campo JSON interno
            if (!empty($p['propietarios_json'])) {
                $p['copropietarios'] = json_decode($p['propietarios_json'], true) ?? [];
            }
            unset($p['propietarios_json']);

            ok(['prospecto' => $p]);
            break;
        }

        // ── CREAR PROSPECTO ────────────────────────────────────────────────
        case 'crear_prospecto': {
            $nombre = trim($body['nombre'] ?? '');
            if (!$nombre) { err('El nombre es obligatorio'); break; }

            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(referencia,3) AS UNSIGNED)) FROM prospectos WHERE referencia LIKE 'PR%'");
            $max  = (int)$stmt->fetchColumn();
            $ref  = 'PR' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);

            $ins = $db->prepare(
                "INSERT INTO prospectos
                 (referencia, nombre, telefono, email, tipo_propiedad, precio_estimado,
                  localidad, provincia, notas, etapa, temperatura, agente_id, activo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)"
            );
            $ins->execute([
                $ref,
                $nombre,
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
            ok(['id' => (int)$db->lastInsertId(), 'referencia' => $ref, 'mensaje' => "Prospecto $ref creado correctamente"]);
            break;
        }

        // ── AÑADIR NOTA / HISTORIAL ────────────────────────────────────────
        case 'anadir_nota': {
            $pid      = (int)($body['prospecto_id'] ?? 0);
            $contenido = trim($body['contenido'] ?? '');
            $tiposOk  = ['nota','llamada','email','visita','whatsapp','otro'];
            $tipo     = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'nota';

            if (!$pid || !$contenido) { err('prospecto_id y contenido son obligatorios'); break; }

            // Verificar acceso
            [$af, $ap] = agentFilter($isAdmin, $userId);
            $chk = $db->prepare("SELECT id FROM prospectos WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$pid], $ap));
            if (!$chk->fetch()) { err('Prospecto no encontrado o sin acceso', 403); break; }

            $stmt = $db->prepare(
                "INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?,?,?,?)"
            );
            $stmt->execute([$pid, $userId, $contenido, $tipo]);
            ok(['mensaje' => 'Nota añadida correctamente', 'id' => (int)$db->lastInsertId()]);
            break;
        }

        // ── LISTAR CLIENTES ────────────────────────────────────────────────
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

        // ── VER CLIENTE ────────────────────────────────────────────────────
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
            ok(['cliente' => $c]);
            break;
        }

        // ── LISTAR TAREAS ──────────────────────────────────────────────────
        case 'tareas': {
            $estado  = $_GET['estado'] ?? '';
            $soloHoy = ($_GET['solo_hoy'] ?? '') === '1';

            $where  = ['(t.asignado_a = ? OR t.creado_por = ?)'];
            $params = [$userId, $userId];
            if ($estado)  { $where[] = 't.estado = ?';                    $params[] = $estado; }
            if ($soloHoy) { $where[] = 'DATE(t.fecha_vencimiento) = CURDATE()'; }

            $sql  = "SELECT t.id, t.titulo, t.tipo, t.estado, t.prioridad,
                            DATE_FORMAT(t.fecha_vencimiento,'%d/%m/%Y %H:%i') as vencimiento
                     FROM tareas t
                     WHERE " . implode(' AND ', $where) .
                    " ORDER BY t.fecha_vencimiento ASC LIMIT 50";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['tareas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        default:
            err("Acción desconocida: $action");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
