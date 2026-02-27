<?php
// Detectar HTTPS con soporte para proxy/CDN
$forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
$forwardedSsl = strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
  || ($forwardedProto === 'https')
  || ($forwardedSsl === 'on');
@ini_set('session.use_strict_mode', '1');
@ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) {
  session_name('FUNDACIONSESSID');
}
if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
}
session_start();
if (empty($_SESSION['csrf'])) {
  try {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  } catch (Exception $e) {
    $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
  }
}
require __DIR__ . '/db.php';
$stmt = $pdo->query("SELECT password_hash FROM admin_config WHERE id=1");
$row = $stmt->fetch();
$hash = $row ? ($row['password_hash'] ?? '') : '';
function isLoggedIn() {
  if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) return false;
  $maxIdle = 1800; // 30 minutos
  $now = time();
  if (!isset($_SESSION['admin_last']) || $now - (int)$_SESSION['admin_last'] > $maxIdle) {
    session_unset();
    session_destroy();
    return false;
  }
  $_SESSION['admin_last'] = $now;
  $currIp = $_SERVER['REMOTE_ADDR'] ?? '';
  $currUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
  if (isset($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== $currIp) return false;
  if (isset($_SESSION['admin_ua']) && $_SESSION['admin_ua'] !== substr($currUa, 0, 200)) return false;
  return true;
}
function requireLogin() {
  if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
  }
}
