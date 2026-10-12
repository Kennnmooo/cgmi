<?php
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/App.php';
require_once __DIR__ . '/Helpers.php';

function require_login(?array $roles = null): void {
    if (empty($_SESSION['user'])) {
        header('Location: ' . app_url('/index.php'));
        exit;
    }

    if ($roles !== null && !in_array($_SESSION['user']['role'], $roles, true)) {
        header('Location: ' . get_home_for_role($_SESSION['user']['role']));
        exit;
    }
}

function get_home_for_role(string $role): string {
    return match ($role) {
        'super-admin' => app_url('/pages/superadmin/dashboard.php'),
        'admin' => app_url('/pages/admin/dashboard.php'),
        'inventory' => app_url('/pages/inventory/dashboard.php'),
        'checker' => app_url('/pages/checker/checker.php'),
        default => app_url('/index.php'),
    };
}
