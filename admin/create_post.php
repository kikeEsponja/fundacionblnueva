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
if (!in_array($hash, $allowed, true)) { $hash = '#nueva-publicacion'; }
$title = trim($_POST['title'] ?? '');
$slug = trim($_POST['slug'] ?? '');
$excerpt = trim($_POST['excerpt'] ?? '');
$content = trim($_POST['content'] ?? '');
if ($title === '' || $slug === '') { header('Location: index.php'.$hash); exit; }
$slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug));
$featured = !empty($_POST['featured']) ? 1 : 0;
$stmt = $pdo->prepare("INSERT INTO posts (title, slug, excerpt, content, date, featured) VALUES (?, ?, ?, ?, NOW(), ?)");
$stmt->execute([$title, $slug, $excerpt, $content, $featured]);
// regenerate posts.json for public blog
try {
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
} catch (Throwable $e) {}
// flash message
$_SESSION['flash'] = ['type'=>'success','text'=>'Publicación creada'];
header('Location: index.php'.$hash);
