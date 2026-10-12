<?php
/**
 * GET notifications for the logged-in user, newest first.
 * Returns the list plus the current unread count so the bell can render both
 * from one request. Optional ?limit= (default 30, max 100).
 */
require_once __DIR__ . '/common.php';
require_login(notification_supported_roles());

try {
    ensure_notifications_table($pdo);
    $user = current_user() ?: [];
    $userId = (int)($user['id'] ?? 0);
    if ($userId < 1) {
        json_response(['success' => false, 'message' => 'Not authenticated.'], 401);
    }

    $limit = (int)($_GET['limit'] ?? 30);
    if ($limit < 1) { $limit = 30; }
    if ($limit > 100) { $limit = 100; }

    $stmt = $pdo->prepare('SELECT id, user_id, role, type, title, message, link, is_read, read_at, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT ' . $limit);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map('notification_to_frontend', $rows);

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $countStmt->execute([$userId]);
    $unread = (int)$countStmt->fetchColumn();

    json_response([
        'success'     => true,
        'notifications' => $items,
        'unreadCount' => $unread,
    ]);
} catch (Throwable $e) {
    // Degrade gracefully so the bell never breaks a page.
    json_response(['success' => true, 'notifications' => [], 'unreadCount' => 0]);
}
