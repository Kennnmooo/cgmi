<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['super-admin']);
$user = current_user() ?: [];
$sidebarRole = 'super-admin';
$sidebarActive = 'incidents';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CGMI.IMS   Incident Reports</title>
  <meta name="description" content="Hardware & Construction Supply Incident Reports Panel">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/admin/incidents.css') ?>">
</head>

<body>
  <div class="app-wrapper">

    <!--  SIDEBAR  -->
    <?php require __DIR__ . '/../shared/sidebar.php'; ?>

    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

    <!--  MAIN CONTENT  -->
    <main class="main-content">

      <!-- â”€â”€ Top Header â”€â”€ -->
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

      <div class="page-content">

        <!--    STATISTICS ROW   -->
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

        <!--  ¡ REPORTS DISPLAY PANEL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
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

              <!--  ¢ SORT TOGGLE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
              <button class="sort-btn" id="sort-btn" type="button" aria-label="Toggle sort order">
                Sort <span id="sort-label">Latest First</span> ▾
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
            <button class="reports-tab" role="tab" aria-selected="false" data-tab="trash" id="tab-trash">
              Trash <span class="tab-count" id="tab-count-trash">0</span>
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

  <!--  £ DETAIL MODAL -->
  <div class="modal-backdrop" id="modal-backdrop" role="dialog"
       aria-modal="true" aria-label="Incident Report Details" aria-hidden="true">
    <div id="modal-content"><!-- JS-rendered --></div>
  </div>

  <!-- TOAST CONTAINER -->
  <div id="toast-container" aria-live="polite" aria-atomic="false"></div>

  <script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script>
  <script>window.CGMI_BASE_URL = <?= json_encode(app_base_path()); ?>;</script>
  <script>window.CGMI_INCIDENTS_API = <?= json_encode(['list' => app_url('/api/incidents/list.php'), 'review' => app_url('/api/incidents/review.php')]); ?>;</script>
  <!-- Super-admin only: enable the UI-only Delete/Trash flow in place of Archive. -->
  <script>window.CGMI_INCIDENTS_TRASH = true;</script>
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
