<?php
/**
 * API para Prospectos - Operaciones AJAX
 * Soporta: edición inline, historial CRUD, tareas por día, próximo contacto
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$db = getDB();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Verify CSRF for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }
}

switch ($accion) {

    // ─────────────────────────────────────────────
    // EDICIÓN INLINE DE CAMPO
    // ─────────────────────────────────────────────
    case 'editar_campo':
        $id = intval($_POST['id'] ?? 0);
        $campo = $_POST['campo'] ?? '';
        $valor = $_POST['valor'] ?? '';

        if (!$id || !$campo) {
            echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
            exit;
        }

        // Campos permitidos para edición inline
        $camposPermitidos = [
            'nombre', 'email', 'telefono', 'telefono2', 'etapa', 'estado', 'temperatura',
            'tipo_propiedad', 'operacion', 'direccion', 'numero', 'piso_puerta', 'barrio',
            'localidad', 'provincia', 'comunidad_autonoma', 'codigo_postal',
            'precio_estimado', 'precio_propietario', 'precio_comunidad',
            'superficie', 'superficie_construida', 'superficie_util', 'superficie_parcela',
            'habitaciones', 'banos', 'aseos', 'planta',
            'ascensor', 'garaje_incluido', 'trastero_incluido', 'terraza', 'balcon', 'jardin', 'piscina', 'aire_acondicionado',
            'calefaccion', 'orientacion', 'antiguedad', 'estado_conservacion', 'certificacion_energetica', 'referencia_catastral',
            'enlace', 'descripcion', 'descripcion_interna', 'comision', 'exclusividad', 'notas', 'reformas',
            'fecha_contacto', 'fecha_proximo_contacto', 'agente_id'
        ];

        if (!in_array($campo, $camposPermitidos)) {
            echo json_encode(['success' => false, 'error' => 'Campo no permitido: ' . $campo]);
            exit;
        }

        // Sanitizar y convertir según tipo
        $camposNumericos = ['precio_estimado', 'precio_propietario', 'precio_comunidad', 'superficie', 'superficie_construida', 'superficie_util', 'superficie_parcela', 'habitaciones', 'banos', 'aseos', 'antiguedad', 'comision'];
        $camposBoolean = ['exclusividad', 'ascensor', 'garaje_incluido', 'trastero_incluido', 'terraza', 'balcon', 'jardin', 'piscina', 'aire_acondicionado'];

        if (in_array($campo, $camposNumericos)) {
            $valor = $valor !== '' ? floatval(str_replace(',', '.', $valor)) : null;
        } elseif (in_array($campo, $camposBoolean)) {
            $valor = intval($valor) ? 1 : 0;
        } elseif ($valor === '') {
            $valor = null;
        }

        try {
            $stmt = $db->prepare("UPDATE prospectos SET `$campo` = ? WHERE id = ?");
            $stmt->execute([$valor, $id]);
            registrarActividad('editar_inline', 'prospecto', $id, "Campo: $campo");

            // Devolver valor formateado
            $valorFormateado = $valor;
            if (in_array($campo, ['precio_estimado', 'precio_propietario'])) {
                $valorFormateado = $valor ? number_format($valor, 2, ',', '.') . ' €' : '-';
            } elseif ($campo === 'superficie') {
                $valorFormateado = $valor ? number_format($valor, 2, ',', '.') . ' m²' : '-';
            } elseif ($campo === 'comision') {
                $valorFormateado = $valor ? $valor . '%' : '-';
            } elseif (in_array($campo, ['fecha_contacto', 'fecha_proximo_contacto'])) {
                $valorFormateado = $valor ? date('d/m/Y', strtotime($valor)) : '-';
            }

            echo json_encode(['success' => true, 'valor_formateado' => $valorFormateado]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────
    // HISTORIAL DE CONTACTOS
    // ─────────────────────────────────────────────
    case 'add_historial':
        $prospectoId = intval($_POST['prospecto_id'] ?? 0);
        $contenido = trim($_POST['contenido'] ?? '');
        $tipo = $_POST['tipo'] ?? 'nota';

        if (!$prospectoId || !$contenido) {
            echo json_encode(['success' => false, 'error' => 'Prospecto y contenido son obligatorios']);
            exit;
        }

        $tiposPermitidos = ['llamada', 'email', 'visita', 'nota', 'whatsapp', 'otro'];
        if (!in_array($tipo, $tiposPermitidos)) $tipo = 'nota';

        try {
            $stmt = $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$prospectoId, currentUserId(), $contenido, $tipo]);
            $newId = $db->lastInsertId();

            // Obtener el registro recién creado con datos del usuario
            $stmt2 = $db->prepare("SELECT h.*, u.nombre as usuario_nombre, u.apellidos as usuario_apellidos 
                                   FROM historial_prospectos h 
                                   LEFT JOIN usuarios u ON h.usuario_id = u.id 
                                   WHERE h.id = ?");
            $stmt2->execute([$newId]);
            $entrada = $stmt2->fetch();

            registrarActividad('contacto', 'prospecto', $prospectoId, "[$tipo] $contenido");

            echo json_encode([
                'success' => true,
                'entrada' => [
                    'id' => $entrada['id'],
                    'contenido' => htmlspecialchars($entrada['contenido']),
                    'tipo' => $entrada['tipo'],
                    'usuario' => htmlspecialchars(($entrada['usuario_nombre'] ?? '') . ' ' . ($entrada['usuario_apellidos'] ?? '')),
                    'usuario_iniciales' => strtoupper(mb_substr($entrada['usuario_nombre'] ?? '', 0, 1) . mb_substr($entrada['usuario_apellidos'] ?? '', 0, 1)),
                    'fecha' => date('d/m/Y H:i', strtotime($entrada['created_at'])),
                    'fecha_relativa' => 'Ahora',
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_historial':
        $prospectoId = intval($_GET['prospecto_id'] ?? 0);
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $stmt = $db->prepare("SELECT h.*, u.nombre as usuario_nombre, u.apellidos as usuario_apellidos 
                              FROM historial_prospectos h 
                              LEFT JOIN usuarios u ON h.usuario_id = u.id 
                              WHERE h.prospecto_id = ? 
                              ORDER BY h.created_at DESC 
                              LIMIT $perPage OFFSET $offset");
        $stmt->execute([$prospectoId]);
        $entradas = $stmt->fetchAll();

        $total = $db->prepare("SELECT COUNT(*) FROM historial_prospectos WHERE prospecto_id = ?");
        $total->execute([$prospectoId]);

        $result = [];
        foreach ($entradas as $e) {
            $result[] = [
                'id' => $e['id'],
                'contenido' => htmlspecialchars($e['contenido']),
                'tipo' => $e['tipo'],
                'usuario' => htmlspecialchars(($e['usuario_nombre'] ?? '') . ' ' . ($e['usuario_apellidos'] ?? '')),
                'usuario_iniciales' => strtoupper(mb_substr($e['usuario_nombre'] ?? '', 0, 1) . mb_substr($e['usuario_apellidos'] ?? '', 0, 1)),
                'fecha' => date('d/m/Y H:i', strtotime($e['created_at'])),
            ];
        }

        echo json_encode(['success' => true, 'entradas' => $result, 'total' => $total->fetchColumn()]);
        break;

    case 'delete_historial':
        $entradaId = intval($_POST['entrada_id'] ?? 0);
        if (!$entradaId) {
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            exit;
        }
        try {
            $db->prepare("DELETE FROM historial_prospectos WHERE id = ? AND usuario_id = ?")->execute([$entradaId, currentUserId()]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────
    // TAREAS POR DÍA (mini calendario)
    // ─────────────────────────────────────────────
    case 'tareas_dia':
        $prospectoId = intval($_GET['prospecto_id'] ?? 0);
        $fecha = $_GET['fecha'] ?? date('Y-m-d');

        // Buscar tareas del prospecto para ese día
        // Usamos la relación indirecta via historial o directa via tareas
        $stmt = $db->prepare("SELECT t.id, t.titulo, t.tipo, t.estado, t.prioridad, t.fecha_vencimiento 
                              FROM tareas t 
                              WHERE DATE(t.fecha_vencimiento) = ? 
                              AND (t.asignado_a = ? OR t.creado_por = ?)
                              ORDER BY t.fecha_vencimiento ASC");
        $stmt->execute([$fecha, currentUserId(), currentUserId()]);
        $tareas = $stmt->fetchAll();

        echo json_encode(['success' => true, 'tareas' => $tareas, 'fecha' => $fecha]);
        break;

    // ─────────────────────────────────────────────
    // ACTUALIZAR PRÓXIMO CONTACTO
    // ─────────────────────────────────────────────
    case 'set_proximo_contacto':
        $prospectoId = intval($_POST['prospecto_id'] ?? 0);
        $fecha = $_POST['fecha'] ?? '';

        if (!$prospectoId || !$fecha) {
            echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
            exit;
        }

        try {
            $db->prepare("UPDATE prospectos SET fecha_proximo_contacto = ? WHERE id = ?")
               ->execute([$fecha, $prospectoId]);
            registrarActividad('seguimiento', 'prospecto', $prospectoId, "Próximo contacto: $fecha");
            echo json_encode(['success' => true, 'fecha_formateada' => date('d/m/Y', strtotime($fecha))]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────
    // EDITAR CAMPO PERSONALIZADO
    // ─────────────────────────────────────────────
    case 'editar_custom_field':
        $prospectoId = intval($_POST['prospecto_id'] ?? 0);
        $fieldId = intval($_POST['field_id'] ?? 0);
        $valor = $_POST['valor'] ?? '';

        if (!$prospectoId || !$fieldId) {
            echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
            exit;
        }

        try {
            $stmt = $db->prepare("INSERT INTO custom_field_values (field_id, entidad_id, valor) 
                                  VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmt->execute([$fieldId, $prospectoId, $valor]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no reconocida: ' . $accion]);
        break;
}
