<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['super-admin']);
$user = current_user() ?: [];
$sidebarRole = 'super-admin';
$sidebarActive = 'suppliers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CGMI.IMS   Supplier Management</title>
  <meta name="description" content="Hardware & Construction Supply Supplier Management Panel">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- jQuery -->
  <script src="<?= app_url('/assets/lib/jquery/jquery-3.7.1.min.js') ?>"></script>
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="<?= app_url('/assets/lib/dataTables/dataTables.css') ?>">
  <!-- DataTables JS -->
  <script src="<?= app_url('/assets/lib/dataTables/dataTables.js') ?>"></script>

  <!-- Design system + module CSS -->
  <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/admin/suppliers.css') ?>">
</head>
<body>
<div class="app-wrapper">

  <!--  SIDEBAR  -->
  <?php require __DIR__ . '/../shared/sidebar.php'; ?>

  <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

  <!--  MAIN CONTENT  -->
  <main class="main-content">

    <header class="top-header">
      <div class="header-left">
        <button class="menu-toggle" id="menu-toggle" aria-label="Toggle sidebar"><span class="ui-icon icon-menu" aria-hidden="true"></span></button>
        <div>
          <div class="page-title">Supplier Management</div>
          <div class="page-breadcrumb"><span>Management &gt; Suppliers</span></div>
        </div>
      </div>
    <?php require __DIR__ . '/../shared/notification-bell.php'; ?>
    </header>

    <div class="page-content">

      <!-- Stats -->
      <div class="stats-row" role="region" aria-label="Supplier Summary">
        <div class="stat-card" style="--stat-accent:#1F3F74;--stat-bg:rgba(31,63,116,0.1)">
          <div class="stat-icon ui-icon icon-inventory" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="stat-total">0</div>
            <div class="stat-label">Total Suppliers</div>
          </div>
        </div>
        <div class="stat-card" style="--stat-accent:#16a34a;--stat-bg:rgba(22,163,74,0.1)">
          <div class="stat-icon ui-icon icon-check" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="stat-active">0</div>
            <div class="stat-label">Active Suppliers</div>
          </div>
        </div>
        <div class="stat-card" style="--stat-accent:#dc2626;--stat-bg:rgba(220,38,38,0.1)">
          <div class="stat-icon ui-icon icon-off" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="stat-inactive">0</div>
            <div class="stat-label">Inactive Suppliers</div>
          </div>
        </div>
      </div>

      <!-- Supplier Table Panel -->
      <div class="panel-card">
        <div class="panel-header">
          <div class="panel-title"><span class="panel-title-icon ui-icon icon-inventory" aria-hidden="true"></span>Supplier Records <span class="panel-title-badge" id="supplier-count-badge">0 suppliers</span></div>
          <div class="panel-actions"><button class="btn btn-primary" id="add-supplier-btn"><span class="btn-inline-icon ui-icon icon-add" aria-hidden="true"></span>Add Supplier</button></div>
        </div>

        <!-- Search Bar -->
        <div class="supplier-search-bar">
          <div class="search-wrapper">
            <span class="search-icon ui-icon icon-search" aria-hidden="true"></span>
            <input type="search" id="supplier-search-input" placeholder="Search suppliers" aria-label="Search suppliers">
          </div>
        </div>

        <!-- Tabs -->
        <div class="supplier-tabs">
          <button class="tab-btn active" data-tab="active">Active Suppliers</button>
          <button class="tab-btn" data-tab="inactive">Inactive Suppliers</button>
        </div>

        <!-- Active Table -->
        <div class="dt-container tab-panel active" id="active-panel">
          <table id="supplier-table-active" class="display" role="grid" aria-label="Active Suppliers">
            <thead>
              <tr>
                <th>Supplier Name</th>
                <th>Address</th>
                <th>Phone Number</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

        <!-- Inactive Table -->
        <div class="dt-container tab-panel" id="inactive-panel">
          <table id="supplier-table-inactive" class="display" role="grid" aria-label="Inactive Suppliers">
            <thead>
              <tr>
                <th>Supplier Name</th>
                <th>Address</th>
                <th>Phone Number</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

    </div><!-- /.page-content -->
  </main>
</div><!-- /.app-wrapper -->

<!--  ADD / EDIT MODAL  -->
<div class="modal-backdrop" id="supplier-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title-text">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title-wrap">
        <div class="modal-icon ui-icon icon-inventory" id="modal-icon" aria-hidden="true"></div>
        <div>
          <div class="modal-title" id="modal-title-text">Add Supplier</div>
          <div class="modal-subtitle" id="modal-subtitle">Fill in the details to add a new supplier.</div>
        </div>
      </div>
      <button class="modal-close" id="modal-close-btn" aria-label="Close modal">&times;</button>
    </div>
    <div class="modal-body">
      <div class="modal-section-label">Supplier Information</div>
      <div class="form-grid">
        <div class="form-group span-2">
          <label class="form-label required" for="input-name">Supplier Name</label>
          <input class="form-control" type="text" id="input-name" placeholder="e.g. PrimeBuild Hardware" maxlength="50">
          <span class="field-error" id="err-name"></span>
        </div>
        <div class="form-group span-2">
          <label class="form-label required" for="input-address">Address</label>
          <input class="form-control" type="text" id="input-address" placeholder="e.g. 123 Main St, Calamba City" maxlength="255">
          <span class="field-error" id="err-address"></span>
        </div>
        <div class="form-group span-2">
          <label class="form-label required" for="input-phone">Phone Number</label>
          <input class="form-control" type="text" id="input-phone" placeholder="e.g. 09171234567" maxlength="11">
          <span class="field-error" id="err-phone"></span>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <span class="save-hint">Fields marked with <strong>*</strong> are required.</span>
      <button class="btn btn-ghost" id="modal-cancel-btn">Cancel</button>
      <button class="btn btn-primary" id="modal-save-btn">Save Supplier</button>
    </div>
  </div>
</div>

<!--  CONFIRM DIALOG  -->
<div class="confirm-overlay" id="confirm-overlay" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">
  <div class="confirm-box">
    <div class="confirm-icon ui-icon icon-question" id="confirm-icon" aria-hidden="true"></div>
    <div class="confirm-title" id="confirm-title">Are you sure?</div>
    <div class="confirm-msg" id="confirm-msg"></div>
    <div class="confirm-actions">
      <button class="btn btn-ghost" id="confirm-cancel-btn">Cancel</button>
      <button class="btn btn-danger" id="confirm-ok-btn">Delete</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="toast-container" aria-live="polite" aria-atomic="false"></div>

<script>
window.CGMI_SUPPLIERS_API = <?= json_encode([
    'list'   => app_url('/api/suppliers/list.php'),
    'save'   => app_url('/api/suppliers/save.php'),
    'delete' => app_url('/api/suppliers/delete.php'),
    'toggle' => app_url('/api/suppliers/toggle.php'),
]); ?>;
</script>
<script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script>
<script src="<?= app_url('/assets/js/modules/admin/suppliers.js') ?>"></script>
</body>
</html>
