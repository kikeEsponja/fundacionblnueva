<?php
require __DIR__ . '/auth.php';
requireLogin();
require __DIR__ . '/db.php';
$tok = $_POST['csrf'] ?? '';
if (!$tok || !hash_equals($_SESSION['csrf'] ?? '', $tok)) {
  http_response_code(403);
  exit;
}
$hash = $_POST['return_hash'] ?? '';
$allowed = ['#subir-medios','#gestionar-imagenes','#gestionar-videos','#gestionar-publicaciones','#nueva-publicacion','#preferencias','#perfil'];
if (!in_array($hash, $allowed, true)) { $hash = '#perfil'; }
$imgDir = realpath(__DIR__ . '/../recursos/imagenes/galeria');
if (!$imgDir) { http_response_code(500); exit; }
if (!isset($_FILES['profile']) || $_FILES['profile']['error'] !== UPLOAD_ERR_OK) { header('Location: index.php'.$hash); exit; }
$maxSize = 2 * 1024 * 1024; // 2MB
$size = (int)($_FILES['profile']['size'] ?? 0);
if ($size <= 0 || $size > $maxSize) { $_SESSION['flash'] = ['type'=>'error','text'=>'Imagen de perfil muy pesada (máx 2MB)']; header('Location: index.php'.$hash); exit; }
$tmp = $_FILES['profile']['tmp_name'];
$info = getimagesize($tmp);
if ($info === false) { $_SESSION['flash'] = ['type'=>'error','text'=>'Archivo no es imagen']; header('Location: index.php'.$hash); exit; }
$mime = $info['mime'];
if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) { $_SESSION['flash'] = ['type'=>'error','text'=>'Formato no permitido']; header('Location: index.php'.$hash); exit; }
$ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
$basename = 'profile_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)),0,8) . '.' . $ext;
$dest = $imgDir . DIRECTORY_SEPARATOR . $basename;
move_uploaded_file($tmp, $dest);
// guardar ruta relativa
$rel = '../imagenes/galeria/' . $basename;
$stmt = $pdo->prepare("UPDATE admin_config SET profile_photo=? WHERE id=1");
$stmt->execute([$rel]);
$_SESSION['flash'] = ['type'=>'success','text'=>'Foto de perfil actualizada'];
header('Location: index.php'.$hash);
exit;
