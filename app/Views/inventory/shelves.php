<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_login(['inventory']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CGMI.IMS   Shelves</title>
  <meta name="description" content="Hardware & Construction Supply Shelves Panel">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <script src="<?= app_url('/assets/lib/jquery/jquery-3.7.1.min.js') ?>"></script>
  <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
  <link rel="stylesheet" href="<?= app_url('/assets/css/admin/shelves.css') ?>">
</head>
<body>
<div class="app-wrapper">
  <aside class="sidebar" id="sidebar" role="navigation" aria-label="Main Navigation">
    <div class="sidebar-logo"><div class="logo-icon ui-icon icon-store" aria-hidden="true"></div><div class="logo-text">Calamba General Merchandise Inc.<span>Hardware &amp; Supply</span></div></div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <a class="nav-item" href="<?= app_url('/pages/inventory/dashboard.php') ?>"><span class="nav-icon ui-icon icon-dashboard" aria-hidden="true"></span>Dashboard</a>
        <a class="nav-item" href="<?= app_url('/pages/inventory/inventory.php') ?>"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Inventory</a>
            <a class="nav-item" href="<?= app_url('/pages/inventory/incoming-confirmation.php') ?>"><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
        <a class="nav-item" href="<?= app_url('/pages/inventory/incident-report.php') ?>"><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
        <a class="nav-item active" href="<?= app_url('/pages/inventory/shelves.php') ?>"><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>
    <div class="nav-section-label">Settings</div><a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a></nav>
    <div class="sidebar-footer"><div class="user-card"><div class="user-avatar">U</div><div class="user-info"><div class="user-name">User</div><div class="user-role">Inventory</div></div></div></div>
  </aside>
  <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
  <main class="main-content">
    <header class="top-header"><div class="header-left"><button class="menu-toggle" id="menu-toggle" aria-label="Toggle sidebar"><span class="ui-icon icon-menu" aria-hidden="true"></span></button><div><div class="page-title">Shelves</div><div class="page-breadcrumb"><span>Main &gt; Shelf Management</span></div></div></div><?php require __DIR__ . '/../shared/notification-bell.php'; ?>
    </header>
    <div class="page-content">
      <div class="stats-row" role="region" aria-label="Shelf Status Summary">
        <div class="stat-card" style="--stat-accent:#1F3F74;--stat-bg:rgba(31,63,116,0.1)"><div class="stat-icon ui-icon icon-shelves" aria-hidden="true"></div><div class="stat-info"><div class="stat-value" id="stat-total">0</div><div class="stat-label">Total Shelves</div><div class="stat-change neutral">All Shelves</div></div></div>
        <div class="stat-card" style="--stat-accent:#16a34a;--stat-bg:rgba(22,163,74,0.1)"><div class="stat-icon ui-icon icon-inventory" aria-hidden="true"></div><div class="stat-info"><div class="stat-value" id="stat-occupied">0</div><div class="stat-label">Occupied Shelves</div><div class="stat-change up">In Use</div></div></div>
        <div class="stat-card" style="--stat-accent:#4569AD;--stat-bg:rgba(69,105,173,0.1)"><div class="stat-icon ui-icon icon-empty" aria-hidden="true"></div><div class="stat-info"><div class="stat-value" id="stat-unused">0</div><div class="stat-label">Unused Shelves</div><div class="stat-change neutral">Available</div></div></div>
        <div class="stat-card" style="--stat-accent:#dc2626;--stat-bg:rgba(220,38,38,0.1)"><div class="stat-icon ui-icon icon-incident" aria-hidden="true"></div><div class="stat-info"><div class="stat-value" id="stat-maintenance">0</div><div class="stat-label">Maintenance</div><div class="stat-change down">Needs Attention</div></div></div>
      </div>
      <div class="panel-card">
        <div class="panel-header"><div class="panel-title"><span class="panel-title-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves <span class="panel-title-badge" id="shelves-count-badge">0</span></div><div class="panel-actions"><button class="btn btn-primary" id="add-shelf-btn">Add Shelf</button></div></div>
        <div class="shelves-grid" id="shelves-grid"></div>
        <div class="empty-state" id="shelves-empty" style="display:none;"><div class="empty-state-icon ui-icon icon-shelves" aria-hidden="true"></div><div class="empty-state-title">No Shelves Yet</div><div class="empty-state-desc">Click "Add Shelf" to register your first shelf.</div></div>
      </div>
    </div>
  </main>
</div>
<div class="modal-backdrop" id="details-modal-backdrop" role="dialog" aria-modal="true"><div class="modal" style="max-width:540px;"><div class="modal-header"><div class="modal-title-wrap"><div class="modal-icon ui-icon icon-info" aria-hidden="true"></div><div><div class="modal-title">Shelf Details</div><div class="modal-subtitle">Full information about this shelf</div></div></div><button class="modal-close" id="details-modal-close" aria-label="Close">x</button></div><div class="modal-body"><div class="modal-section-label">Shelf Identity</div><div class="det-info-grid"><div class="det-info-item span-2"><div class="det-info-label">Shelf Name</div><div class="det-info-value" id="det-shelf-name"> </div></div><div class="det-info-item"><div class="det-info-label">Location</div><div class="det-info-value" id="det-location"> </div></div><div class="det-info-item"><div class="det-info-label">Status</div><div id="det-status-wrap"> </div></div></div><div class="modal-section-label">Stored Item</div><div class="det-info-grid"><div class="det-info-item span-2"><div class="det-info-label">Item Name</div><div class="det-info-value" id="det-item"> </div></div><div class="det-info-item"><div class="det-info-label">Quantity on Shelf</div><div class="det-info-value" id="det-quantity"> </div></div><div class="det-info-item"><div class="det-info-label">Warehouse Remaining</div><div class="det-info-value" id="det-warehouse"> </div></div></div><div class="modal-section-label">Weight Thresholds</div><div class="det-info-grid"><div class="det-info-item"><div class="det-info-label">Min Weight (kg)</div><div class="det-info-value" id="det-min"> </div></div><div class="det-info-item"><div class="det-info-label">Max Weight (kg)</div><div class="det-info-value" id="det-max"> </div></div></div></div><div class="modal-footer"><button class="btn btn-ghost" onclick="document.getElementById('details-modal-close').click()">Close</button></div></div></div>
<div class="modal-backdrop" id="form-modal-backdrop" role="dialog" aria-modal="true"><div class="modal" style="max-width:720px;"><div class="modal-header"><div class="modal-title-wrap"><div class="modal-icon ui-icon icon-shelves" aria-hidden="true"></div><div><div class="modal-title" id="form-modal-title">Add New Shelf</div><div class="modal-subtitle" id="form-modal-subtitle">Fill in the details to register a shelf</div></div></div><button class="modal-close" id="form-modal-close" aria-label="Close">x</button></div><div class="modal-body"><div class="form-group"><label class="form-label required" for="form-shelf-name">Shelf Name</label><input class="form-control" type="text" id="form-shelf-name" placeholder="e.g. Shelf A1" autocomplete="off"></div><div class="form-group"><label class="form-label" for="form-item">Shelf Item</label><select class="form-control" id="form-item"><option value="">  Select IoT shelf item  </option></select><p class="hint-text">Only inventory items with Storage Type set to IoT Shelves are shown here.</p></div><div class="form-group"><label class="form-label required" for="form-location">Shelf Location</label><select class="form-control" id="form-location"><option value="">  Select location  </option><option value="Top Layer">Top Layer</option><option value="Middle Layer">Middle Layer</option><option value="Bottom Layer">Bottom Layer</option></select></div><div class="form-group"><label class="form-label" for="form-status">Shelf Status</label><select class="form-control" id="form-status"><option value="Unused">Unused</option><option value="Occupied">Occupied</option><option value="Maintenance">Maintenance</option></select></div></div><div class="modal-footer"><span class="save-hint">Fields marked <span style="color:var(--accent-danger)">*</span> are required.</span><button class="btn btn-ghost" id="form-cancel-btn">Cancel</button><button class="btn btn-primary" id="form-save-btn">Add Shelf</button></div></div></div>
<div class="loading-overlay" id="loading-overlay" aria-hidden="true"><div class="loader"></div></div><div id="toast-container" aria-live="polite" aria-atomic="false"></div>
<script>window.CGMI_BASE_URL = <?= json_encode(app_base_path()); ?>;</script>
<script>window.CGMI_SHELVES_API = <?= json_encode(['list'=>app_url('/api/shelves/list.php'),'save'=>app_url('/api/shelves/save.php')]); ?>;</script>
<script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script><script src="<?= app_url('/assets/js/modules/shelves/shelves-admin.js') ?>"></script>
<script>
window.CGMI_USER = <?php echo json_encode($_SESSION['user']); ?>;
document.addEventListener('DOMContentLoaded', () => {
 const user = window.CGMI_USER || {};
 document.querySelectorAll('.user-name').forEach(el => el.textContent = ((user.first_name || '') + ' ' + (user.last_name || '')).trim() || 'User');
 document.querySelectorAll('.user-role').forEach(el => { const role = (user.role || '').toString(); el.textContent = role ? role.charAt(0).toUpperCase() + role.slice(1) : 'User'; });
 document.querySelectorAll('.user-avatar').forEach(el => { const first = (user.first_name || '').charAt(0).toUpperCase(); const last = (user.last_name || '').charAt(0).toUpperCase(); el.textContent = (first + last) || 'U'; });
});
</script>
</body></html>