<?php
/**
 * Mark a single notification as read for the logged-in user.
 * Body: { "id": <int> }. Scoped to the caller's own rows.
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

    $input = get_json_input();
    $id = (int)($input['id'] ?? 0);
    if ($id < 1) {
        json_response(['success' => false, 'message' => 'A valid notification id is required.'], 422);
    }

    // is_read guard keeps read_at stable if it was already read.
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW()
        WHERE id = ? AND user_id = ? AND is_read = 0');
    $stmt->execute([$id, $userId]);

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $countStmt->execute([$userId]);

    json_response(['success' => true, 'unreadCount' => (int)$countStmt->fetchColumn()]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to update notification: ' . $e->getMessage()], 500);
}
