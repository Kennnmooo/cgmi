<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_login(['admin', 'inventory']);
$user = current_user() ?: [];
$role = $user['role'] ?? 'inventory';
$isAdmin = $role === 'admin';
$basePage = $isAdmin ? '/pages/admin' : '/pages/inventory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CGMI.IMS — Incoming Confirmation</title>
  <meta name="description" content="Hardware & Construction Supply Incoming Confirmation Panel">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Design System: root variables must load first -->
  <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">

  <!-- Module CSS -->
  <link rel="stylesheet" href="<?= app_url('/assets/css/modules/incoming-confirmation/main.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/modules/incoming-confirmation/responsive.css') ?>">
</head>
<body>
<div class="app-wrapper">

  <?php if ($isAdmin): ?>
    <!-- ADMIN SIDEBAR -->
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
        <a class="nav-item active" href="<?= app_url('/pages/admin/incoming-confirmation.php') ?>" aria-current="page"><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
        <a class="nav-item" href="<?= app_url('/pages/admin/shelves-admin.php') ?>"><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>

        <div class="nav-section-label">management</div>
        <a class="nav-item" href="<?= app_url('/pages/admin/incident-report.php') ?>"><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
        <a class="nav-item" href="<?= app_url('/pages/admin/user-management.php') ?>"><span class="nav-icon ui-icon icon-users" aria-hidden="true"></span>User Management</a>
        <a class="nav-item" href="<?= app_url('/pages/admin/suppliers.php') ?>"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Suppliers</a>
        <div class="nav-section-label">Settings</div>
        <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>
      </nav>

      <div class="sidebar-footer">
        <div class="user-card">
          <div class="user-avatar"><?= h(strtoupper(substr($user['first_name'] ?? 'U', 0, 1))) ?></div>
          <div class="user-info">
            <div class="user-name"><?= h(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'User') ?></div>
            <div class="user-role">Admin</div>
          </div>
        </div>
      </div>
    </aside>

  <?php else: ?>
    <!-- INVENTORY SIDEBAR — copied from inventory/inventory.php, active item swapped -->
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="Main Navigation">
      <div class="sidebar-logo">
        <div class="logo-icon ui-icon icon-store" aria-hidden="true"></div>
        <div class="logo-text">Calamba General Merchandise Inc.<span>Hardware &amp; Supply</span></div>
      </div>

      <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
          <a class="nav-item" href="<?= app_url('/pages/inventory/dashboard.php') ?>"><span class="nav-icon ui-icon icon-dashboard" aria-hidden="true"></span>Dashboard</a>
          <a class="nav-item" href="<?= app_url('/pages/inventory/inventory.php') ?>"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Inventory</a>
          <a class="nav-item active" href="<?= app_url('/pages/inventory/incoming-confirmation.php') ?>" aria-current="page"><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
          <a class="nav-item" href="<?= app_url('/pages/inventory/incident-report.php') ?>"><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
          <a class="nav-item" href="<?= app_url('/pages/inventory/shelves.php') ?>"><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>
        <div class="nav-section-label">Settings</div>
          <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>
      </nav>

      <div class="sidebar-footer">
        <div class="user-card">
          <div class="user-avatar"><?= h(strtoupper(substr($user['first_name'] ?? 'U', 0, 1))) ?></div>
          <div class="user-info">
            <div class="user-name"><?= h(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'User') ?></div>
            <div class="user-role">Inventory</div>
          </div>
        </div>
      </div>
    </aside>
  <?php endif; ?>

  <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

  <!-- MAIN CONTENT -->
  <main class="main-content">

    <header class="top-header">
      <div class="header-left">
        <button class="menu-toggle" id="menu-toggle" aria-label="Toggle sidebar"><span class="ui-icon icon-menu" aria-hidden="true"></span></button>
        <div>
          <div class="page-title">Incoming Confirmation</div>
          <div class="page-breadcrumb"><span>Main</span><span>&rsaquo;</span><span>Incoming Confirmation</span></div>
        </div>
      </div>
      <?php require __DIR__ . '/notification-bell.php'; ?>
    </header>

    <div class="page-content">

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon ui-icon icon-pending" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="pending-count">0</div>
            <div class="stat-label">Pending Deliveries</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon ui-icon icon-check" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="checked-count">0</div>
            <div class="stat-label">Checked Deliveries</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon ui-icon icon-inventory" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="total-count">0</div>
            <div class="stat-label">Total Deliveries</div>
          </div>
        </div>
      </div>

      <!-- Confirmation Table Panel -->
      <div class="panel-card">
        <div class="panel-header">
          <div>
            <div class="panel-title">Confirm Checked Incoming Items</div>
            <div class="panel-subtitle">Approve checker-verified deliveries to add quantity to inventory. Reject records that should not affect stock.</div>
          </div>
          <div class="panel-actions">
            <button type="button" class="btn btn-primary" id="refresh-btn">
              <span class="ui-icon icon-reports" aria-hidden="true"></span>
              Refresh
            </button>
          </div>
        </div>

        <!-- Tabs -->
        <div class="panel-tabs" role="tablist" aria-label="Record filter tabs">
          <button class="panel-tab active" role="tab" aria-selected="true" aria-controls="tab-panel-all" id="tab-all" data-tab="all">
            All
            <span class="tab-count" id="tab-all-count">0</span>
          </button>
          <button class="panel-tab" role="tab" aria-selected="false" aria-controls="tab-panel-pending" id="tab-pending" data-tab="pending">
            Pending
            <span class="tab-count" id="tab-pending-count">0</span>
          </button>
          <button class="panel-tab" role="tab" aria-selected="false" aria-controls="tab-panel-checked" id="tab-checked" data-tab="checked">
            Checked
            <span class="tab-count" id="tab-checked-count">0</span>
          </button>
        </div>

        <!-- All Tab Panel -->
        <div class="tab-panel" id="tab-panel-all" role="tabpanel" aria-labelledby="tab-all">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Supplier</th>
                  <th>Items</th>
                  <th>Total Qty</th>
                  <th>Status</th>
                  <th>Checker</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="all-confirm-table">
                <tr><td colspan="7" class="empty">Loading&hellip;</td></tr>
              </tbody>
            </table>
          </div>
          <div class="table-pagination" id="all-pagination"></div>
        </div>

        <!-- Pending Tab Panel -->
        <div class="tab-panel hidden" id="tab-panel-pending" role="tabpanel" aria-labelledby="tab-pending">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Supplier</th>
                  <th>Items</th>
                  <th>Total Qty</th>
                  <th>Status</th>
                  <th>Checker</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="pending-confirm-table">
                <tr><td colspan="7" class="empty">Loading&hellip;</td></tr>
              </tbody>
            </table>
          </div>
          <div class="table-pagination" id="pending-pagination"></div>
        </div>

        <!-- Checked Tab Panel -->
        <div class="tab-panel hidden" id="tab-panel-checked" role="tabpanel" aria-labelledby="tab-checked">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Supplier</th>
                  <th>Items</th>
                  <th>Total Qty</th>
                  <th>Status</th>
                  <th>Checker</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="checked-confirm-table">
                <tr><td colspan="7" class="empty">Loading&hellip;</td></tr>
              </tbody>
            </table>
          </div>
          <div class="table-pagination" id="checked-pagination"></div>
        </div>

      </div>

    </div><!-- /.page-content -->
  </main>
</div><!-- /.app-wrapper -->

<!-- Incoming Confirmation Details Modal -->
<div class="modal-backdrop" id="confirm-details-modal">
  <div class="modal">
    <div class="modal-header">
      <div>
        <h3 id="confirm-details-title">Delivery Details</h3>
        <p id="confirm-details-subtitle">All items in this delivery.</p>
      </div>
      <button type="button" id="confirm-details-close">&#x2715;</button>
    </div>
    <div id="confirm-details-body" class="review-items-list">
      <p class="empty">Loading&hellip;</p>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="confirm-details-done">Close</button>
    </div>
  </div>
</div>

<!-- Incoming Item Action Modal (confirm/reject a single item with a note) -->
<div class="modal-backdrop" id="confirm-action-modal">
  <form class="modal" id="confirm-action-form">
    <div class="modal-header">
      <div>
        <h3 id="confirm-action-title">Confirm Item</h3>
        <p id="confirm-action-subtitle">Review the item before applying the action.</p>
      </div>
      <button type="button" id="confirm-action-close">&#x2715;</button>
    </div>
    <input type="hidden" id="confirm-action-item-id">
    <input type="hidden" id="confirm-action-type">
    <div class="action-item-summary" id="confirm-action-summary"></div>
    <div class="form-group">
      <label id="confirm-action-note-label">Note (optional)</label>
      <textarea rows="3" id="confirm-action-notes" placeholder="Add a note for this decision."></textarea>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="confirm-action-cancel">Cancel</button>
      <button type="submit" class="btn btn-primary" id="confirm-action-submit">Confirm</button>
    </div>
  </form>
</div>

<div id="toast-container" aria-live="polite"></div>

<script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script>
<script src="<?= app_url('/assets/js/modules/checker/incoming-confirmation.js') ?>"></script>
</body>
</html>
