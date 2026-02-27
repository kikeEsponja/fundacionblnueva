<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
header('Pragma: no-cache');
require __DIR__ . '/../../admin/db.php';
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
echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
