<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $userId = (int)($payload['id'] ?? 0);
    $action = strtolower(trim((string)($payload['action'] ?? '')));

    if ($userId < 1 || !in_array($action, ['activate', 'deactivate'], true)) {
        json_response(['success' => false, 'message' => 'Invalid request.'], 422);
    }

    $current = current_user();
    $currentUserId = (int)($current['id'] ?? 0);
    if ($action === 'deactivate' && $userId === $currentUserId) {
        json_response(['success' => false, 'message' => 'You cannot deactivate your own account while logged in.'], 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        json_response(['success' => false, 'message' => 'User not found.'], 404);
    }

    $actorRole = (string)($current['role'] ?? '');
    if (!can_actor_manage_role($actorRole, (string)$user['role'])) {
        json_response(['success' => false, 'message' => 'You are not allowed to modify this account.'], 403);
    }

    if ($action === 'deactivate' && strtolower((string)$user['role']) === 'admin') {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
        $activeAdmins = (int)$countStmt->fetchColumn();
        if ($activeAdmins <= 1 && !empty($user['is_active'])) {
            json_response(['success' => false, 'message' => 'You cannot deactivate the last active admin account.'], 422);
        }
    }

    $isActive = $action === 'activate' ? 1 : 0;
    $update = $pdo->prepare('UPDATE users SET is_active = ?, failed_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ?');
    $update->execute([$isActive, $userId]);

    $fetch = $pdo->prepare('SELECT id, first_name, last_name, username, role, is_active, failed_attempts, locked_until, last_login_at, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
    $fetch->execute([$userId]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    json_response([
        'success' => true,
        'message' => $action === 'activate' ? 'User activated successfully.' : 'User deactivated successfully.',
        'user' => user_row_to_frontend($row),
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to update user status: ' . $e->getMessage()], 500);
}
