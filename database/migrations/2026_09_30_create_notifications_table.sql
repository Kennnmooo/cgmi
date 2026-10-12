-- Migration: create the notifications table used by the in-app notification bell.
-- Safe to run repeatedly (IF NOT EXISTS). See database/notifications.sql for docs.

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT(10) UNSIGNED NOT NULL,
  `role`       VARCHAR(30) NOT NULL DEFAULT '',
  `type`       VARCHAR(40) NOT NULL DEFAULT 'system',
  `title`      VARCHAR(160) NOT NULL,
  `message`    TEXT DEFAULT NULL,
  `link`       VARCHAR(255) DEFAULT NULL,
  `dedupe_key` VARCHAR(160) DEFAULT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `read_at`    DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`, `is_read`),
  KEY `idx_notifications_user_created` (`user_id`, `created_at`),
  UNIQUE KEY `uniq_notifications_user_dedupe` (`user_id`, `dedupe_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
