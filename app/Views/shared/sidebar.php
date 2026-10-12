<?php
/**
 * Shared role-aware sidebar partial.
 *
 * Renders the navigation for the current user's role. For existing roles
 * (admin, inventory, checker) it reproduces the exact same markup those
 * panels already used, so there is no visual change. For the new
 * `super-admin` role it renders a consolidated navigation that exposes
 * every panel required by the Super Admin.
 *
 * Expected variables (optional, with safe fallbacks):
 *   $sidebarRole   string  Role whose navigation should render. Defaults to the
 *                          logged-in user's role.
 *   $sidebarActive string  Active nav key used to highlight the current page.
 *   $user          array   Current user (falls back to current_user()).
 *
 * Active keys:
 *   dashboard, inventory-report, sales-report, inventory,
 *   incoming-confirmation, shelves, incidents, users, suppliers, checker
 */

require_once __DIR__ . '/../../Core/App.php';
require_once __DIR__ . '/../../Core/Helpers.php';

if (!isset($user) || !is_array($user)) {
    $user = function_exists('current_user') ? (current_user() ?: []) : [];
}

$sidebarRole   = $sidebarRole   ?? (string)($user['role'] ?? 'admin');
$sidebarActive = $sidebarActive ?? '';

$__first = (string)($user['first_name'] ?? '');
$__last  = (string)($user['last_name'] ?? '');
$__fullName = trim($__first . ' ' . $__last);
if ($__fullName === '') {
    $__fullName = (string)($user['username'] ?? 'User');
}
$__avatar = strtoupper(substr($__first !== '' ? $__first : ($user['username'] ?? 'U'), 0, 1) . substr($__last, 0, 1));
if ($__avatar === '') {
    $__avatar = 'U';
}
$__roleLabel = match ((string)($sidebarRole)) {
    'super-admin' => 'Super Admin',
    'admin'       => 'Admin',
    'inventory'   => 'Inventory',
    'checker'     => 'Checker',
    default       => ucfirst((string)($sidebarRole ?: 'User')),
};

/** Helper: render active class for a nav key. */
$navActive = static function (string $key) use ($sidebarActive): string {
    return $sidebarActive === $key ? ' active' : '';
};
$navAria = static function (string $key) use ($sidebarActive): string {
    return $sidebarActive === $key ? ' aria-current="page"' : '';
};
?>
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Main Navigation">
  <div class="sidebar-logo">
    <div class="logo-icon ui-icon icon-store" aria-hidden="true"></div>
    <div class="logo-text">Calamba General Merchandise Inc.<span>Hardware &amp; Supply</span></div>
  </div>

  <nav class="sidebar-nav">
  <?php if ($sidebarRole === 'super-admin'): ?>
    <div class="nav-section-label">main</div>
    <a class="nav-item<?= $navActive('dashboard') ?>" href="<?= app_url('/pages/superadmin/dashboard.php') ?>"<?= $navAria('dashboard') ?>><span class="nav-icon ui-icon icon-dashboard" aria-hidden="true"></span>Dashboard</a>
    <a class="nav-item<?= $navActive('inventory-report') ?>" href="<?= app_url('/pages/superadmin/inventory-report.php') ?>"<?= $navAria('inventory-report') ?>><span class="nav-icon ui-icon icon-reports" aria-hidden="true"></span>Inventory Report</a>

    <div class="nav-section-label">inventory</div>
    <a class="nav-item<?= $navActive('inventory') ?>" href="<?= app_url('/pages/superadmin/inventory-admin.php') ?>"<?= $navAria('inventory') ?>><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Inventory</a>
    <a class="nav-item<?= $navActive('incoming-confirmation') ?>" href="<?= app_url('/pages/superadmin/incoming-confirmation.php') ?>"<?= $navAria('incoming-confirmation') ?>><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
    <a class="nav-item<?= $navActive('shelves') ?>" href="<?= app_url('/pages/superadmin/shelves-admin.php') ?>"<?= $navAria('shelves') ?>><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>

    <div class="nav-section-label">management</div>
    <a class="nav-item<?= $navActive('incidents') ?>" href="<?= app_url('/pages/superadmin/incident-report.php') ?>"<?= $navAria('incidents') ?>><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
    <a class="nav-item<?= $navActive('users') ?>" href="<?= app_url('/pages/superadmin/user-management.php') ?>"<?= $navAria('users') ?>><span class="nav-icon ui-icon icon-users" aria-hidden="true"></span>User Management</a>
    <a class="nav-item<?= $navActive('suppliers') ?>" href="<?= app_url('/pages/superadmin/suppliers.php') ?>"<?= $navAria('suppliers') ?>><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Suppliers</a>

    <div class="nav-section-label">checker</div>
    <a class="nav-item<?= $navActive('checker') ?>" href="<?= app_url('/pages/superadmin/checker.php') ?>"<?= $navAria('checker') ?>><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Warehouse Checking</a>

    <div class="nav-section-label">Settings</div>
    <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>

  <?php elseif ($sidebarRole === 'checker'): ?>
    <div class="nav-section-label">Checker</div>
    <a class="nav-item<?= $navActive('checker') ?>" href="<?= app_url('/pages/checker/checker.php') ?>"<?= $navAria('checker') ?>><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Warehouse Checking</a>
    <div class="nav-section-label">Settings</div>
    <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>

  <?php elseif ($sidebarRole === 'inventory'): ?>
    <div class="nav-section-label">Main</div>
    <a class="nav-item<?= $navActive('dashboard') ?>" href="<?= app_url('/pages/inventory/dashboard.php') ?>"<?= $navAria('dashboard') ?>><span class="nav-icon ui-icon icon-dashboard" aria-hidden="true"></span>Dashboard</a>
    <a class="nav-item<?= $navActive('inventory') ?>" href="<?= app_url('/pages/inventory/inventory.php') ?>"<?= $navAria('inventory') ?>><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Inventory</a>
    <a class="nav-item<?= $navActive('incoming-confirmation') ?>" href="<?= app_url('/pages/inventory/incoming-confirmation.php') ?>"<?= $navAria('incoming-confirmation') ?>><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
    <a class="nav-item<?= $navActive('incidents') ?>" href="<?= app_url('/pages/inventory/incident-report.php') ?>"<?= $navAria('incidents') ?>><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
    <a class="nav-item<?= $navActive('shelves') ?>" href="<?= app_url('/pages/inventory/shelves.php') ?>"<?= $navAria('shelves') ?>><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>
    <div class="nav-section-label">Settings</div>
    <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>

  <?php else: /* admin */ ?>
    <div class="nav-section-label">main</div>
    <a class="nav-item<?= $navActive('dashboard') ?>" href="<?= app_url('/pages/admin/dashboard.php') ?>"<?= $navAria('dashboard') ?>><span class="nav-icon ui-icon icon-dashboard" aria-hidden="true"></span>Dashboard</a>
    <a class="nav-item<?= $navActive('inventory-report') ?>" href="<?= app_url('/pages/admin/inventory-report.php') ?>"<?= $navAria('inventory-report') ?>><span class="nav-icon ui-icon icon-reports" aria-hidden="true"></span>Inventory Report</a>
    <div class="nav-section-label">inventory</div>
    <a class="nav-item<?= $navActive('inventory') ?>" href="<?= app_url('/pages/admin/inventory-admin.php') ?>"<?= $navAria('inventory') ?>><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Inventory</a>
    <a class="nav-item<?= $navActive('incoming-confirmation') ?>" href="<?= app_url('/pages/admin/incoming-confirmation.php') ?>"<?= $navAria('incoming-confirmation') ?>><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
    <a class="nav-item<?= $navActive('shelves') ?>" href="<?= app_url('/pages/admin/shelves-admin.php') ?>"<?= $navAria('shelves') ?>><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>
    <div class="nav-section-label">management</div>
    <a class="nav-item<?= $navActive('incidents') ?>" href="<?= app_url('/pages/admin/incident-report.php') ?>"<?= $navAria('incidents') ?>><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
    <a class="nav-item<?= $navActive('users') ?>" href="<?= app_url('/pages/admin/user-management.php') ?>"<?= $navAria('users') ?>><span class="nav-icon ui-icon icon-users" aria-hidden="true"></span>User Management</a>
    <a class="nav-item<?= $navActive('suppliers') ?>" href="<?= app_url('/pages/admin/suppliers.php') ?>"<?= $navAria('suppliers') ?>><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Suppliers</a>
    <div class="nav-section-label">Settings</div>
    <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>
  <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar"><?= h($__avatar) ?></div>
      <div class="user-info">
        <div class="user-name"><?= h($__fullName) ?></div>
        <div class="user-role"><?= h($__roleLabel) ?></div>
      </div>
    </div>
  </div>
</aside>
