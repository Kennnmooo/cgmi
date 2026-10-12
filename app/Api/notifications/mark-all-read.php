<?php
/**
 * Mark ALL of the logged-in user's unread notifications as read.
 */
require_once __DIR__ . '/common.php';
require_login(notification_supported_roles());

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    ensure_notifications_table($pdo);
    $userId = (int)(current_user()['id'] ?? 0);
    if ($userId < 1) {
        json_response(['success' => false, 'message' => 'Not authenticated.'], 401);
    }

    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW()
        WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);

    json_response(['success' => true, 'unreadCount' => 0, 'updated' => $stmt->rowCount()]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to update notifications: ' . $e->getMessage()], 500);
}
