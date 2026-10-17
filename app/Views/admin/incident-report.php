<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_login(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CGMI.IMS   Incident Reports</title>
  <meta name="description" content="Hardware & Construction Supply Incident Reports Panel">

  <!-- Google Fonts -->
  

  <!--
       CSS FILE ORDER:
       1. main.css        base variables, reset, layout, sidebar, shared components
       2. responsive.css   all breakpoints / media queries
       3. incident-reports.css   page-specific styles for the reports panel
  -->
    <link rel="stylesheet" href="<?= app_url('/assets/fonts/montserrat.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/admin/incidents.css') ?>">
</head>

<body>
  <div class="app-wrapper">

    <!--
         DO NOT MODIFY   sidebar layout is locked.
    -->
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
        <a class="nav-item active" href="<?= app_url('/pages/admin/incident-report.php') ?>"><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
        <a class="nav-item" href="<?= app_url('/pages/admin/user-management.php') ?>"><span class="nav-icon ui-icon icon-users" aria-hidden="true"></span>User Management</a>
        <a class="nav-item" href="<?= app_url('/pages/admin/suppliers.php') ?>"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Suppliers</a>
        <div class="nav-section-label">Settings</div>
        <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>
      </nav>

      <div class="sidebar-footer">
        <div class="user-card">
          <div class="user-avatar">JD</div>
          <div class="user-info">
            <div class="user-name">John Dela Cruz</div>
            <div class="user-role">Inventory Manager</div>
          </div>
        </div>
      </div>

    </aside>

    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

    <!-- MAIN CONTENT -->
    <main class="main-content">

      <!-- Top Header -->
      <header class="top-header">
        <div class="header-left">
          <button class="menu-toggle" id="menu-toggle" aria-label="Toggle sidebar"><span class="ui-icon icon-menu" aria-hidden="true"></span></button>
          <div>
            <div class="page-title">Incident Reports</div>
            <div class="page-breadcrumb"><span>Management > Incident Reports</span></div>
          </div>
        </div>
      <?php require __DIR__ . '/../shared/notification-bell.php'; ?>
    </header>

      <!-- 
           PAGE CONTENT   all modifications are within this div
      -->
      <div class="page-content">

        <!-- STATISTICS ROW -->
        <div class="reports-stats-row" role="region" aria-label="Report Status Summary">

          <div class="stat-card" style="--stat-accent:#1F3F74;--stat-bg:rgba(31,63,116,0.1)">
            <div class="stat-icon ui-icon icon-reports" aria-hidden="true"></div>
            <div class="stat-info">
              <div class="stat-value" id="stat-total">0</div>
              <div class="stat-label">Total Reports</div>
              <div class="stat-change neutral">All Reports</div>
            </div>
          </div>

          <div class="stat-card" style="--stat-accent:#16a34a;--stat-bg:rgba(22,163,74,0.1)">
            <div class="stat-icon ui-icon icon-check" aria-hidden="true"></div>
            <div class="stat-info">
              <div class="stat-value" id="stat-approved">0</div>
              <div class="stat-label">Checked Reports</div>
              <div class="stat-change up">Resolved</div>
            </div>
          </div>

          <div class="stat-card" style="--stat-accent:#d97706;--stat-bg:rgba(217,119,6,0.1)">
            <div class="stat-icon ui-icon icon-pending" aria-hidden="true"></div>
            <div class="stat-info">
              <div class="stat-value" id="stat-pending">0</div>
              <div class="stat-label">Pending Reports</div>
              <div class="stat-change down">Awaiting Review</div>
            </div>
          </div>

          <div class="stat-card" style="--stat-accent:#dc2626;--stat-bg:rgba(220,38,38,0.1)">
            <div class="stat-icon ui-icon icon-close" aria-hidden="true"></div>
            <div class="stat-info">
              <div class="stat-value" id="stat-rejected">0</div>
              <div class="stat-label">Rejected Reports</div>
              <div class="stat-change neutral">Declined</div>
            </div>
          </div>

        </div>
        <!-- /STATISTICS ROW -->

        <!-- REPORTS DISPLAY PANEL -->
        <div class="panel-card" role="region" aria-label="Incident Reports Panel">

          <!-- Panel Header -->
          <div class="panel-header">
            <div class="panel-title">
              Incident Reports
              <span class="panel-title-badge" id="reports-count-badge">0</span>
            </div>
            <div class="panel-actions">
              <!-- Date Filter -->
              <div class="date-filter-wrapper" role="group" aria-label="Date filter">
                <input type="date" class="form-control date-filter-input" id="date-filter" aria-label="Filter by date">
                <button class="btn btn-ghost btn-sm" id="clear-date-filter" style="display:none;">Clear</button>
              </div>

              <!-- REFRESH -->
              <button class="btn btn-ghost" id="refresh-btn" type="button" aria-label="Refresh reports">
                <span class="btn-inline-icon ui-icon icon-refresh" aria-hidden="true"></span>Refresh
              </button>

              <!-- SORT TOGGLE -->
              <button class="sort-btn" id="sort-btn" type="button" aria-label="Toggle sort order">
                Sort <span id="sort-label">Latest First</span> ?
              </button>
              <!-- /SORT TOGGLE -->
            </div>
          </div>

          <!-- Tab Navigation -->
          <div class="reports-tabs" role="tablist" aria-label="Report status filter">
            <button class="reports-tab active" role="tab" aria-selected="true" data-tab="pending" id="tab-pending">
              Pending <span class="tab-count" id="tab-count-pending">0</span>
            </button>
            <button class="reports-tab" role="tab" aria-selected="false" data-tab="approved" id="tab-approved">
              Checked <span class="tab-count" id="tab-count-approved">0</span>
            </button>
            <button class="reports-tab" role="tab" aria-selected="false" data-tab="rejected" id="tab-rejected">
              Rejected <span class="tab-count" id="tab-count-rejected">0</span>
            </button>

          </div>

          <!-- Report Cards Grid   populated by JS -->
          <div class="reports-grid" id="reports-grid" aria-label="Report cards list"></div>

        </div>
        <!-- /REPORTS DISPLAY PANEL -->

      </div>
      <!-- /PAGE CONTENT -->

    </main>
    <!-- /MAIN CONTENT -->

  </div>
  <!-- /APP WRAPPER -->

  <!-- DETAIL MODAL
       aria-modal + role="dialog" for accessibility.
       Inner content is injected by JS (openModal function).
  -->
  <div class="modal-backdrop" id="modal-backdrop" role="dialog"
       aria-modal="true" aria-label="Incident Report Details" aria-hidden="true">
    <div id="modal-content"><!-- JS-rendered --></div>
  </div>

  <!-- TOAST CONTAINER -->
  <div id="toast-container" aria-live="polite" aria-atomic="false"></div>

  <!-- 
       JS FILE ORDER:
       1. sidebar.js            sidebar toggle & dropdown (shared component)
       2. incident-reports.js   page-specific logic
   -->
  <script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script>
  <script>window.CGMI_BASE_URL = <?= json_encode(app_base_path()); ?>;</script>
  <script>window.CGMI_INCIDENTS_API = <?= json_encode(['list' => app_url('/api/incidents/list.php'), 'review' => app_url('/api/incidents/review.php')]); ?>;</script>
  <script src="<?= app_url('/assets/js/modules/incident-report-admin/incident-reports.js') ?>"></script>

  <script>
    window.CGMI_USER = <?php echo json_encode($_SESSION['user']); ?>;
    document.addEventListener('DOMContentLoaded', () => {
      const user = window.CGMI_USER || {};
      document.querySelectorAll('.user-name').forEach(el => el.textContent = ((user.first_name || '') + ' ' + (user.last_name || '')).trim() || 'User');
      document.querySelectorAll('.user-role').forEach(el => {
        const role = (user.role || '').toString();
        el.textContent = role ? role.charAt(0).toUpperCase() + role.slice(1) : 'User';
      });
      document.querySelectorAll('.user-avatar').forEach(el => {
        const first = (user.first_name || '').charAt(0).toUpperCase();
        const last = (user.last_name || '').charAt(0).toUpperCase();
        el.textContent = (first + last) || 'U';
      });
    });
  </script>

</body>
</html>
