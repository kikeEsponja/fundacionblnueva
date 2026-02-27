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
$allowed = [ '#subir-medios', '#gestionar-imagenes', '#gestionar-videos', '#gestionar-publicaciones', '#nueva-publicacion', '#preferencias', '#perfil' ];
if (!in_array($hash, $allowed, true)) {
  $hash = '#subir-medios';
}
$vidDirPath = __DIR__ . '/../recursos/videos/galeria';
// Crear el directorio si no existe (necesario en hostings donde la carpeta no fue creada)
if (!is_dir($vidDirPath)) {
  @mkdir($vidDirPath, 0755, true);
}
$vidDir = realpath($vidDirPath);
if (!$vidDir) {
  http_response_code(500);
  exit;
}
if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
  header('Location: index.php' . $hash);
  exit;
}
$maxSize = 50 * 1024 * 1024; // 50MB
$size = (int) ($_FILES['video']['size'] ?? 0);
if ($size <= 0 || $size > $maxSize) {
  header('Location: index.php' . $hash);
  exit;
}
$tmp = $_FILES['video']['tmp_name'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp);
if ($mime !== 'video/mp4') {
  header('Location: index.php' . $hash);
  exit;
}
$basename = 'vid_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.mp4';
$dest = $vidDir . DIRECTORY_SEPARATOR . $basename;
move_uploaded_file($tmp, $dest);
$rel = '../videos/galeria/' . $basename;
$featured = !empty($_POST['featured']) ? 1 : 0;
$stmt = $pdo->prepare("INSERT INTO gallery_videos (src, featured, date) VALUES (?, ?, NOW())");
$stmt->execute([ $rel, $featured ]);
// regenerate gallery_videos.json for public gallery
try {
  $rows = $pdo->query("SELECT src, featured, date FROM gallery_videos")->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'src' => $r['src'],
      'featured' => $r['featured'] ? true : false,
      'date' => date('c', strtotime($r['date']))
    ];
  }
  $jsonPath = realpath(__DIR__ . '/../recursos/data') ?: (__DIR__ . '/../recursos/data');
  @file_put_contents($jsonPath . DIRECTORY_SEPARATOR . 'gallery_videos.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
} catch (Throwable $e) {
}
// flash message
$_SESSION['flash'] = [ 'type' => 'success', 'text' => 'Video subido' ];
header('Location: index.php' . $hash);
