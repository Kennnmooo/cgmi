-- ─────────────────────────────────────────────────────────────────────────────
-- CGMI IMS — Notifications
-- Stores per-user, role-aware notifications for the in-app notification bell.
--
-- Delivery model:
--   * A notification always targets a single recipient row (one row per user).
--   * `role` records the recipient's role at creation time (used only for
--     display/filtering context; access control is by `user_id`).
--   * When an event is relevant to a whole role, the app fans it out to one row
--     per active user holding that role (see notify_role() in
--     app/Api/notifications/common.php).
--
-- De-duplication:
--   * `dedupe_key` is a stable per-recipient string derived from the event
--     (e.g. "incident:42:submitted:u7"). A UNIQUE index on (user_id, dedupe_key)
--     prevents duplicate notifications for the same event + recipient.
--
-- This mirrors the modern `users` table conventions (INT UNSIGNED id, utf8mb4,
-- timestamp created_at). The application also self-heals this table at runtime
-- via ensure_notifications_table() so a manual import is optional.
-- ─────────────────────────────────────────────────────────────────────────────

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
