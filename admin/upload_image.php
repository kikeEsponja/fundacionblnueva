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
if (!in_array($hash, $allowed, true)) { $hash = '#subir-medios'; }
$imgDir = realpath(__DIR__ . '/../recursos/imagenes/galeria');
if (!$imgDir) { http_response_code(500); exit; }
$maxSize = 8 * 1024 * 1024; // 8MB
$allowedMimes = ['image/jpeg','image/png','image/webp'];
$alt = trim($_POST['alt'] ?? '');
$size = (int)($_FILES['image']['size'] ?? 0);
if ($size <= 0 || $size > $maxSize) { header('Location: index.php'.$hash); exit; }
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) { header('Location: index.php'.$hash); exit; }
$tmp = $_FILES['image']['tmp_name'];
$info = getimagesize($tmp);
if ($info === false) { header('Location: index.php'.$hash); exit; }
// MIME detect
$mime = $info['mime'] ?? '';
if (!in_array($mime, $allowedMimes, true)) { header('Location: index.php'.$hash); exit; }
$ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
$basename = 'img_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)),0,8);
$origName = $basename . '.' . $ext;
$dest = $imgDir . DIRECTORY_SEPARATOR . $origName;
move_uploaded_file($tmp, $dest);
function makeImg($srcPath, $maxW, $outPath, $type) {
  $info = getimagesize($srcPath);
  $w = $info[0]; $h = $info[1];
  $ratio = $w > $maxW ? $maxW / $w : 1;
  $nw = max(1, (int)($w * $ratio));
  $nh = max(1, (int)($h * $ratio));
  $src = null;
  switch ($info['mime']) {
    case 'image/jpeg':
      if (function_exists('imagecreatefromjpeg')) { $src = imagecreatefromjpeg($srcPath); }
      break;
    case 'image/png':
      if (function_exists('imagecreatefrompng')) { $src = imagecreatefrompng($srcPath); }
      break;
    case 'image/webp':
      if (function_exists('imagecreatefromwebp')) { $src = imagecreatefromwebp($srcPath); }
      break;
  }
  if (!$src) return false;
  $dst = imagecreatetruecolor($nw, $nh);
  imagecopyresampled($dst, $src, 0,0,0,0, $nw,$nh, $w,$h);
  $ok = false;
  if ($type === 'webp' && function_exists('imagewebp')) { $ok = imagewebp($dst, $outPath, 80); }
  elseif ($type === 'jpg' && function_exists('imagejpeg')) { $ok = imagejpeg($dst, $outPath, 85); }
  elseif ($type === 'png' && function_exists('imagepng')) { $ok = imagepng($dst, $outPath, 7); }
  return $ok;
}
$webpName = $basename . '.webp';
$thumbName = $basename . '_thumb.jpg';
$thumbWebpName = $basename . '_thumb.webp';
$webpPath = $imgDir . DIRECTORY_SEPARATOR . $webpName;
$thumbPath = $imgDir . DIRECTORY_SEPARATOR . $thumbName;
$thumbWebpPath = $imgDir . DIRECTORY_SEPARATOR . $thumbWebpName;
$madeWebp = false;
if (function_exists('imagewebp')) {
  $madeWebp = makeImg($dest, 1600, $webpPath, 'webp');
}
@makeImg($dest, 480, $thumbPath, 'jpg');
if (function_exists('imagewebp')) {
  @makeImg($dest, 480, $thumbWebpPath, 'webp');
}
$relBase = '../imagenes/galeria/';
$featured = !empty($_POST['featured']) ? 1 : 0;
$src = $relBase . $origName;
$srcWebp = $madeWebp ? ($relBase . $webpName) : null;
$thumb = file_exists($thumbPath) ? ($relBase . $thumbName) : $src;
$thumbWebp = (function_exists('imagewebp') && file_exists($thumbWebpPath)) ? ($relBase . $thumbWebpName) : null;
$stmt = $pdo->prepare("INSERT INTO gallery_images (src, src_webp, thumb, thumb_webp, alt, featured, date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
$stmt->execute([$src, $srcWebp, $thumb, $thumbWebp, $alt, $featured]);
// regenerate gallery_images.json for public gallery
try {
  $rows = $pdo->query("SELECT src, src_webp, thumb, thumb_webp, alt, featured, date FROM gallery_images")->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'src' => $r['src'],
      'srcWebp' => $r['src_webp'] ?: '',
      'thumb' => $r['thumb'] ?: '',
      'thumbWebp' => $r['thumb_webp'] ?: '',
      'alt' => $r['alt'] ?: '',
      'featured' => $r['featured'] ? true : false,
      'date' => date('c', strtotime($r['date']))
    ];
  }
  $jsonPath = realpath(__DIR__ . '/../recursos/data') ?: (__DIR__ . '/../recursos/data');
  @file_put_contents($jsonPath . DIRECTORY_SEPARATOR . 'gallery_images.json', json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
} catch (Throwable $e) {}
// flash message
$_SESSION['flash'] = ['type'=>'success','text'=>'Imagen subida'];
header('Location: index.php'.$hash);
