<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $editingId = isset($payload['id']) && $payload['id'] !== '' ? (int)$payload['id'] : null;

    $validation = validate_user_payload($pdo, $payload, $editingId);
    if ($validation['errors']) {
        json_response(['success' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $validation['errors']], 422);
    }

    $data = $validation['data'];

    if ($editingId === null) {
        $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, username, password_hash, role, is_active, failed_attempts, locked_until, last_login_at) VALUES (?, ?, ?, ?, ?, 1, 0, NULL, NULL)');
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'],
        ]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $check = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $check->execute([$editingId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            json_response(['success' => false, 'message' => 'User not found.'], 404);
        }

        $actorRole = (string)((current_user()['role']) ?? '');
        if (!can_actor_manage_role($actorRole, (string)$existing['role'])) {
            json_response(['success' => false, 'message' => 'You are not allowed to modify this account.'], 403);
        }

        if ($data['password'] !== '') {
            $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, username = ?, password_hash = ?, role = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['username'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['role'],
                $editingId,
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, username = ?, role = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['username'],
                $data['role'],
                $editingId,
            ]);
        }

        $userId = $editingId;
    }

    $fetch = $pdo->prepare('SELECT id, first_name, last_name, username, role, is_active, failed_attempts, locked_until, last_login_at, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
    $fetch->execute([$userId]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    // ── Notify super-admins of new account creation (audit-relevant). ──
    if ($editingId === null) {
        require_once __DIR__ . '/../notifications/common.php';
        $actorId = (int)((current_user()['id']) ?? 0);
        $newName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: ($data['username'] ?? 'A user');
        notify_role(
            $pdo,
            'super-admin',
            'user',
            'New user account created',
            $newName . ' was added with the ' . $data['role'] . ' role.',
            notification_link_for_role('super-admin', 'users'),
            'user:' . $userId . ':created',
            $actorId
        );
    }

    json_response([
        'success' => true,
        'message' => $editingId === null ? 'User account created successfully.' : 'User account updated successfully.',
        'user' => user_row_to_frontend($row),
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to save user: ' . $e->getMessage()], 500);
}
