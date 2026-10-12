<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['admin', 'super-admin']);

function user_role_db_to_label(string $role): string {
    return match (strtolower($role)) {
        'admin' => 'Admin',
        'super-admin' => 'Super Admin',
        'inventory' => 'Inventory Manager',
        'checker' => 'Checker',
        default => ucfirst($role),
    };
}

function user_role_label_to_db(string $role): ?string {
    $normalized = strtolower(trim($role));
    return match ($normalized) {
        'admin' => 'admin',
        'super admin', 'super-admin', 'super_admin', 'superadmin' => 'super-admin',
        'inventory', 'inventory manager', 'inventory staff' => 'inventory',
        'checker' => 'checker',
        default => null,
    };
}

/**
 * Roles an "admin" account is permitted to create or modify.
 * Admins may only manage Checker and Inventory Manager accounts —
 * they cannot create or edit Admin or Super Admin accounts.
 * Super Admins are not restricted.
 */
function assignable_roles_for(string $actorRole): array {
    return match (strtolower($actorRole)) {
        'super-admin' => ['admin', 'super-admin', 'inventory', 'checker'],
        'admin' => ['inventory', 'checker'],
        default => [],
    };
}

function can_actor_manage_role(string $actorRole, string $targetRole): bool {
    return in_array(strtolower($targetRole), assignable_roles_for($actorRole), true);
}

/**
 * Roles an actor is allowed to see listed in the panel.
 * Admins never see Admin or Super Admin accounts; Super Admins see everyone.
 */
function visible_roles_for(string $actorRole): array {
    return match (strtolower($actorRole)) {
        'super-admin' => ['admin', 'super-admin', 'inventory', 'checker'],
        'admin' => ['inventory', 'checker'],
        default => [],
    };
}

function user_row_to_frontend(array $row): array {
    $firstName = trim((string)($row['first_name'] ?? ''));
    $lastName = trim((string)($row['last_name'] ?? ''));
    return [
        'id' => (int)$row['id'],
        'firstName' => $firstName,
        'lastName' => $lastName,
        'fullName' => trim($firstName . ' ' . $lastName),
        'username' => (string)($row['username'] ?? ''),
        'role' => user_role_db_to_label((string)($row['role'] ?? '')),
        'status' => !empty($row['is_active']) ? 'active' : 'deactivated',
        'isActive' => !empty($row['is_active']),
        'lastLoginAt' => $row['last_login_at'] ?? null,
        'failedAttempts' => (int)($row['failed_attempts'] ?? 0),
        'lockedUntil' => $row['locked_until'] ?? null,
        'createdAt' => $row['created_at'] ?? null,
        'updatedAt' => $row['updated_at'] ?? null,
    ];
}

function validate_user_payload(PDO $pdo, array $payload, ?int $editingId = null): array {
    $firstName = trim((string)($payload['firstName'] ?? ''));
    $lastName = trim((string)($payload['lastName'] ?? ''));
    $username = trim((string)($payload['username'] ?? ''));
    $password = (string)($payload['password'] ?? '');
    $roleLabel = trim((string)($payload['role'] ?? ''));
    $role = user_role_label_to_db($roleLabel);

    $errors = [];

    if ($firstName === '') $errors['firstname'] = 'First name is required.';
    if ($lastName === '') $errors['lastname'] = 'Last name is required.';

    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        $errors['username'] = 'Use 3-50 letters, numbers, dot, underscore, or dash.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1');
        $stmt->execute([$username]);
        $existing = $stmt->fetchColumn();
        if ($existing && (int)$existing !== (int)$editingId) {
            $errors['username'] = 'Username is already taken.';
        }
    }

    if ($editingId === null) {
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
    } elseif ($password !== '' && strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    if ($role === null) {
        $errors['role'] = 'Please select a valid role.';
    } else {
        $actorRole = (string)((current_user()['role']) ?? '');
        if (!can_actor_manage_role($actorRole, $role)) {
            $errors['role'] = 'You are not allowed to assign this role.';
        }
    }

    return [
        'errors' => $errors,
        'data' => [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'password' => $password,
            'role' => $role,
        ],
    ];
}
