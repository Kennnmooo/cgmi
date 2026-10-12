<?php
require_once __DIR__ . '/common.php';

try {
    $stmt = $pdo->query('SELECT id, first_name, last_name, username, role, is_active, failed_attempts, locked_until, last_login_at, created_at, updated_at FROM users ORDER BY first_name ASC, last_name ASC, username ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Only expose accounts whose role the acting user is permitted to see.
    // Admins never receive Admin or Super Admin accounts.
    $visibleRoles = visible_roles_for((string)((current_user()['role']) ?? ''));
    $rows = array_filter($rows, static fn($row) => in_array(strtolower((string)$row['role']), $visibleRoles, true));

    $users = array_map('user_row_to_frontend', array_values($rows));
    json_response(['success' => true, 'users' => $users]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load users: ' . $e->getMessage()], 500);
}
