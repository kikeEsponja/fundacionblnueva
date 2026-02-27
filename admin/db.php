<?php
// Carga de credenciales por entorno: variables de entorno o archivo secreto opcional
$env = getenv('FUNDACION_ENV') ?: 'development';
$dbName = getenv('FUNDACION_DB_NAME') ?: ($env === 'production' ? '' : 'sistema_fundacion');
$dbUser = getenv('FUNDACION_DB_USER') ?: ($env === 'production' ? '' : 'root');
$dbPass = getenv('FUNDACION_DB_PASS');
if ($dbPass === false) {
  $dbPass = ($env === 'production' ? null : '');
}
$dbHost = getenv('FUNDACION_DB_HOST') ?: '127.0.0.1';
// Override por archivo secreto no versionado (subido solo en hosting)
$secretPath = __DIR__ . '/secret_db.php';
if (is_file($secretPath)) {
  $cfg = @include $secretPath;
  if (is_array($cfg)) {
    $env = $cfg['env'] ?? $env;
    $dbName = $cfg['name'] ?? $dbName;
    $dbUser = $cfg['user'] ?? $dbUser;
    $dbPass = array_key_exists('pass', $cfg) ? $cfg['pass'] : $dbPass;
    $dbHost = $cfg['host'] ?? $dbHost;
  }
}
$dsnNoDb = "mysql:host=$dbHost;charset=utf8mb4";
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
if ($env === 'production') {
  if (!$dbName || !$dbUser || $dbPass === null || $dbPass === '') {
    http_response_code(500);
    exit;
  }
}
try {
  $pdo = new PDO($dsn, $dbUser, (string)$dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
} catch (PDOException $e) {
  if ($env !== 'production') {
    try {
      $pdoCreate = new PDO($dsnNoDb, $dbUser, (string)$dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
      ]);
      $pdoCreate->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
      $pdo = new PDO($dsn, $dbUser, (string)$dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);
    } catch (PDOException $ee) {
      http_response_code(500);
      exit;
    }
  } else {
    http_response_code(500);
    exit;
  }
}
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_config (
  id TINYINT PRIMARY KEY DEFAULT 1,
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  theme VARCHAR(10) NOT NULL DEFAULT 'light',
  failed_attempts INT NOT NULL DEFAULT 0,
  lock_until DATETIME NULL,
  profile_photo VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("INSERT IGNORE INTO admin_config (id, password_hash) VALUES (1, '')");
// ensure theme column exists for older installs
try {
  $pdo->exec("ALTER TABLE admin_config ADD COLUMN theme VARCHAR(10) NOT NULL DEFAULT 'light'");
} catch (PDOException $e) {
  // ignore if already exists
}
// set default theme if null/empty
try {
  $pdo->exec("UPDATE admin_config SET theme='light' WHERE id=1 AND (theme IS NULL OR theme='')");
} catch (PDOException $e) {
}
// ensure lockout columns exist
try {
  $pdo->exec("ALTER TABLE admin_config ADD COLUMN failed_attempts INT NOT NULL DEFAULT 0");
} catch (PDOException $e) {
}
try {
  $pdo->exec("ALTER TABLE admin_config ADD COLUMN lock_until DATETIME NULL");
} catch (PDOException $e) {
}
// ensure profile photo column
try {
  $pdo->exec("ALTER TABLE admin_config ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {
}
$pdo->exec("CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  excerpt TEXT,
  content MEDIUMTEXT,
  date DATETIME NOT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS gallery_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  src VARCHAR(255) NOT NULL,
  src_webp VARCHAR(255) DEFAULT NULL,
  thumb VARCHAR(255) DEFAULT NULL,
  thumb_webp VARCHAR(255) DEFAULT NULL,
  alt VARCHAR(255) DEFAULT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  date DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS gallery_videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  src VARCHAR(255) NOT NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  date DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS post_stats (
  post_id INT PRIMARY KEY,
  likes INT NOT NULL DEFAULT 0,
  stars_sum INT NOT NULL DEFAULT 0,
  stars_count INT NOT NULL DEFAULT 0,
  emojis TEXT DEFAULT NULL,
  CONSTRAINT fk_post_stats FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS post_reactions (
  post_id INT NOT NULL,
  fp_hash CHAR(64) NOT NULL,
  last_type VARCHAR(10) NOT NULL,
  last_value VARCHAR(32) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, fp_hash),
  CONSTRAINT fk_post_reactions FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
