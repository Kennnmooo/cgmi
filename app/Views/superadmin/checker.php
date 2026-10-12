<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['super-admin']);
$user = current_user() ?: [];
$sidebarRole = 'super-admin';
$sidebarActive = 'checker';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CGMI.IMS   Warehouse Checking</title>
  <meta name="description" content="Hardware & Construction Supply Checker Panel">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Design system + module CSS -->
  <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/modules/checker/main.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/modules/checker/responsive.css') ?>">
</head>
<body>
<div class="app-wrapper">

  <?php require __DIR__ . '/../shared/sidebar.php'; ?>

  <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

  <!--  MAIN CONTENT  -->
  <main class="main-content">

    <header class="top-header">
      <div class="header-left">
        <button class="menu-toggle" id="menu-toggle" aria-label="Toggle sidebar"><span class="ui-icon icon-menu" aria-hidden="true"></span></button>
        <div>
          <div class="page-title">Checker Module</div>
          <div class="page-breadcrumb"><span>Main &gt; Warehouse Incoming and Outgoing Verification</span></div>
        </div>
      </div>
    <?php require __DIR__ . '/../shared/notification-bell.php'; ?>
    </header>

    <div class="page-content">

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-card" style="--stat-accent:var(--blue-medium);--stat-bg:rgba(69,105,173,0.1)">
          <div class="stat-icon ui-icon icon-inventory" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="incoming-count">0</div>
            <div class="stat-label">Incoming Delivery Records</div>
          </div>
        </div>
        <div class="stat-card" style="--stat-accent:var(--accent-warning);--stat-bg:rgba(217,119,6,0.1)">
          <div class="stat-icon ui-icon icon-incoming" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="incoming-pending-count">0</div>
            <div class="stat-label">Pending Incoming Checks</div>
          </div>
        </div>
        <div class="stat-card" style="--stat-accent:var(--navy-mid);--stat-bg:rgba(31,63,116,0.1)">
          <div class="stat-icon ui-icon icon-outgoing" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="outgoing-count">0</div>
            <div class="stat-label">Outgoing Records</div>
          </div>
        </div>
        <div class="stat-card" style="--stat-accent:var(--accent-warning);--stat-bg:rgba(217,119,6,0.1)">
          <div class="stat-icon ui-icon icon-form" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="pending-count">0</div>
            <div class="stat-label">Pending Outgoing Checks</div>
          </div>
        </div>
        <div class="stat-card" style="--stat-accent:var(--accent-danger, #dc2626);--stat-bg:rgba(220,38,38,0.1)">
          <div class="stat-icon ui-icon icon-incident" aria-hidden="true"></div>
          <div class="stat-info">
            <div class="stat-value" id="rechecking-count">0</div>
            <div class="stat-label">For Re-checking</div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="checker-tabs">
        <button class="tab-btn active" data-tab="incoming"><span class="btn-inline-icon ui-icon icon-incoming" aria-hidden="true"></span>Incoming Deliveries</button>
        <button class="tab-btn" data-tab="outgoing"><span class="btn-inline-icon ui-icon icon-outgoing" aria-hidden="true"></span>Outgoing Warehouse Items</button>
        <button class="tab-btn" data-tab="rechecking"><span class="btn-inline-icon ui-icon icon-form" aria-hidden="true"></span>For Re-checking</button>
      </div>

      <!-- Incoming Panel -->
      <div class="panel-card tab-panel active" id="incoming-panel">
        <div class="panel-header">
          <div>
            <h2>Incoming Deliveries</h2>
            <p>Records stay pending until Admin/Inventory confirms them.</p>
          </div>
          <button type="button" class="btn btn-primary" id="toggle-incoming-form"><span class="btn-inline-icon ui-icon icon-add" aria-hidden="true"></span>New Delivery</button>
        </div>
        <div class="incoming-filter-tabs" role="tablist" aria-label="Incoming status filter">
          <button class="filter-tab active" data-filter="all">All</button>
          <button class="filter-tab" data-filter="Pending">Pending</button>
          <button class="filter-tab" data-filter="Checked">Checked</button>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Supplier</th><th>Items</th><th>Total Qty</th><th>Status</th><th>Checker</th><th>Action</th></tr></thead>
            <tbody id="incoming-table"><tr><td colspan="7" class="empty">Loading </td></tr></tbody>
          </table>
        </div>
        <div class="table-pagination" id="incoming-pagination"></div>
      </div>

      <!-- Outgoing Panel -->
      <div class="panel-card tab-panel" id="outgoing-panel">
        <div class="panel-header">
          <div>
            <h2>Outgoing Items from Warehouse</h2>
            <p>POS transactions marked as Warehouse Storage appear automatically. For manual outgoing, choose existing inventory items so records stay consistent with the inventory database.</p>
          </div>
          <button type="button" class="btn btn-primary" id="manual-outgoing-open"><span class="btn-inline-icon ui-icon icon-add" aria-hidden="true"></span>Add Manual Outgoing</button>
        </div>
        <div class="incoming-filter-tabs" role="tablist" aria-label="Outgoing status filter">
          <button class="filter-tab outgoing-filter-tab active" data-outgoing-filter="all">All</button>
          <button class="filter-tab outgoing-filter-tab" data-outgoing-filter="Pending">Pending</button>
          <button class="filter-tab outgoing-filter-tab" data-outgoing-filter="Checked">Checked</button>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Source</th><th>Date</th><th>Purpose</th><th>SKU</th><th>Items</th><th>Status</th><th>Action</th></tr></thead>
            <tbody id="outgoing-table"><tr><td colspan="7" class="empty">Loading </td></tr></tbody>
          </table>
        </div>
        <div class="table-pagination" id="outgoing-pagination"></div>
      </div>

      <!-- Re-checking Panel -->
      <div class="panel-card tab-panel" id="rechecking-panel">
        <div class="panel-header">
          <div>
            <h2>Items For Re-checking</h2>
            <p>Items approved by Admin/Inventory but marked as Damaged or Incomplete/Missing. These need to be re-verified before restocking.</p>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date Confirmed</th><th>Supplier</th><th>Item</th><th>Qty</th><th>Condition</th><th>Confirmed By</th><th>Notes</th><th>Action</th></tr></thead>
            <tbody id="rechecking-table"><tr><td colspan="8" class="empty">Loading </td></tr></tbody>
          </table>
        </div>
      </div>

    </div><!-- /.page-content -->
  </main>
</div><!-- /.app-wrapper -->

<!-- Incoming Delivery Modal -->
<div class="modal-backdrop" id="incoming-modal">
  <form class="modal" id="incoming-form">
    <div class="modal-header">
      <div>
        <h3>New Delivery</h3>
        <p>Log an incoming delivery. Records stay pending until Admin/Inventory confirms them.</p>
      </div>
      <button type="button" id="incoming-modal-close">&#x2715;</button>
    </div>
    <div class="form-group"><label>Supplier</label><select id="incoming-supplier" required><option value="">Select supplier</option></select></div>
    <div class="multi-items">
      <div class="multi-items-header"><strong>Items</strong><button type="button" class="btn btn-ghost small-btn" id="incoming-add-row"><span class="btn-inline-icon ui-icon icon-add" aria-hidden="true"></span>Add Item</button></div>
      <div id="incoming-items-list" class="items-list"></div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="incoming-modal-cancel">Cancel</button>
      <button type="submit" class="btn btn-primary">Save Delivery</button>
    </div>
  </form>
</div>

<!-- Incoming Delivery Details Modal (read-only) -->
<div class="modal-backdrop" id="incoming-details-modal">
  <div class="modal">
    <div class="modal-header">
      <div>
        <h3 id="incoming-details-title">Delivery Details</h3>
        <p id="incoming-details-subtitle">All items in this delivery.</p>
      </div>
      <button type="button" id="incoming-details-close">&#x2715;</button>
    </div>
    <div id="incoming-details-body" class="review-items-list">
      <p class="empty">Loading </p>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="incoming-details-done">Close</button>
    </div>
  </div>
</div>

<!-- Outgoing Modal -->
<div class="modal-backdrop" id="outgoing-modal">
  <form class="modal" id="outgoing-form">
    <div class="modal-header">
      <div>
        <h3 id="outgoing-modal-title">Outgoing Item Check</h3>
        <p id="outgoing-modal-subtitle">Verify warehouse item before release.</p>
      </div>
      <button type="button" id="modal-close">&#x2715;</button>
    </div>
    <input type="hidden" id="outgoing-mode" value="pos">
    <input type="hidden" id="outgoing-sale-item-id">
    <input type="hidden" id="outgoing-max-qty">
    <div class="manual-only form-group"><label>Purpose</label><select id="outgoing-purpose"><option value="Customer Purchase">Customer Purchase</option><option value="Damaged Item Removal">Damaged Item Removal</option><option value="For Replacement">For Replacement</option><option value="Store Replenishment">Store Replenishment</option><option value="IoT Shelves Replenishment">IoT Shelves Replenishment</option><option value="Emergency Release">Emergency Release</option></select><small class="field-hint">Applies to all items added below.</small></div>
    <div class="pos-only form-group"><label>Quantity Released / Listed</label><input type="number" min="1" max="100000" step="0.01" id="outgoing-qty"></div>
    <div class="pos-only form-group"><label>Status</label><select id="outgoing-status"><option value="released">Released / Good</option><option value="damaged">Damaged</option><option value="replacement">For Replacement</option><option value="missing">Missing / Not Released</option><option value="pending">Pending Review</option><option value="cancelled">Cancelled</option></select></div>
    <div class="pos-only form-group"><label>Note / Description</label><textarea rows="4" id="outgoing-notes" placeholder="Example: Released complete, no damage. Or: 1 item replaced due to visible crack."></textarea></div>
    <div class="manual-only multi-items">
      <div class="multi-items-header"><strong>Manual Outgoing Items</strong><button type="button" class="btn btn-ghost small-btn" id="outgoing-add-row"><span class="btn-inline-icon ui-icon icon-add" aria-hidden="true"></span>Add Item</button></div>
      <div id="outgoing-items-list" class="items-list"></div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="modal-cancel">Cancel</button>
      <button type="submit" class="btn btn-primary">Save Outgoing Check</button>
    </div>
  </form>
</div>

<!-- Outgoing Review Modal (per-transaction; confirm/reject each item) -->
<div class="modal-backdrop" id="outgoing-review-modal">
  <div class="modal">
    <div class="modal-header">
      <div>
        <h3 id="outgoing-review-title">Review Outgoing Transaction</h3>
        <p id="outgoing-review-subtitle">Confirm or reject each item individually.</p>
      </div>
      <button type="button" id="outgoing-review-close">&#x2715;</button>
    </div>
    <div id="outgoing-review-body" class="review-items-list">
      <p class="empty">Loading </p>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="outgoing-review-done">Close</button>
    </div>
  </div>
</div>

<!-- Outgoing Item Action Modal (confirm/reject a single item with a note) -->
<div class="modal-backdrop" id="outgoing-action-modal">
  <form class="modal" id="outgoing-action-form">
    <div class="modal-header">
      <div>
        <h3 id="outgoing-action-title">Confirm Item</h3>
        <p id="outgoing-action-subtitle">Review the item before applying the action.</p>
      </div>
      <button type="button" id="outgoing-action-close">&#x2715;</button>
    </div>
    <input type="hidden" id="outgoing-action-item-id">
    <input type="hidden" id="outgoing-action-type">
    <div class="action-item-summary" id="outgoing-action-summary"></div>
    <div class="form-group">
      <label id="outgoing-action-note-label">Note (optional)</label>
      <textarea rows="3" id="outgoing-action-notes" placeholder="Add a note for this decision."></textarea>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="outgoing-action-cancel">Cancel</button>
      <button type="submit" class="btn btn-primary" id="outgoing-action-submit">Confirm</button>
    </div>
  </form>
</div>

<!-- Re-check Modal -->
<div class="modal-backdrop" id="recheck-modal">
  <form class="modal" id="recheck-form">
    <div class="modal-header">
      <div>
        <h3 id="recheck-modal-title">Re-check Item</h3>
        <p id="recheck-modal-subtitle">Update condition and send back for admin/inventory confirmation.</p>
      </div>
      <button type="button" id="recheck-modal-close">&#x2715;</button>
    </div>
    <input type="hidden" id="recheck-item-id">
    <div class="form-group"><label>Updated Condition</label><select id="recheck-condition" required><option value="Accepted / Good">Accepted / Good</option><option value="Damaged">Damaged</option><option value="Incomplete / Missing">Incomplete / Missing</option></select></div>
    <div class="form-group"><label>Re-check Notes</label><textarea rows="4" id="recheck-notes" placeholder="Describe what was found during re-checking. E.g.: Item repacked, now complete. Or: Still damaged, needs supplier return."></textarea></div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="recheck-modal-cancel">Cancel</button>
      <button type="submit" class="btn btn-primary">Submit Re-check</button>
    </div>
  </form>
</div>

<!-- Floating toast notifications -->
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

<script>window.CGMI_CURRENT_USER = <?= json_encode($user) ?>;</script>
<script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script>
<script src="<?= app_url('/assets/js/modules/checker/checker.js') ?>"></script>
</body>
</html>
