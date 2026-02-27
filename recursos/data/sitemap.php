<?php
header('Content-Type: application/xml; charset=utf-8');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
  || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
  || (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');
$scheme = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$projectName = basename(dirname(dirname(__DIR__)));
$base = $scheme.'://'.$host.'/'.$projectName;
$pages = [
  $base . '/',
  $base . '/recursos/vistas/nosotros',
  $base . '/recursos/vistas/servicios',
  $base . '/recursos/vistas/galeria',
  $base . '/recursos/vistas/ayudar',
  $base . '/recursos/vistas/donaciones',
  $base . '/recursos/vistas/contacto',
  $base . '/recursos/vistas/blog',
];
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php $now = date('c'); foreach ($pages as $loc): ?>
  <url>
    <loc><?php echo htmlspecialchars($loc, ENT_QUOTES, 'UTF-8'); ?></loc>
    <lastmod><?php echo $now; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
<?php endforeach; ?>
</urlset>
