-- Sistema Fundación - Esquema MySQL
-- Charset recomendado: utf8mb4 / Collation: utf8mb4_unicode_ci

CREATE TABLE IF NOT EXISTS `admin_config` (
  `id` TINYINT PRIMARY KEY DEFAULT 1,
  `password_hash` VARCHAR(255) NOT NULL DEFAULT '',
  `theme` VARCHAR(10) NOT NULL DEFAULT 'light',
  `failed_attempts` INT NOT NULL DEFAULT 0,
  `lock_until` DATETIME NULL,
  `profile_photo` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `admin_config` (`id`, `password_hash`, `theme`) VALUES (1, '', 'light');

CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `excerpt` TEXT,
  `content` MEDIUMTEXT,
  `date` DATETIME NOT NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gallery_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `src` VARCHAR(255) NOT NULL,
  `src_webp` VARCHAR(255) DEFAULT NULL,
  `thumb` VARCHAR(255) DEFAULT NULL,
  `thumb_webp` VARCHAR(255) DEFAULT NULL,
  `alt` VARCHAR(255) DEFAULT NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `date` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gallery_videos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `src` VARCHAR(255) NOT NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `date` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_stats` (
  `post_id` INT PRIMARY KEY,
  `likes` INT NOT NULL DEFAULT 0,
  `stars_sum` INT NOT NULL DEFAULT 0,
  `stars_count` INT NOT NULL DEFAULT 0,
  `emojis` TEXT DEFAULT NULL,
  CONSTRAINT `fk_post_stats` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `post_reactions` (
  `post_id` INT NOT NULL,
  `fp_hash` CHAR(64) NOT NULL,
  `last_type` VARCHAR(10) NOT NULL,
  `last_value` VARCHAR(32) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`, `fp_hash`),
  CONSTRAINT `fk_post_reactions` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fin del esquema
