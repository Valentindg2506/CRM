<?php
/**
 * Sistema de Autenticacion
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function login($email, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nombre'] = $user['nombre'] . ' ' . $user['apellidos'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_rol'] = $user['rol'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['login_time'] = time();

        // Actualizar ultimo acceso
        $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        registrarActividad('login', 'usuario', $user['id'], 'Inicio de sesion');
        return true;
    }
    return false;
}

function logout() {
    if (isLoggedIn()) {
        registrarActividad('logout', 'usuario', $_SESSION['user_id'], 'Cierre de sesion');
    }
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['login_time'])
        && (time() - $_SESSION['login_time'] < SESSION_LIFETIME);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_rol'] !== 'admin') {
        header('Location: ' . APP_URL . '/index.php?error=permisos');
        exit;
    }
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function currentUserName() {
    return $_SESSION['user_nombre'] ?? '';
}

function currentUserRole() {
    return $_SESSION['user_rol'] ?? '';
}

function isAdmin() {
    return ($_SESSION['user_rol'] ?? '') === 'admin';
}

function registrarActividad($accion, $entidad, $entidad_id = null, $detalles = null) {
    if (!isset($_SESSION['user_id'])) return;
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO actividad_log (usuario_id, accion, entidad, entidad_id, detalles, ip) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $accion,
            $entidad,
            $entidad_id,
            $detalles,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Silenciar errores de log
    }
}
