<?php
/**
 * Configuracion de Base de Datos
 * CRM Inmobiliario - España
 */

// Configuracion de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'crm_inmobiliario');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuracion de la aplicacion
define('APP_NAME', 'InmoCRM España');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/CRM');
define('APP_TIMEZONE', 'Europe/Madrid');

// Configuracion de uploads
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Configuracion de email
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@tudominio.com');
define('SMTP_FROM_NAME', 'InmoCRM España');

// Configuracion de sesion
define('SESSION_LIFETIME', 3600 * 8); // 8 horas

// Zona horaria
date_default_timezone_set(APP_TIMEZONE);

// Conexion PDO
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die('Error de conexion a la base de datos: ' . $e->getMessage());
        }
    }
    return $pdo;
}
