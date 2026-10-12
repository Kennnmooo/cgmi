<?php
/**
 * Lightweight endpoint returning just the unread count for the logged-in user.
 * Used by the bell's background polling to update the badge cheaply.
 */
require_once __DIR__ . '/common.php';
require_login(notification_supported_roles());

try {
    ensure_notifications_table($pdo);
    $userId = (int)(current_user()['id'] ?? 0);
    if ($userId < 1) {
        json_response(['success' => true, 'unreadCount' => 0]);
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    json_response(['success' => true, 'unreadCount' => (int)$stmt->fetchColumn()]);
} catch (Throwable $e) {
    json_response(['success' => true, 'unreadCount' => 0]);
}
