<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['super-admin']);
$user = current_user() ?: [];
$sidebarRole = 'super-admin';
$sidebarActive = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CGMI.IMS   Dashboard</title>
  <meta name="description" content="Hardware & Construction Supply Dashboard">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <script src="https://code.highcharts.com/highcharts.js"></script>
  <script src="https://code.highcharts.com/modules/accessibility.js"></script>
  <script src="https://code.highcharts.com/modules/exporting.js"></script>
  <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/admin/dashboard.css') ?>">
</head>
<body>
<div class="app-wrapper">
  <?php require __DIR__ . '/../shared/sidebar.php'; ?>

  <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

  <main class="main-content">
    <header class="top-header">
      <div class="header-left">
        <button class="menu-toggle" id="menu-toggle" aria-label="Toggle sidebar"><span class="ui-icon icon-menu" aria-hidden="true"></span></button>
        <div>
          <div class="page-title">Dashboard</div>
          <div class="page-breadcrumb"><span>Main - Dashboard</span></div>
        </div>
      </div>
    <?php require __DIR__ . '/../shared/notification-bell.php'; ?>
    </header>

    <div class="page-content dashboard-page">
      <div class="metrics-grid" id="metrics-grid"></div>

      <nav class="report-nav" id="report-nav" role="tablist" aria-label="Analytical report categories">
        <span class="report-nav-slider" id="report-nav-slider" aria-hidden="true"></span>
        <button type="button" class="report-nav-btn active" data-target="report-inventory">Inventory</button>
        <button type="button" class="report-nav-btn" data-target="report-shelf">Shelf</button>
        <button type="button" class="report-nav-btn" data-target="report-alerts">Alert &amp; Incidents</button>
        <button type="button" class="report-nav-btn" data-target="report-users">User &amp; Audit</button>
      </nav>

      <section class="report-panel active" id="report-inventory" role="tabpanel">
        <div class="section-title"><span class="section-icon ui-icon icon-inventory" aria-hidden="true"></span> Inventory Reports</div>
        <div class="f-body">
          <div class="f-left">
            <div class="card">
              <div class="card-header">
                <span class="card-title">Current Stock Levels by Category</span>
                <span class="pill green">Live</span>
              </div>
              <div class="chart-box-lg" id="ch-stock-levels"></div>
            </div>

            <div class="card">
              <div class="card-header"><span class="card-title">Top Movers vs Refill Priority</span></div>
              <div class="chart-box" id="ch-movers"></div>
            </div>

            <div class="card">
              <div class="card-header">
                <span class="card-title">Stock Distribution by Location</span>
                <span class="pill blue">Warehouse Â· Store Â· Shelf</span>
              </div>
              <div class="chart-box" id="ch-distribution"></div>
            </div>
          </div>

          <div class="f-right">
            <div class="card">
              <div class="card-header"><span class="card-title">Stock Status Overview</span></div>
              <div id="stock-status-bars"></div>
              <div id="ch-stock-pie" style="min-height:220px;"></div>
            </div>

            <div class="card">
              <div class="card-header"><span class="card-title"><span class="mini-inline-icon ui-icon icon-incident" aria-hidden="true"></span>Refill Alerts</span></div>
              <div class="refill-list" id="refill-list"></div>
            </div>

            <div class="card">
              <div class="card-header"><span class="card-title">Supplier Delivery Quality</span></div>
              <div class="chart-box" id="ch-supplier-quality"></div>
            </div>
          </div>
        </div>
      </section>

      <section class="report-panel" id="report-shelf" role="tabpanel">
        <div class="section-title"><span class="section-icon ui-icon icon-shelves" aria-hidden="true"></span> Shelf Reports</div>
        <div class="f-body">
          <div class="f-left">
            <div class="card">
              <div class="card-header"><span class="card-title">Shelf Status</span></div>
              <div class="shelf-grid" id="shelf-grid"></div>
            </div>
          </div>

          <div class="f-right">
            <div class="card">
              <div class="card-header"><span class="card-title">Shelf Weight Distribution</span></div>
              <div class="chart-box" id="ch-shelf-weight"></div>
            </div>
          </div>
        </div>
      </section>

      <section class="report-panel" id="report-alerts" role="tabpanel">
        <div class="section-title"><span class="section-icon ui-icon icon-incident" aria-hidden="true"></span> Alerts &amp; Incidents</div>
        <div class="f-body">
          <div class="f-left">
            <div class="card">
              <div class="card-header"><span class="card-title">Most Common Alert Types</span></div>
              <div class="chart-box" id="ch-alert-types"></div>
            </div>

            <div class="card">
              <div class="card-header"><span class="card-title">Resolved vs Unresolved (7 days)</span></div>
              <div class="chart-box" id="ch-resolved"></div>
            </div>

            <div class="card">
              <div class="card-header"><span class="card-title">Incidents by Category</span></div>
              <div class="chart-box" id="ch-incident-category"></div>
            </div>
          </div>

          <div class="f-right">
            <div class="card">
              <div class="card-header"><span class="card-title">Recent Incidents</span></div>
              <div class="incident-list" id="incident-list"></div>
            </div>
          </div>
        </div>
      </section>

      <section class="report-panel" id="report-users" role="tabpanel">
        <div class="section-title"><span class="section-icon ui-icon icon-users" aria-hidden="true"></span> User &amp; Audit Reports</div>
        <div class="f-body">
          <div class="f-left">
            <div class="card">
              <div class="card-header"><span class="card-title">Users by Role</span></div>
              <div class="chart-box" id="ch-user-actions"></div>
            </div>

            <div class="card">
              <div class="card-header">
                <span class="card-title">Data-Quality Flags</span>
                <span class="pill orange">Governance</span>
              </div>
              <div class="chart-box" id="ch-data-quality"></div>
            </div>

            <div class="card">
              <div class="card-header"><span class="card-title">Stock-Change Audit Trail</span></div>
              <div class="act-list" id="audit-list"></div>
            </div>
          </div>

          <div class="f-right">
            <div class="card">
              <div class="card-header"><span class="card-title">Account Security Watch</span></div>
              <div class="refill-list" id="security-list"></div>
            </div>

            <div class="card">
              <div class="card-header"><span class="card-title">Recent Activity Log</span></div>
              <div class="act-list" id="act-list"></div>
            </div>
          </div>
        </div>
      </section>

    </div>

    <div id="toast-container" aria-live="polite" aria-atomic="false"></div>
  </main>
</div>

<script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script>
<script>
window.CGMI_DASHBOARD_ENDPOINTS = <?= json_encode([
  'dashboard' => app_url('/api/dashboard/summary.php'),
  'inventory' => app_url('/api/reports/inventory_summary.php'),
  'incidents' => app_url('/api/incidents/list.php'),
  'users' => app_url('/api/users/list.php'),
  'shelves' => app_url('/api/shelves/list.php'),
  'governance' => app_url('/api/dashboard/superadmin_summary.php'),
], JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= app_url('/assets/js/modules/dashboard/dashboard.js') ?>"></script>
</body>
</html>
