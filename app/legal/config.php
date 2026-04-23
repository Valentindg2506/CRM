<?php
/**
 * Configuración central de datos legales.
 * Lee las variables LEGAL_* del .env (cargado en config/database.php).
 * Todas las páginas legales incluyen este archivo.
 */

// Asegurarse de que el .env está cargado
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/database.php';
}

/**
 * Retorna una variable legal del entorno.
 * Si no está definida, devuelve un span HTML resaltado con el nombre del campo.
 */
function legalVar(string $key, string $fallbackLabel = ''): string {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return htmlspecialchars($value);
    }
    $label = $fallbackLabel ?: str_replace('LEGAL_EMPRESA_', '', $key);
    return '<span class="placeholder">[' . htmlspecialchars($label) . ']</span>';
}

/**
 * Retorna una variable legal como texto plano (para uso en markdown/texto).
 * Devuelve el marcador sin HTML si no está definida.
 */
function legalVarPlain(string $key, string $fallbackLabel = ''): string {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    $label = $fallbackLabel ?: str_replace('LEGAL_EMPRESA_', '', $key);
    return '[' . $label . ']';
}

// ─── Variables accesibles como constantes ────────────────────────────────────
define('LEGAL_NOMBRE',         getenv('LEGAL_EMPRESA_NOMBRE')           ?: '');
define('LEGAL_CIF',            getenv('LEGAL_EMPRESA_CIF')              ?: '');
define('LEGAL_DIRECCION',      getenv('LEGAL_EMPRESA_DIRECCION')        ?: '');
define('LEGAL_CIUDAD',         getenv('LEGAL_EMPRESA_CIUDAD')           ?: '');
define('LEGAL_CP',             getenv('LEGAL_EMPRESA_CP')               ?: '');
define('LEGAL_EMAIL',          getenv('LEGAL_EMPRESA_EMAIL_PRIVACIDAD') ?: 'privacidad@tinoprop.es');
define('LEGAL_TELEFONO',       getenv('LEGAL_EMPRESA_TELEFONO')         ?: '');
define('LEGAL_REGISTRO',       getenv('LEGAL_EMPRESA_REGISTRO_MERCANTIL') ?: '');
define('LEGAL_URL_PRECIOS',    getenv('LEGAL_URL_PRECIOS')              ?: (APP_URL . '/precios'));

// Dirección completa formateada
define('LEGAL_DIRECCION_COMPLETA', implode(', ', array_filter([
    LEGAL_DIRECCION,
    LEGAL_CP && LEGAL_CIUDAD ? LEGAL_CP . ' ' . LEGAL_CIUDAD : LEGAL_CIUDAD,
    'España',
])));

/**
 * ¿Están rellenos los datos mínimos obligatorios?
 * Útil para mostrar advertencia en las páginas legales.
 */
function legalDatosCompletos(): bool {
    return LEGAL_NOMBRE !== '' && LEGAL_CIF !== '' && LEGAL_DIRECCION !== '';
}
