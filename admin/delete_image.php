<?php
require __DIR__ . '/auth.php';
requireLogin();
require __DIR__ . '/db.php';
$tok = $_POST['csrf'] ?? '';
if (!$tok || !hash_equals($_SESSION['csrf'] ?? '', $tok)) { http_response_code(403); exit; }
$hash = $_POST['return_hash'] ?? '';
$allowed = ['#subir-medios','#gestionar-imagenes','#gestionar-videos','#gestionar-publicaciones','#nueva-publicacion','#preferencias','#perfil'];
if (!in_array($hash, $allowed, true)) { $hash = '#gestionar-imagenes'; }
$all = !empty($_POST['delete_all']);
$ids = array_map('intval', $_POST['ids'] ?? []);
$ids = array_values(array_filter($ids, function($v){ return $v > 0; }));
if (!$all && !$ids) { $_SESSION['flash'] = ['type'=>'error','text'=>'Nada para eliminar']; header('Location: index.php'.$hash); exit; }
$imgRoot = realpath(__DIR__ . '/../recursos/imagenes/galeria') ?: (__DIR__ . '/../recursos/imagenes/galeria');
if ($all) {
  $rows = $pdo->query("SELECT src, src_webp, thumb, thumb_webp FROM gallery_images")->fetchAll();
  foreach ($rows as $r) {
    foreach (['src','src_webp','thumb','thumb_webp'] as $k) {
      $p = $r[$k] ?? '';
      if ($p && strpos($p, '../imagenes/galeria/') === 0) {
        $abs = realpath($imgRoot . DIRECTORY_SEPARATOR . basename($p));
        if ($abs && is_file($abs)) @unlink($abs);
      }
    }
  }
  $pdo->exec("TRUNCATE TABLE gallery_images");
} else {
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT src, src_webp, thumb, thumb_webp FROM gallery_images WHERE id IN ($in)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
      foreach (['src','src_webp','thumb','thumb_webp'] as $k) {
        $p = $r[$k] ?? '';
        if ($p && strpos($p, '../imagenes/galeria/') === 0) {
          $abs = realpath($imgRoot . DIRECTORY_SEPARATOR . basename($p));
          if ($abs && is_file($abs)) @unlink($abs);
        }
      }
    }
    $stmt = $pdo->prepare("DELETE FROM gallery_images WHERE id IN ($in)");
    $stmt->execute($ids);
  }
}
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
$_SESSION['flash'] = ['type'=>'success','text'=> $all ? 'Todas las imágenes eliminadas' : 'Imágenes eliminadas'];
header('Location: index.php'.$hash);
exit;
