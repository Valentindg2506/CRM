<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$codigo = trim($_GET['c'] ?? '');
if (!$codigo) { header('Location: /'); exit; }

$af = $db->prepare("SELECT * FROM afiliados WHERE codigo=? AND activo=1"); $af->execute([$codigo]); $af=$af->fetch();
if (!$af) { header('Location: /'); exit; }

// Track referral
$db->prepare("INSERT INTO afiliado_referidos (afiliado_id, ip) VALUES (?,?)")->execute([$af['id'], $_SERVER['REMOTE_ADDR']]);
$db->prepare("UPDATE afiliados SET total_referidos=total_referidos+1 WHERE id=?")->execute([$af['id']]);

// Set cookie
setcookie('ref_code', $codigo, time()+86400*30, '/');

// Redirect to homepage or landing
header('Location: ' . (defined('APP_URL') ? APP_URL : '/'));
