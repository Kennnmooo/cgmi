<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['admin']);
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CGMI.IMS   User Management</title>
  <meta name="description" content="Hardware & Construction Supply User Management Panel">
  
  <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/admin/users.css') ?>">
</head>
<body>
  <div class="app-wrapper">
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="Main Navigation">
      <div class="sidebar-logo">
        <div class="logo-icon ui-icon icon-store" aria-hidden="true"></div>
        <div class="logo-text">Calamba General Merchandise Inc.<span>Hardware &amp; Supply</span></div>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section-label">main</div>
        <a class="nav-item" href="<?= app_url('/pages/admin/dashboard.php') ?>"><span class="nav-icon ui-icon icon-dashboard" aria-hidden="true"></span>Dashboard</a>
        <a class="nav-item" href="<?= app_url('/pages/admin/inventory-report.php') ?>"><span class="nav-icon ui-icon icon-reports" aria-hidden="true"></span>Inventory Report</a>
        <div class="nav-section-label">inventory</div>
        <a class="nav-item" href="<?= app_url('/pages/admin/inventory-admin.php') ?>"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Inventory</a>
        <a class="nav-item" href="<?= app_url('/pages/admin/incoming-confirmation.php') ?>"><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
        <a class="nav-item" href="<?= app_url('/pages/admin/shelves-admin.php') ?>"><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>
        <div class="nav-section-label">management</div>
        <a class="nav-item" href="<?= app_url('/pages/admin/incident-report.php') ?>"><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
        <a class="nav-item active" href="<?= app_url('/pages/admin/user-management.php') ?>"><span class="nav-icon ui-icon icon-users" aria-hidden="true"></span>User Management</a>
        <a class="nav-item" href="<?= app_url('/pages/admin/suppliers.php') ?>"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Suppliers</a>
        <div class="nav-section-label">Settings</div>
        <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>
      </nav>
      <div class="sidebar-footer">
        <div class="user-card">
          <div class="user-avatar"><?= h(substr(($user['first_name'] ?? $user['username'] ?? 'U'), 0, 1) . substr(($user['last_name'] ?? ''), 0, 1)) ?></div>
          <div class="user-info">
            <div class="user-name"><?= h(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'Admin')) ?></div>
            <div class="user-role"><?= h(ucfirst($user['role'] ?? 'admin')) ?></div>
          </div>
        </div>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
    <main class="main-content">
      <header class="top-header">
        <div class="header-left">
          <button class="menu-toggle" id="menu-toggle" aria-label="Toggle sidebar"><span class="ui-icon icon-menu" aria-hidden="true"></span></button>
          <div>
            <div class="page-title">User Management</div>
            <div class="page-breadcrumb"><span>Management &gt; User Management</span></div>
          </div>
        </div>
      <?php require __DIR__ . '/../shared/notification-bell.php'; ?>
    </header>
      <div class="page-content">
        <div class="stats-row" role="region" aria-label="User Account Summary">
          <div class="stat-card" style="--stat-accent:#1F3F74;--stat-bg:rgba(31,63,116,0.1)"><div class="stat-icon ui-icon icon-users" aria-hidden="true"></div><div class="stat-info"><div class="stat-value" id="stat-total">0</div><div class="stat-label">Total User Accounts</div><div class="stat-change neutral">All Accounts</div></div></div>
          <div class="stat-card" style="--stat-accent:#16a34a;--stat-bg:rgba(22,163,74,0.1)"><div class="stat-icon ui-icon icon-check" aria-hidden="true"></div><div class="stat-info"><div class="stat-value" id="stat-active">0</div><div class="stat-label">Active Accounts</div><div class="stat-change up">Online</div></div></div>
          <div class="stat-card" style="--stat-accent:#dc2626;--stat-bg:rgba(220,38,38,0.1)"><div class="stat-icon ui-icon icon-off" aria-hidden="true"></div><div class="stat-info"><div class="stat-value" id="stat-deactivated">0</div><div class="stat-label">Deactivated Accounts</div><div class="stat-change down">Inactive</div></div></div>
        </div>
        <div class="panel-card">
          <div class="panel-header">
            <div class="panel-title">
              <span class="panel-title-icon ui-icon icon-users" aria-hidden="true"></span>User Accounts
              <span class="panel-title-badge" id="user-count-badge">0 users</span>
              <div class="status-tabs" role="tablist" aria-label="Filter accounts by status">
                <button class="status-tab active" data-status="active" role="tab" aria-selected="true">Active</button>
                <button class="status-tab" data-status="inactive" role="tab" aria-selected="false">Inactive</button>
              </div>
            </div>

          </div>
          <div class="user-cards-grid" id="user-cards-grid"></div>
          <div class="empty-state" id="empty-state" style="display:none;">
            <div class="empty-state-icon ui-icon icon-users" aria-hidden="true"></div>
            <div class="empty-state-title">No Users Found</div>
            <div class="empty-state-desc">No user accounts have been created yet.</div>
          </div>
        </div>
      </div>
    </main>
  </div>
  <div class="modal-backdrop" id="user-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title-text">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title-wrap">
          <div class="modal-icon ui-icon icon-users" id="modal-icon" aria-hidden="true"></div>
          <div>
            <div class="modal-title" id="modal-title-text">Add User</div>
            <div class="modal-subtitle" id="modal-subtitle">Fill in the details to create a new account.</div>
          </div>
        </div>
        <button class="modal-close" id="modal-close-btn" aria-label="Close modal">x</button>
      </div>
      <div class="modal-body">
        <div class="modal-section-label">Account Information</div>
        <div class="form-grid">
          <div class="form-group"><label class="form-label required" for="input-firstname">First Name</label><input class="form-control" type="text" id="input-firstname" placeholder="e.g. Juan" autocomplete="given-name"><span class="field-error" id="err-firstname"></span></div>
          <div class="form-group"><label class="form-label required" for="input-lastname">Last Name</label><input class="form-control" type="text" id="input-lastname" placeholder="e.g. Dela Cruz" autocomplete="family-name"><span class="field-error" id="err-lastname"></span></div>
          <div class="form-group span-2"><label class="form-label required" for="input-username">Username</label><input class="form-control" type="text" id="input-username" placeholder="e.g. jdelacruz" autocomplete="username"><span class="field-error" id="err-username"></span></div>
          <div class="form-group span-2"><label class="form-label required" for="input-password">Password</label><div class="password-wrap"><input class="form-control" type="password" id="input-password" placeholder="Enter password" autocomplete="new-password"><button type="button" class="pw-toggle" id="pw-toggle" aria-label="Toggle password visibility">Show</button></div><span class="field-error" id="err-password"></span></div>
          <div class="form-group span-2"><label class="form-label required" for="input-role">Role</label><select class="form-control" id="input-role"><option value="">  Select Role  </option>
<?php
            // The role options an admin may assign are limited server-side.
            // Super Admin can assign every role; Admin can only assign Checker and Inventory Manager.
            $roleOptions = strtolower($user['role'] ?? '') === 'super-admin'
              ? ['Super Admin', 'Admin', 'Inventory Manager', 'Checker']
              : ['Inventory Manager', 'Checker'];
            foreach ($roleOptions as $roleOption):
?>
            <option value="<?= h($roleOption) ?>"><?= h($roleOption) ?></option>
<?php endforeach; ?>
          </select><span class="field-error" id="err-role"></span></div>
        </div>
      </div>
      <div class="modal-footer"><span class="save-hint">Fields marked with <strong>*</strong> are required.</span><button class="btn btn-ghost" id="modal-cancel-btn">Cancel</button><button class="btn btn-primary" id="modal-save-btn">Save Account</button></div>
    </div>
  </div>
  <div class="confirm-overlay" id="confirm-overlay" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">
    <div class="confirm-box">
      <div class="confirm-icon ui-icon icon-question" id="confirm-icon" aria-hidden="true"></div>
      <div class="confirm-title" id="confirm-title">Are you sure?</div>
      <div class="confirm-msg" id="confirm-msg"></div>
      <div class="confirm-actions"><button class="btn btn-ghost" id="confirm-cancel-btn">Cancel</button><button class="btn btn-primary" id="confirm-ok-btn">Confirm</button></div>
    </div>
  </div>
  <div id="toast-container" aria-live="polite" aria-atomic="false"></div>
  <script>window.CGMI_USERS_API = <?= json_encode(['list' => app_url('/api/users/list.php'),'save' => app_url('/api/users/save.php'),'toggle' => app_url('/api/users/toggle.php')]); ?>;
    window.CGMI_USERS_CONTEXT = <?= json_encode([
      'actorRole' => strtolower($user['role'] ?? ''),
      'manageableRoles' => strtolower($user['role'] ?? '') === 'super-admin'
        ? ['admin', 'super-admin', 'inventory', 'checker']
        : ['inventory', 'checker'],
      // Admins may activate/deactivate accounts but cannot edit user information —
      // that privilege is reserved for Super Admins only.
      'canEdit' => strtolower($user['role'] ?? '') === 'super-admin',
    ]); ?>;</script>
  <script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script>
  <script src="<?= app_url('/assets/js/modules/user-management/user-management.js') ?>"></script>
</body>
</html>
