<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['inventory']);
$user = current_user();
$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Inventory User';
$role = ucfirst((string)($user['role'] ?? 'inventory'));
$avatar = 'U';
if ($fullName !== '') {
    $parts = preg_split('/\s+/', $fullName) ?: [];
    $avatar = strtoupper(substr(($parts[0] ?? 'U'), 0, 1) . substr(($parts[1] ?? ''), 0, 1));
    $avatar = $avatar !== '' ? $avatar : 'U';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CGMI.IMS   Inventory Dashboard</title>
  <meta name="description" content="Hardware & Construction Supply Inventory Dashboard">
  
  
  
  
    <link rel="stylesheet" href="<?= app_url('/assets/fonts/montserrat.css') ?>">
  <script src="<?= app_url('/assets/lib/highCharts/scripts/vendor/highcharts.js') ?>"></script>
  <script src="<?= app_url('/assets/lib/highCharts/scripts/vendor/accessibility.js') ?>"></script>
  <script src="<?= app_url('/assets/lib/highCharts/scripts/vendor/exporting.js') ?>"></script>
  <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/admin/dashboard.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/inventory/dashboard.css') ?>">
</head>
<body>
<div class="app-wrapper">

  <!--  SIDEBAR  -->
  <aside class="sidebar" id="sidebar" role="navigation" aria-label="Main Navigation">
    <div class="sidebar-logo">
      <div class="logo-icon ui-icon icon-store" aria-hidden="true"></div>
      <div class="logo-text">Calamba General Merchandise Inc.<span>Hardware &amp; Supply</span></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
        <a class="nav-item active" href="<?= app_url('/pages/inventory/dashboard.php') ?>" aria-current="page"><span class="nav-icon ui-icon icon-dashboard" aria-hidden="true"></span>Dashboard</a>
        <a class="nav-item" href="<?= app_url('/pages/inventory/inventory.php') ?>"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Inventory</a>
        <a class="nav-item" href="<?= app_url('/pages/inventory/incoming-confirmation.php') ?>"><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
        <a class="nav-item" href="<?= app_url('/pages/inventory/incident-report.php') ?>"><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
        <a class="nav-item" href="<?= app_url('/pages/inventory/shelves.php') ?>"><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>
      <div class="nav-section-label">Settings</div>
        <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-card">
        <div class="user-avatar"><?= h($avatar) ?></div>
        <div class="user-info">
          <div class="user-name"><?= h($fullName) ?></div>
          <div class="user-role"><?= h($role) ?></div>
        </div>
      </div>
    </div>
  </aside>

  <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

  <!--  MAIN CONTENT  -->
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

      <!-- KPI CARDS -->
      <div class="metrics-grid" id="metrics-grid"></div>

      <!--                                     REPORT CATEGORY TABS                                     -->
      <nav class="report-nav" id="report-nav" role="tablist" aria-label="Dashboard report categories">
        <span class="report-nav-slider" id="report-nav-slider" aria-hidden="true"></span>
        <button type="button" class="report-nav-btn active" data-target="report-inventory">Inventory</button>
        <button type="button" class="report-nav-btn" data-target="report-shelves">Shelves</button>
        <button type="button" class="report-nav-btn" data-target="report-incoming">Incoming</button>
        <button type="button" class="report-nav-btn" data-target="report-incidents">Incidents</button>
      </nav>

      <!-- ── INVENTORY  ─────────── -->
      <section class="report-panel active" id="report-inventory" role="tabpanel">
        <div class="section-title"><span class="section-icon ui-icon icon-inventory" aria-hidden="true"></span> Inventory Overview</div>
        <div class="f-body">
          <div class="f-left">
            <div class="card">
              <div class="card-header">
                <span class="card-title">Stock Units by Category</span>
                <span class="pill green">Live</span>
              </div>
              <div class="chart-box-lg" id="ch-stock-levels"></div>
            </div>
            <div class="card">
              <div class="card-header">
                <span class="card-title">Stock Distribution by Location</span>
                <span class="pill blue">Warehouse · Store · Shelf</span>
              </div>
              <div class="chart-box" id="ch-distribution"></div>
            </div>
            <div class="card">
              <div class="card-header"><span class="card-title">Outgoing Stock by Purpose</span></div>
              <div class="chart-box" id="ch-outgoing-purpose"></div>
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

      <!-- ── SHELVES  ───────────── -->
      <section class="report-panel" id="report-shelves" role="tabpanel">
        <div class="section-title"><span class="section-icon ui-icon icon-shelves" aria-hidden="true"></span> Shelf Monitoring</div>
        <div class="f-body">
          <div class="f-left">
            <div class="card">
              <div class="card-header">
                <span class="card-title">Shelf Weight Utilization</span>
                <span class="pill blue">IoT load vs threshold</span>
              </div>
              <div class="chart-box-lg" id="ch-shelf-weight"></div>
            </div>
            <div class="card">
              <div class="card-header"><span class="card-title">Shelf Status Breakdown</span></div>
              <div class="chart-box" id="ch-shelf-status"></div>
            </div>
            <div class="card">
              <div class="card-header"><span class="card-title">Shelves by Location Layer</span></div>
              <div class="chart-box" id="ch-shelf-location"></div>
            </div>
          </div>
          <div class="f-right">
            <div class="card">
              <div class="card-header"><span class="card-title">Shelf Health Overview</span></div>
              <div id="shelf-health-bars"></div>
            </div>
            <div class="card">
              <div class="card-header"><span class="card-title">Low-Stock Shelves</span></div>
              <div class="refill-list" id="shelf-alert-list"></div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── INCOMING CONFIRMATION ────────────────────────────── -->
      <section class="report-panel" id="report-incoming" role="tabpanel">
        <div class="section-title"><span class="section-icon ui-icon icon-check" aria-hidden="true"></span> Incoming Confirmation</div>
        <div class="chart-duo">
          <div class="card">
            <div class="card-header"><span class="card-title">Item Condition Breakdown</span></div>
            <div class="chart-box" id="ch-incoming-condition"></div>
          </div>
          <div class="card">
            <div class="card-header"><span class="card-title">Confirmation Status</span></div>
            <div class="chart-box" id="ch-incoming-status"></div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <span class="card-title">Pending Items Awaiting Confirmation</span>
            <span class="pill orange">Action needed</span>
          </div>
          <table class="data-table">
            <thead><tr><th>Item</th><th>Qty</th><th>Condition</th><th>Supplier</th></tr></thead>
            <tbody id="pending-incoming-tbody"></tbody>
          </table>
        </div>
      </section>

      <!-- ── INCIDENT REPORTS  ──── -->
      <section class="report-panel" id="report-incidents" role="tabpanel">
        <div class="section-title"><span class="section-icon ui-icon icon-incident" aria-hidden="true"></span> Incident Reports</div>
        <div class="f-body">
          <div class="f-left">
            <div class="card">
              <div class="card-header"><span class="card-title">Reports by Status</span></div>
              <div class="chart-box" id="ch-incident-status"></div>
            </div>
            <div class="card">
              <div class="card-header"><span class="card-title">Reports by Category</span></div>
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

    </div>

    <div id="toast-container" aria-live="polite" aria-atomic="false"></div>
  </main>
</div>

<script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script>
<script>
window.CGMI_INVENTORY_DASHBOARD_API = <?= json_encode([
  'summary' => app_url('/api/dashboard/inventory_summary.php'),
], JSON_UNESCAPED_SLASHES) ?>;
window.CGMI_USER = <?= json_encode($_SESSION['user'] ?? []) ?>;
</script>
<script src="<?= app_url('/assets/js/modules/dashboard/inventory-dashboard.js') ?>"></script>
</body>
</html>
