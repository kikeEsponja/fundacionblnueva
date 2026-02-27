<?php
require __DIR__ . '/auth.php';
requireLogin();
require __DIR__ . '/db.php';
$tok = $_POST['csrf'] ?? '';
if (!$tok || !hash_equals($_SESSION['csrf'] ?? '', $tok)) { http_response_code(403); exit; }
$hash = $_POST['return_hash'] ?? '';
$allowed = ['#subir-medios','#gestionar-imagenes','#gestionar-videos','#gestionar-publicaciones','#nueva-publicacion','#preferencias','#perfil'];
if (!in_array($hash, $allowed, true)) { $hash = '#gestionar-videos'; }
$all = !empty($_POST['delete_all']);
$ids = array_map('intval', $_POST['ids'] ?? []);
$ids = array_values(array_filter($ids, function($v){ return $v > 0; }));
if (!$all && !$ids) { $_SESSION['flash'] = ['type'=>'error','text'=>'Nada para eliminar']; header('Location: index.php'.$hash); exit; }
$vidRoot = realpath(__DIR__ . '/../recursos/videos/galeria') ?: (__DIR__ . '/../recursos/videos/galeria');
if ($all) {
  $rows = $pdo->query("SELECT src FROM gallery_videos")->fetchAll();
  foreach ($rows as $r) {
    $p = $r['src'] ?? '';
    if ($p && strpos($p, '../videos/galeria/') === 0) {
      $abs = realpath($vidRoot . DIRECTORY_SEPARATOR . basename($p));
      if ($abs && is_file($abs)) @unlink($abs);
    }
  }
  $pdo->exec("TRUNCATE TABLE gallery_videos");
} else {
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT src FROM gallery_videos WHERE id IN ($in)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
      $p = $r['src'] ?? '';
      if ($p && strpos($p, '../videos/galeria/') === 0) {
        $abs = realpath($vidRoot . DIRECTORY_SEPARATOR . basename($p));
        if ($abs && is_file($abs)) @unlink($abs);
      }
    }
    $stmt = $pdo->prepare("DELETE FROM gallery_videos WHERE id IN ($in)");
    $stmt->execute($ids);
  }
}
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
@file_put_contents($jsonPath . DIRECTORY_SEPARATOR . 'gallery_videos.json', json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$_SESSION['flash'] = ['type'=>'success','text'=> $all ? 'Todos los videos eliminados' : 'Videos eliminados'];
header('Location: index.php'.$hash);
exit;
