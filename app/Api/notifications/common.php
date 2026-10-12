<?php
/**
 * Notifications module — shared bootstrap and helpers.
 *
 * Follows the project conventions:
 *   - Requires Core/Auth, Core/Database ($pdo), Core/Helpers.
 *   - Self-heals its table at runtime via ensure_notifications_table() so the
 *     feature works without a manual SQL import (mirrors the checker module's
 *     ensure_*_tables pattern).
 *   - Exposes notify_user() / notify_role() / notify_roles() creator helpers
 *     that other modules include to emit notifications on system events.
 *
 * Access control: a notification belongs to exactly one recipient (user_id).
 * Users can only ever read/modify their own rows — endpoints scope every query
 * by the logged-in user's id.
 */

require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/Helpers.php';

/**
 * Roles that own an in-app notification bell. Cashier (POS) is intentionally
 * excluded — the POS layout has no header bell.
 */
function notification_supported_roles(): array {
    return ['super-admin', 'admin', 'inventory', 'checker'];
}

/**
 * Known notification categories/types → the icon + accent the frontend renders.
 * Kept here so the type vocabulary lives in one place.
 */
function notification_type_catalog(): array {
    return [
        'system'    => ['icon' => 'icon-info',      'label' => 'System'],
        'incoming'  => ['icon' => 'icon-incoming',  'label' => 'Incoming'],
        'outgoing'  => ['icon' => 'icon-outgoing',  'label' => 'Outgoing'],
        'inventory' => ['icon' => 'icon-inventory', 'label' => 'Inventory'],
        'low-stock' => ['icon' => 'icon-weight',    'label' => 'Low Stock'],
        'incident'  => ['icon' => 'icon-incident',  'label' => 'Incident'],
        'sales'     => ['icon' => 'icon-sales',     'label' => 'Sales'],
        'user'      => ['icon' => 'icon-users',     'label' => 'User'],
        'security'  => ['icon' => 'icon-off',       'label' => 'Security'],
        'audit'     => ['icon' => 'icon-document',  'label' => 'Audit'],
    ];
}

/**
 * Ensure the notifications table exists. Cheap guard cached per-request so
 * repeated calls in one request don't re-hit information_schema.
 */
function ensure_notifications_table(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $ensured = true;
    } catch (Throwable $e) {
        // Non-fatal: notifications are auxiliary and must never block a primary
        // operation. Endpoints degrade to "no notifications" if the table is
        // unavailable.
    }
}

/**
 * Shape a DB row for the frontend, adding the icon/label for its type and a
 * relative "time ago" string. Keeps the JS dumb.
 */
function notification_to_frontend(array $row): array {
    $catalog = notification_type_catalog();
    $type = (string)($row['type'] ?? 'system');
    $meta = $catalog[$type] ?? $catalog['system'];

    return [
        'id'        => (int)($row['id'] ?? 0),
        'type'      => $type,
        'typeLabel' => $meta['label'],
        'icon'      => $meta['icon'],
        'title'     => (string)($row['title'] ?? ''),
        'message'   => (string)($row['message'] ?? ''),
        'link'      => $row['link'] !== null && $row['link'] !== '' ? (string)$row['link'] : null,
        'isRead'    => (int)($row['is_read'] ?? 0) === 1,
        'createdAt' => $row['created_at'] ?? null,
        'timeAgo'   => notification_time_ago($row['created_at'] ?? null),
    ];
}

/** Human-friendly relative time, e.g. "2h ago". Falls back to the date. */
function notification_time_ago(?string $timestamp): string {
    if (!$timestamp) {
        return '';
    }
    $then = strtotime($timestamp);
    if ($then === false) {
        return (string)$timestamp;
    }
    $diff = time() - $then;
    if ($diff < 0) {
        $diff = 0;
    }
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = (int)floor($diff / 60);
        return $m . 'm ago';
    }
    if ($diff < 86400) {
        $hr = (int)floor($diff / 3600);
        return $hr . 'h ago';
    }
    if ($diff < 604800) {
        $d = (int)floor($diff / 86400);
        return $d . 'd ago';
    }
    return date('M j, Y', $then);
}

/**
 * Create one notification for a specific user.
 *
 * De-duplication: if $dedupeKey is provided, an existing UNREAD row with the
 * same (user_id, dedupe_key) suppresses a duplicate. Read rows do not block a
 * new notification, so a recurring condition (e.g. low stock) can re-alert once
 * the user has cleared the previous one.
 *
 * Never throws — notification creation must not break the triggering operation.
 *
 * @return int The created notification id, or 0 if skipped/failed.
 */
function notify_user(PDO $pdo, int $userId, string $type, string $title, string $message = '', ?string $link = null, ?string $dedupeKey = null, ?string $role = null): int {
    if ($userId < 1 || $title === '') {
        return 0;
    }
    try {
        ensure_notifications_table($pdo);

        if ($dedupeKey !== null && $dedupeKey !== '') {
            $dup = $pdo->prepare('SELECT id FROM notifications WHERE user_id = ? AND dedupe_key = ? AND is_read = 0 LIMIT 1');
            $dup->execute([$userId, $dedupeKey]);
            if ($dup->fetchColumn() !== false) {
                return 0; // an unread duplicate already exists
            }
        }

        if ($role === null) {
            $roleStmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
            $roleStmt->execute([$userId]);
            $role = (string)($roleStmt->fetchColumn() ?: '');
        }

        // INSERT ... the UNIQUE(user_id, dedupe_key) index is a second line of
        // defense against races; use INSERT IGNORE-style handling via try/catch.
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, role, type, title, message, link, dedupe_key)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $role,
            $type,
            mb_substr($title, 0, 160),
            $message !== '' ? $message : null,
            $link !== null && $link !== '' ? $link : null,
            $dedupeKey !== null && $dedupeKey !== '' ? mb_substr($dedupeKey, 0, 160) : null,
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        // Unique-constraint violation (duplicate) or any other issue: swallow.
        return 0;
    }
}

/**
 * Fan a notification out to every active user holding one of the given roles.
 * The dedupe key is suffixed per-recipient so each user gets at most one.
 *
 * @param array       $roles         Role strings to target.
 * @param int|null    $excludeUserId Optional actor to skip (don't notify self).
 * @return int Number of notifications created.
 */
function notify_roles(PDO $pdo, array $roles, string $type, string $title, string $message = '', ?string $link = null, ?string $dedupeKey = null, ?int $excludeUserId = null): int {
    $roles = array_values(array_filter(array_unique($roles)));
    if (!$roles) {
        return 0;
    }
    try {
        ensure_notifications_table($pdo);
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT id, role FROM users WHERE role IN ($placeholders) AND is_active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($roles);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return 0;
    }

    $count = 0;
    foreach ($recipients as $r) {
        $uid = (int)$r['id'];
        if ($excludeUserId !== null && $uid === $excludeUserId) {
            continue;
        }
        $perUserKey = $dedupeKey !== null && $dedupeKey !== '' ? $dedupeKey . ':u' . $uid : null;
        $count += notify_user($pdo, $uid, $type, $title, $message, $link, $perUserKey, (string)$r['role']) > 0 ? 1 : 0;
    }
    return $count;
}

/** Convenience wrapper for a single role. */
function notify_role(PDO $pdo, string $role, string $type, string $title, string $message = '', ?string $link = null, ?string $dedupeKey = null, ?int $excludeUserId = null): int {
    return notify_roles($pdo, [$role], $type, $title, $message, $link, $dedupeKey, $excludeUserId);
}

/**
 * Resolve the correct app path for a given logical page based on the recipient's
 * role, so a notification link opens the page inside the recipient's own panel
 * (admin/inventory/superadmin all have their own copies).
 *
 * @param string $page One of: incoming-confirmation, inventory, incidents,
 *                     shelves, users, checker, dashboard, suppliers.
 */
function notification_link_for_role(string $role, string $page): ?string {
    $map = [
        'super-admin' => [
            'incoming-confirmation' => '/pages/superadmin/incoming-confirmation.php',
            'outgoing-confirmation' => '/pages/superadmin/inventory-admin.php',
            'inventory'             => '/pages/superadmin/inventory-admin.php',
            'incidents'             => '/pages/superadmin/incident-report.php',
            'shelves'               => '/pages/superadmin/shelves-admin.php',
            'users'                 => '/pages/superadmin/user-management.php',
            'checker'               => '/pages/superadmin/checker.php',
            'dashboard'             => '/pages/superadmin/dashboard.php',
            'suppliers'             => '/pages/superadmin/suppliers.php',
        ],
        'admin' => [
            'incoming-confirmation' => '/pages/admin/incoming-confirmation.php',
            'outgoing-confirmation' => '/pages/admin/inventory-admin.php',
            'inventory'             => '/pages/admin/inventory-admin.php',
            'incidents'             => '/pages/admin/incident-report.php',
            'shelves'               => '/pages/admin/shelves-admin.php',
            'users'                 => '/pages/admin/user-management.php',
            'dashboard'             => '/pages/admin/dashboard.php',
            'suppliers'             => '/pages/admin/suppliers.php',
        ],
        'inventory' => [
            'incoming-confirmation' => '/pages/inventory/incoming-confirmation.php',
            'outgoing-confirmation' => '/pages/inventory/inventory.php',
            'inventory'             => '/pages/inventory/inventory.php',
            'incidents'             => '/pages/inventory/incident-report.php',
            'shelves'               => '/pages/inventory/shelves.php',
            'dashboard'             => '/pages/inventory/dashboard.php',
        ],
        'checker' => [
            'checker'               => '/pages/checker/checker.php',
        ],
    ];
    $path = $map[$role][$page] ?? null;
    if ($path === null) {
        return null;
    }
    return function_exists('app_url') ? app_url($path) : $path;
}
