-- Social media posts — manual import for phpMyAdmin (MySQL / MariaDB)
-- Use this ONLY if "Run migrations" from admin page does not work.
-- Safe to re-run: uses IF NOT EXISTS / information_schema checks.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `social_media_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `submitted_by_user_id` bigint unsigned NOT NULL,
  `submitted_by_name` varchar(191) NOT NULL,
  `posted_on` date NOT NULL,
  `post_url` varchar(2048) NOT NULL,
  `description` text NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `smp_submitted_by_posted_idx` (`submitted_by_user_id`, `posted_on`),
  KEY `smp_posted_on_idx` (`posted_on`),
  CONSTRAINT `fk_social_media_post_user` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'social_media_posts' AND COLUMN_NAME = 'posted_platforms') = 0,
  'ALTER TABLE `social_media_posts` ADD COLUMN `posted_platforms` json NULL AFTER `post_url`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'social_media_posts' AND COLUMN_NAME = 'platform') = 0,
  'ALTER TABLE `social_media_posts` ADD COLUMN `platform` varchar(64) NULL AFTER `post_url`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'social_media_posts' AND COLUMN_NAME = 'thumbnail_url') = 0,
  'ALTER TABLE `social_media_posts` ADD COLUMN `thumbnail_url` varchar(2048) NULL AFTER `platform`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'social_media_posts' AND COLUMN_NAME = 'preview_title') = 0,
  'ALTER TABLE `social_media_posts` ADD COLUMN `preview_title` varchar(500) NULL AFTER `thumbnail_url`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @next_batch := (SELECT IFNULL(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_20_120000_create_social_media_posts_table', @next_batch
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_05_20_120000_create_social_media_posts_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_21_100000_add_preview_fields_to_social_media_posts_table', @next_batch
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_05_21_100000_add_preview_fields_to_social_media_posts_table'
);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_22_100000_add_posted_platforms_to_social_media_posts_table', @next_batch
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_05_22_100000_add_posted_platforms_to_social_media_posts_table'
);

SET FOREIGN_KEY_CHECKS = 1;
