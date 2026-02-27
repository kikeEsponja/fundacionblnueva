<?php
require __DIR__ . '/auth.php';
requireLogin();
require __DIR__ . '/db.php';
$tok = $_POST['csrf'] ?? '';
if (!$tok || !hash_equals($_SESSION['csrf'] ?? '', $tok)) { http_response_code(403); exit; }
$hash = $_POST['return_hash'] ?? '';
$allowed = ['#subir-medios','#gestionar-imagenes','#gestionar-videos','#gestionar-publicaciones','#nueva-publicacion','#preferencias','#perfil'];
if (!in_array($hash, $allowed, true)) { $hash = '#gestionar-publicaciones'; }
$all = !empty($_POST['delete_all']);
$ids = array_filter(array_map('intval', $_POST['ids'] ?? []), fn($v) => $v > 0);
if (!$all && !$ids) { $_SESSION['flash'] = ['type'=>'error','text'=>'Nada para eliminar']; header('Location: index.php'.$hash); exit; }
if ($all) {
  $pdo->exec("TRUNCATE TABLE posts");
} else {
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id IN ($in)");
    $stmt->execute($ids);
  }
}
$rows = $pdo->query("SELECT title, slug, excerpt, content, date, featured FROM posts")->fetchAll();
$out = [];
foreach ($rows as $r) {
  $out[] = [
    'title' => $r['title'],
    'slug' => $r['slug'],
    'excerpt' => $r['excerpt'],
    'content' => $r['content'],
    'date' => date('c', strtotime($r['date'])),
    'featured' => $r['featured'] ? true : false
  ];
}
$jsonPath = realpath(__DIR__ . '/../recursos/data') ?: (__DIR__ . '/../recursos/data');
@file_put_contents($jsonPath . DIRECTORY_SEPARATOR . 'posts.json', json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$_SESSION['flash'] = ['type'=>'success','text'=> $all ? 'Todas las publicaciones eliminadas' : 'Publicaciones eliminadas'];
header('Location: index.php'.$hash);
exit;
