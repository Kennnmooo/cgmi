<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['admin']);
$user = current_user();
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CGMI.IMS   Inventory Reports</title>
  <meta name="description" content="Inventory Reports">
  
  <script src="<?= app_url('/assets/lib/jquery/jquery-3.7.1.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= app_url('/assets/fonts/montserrat.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/admin/reports.css') ?>">
  <style>
    /* ── Inventory Report Page Styles ── */
    .page-content { padding: 20px; }
    .toolbar { display:flex; gap:12px; align-items:end; justify-content:space-between; flex-wrap:wrap; margin-bottom:18px; }
    .filter-form { display:flex; gap:12px; align-items:end; flex-wrap:wrap; }
    .field { display:flex; flex-direction:column; gap:6px; }
    .field label { font-size:12px; font-weight:700; color:#52607a; text-transform:uppercase; letter-spacing:.04em; }
    .field input { height:44px; border:1px solid #dbe3f0; border-radius:12px; padding:0 14px; font:inherit; background:#fff; color:#183153; min-width:140px; }
    .actions { display:flex; gap:10px; flex-wrap:wrap; }
    .btn { height:44px; border:none; border-radius:12px; padding:0 16px; font:700 14px/44px 'Montserrat',sans-serif; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; }
    .btn-primary { background:#1f4da0; color:#fff; }
    .btn-secondary { background:#edf2fb; color:#1f4da0; }
    .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:20px; }
    .stat-card, .panel { background:#fff; border-radius:18px; padding:18px; box-shadow:0 8px 24px rgba(15,23,42,.08); }
    .stat-card { border-top:4px solid #4f79c7; }
    .stat-label { font-size:13px; color:#6b7280; margin-bottom:6px; }
    .stat-value { font-size:28px; font-weight:800; color:#0f172a; }
    .report-layout { display:grid; grid-template-columns:1.1fr 1fr; gap:20px; margin-bottom:20px; }
    .report-table { width:100%; border-collapse:collapse; }
    .report-table th, .report-table td { padding:12px 10px; border-bottom:1px solid #e5e7eb; font-size:13px; text-align:left; vertical-align:top; }
    .report-table th { font-size:12px; text-transform:uppercase; color:#71809c; letter-spacing:.04em; }
    .panel h3 { margin:0 0 14px; font-size:22px; color:#16315f; }
    .empty-state { text-align:center; color:#6b7280; padding:20px; }
    .bar-row { display:grid; grid-template-columns:160px 1fr 120px; gap:12px; align-items:center; margin-bottom:10px; }
    .bar-track { background:#e5e7eb; border-radius:999px; height:12px; overflow:hidden; }
    .bar-fill { background:#10b981; height:100%; border-radius:999px; }
    .bar-label, .bar-value { font-size:12px; color:#475569; }
    .user-avatar { display:flex; align-items:center; justify-content:center; }
    .section-note { font-size:13px; color:#64748b; margin:-6px 0 14px; }
    .panel-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; }
    .muted { font-size:12px; color:#7a879f; }
    .table-responsive { overflow:auto; -webkit-overflow-scrolling:touch; }
    .badge { display:inline-flex; align-items:center; gap:6px; background:#eef4ff; color:#1f4da0; border-radius:999px; padding:4px 10px; font-size:11px; font-weight:700; text-transform:uppercase; }
    .print-note { display:none; }

    /* ── Tablet: collapse 2-col report layout ── */
    @media (max-width: 980px) {
      .report-layout { grid-template-columns: 1fr; }
      .bar-row { grid-template-columns: 1fr; }
      .bar-value { text-align: left; }
    }

    /* ── Tablet portrait: stack filter form ── */
    @media (max-width: 768px) {
      .page-content { padding: 14px; }
      .toolbar { flex-direction: column; align-items: stretch; gap: 10px; }
      .filter-form { flex-direction: column; align-items: stretch; gap: 10px; width: 100%; }
      .field input { min-width: 0; width: 100%; font-size: 16px; /* prevent iOS zoom */ }
      .actions { width: 100%; }
      .actions .btn { flex: 1; justify-content: center; min-width: 0; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
      .stat-value { font-size: 22px; }
      .panel { padding: 14px; }
      .panel h3 { font-size: 18px; }
      .bar-row { grid-template-columns: 1fr; }
      .bar-value { text-align: left; }
    }

    /* ── Mobile: single column everything ── */
    @media (max-width: 480px) {
      .page-content { padding: 10px; }
      .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
      .stat-card { padding: 14px; }
      .stat-value { font-size: 20px; }
      .stat-label { font-size: 12px; }
      .report-table th, .report-table td { padding: 8px 6px; font-size: 12px; }
    }

    @media (max-width: 360px) {
      .stats-grid { grid-template-columns: 1fr; }
    }

    @media print {
      body { background: #fff; }
      .sidebar, .top-header, .toolbar, .sidebar-overlay { display: none !important; }
      .main-content { margin: 0 !important; width: 100% !important; }
      .page-content { padding: 0; }
      .panel, .stat-card { box-shadow: none; border: 1px solid #d7deea; }
      .print-note { display: block; margin-bottom: 16px; color: #42526b; }
    }
  </style>
</head>
<body>
<div class="app-wrapper">
  <aside class="sidebar" id="sidebar" role="navigation" aria-label="Main Navigation">
    <div class="sidebar-logo"><div class="logo-icon ui-icon icon-store" aria-hidden="true"></div><div class="logo-text">Calamba General Merchandise Inc.<span>Hardware &amp; Supply</span></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">main</div>
      <a class="nav-item" href="<?= app_url('/pages/admin/dashboard.php') ?>"><span class="nav-icon ui-icon icon-dashboard" aria-hidden="true"></span>Dashboard</a>
      <a class="nav-item active" href="<?= app_url('/pages/admin/inventory-report.php') ?>"><span class="nav-icon ui-icon icon-reports" aria-hidden="true"></span>Inventory Report</a>
      <div class="nav-section-label">inventory</div>
      <a class="nav-item" href="<?= app_url('/pages/admin/inventory-admin.php') ?>"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Inventory</a>
        <a class="nav-item" href="<?= app_url('/pages/admin/incoming-confirmation.php') ?>"><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
      <a class="nav-item" href="<?= app_url('/pages/admin/shelves-admin.php') ?>"><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>
      <div class="nav-section-label">management</div>
      <a class="nav-item" href="<?= app_url('/pages/admin/incident-report.php') ?>"><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
      <a class="nav-item" href="<?= app_url('/pages/admin/user-management.php') ?>"><span class="nav-icon ui-icon icon-users" aria-hidden="true"></span>User Management</a>
      <a class="nav-item" href="<?= app_url('/pages/admin/suppliers.php') ?>"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Suppliers</a>
      <div class="nav-section-label">Settings</div>
      <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>
    </nav>
    <div class="sidebar-footer"><div class="user-card"><div class="user-avatar"><?= h(strtoupper(substr(($user['first_name'] ?? 'U'), 0, 1))) ?></div><div class="user-info"><div class="user-name"><?= h(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'User') ?></div><div class="user-role"><?= h(ucfirst($user['role'] ?? 'admin')) ?></div></div></div></div>
  </aside>
  <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
  <main class="main-content">
    <header class="top-header"><div class="header-left"><button class="menu-toggle" id="menu-toggle" aria-label="Toggle sidebar"><span class="ui-icon icon-menu" aria-hidden="true"></span></button><div><div class="page-title">Inventory Reports</div><div class="page-breadcrumb"><span>Main > Reports > Inventory Reports</span></div></div></div><?php require __DIR__ . '/../shared/notification-bell.php'; ?>
    </header>
    <div class="page-content">
      <div class="print-note">CGMI Inventory Report</div>
      <div class="toolbar">
        <form class="filter-form" id="inventory-filter-form">
          <div class="field"><label for="inventory-start-date">Start date</label><input type="date" id="inventory-start-date" value="<?= h($monthStart) ?>"></div>
          <div class="field"><label for="inventory-end-date">End date</label><input type="date" id="inventory-end-date" value="<?= h($today) ?>"></div>
          <div class="actions">
            <button class="btn btn-primary" type="submit">View report</button>
            <button class="btn btn-secondary" type="button" id="inventory-print-btn">Print</button>
            <a class="btn btn-secondary" href="#" id="inventory-download-btn">Download CSV</a>
          </div>
        </form>
      </div>

      <div class="stats-grid">
        <div class="stat-card"><div class="stat-label">Inventory records</div><div class="stat-value" data-kpi="item-count">0</div></div>
        <div class="stat-card"><div class="stat-label">Active items</div><div class="stat-value" data-kpi="active-count">0</div></div>
        <div class="stat-card"><div class="stat-label">Units on hand</div><div class="stat-value" data-kpi="total-units">0</div></div>
        <div class="stat-card"><div class="stat-label">Stock value</div><div class="stat-value" data-kpi="stock-value"> 0.00</div></div>
        <div class="stat-card"><div class="stat-label">Low-stock items</div><div class="stat-value" data-kpi="low-stock-count">0</div></div>
        <div class="stat-card"><div class="stat-label">Out of stock</div><div class="stat-value" data-kpi="out-of-stock-count">0</div></div>
      </div>

      <div class="report-layout">
        <section class="panel">
          <div class="panel-head"><div><h3>Inventory value by category</h3><div class="section-note">Current stock value grouped by category.</div></div><div class="muted">Snapshot</div></div>
          <div id="category-bars"><div class="empty-state">Loading </div></div>
        </section>
        <section class="panel">
          <div class="panel-head"><div><h3>Category summary</h3><div class="section-note">Current inventory totals by category.</div></div></div>
          <div class="table-responsive"><table class="report-table"><thead><tr><th>Category</th><th>Items</th><th>Units</th><th>Value</th></tr></thead><tbody id="category-summary-body"><tr><td colspan="4" class="empty-state">Loading </td></tr></tbody></table></div>
        </section>
      </div>

      <div class="report-layout">
        <section class="panel">
          <div class="panel-head"><div><h3>Stock movement activity</h3><div class="section-note">Inventory changes that happened within the selected date range.</div></div><div class="muted" id="inventory-range-label"> </div></div>
          <div class="table-responsive"><table class="report-table"><thead><tr><th>Date</th><th>SKU</th><th>Item</th><th>Type</th><th>Change</th><th>After</th><th>Reference</th></tr></thead><tbody id="movement-body"><tr><td colspan="7" class="empty-state">Loading </td></tr></tbody></table></div>
        </section>
        <section class="panel">
          <div class="panel-head"><div><h3>Movement summary</h3><div class="section-note">Counts of activity types within the selected date range.</div></div></div>
          <div class="table-responsive"><table class="report-table"><thead><tr><th>Type</th><th>Entries</th><th>Total change</th></tr></thead><tbody id="movement-summary-body"><tr><td colspan="3" class="empty-state">Loading </td></tr></tbody></table></div>
        </section>
      </div>


      <section class="panel">
        <div class="panel-head"><div><h3>Filtered inventory report</h3><div class="section-note">Detailed inventory activity for the selected date range.</div></div><div class="muted" id="inventory-filtered-range-label"> </div></div>
        <div class="table-responsive"><table class="report-table"><thead><tr><th>Date</th><th>SKU</th><th>Item</th><th>Category</th><th>Movement</th><th>Change</th><th>After</th><th>Reference</th></tr></thead><tbody id="filtered-inventory-body"><tr><td colspan="8" class="empty-state">Loading </td></tr></tbody></table></div>
      </section>

      <section class="panel">
        <div class="panel-head"><div><h3>Low-stock items</h3><div class="section-note">Current items at or below their reorder level.</div></div><span class="badge">Restock watch</span></div>
        <div class="table-responsive"><table class="report-table"><thead><tr><th>SKU</th><th>Item</th><th>Category</th><th>Qty</th><th>Reorder</th><th>Price</th></tr></thead><tbody id="low-stock-body"><tr><td colspan="6" class="empty-state">Loading </td></tr></tbody></table></div>
      </section>
    </div>
  </main>
</div>
<script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script>
<script>
  window.CGMI_BASE_URL = <?= json_encode(app_base_path()); ?>;
  window.CGMI_INVENTORY_REPORT_API = <?= json_encode(app_url('/api/reports/inventory_summary.php')); ?>;
  window.CGMI_INVENTORY_EXPORT_API = <?= json_encode(app_url('/api/reports/inventory_export.php')); ?>;
</script>
<script src="<?= app_url('/assets/js/modules/inventory-report/report.js') ?>"></script>
</body>
</html>
