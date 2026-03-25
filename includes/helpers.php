<?php
/**
 * Funciones auxiliares del CRM
 */

/**
 * Sanitizar entrada de texto
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Obtener valor POST sanitizado
 */
function post($key, $default = '') {
    return isset($_POST[$key]) ? sanitize($_POST[$key]) : $default;
}

/**
 * Obtener valor GET sanitizado
 */
function get($key, $default = '') {
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
}

/**
 * Formatear precio en euros
 */
function formatPrecio($precio) {
    return number_format($precio, 2, ',', '.') . ' &euro;';
}

/**
 * Formatear fecha a formato español
 */
function formatFecha($fecha) {
    if (empty($fecha)) return '-';
    $dt = new DateTime($fecha);
    return $dt->format('d/m/Y');
}

/**
 * Formatear fecha y hora
 */
function formatFechaHora($fecha) {
    if (empty($fecha)) return '-';
    $dt = new DateTime($fecha);
    return $dt->format('d/m/Y H:i');
}

/**
 * Formatear superficie
 */
function formatSuperficie($metros) {
    if (empty($metros)) return '-';
    return number_format($metros, 2, ',', '.') . ' m&sup2;';
}

/**
 * Generar referencia unica para propiedad
 */
function generarReferencia() {
    $db = getDB();
    do {
        $ref = 'INM-' . strtoupper(substr(uniqid(), -6));
        $stmt = $db->prepare("SELECT COUNT(*) FROM propiedades WHERE referencia = ?");
        $stmt->execute([$ref]);
    } while ($stmt->fetchColumn() > 0);
    return $ref;
}

/**
 * Subir imagen
 */
function uploadImage($file, $dir = 'propiedades') {
    $uploadDir = UPLOAD_DIR . $dir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Error al subir el archivo'];
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['error' => 'El archivo excede el tamano maximo permitido (10MB)'];
    }

    if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
        return ['error' => 'Tipo de archivo no permitido. Solo JPG, PNG y WebP'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '_' . time() . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['success' => true, 'filename' => $dir . '/' . $filename];
    }

    return ['error' => 'No se pudo guardar el archivo'];
}

/**
 * Subir documento
 */
function uploadDocument($file) {
    $uploadDir = UPLOAD_DIR . 'documentos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Error al subir el archivo'];
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['error' => 'El archivo excede el tamano maximo'];
    }

    $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOC_TYPES);
    if (!in_array($file['type'], $allowedTypes)) {
        return ['error' => 'Tipo de archivo no permitido'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('doc_') . '_' . time() . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['success' => true, 'filename' => 'documentos/' . $filename, 'size' => $file['size']];
    }

    return ['error' => 'No se pudo guardar el archivo'];
}

/**
 * Eliminar archivo subido
 */
function deleteUpload($filename) {
    $filepath = UPLOAD_DIR . $filename;
    if (file_exists($filepath)) {
        unlink($filepath);
    }
}

/**
 * Paginacion
 */
function paginate($total, $perPage = 20, $currentPage = 1) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

/**
 * Renderizar paginacion HTML
 */
function renderPagination($pagination, $baseUrl) {
    if ($pagination['total_pages'] <= 1) return '';

    $html = '<nav><ul class="pagination justify-content-center">';

    // Anterior
    if ($pagination['current_page'] > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($pagination['current_page'] - 1) . '">&laquo;</a></li>';
    }

    // Paginas
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
    }

    // Siguiente
    if ($pagination['current_page'] < $pagination['total_pages']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($pagination['current_page'] + 1) . '">&raquo;</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Mensaje flash (session-based)
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Token CSRF
 */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Token de seguridad invalido. Recarga la pagina e intenta de nuevo.');
    }
}

/**
 * Provincias de España
 */
function getProvincias() {
    return [
        'A Coruña', 'Alava', 'Albacete', 'Alicante', 'Almeria', 'Asturias', 'Avila',
        'Badajoz', 'Barcelona', 'Bizkaia', 'Burgos', 'Caceres', 'Cadiz', 'Cantabria',
        'Castellon', 'Ciudad Real', 'Cordoba', 'Cuenca', 'Gipuzkoa', 'Girona', 'Granada',
        'Guadalajara', 'Huelva', 'Huesca', 'Illes Balears', 'Jaen', 'La Rioja',
        'Las Palmas', 'Leon', 'Lleida', 'Lugo', 'Madrid', 'Malaga', 'Murcia', 'Navarra',
        'Ourense', 'Palencia', 'Pontevedra', 'Salamanca', 'Santa Cruz de Tenerife',
        'Segovia', 'Sevilla', 'Soria', 'Tarragona', 'Teruel', 'Toledo', 'Valencia',
        'Valladolid', 'Zamora', 'Zaragoza', 'Ceuta', 'Melilla'
    ];
}

/**
 * Tipos de propiedad con etiquetas
 */
function getTiposPropiedad() {
    return [
        'piso' => 'Piso', 'casa' => 'Casa', 'chalet' => 'Chalet',
        'adosado' => 'Adosado', 'atico' => 'Atico', 'duplex' => 'Duplex',
        'estudio' => 'Estudio', 'local' => 'Local comercial', 'oficina' => 'Oficina',
        'nave' => 'Nave industrial', 'terreno' => 'Terreno', 'garaje' => 'Garaje',
        'trastero' => 'Trastero', 'edificio' => 'Edificio', 'otro' => 'Otro'
    ];
}

/**
 * Obtener conteo para el dashboard
 */
function getCount($table, $where = '1=1', $params = []) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE $where");
    $stmt->execute($params);
    return $stmt->fetchColumn();
}
