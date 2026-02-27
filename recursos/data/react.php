<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, max-age=0, must-revalidate');
header('Pragma: no-cache');
require __DIR__ . '/../../admin/db.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false]);
  exit;
}
$slug = trim($_POST['slug'] ?? '');
$type = trim($_POST['type'] ?? '');
$value = trim($_POST['value'] ?? '');
$visitor = trim($_POST['visitor'] ?? '');
if ($slug === '' || $type === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false]);
  exit;
}
$stmt = $pdo->prepare("SELECT id FROM posts WHERE slug=?");
$stmt->execute([$slug]);
$row = $stmt->fetch();
if (!$row) {
  http_response_code(404);
  echo json_encode(['ok'=>false]);
  exit;
}
$postId = (int)$row['id'];
$fingerprint = '';
if ($visitor !== '') {
  $visitor = substr($visitor, 0, 128);
  $fingerprint = hash('sha256', $visitor);
} else {
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $fingerprint = hash('sha256', $ip.'|'.$ua);
}
$pdo->beginTransaction();
try {
  $pdo->prepare("INSERT IGNORE INTO post_stats (post_id) VALUES (?)")->execute([$postId]);
  $ins = $pdo->prepare("INSERT IGNORE INTO post_reactions (post_id, fp_hash, last_type, last_value) VALUES (?,?,?,?)");
  $ins->execute([$postId, $fingerprint, $type, $value]);
  $already = $ins->rowCount() === 0;
  if ($already) {
    $pdo->rollBack();
    $q = $pdo->prepare("SELECT likes, stars_sum, stars_count, emojis FROM post_stats WHERE post_id=?");
    $q->execute([$postId]);
    $s = $q->fetch();
    $out = ['ok'=>true,'already'=>true];
    if ($s) {
      $avg = ($s['stars_count'] > 0) ? round($s['stars_sum'] / $s['stars_count'], 2) : 0;
      $out['likes'] = (int)$s['likes'];
      $out['starsAvg'] = $avg;
      $out['emojis'] = $s['emojis'] ? json_decode($s['emojis'], true) : (object)[];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }
  if ($type === 'star') {
    $stars = max(1, min(5, (int)$value));
    $pdo->prepare("UPDATE post_stats SET stars_sum = stars_sum + ?, stars_count = stars_count + 1 WHERE post_id=?")->execute([$stars, $postId]);
  } elseif ($type === 'emoji') {
    $emo = substr($value, 0, 16);
    $stats = $pdo->prepare("SELECT emojis FROM post_stats WHERE post_id=?");
    $stats->execute([$postId]);
    $r = $stats->fetch();
    $map = [];
    if ($r && !empty($r['emojis'])) {
      $tmp = json_decode($r['emojis'], true);
      if (is_array($tmp)) $map = $tmp;
    }
    $map[$emo] = isset($map[$emo]) ? ((int)$map[$emo] + 1) : 1;
    $pdo->prepare("UPDATE post_stats SET emojis=? WHERE post_id=?")->execute([json_encode($map, JSON_UNESCAPED_UNICODE), $postId]);
  } else {
    throw new Exception();
  }
  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false]);
  exit;
}
$out = ['ok'=>true,'already'=>false];
$q = $pdo->prepare("SELECT likes, stars_sum, stars_count, emojis FROM post_stats WHERE post_id=?");
$q->execute([$postId]);
$s = $q->fetch();
if ($s) {
  $avg = ($s['stars_count'] > 0) ? round($s['stars_sum'] / $s['stars_count'], 2) : 0;
  $out['likes'] = (int)$s['likes'];
  $out['starsAvg'] = $avg;
  $out['emojis'] = $s['emojis'] ? json_decode($s['emojis'], true) : (object)[];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
