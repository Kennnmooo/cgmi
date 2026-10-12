<?php
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_once __DIR__ . '/../../Core/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$data = get_json_input();
$username = trim($data['username'] ?? '');
$password = (string) ($data['password'] ?? '');

if ($username === '' || $password === '') {
    json_response(['success' => false, 'message' => 'Username and password are required.'], 422);
}

$stmt = $pdo->prepare('SELECT id, first_name, last_name, username, password_hash, role, is_active, failed_attempts, locked_until FROM users WHERE username = :username LIMIT 1');
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['success' => false, 'message' => 'Invalid username or password.', 'attempts' => 0, 'locked' => false], 401);
}

$now = new DateTimeImmutable('now');
if (!empty($user['locked_until']) && $now < new DateTimeImmutable($user['locked_until'])) {
    json_response([
        'success' => false,
        'message' => 'Account locked due to too many failed login attempts.',
        'attempts' => (int) $user['failed_attempts'],
        'locked' => true,
    ], 423);
}

if (!(int)$user['is_active']) {
    json_response(['success' => false, 'message' => 'This account is inactive.', 'attempts' => (int) $user['failed_attempts'], 'locked' => false], 403);
}

if (!password_verify($password, $user['password_hash'])) {
    $failedAttempts = ((int) $user['failed_attempts']) + 1;
    $lockedUntil = null;
    if ($failedAttempts >= 5) {
        $lockedUntil = $now->modify('+15 minutes')->format('Y-m-d H:i:s');
    }

    $update = $pdo->prepare('UPDATE users SET failed_attempts = :failed_attempts, locked_until = :locked_until WHERE id = :id');
    $update->execute([
        'failed_attempts' => $failedAttempts,
        'locked_until' => $lockedUntil,
        'id' => $user['id'],
    ]);

    // ── Security notification: alert super-admins when an account auto-locks. ──
    if ($failedAttempts >= 5) {
        require_once __DIR__ . '/../notifications/common.php';
        $lockedName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'A user');
        notify_role(
            $pdo,
            'super-admin',
            'security',
            'Account locked',
            $lockedName . ' (' . ($user['username'] ?? '') . ') was locked after 5 failed login attempts.',
            notification_link_for_role('super-admin', 'users'),
            'user:' . (int)$user['id'] . ':locked:' . $lockedUntil
        );
    }

    json_response([
        'success' => false,
        'message' => $failedAttempts >= 5
            ? 'Account locked due to too many failed login attempts.'
            : 'Invalid username or password.',
        'attempts' => $failedAttempts,
        'locked' => $failedAttempts >= 5,
    ], 401);
}

$reset = $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id');
$reset->execute(['id' => $user['id']]);

session_regenerate_id(true);
$_SESSION['user'] = [
    'id' => (int) $user['id'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'username' => $user['username'],
    'role' => $user['role'],
];

json_response([
    'success'  => true,
    'message'  => 'Login successful.',
    'redirect' => get_home_for_role($user['role']),
    'user'     => $_SESSION['user'],
]);
