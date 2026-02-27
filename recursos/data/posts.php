<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
header('Pragma: no-cache');
require __DIR__ . '/../../admin/db.php';
$cfg = $pdo->query("SELECT profile_photo FROM admin_config WHERE id=1")->fetch();
$avatar = $cfg && !empty($cfg['profile_photo']) ? $cfg['profile_photo'] : null;
$rows = $pdo->query("SELECT p.id, p.title, p.slug, p.excerpt, p.content, p.date, p.featured, s.likes, s.stars_sum, s.stars_count, s.emojis FROM posts p LEFT JOIN post_stats s ON s.post_id = p.id")->fetchAll();
$out = [];
foreach ($rows as $r) {
  $avg = 0;
  if (!empty($r['stars_count'])) {
    $avg = round($r['stars_sum'] / max(1,(int)$r['stars_count']), 2);
  }
  $em = $r['emojis'] ? json_decode($r['emojis'], true) : (object)[];
  $out[] = [
    'title' => $r['title'],
    'slug' => $r['slug'],
    'excerpt' => $r['excerpt'],
    'content' => $r['content'],
    'date' => date('c', strtotime($r['date'])),
    'featured' => $r['featured'] ? true : false,
    'adminAvatar' => $avatar ?: '',
    'likes' => (int)($r['likes'] ?? 0),
    'starsAvg' => $avg,
    'emojis' => $em
  ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
