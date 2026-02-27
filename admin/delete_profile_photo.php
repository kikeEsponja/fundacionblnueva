<?php
require __DIR__ . '/auth.php';
requireLogin();
require __DIR__ . '/db.php';
$tok = $_POST['csrf'] ?? '';
if (!$tok || !hash_equals($_SESSION['csrf'] ?? '', $tok)) { http_response_code(403); exit; }
$hash = $_POST['return_hash'] ?? '';
$allowed = ['#subir-medios','#gestionar-imagenes','#gestionar-videos','#gestionar-publicaciones','#nueva-publicacion','#preferencias','#perfil'];
if (!in_array($hash, $allowed, true)) { $hash = '#perfil'; }
$row = $pdo->query("SELECT profile_photo FROM admin_config WHERE id=1")->fetch();
if ($row && !empty($row['profile_photo'])) {
  $rel = $row['profile_photo'];
  $imgRoot = realpath(__DIR__ . '/../recursos/imagenes/galeria') ?: (__DIR__ . '/../recursos/imagenes/galeria');
  if (strpos($rel, '../imagenes/galeria/') === 0) {
    $abs = realpath($imgRoot . DIRECTORY_SEPARATOR . basename($rel));
    if ($abs && is_file($abs)) @unlink($abs);
  }
  $pdo->prepare("UPDATE admin_config SET profile_photo = NULL WHERE id=1")->execute();
}
$_SESSION['flash'] = ['type'=>'success','text'=>'Foto de perfil eliminada'];
header('Location: index.php'.$hash);
exit;
