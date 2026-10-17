<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_login(['inventory']);
?>
<!DOCTYPE html>
  <html lang="en">
  <head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CGMI.IMS   Inventory Management</title>
    <meta name="description" content="Hardware & Construction Supply Inventory Management Panel">

    <!-- Google Fonts -->
     

    <!-- jQuery -->
    <script src="<?= app_url('/assets/lib/jquery/jquery-3.7.1.min.js') ?>"></script>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="<?= app_url('/assets/lib/dataTables/dataTables.css') ?>">
    <!-- DataTables JS -->
    <script src="<?= app_url('/assets/lib/dataTables/dataTables.js') ?>"></script>

    <!-- App CSS -->
    <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
    <link rel="stylesheet" href="<?= app_url('/assets/css/admin/inventory.css') ?>">
<!-- Root Files for constant design -->
    
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
            <a class="nav-item" href="<?= app_url('/pages/inventory/dashboard.php') ?>"><span class="nav-icon ui-icon icon-dashboard" aria-hidden="true"></span>Dashboard</a>
            <a class="nav-item active" href="<?= app_url('/pages/inventory/inventory.php') ?>" aria-current="page"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Inventory</a>
            <a class="nav-item" href="<?= app_url('/pages/inventory/incoming-confirmation.php') ?>"><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
            <a class="nav-item" href="<?= app_url('/pages/inventory/incident-report.php') ?>"><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
            <a class="nav-item" href="<?= app_url('/pages/inventory/shelves.php') ?>"><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>
          <div class="nav-section-label">Settings</div>
            <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>" aria-current="page"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>
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

      <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

      <!--  MAIN CONTENT  -->
      <main class="main-content">

        <!-- Top Header -->
        <header class="top-header">
          <div class="header-left">
            <button class="menu-toggle" id="menu-toggle" aria-label="Toggle sidebar"><span class="ui-icon icon-menu" aria-hidden="true"></span></button>
            <div>
              <div class="page-title">Inventory</div>
              <div class="page-breadcrumb"><span>Inventory</span><span>-</span><span>Inventory Management</span></div>
            </div>
          </div>

        <?php require __DIR__ . '/../shared/notification-bell.php'; ?>
    </header>

        <!-- Page Content -->
        <div class="page-content">

          <!-- Stats Row -->
          <div class="stats-row" role="region" aria-label="Inventory Summary">
            <div class="stat-card" style="--stat-accent:#1F3F74;--stat-bg:rgba(31,63,116,0.1)">
              <div class="stat-icon ui-icon icon-inventory" aria-hidden="true"></div>
              <div class="stat-info">
                <div class="stat-value" id="stat-total"> </div>
                <div class="stat-label">Total Items</div>
                <div class="stat-change neutral">All SKUs</div>
              </div>
            </div>
            <div class="stat-card" style="--stat-accent:#16a34a;--stat-bg:rgba(22,163,74,0.1)">
              <div class="stat-icon ui-icon icon-check" aria-hidden="true"></div>
              <div class="stat-info">
                <div class="stat-value" id="stat-active"> </div>
                <div class="stat-label">Active Items</div>
                <div class="stat-change up">Available</div>
              </div>
            </div>
            <div class="stat-card" style="--stat-accent:#dc2626;--stat-bg:rgba(220,38,38,0.1)">
              <div class="stat-icon ui-icon icon-off" aria-hidden="true"></div>
              <div class="stat-info">
                <div class="stat-value" id="stat-inactive"> </div>
                <div class="stat-label">Inactive Items</div>
                <div class="stat-change down">Phase-out</div>
              </div>
            </div>
            <div class="stat-card" style="--stat-accent:#d97706;--stat-bg:rgba(217,119,6,0.1)">
              <div class="stat-icon ui-icon icon-incident" aria-hidden="true"></div>
              <div class="stat-info">
                <div class="stat-value" id="stat-lowstock"> </div>
                <div class="stat-label">Low Stock</div>
                <div class="stat-change down">10 units</div>
              </div>
            </div>
          </div>

          <!-- Inventory Panel -->
          <div class="panel-card" role="region" aria-label="Inventory Table">

            <!-- Panel Header -->
            <div class="panel-header">
              <div class="panel-title">
                <span class="panel-title-icon ui-icon icon-inventory" aria-hidden="true"></span>Item Inventory
                <span class="panel-title-badge">Hardware &amp; Construction</span>
              </div>
              <div class="panel-actions">
                <button class="btn btn-primary" id="add-item-btn-2"><span class="btn-inline-icon ui-icon icon-add" aria-hidden="true"></span>Add Item</button>
              </div>
            </div>

            <!-- Toolbar -->
            <div class="toolbar" role="toolbar" aria-label="Table Controls">
              <div class="toolbar-left">
                <div class="search-wrapper">
                  <span class="search-icon ui-icon icon-search" aria-hidden="true"></span>
                  <input type="search" id="search-input" placeholder="Search items " aria-label="Search inventory items">
                  <div class="search-mode-toggle" role="group" aria-label="Search by">
                    <button class="search-mode-btn active" data-mode="name" aria-pressed="true">Name</button>
                    <button class="search-mode-btn" data-mode="sku" aria-pressed="false">SKU</button>
                  </div>
                </div>
                <div class="status-tabs" role="group" aria-label="Filter by status">
                  <button class="status-tab active" data-filter="Active"   aria-pressed="true">Active</button>
                  <button class="status-tab"        data-filter="Inactive" aria-pressed="false">Inactive</button>
                </div>
              </div>

              <div class="toolbar-right">
                <div class="filter-group">
                  <select class="filter-select" id="filter-category"    aria-label="Filter by category"><option value="">All Categories</option></select>
                  <select class="filter-select" id="filter-subcategory" aria-label="Filter by sub-category"><option value="">All Sub-categories</option></select>
                  <select class="filter-select" id="filter-shelf-tracking" aria-label="Filter by storage type"><option value="">All Storage Types</option><option value="shelves">IoT Shelves</option><option value="warehouse">Warehouse</option><option value="store">Store</option></select>
                </div>
                <div class="sort-wrapper">
                  <button class="sort-btn" id="sort-btn" aria-haspopup="true" aria-expanded="false">
                    <span class="btn-inline-icon ui-icon icon-reports" aria-hidden="true"></span><span class="sort-label">Sort By</span>
                  </button>
                  <div class="sort-dropdown" id="sort-dropdown" role="menu">
                    <button class="sort-option" data-col="name"  data-dir="asc"  data-label="A → Z"         role="menuitem"><span class="sort-icon ui-icon icon-reports" aria-hidden="true"></span> A to Z (Name)</button>
                    <button class="sort-option" data-col="name"  data-dir="desc" data-label="Z → A"         role="menuitem"><span class="sort-icon ui-icon icon-reports" aria-hidden="true"></span> Z to A (Name)</button>
                    <button class="sort-option" data-col="price" data-dir="asc"  data-label="Lowest Price"  role="menuitem"><span class="sort-icon ui-icon icon-sales" aria-hidden="true"></span> Lowest → Highest Price</button>
                    <button class="sort-option" data-col="price" data-dir="desc" data-label="Highest Price" role="menuitem"><span class="sort-icon ui-icon icon-sales" aria-hidden="true"></span> Highest → Lowest Price</button>
                    <button class="sort-option" data-col="stock" data-dir="asc"  data-label="Lowest Stock"  role="menuitem"><span class="sort-icon ui-icon icon-inventory" aria-hidden="true"></span> Lowest → Highest Stock</button>
                    <button class="sort-option" data-col="stock" data-dir="desc" data-label="Highest Stock" role="menuitem"><span class="sort-icon ui-icon icon-inventory" aria-hidden="true"></span> Highest → Lowest Stock</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- DataTable -->
            <div class="dt-container">
              <table id="inventory-table" role="grid" aria-label="Inventory Items">
                <thead>
                  <tr>
                    <th></th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Storage Type</th>
                    <th>Warehouse Total</th>
                    <th>Store Total</th>
                    <th>Shelves Total</th>
                    <th>Overall Total</th>
                    <th>Weight Check</th>
                    <th style="display:none">CatRaw</th>
                    <th style="display:none">SubRaw</th>
                    <th style="display:none">StorageRaw</th>
                    <th>Unit Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div class="table-pagination" id="inventory-pagination"></div>

          </div><!-- /panel-card -->
        </div><!-- /page-content -->
      </main>
    </div><!-- /app-wrapper -->


    <!--  ITEM MODAL (Add / Edit)  -->
    <div class="modal-backdrop" id="item-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-title">
      <div class="modal" id="item-modal">
        <div class="modal-header">
          <div class="modal-title-wrap">
            <div class="modal-icon ui-icon icon-add" id="modal-icon" aria-hidden="true"></div>
            <div>
              <div class="modal-title" id="modal-title">Add New Item</div>
              <div class="modal-subtitle" id="modal-subtitle">Fill in the details for the new inventory item</div>
            </div>
          </div>
          <button class="modal-close" id="modal-close-btn" aria-label="Close modal">x</button>
        </div>

        <div class="modal-body">
          <form id="item-form" novalidate autocomplete="off">

            <!-- Image -->
            <div class="form-group">
              <label class="form-label">Item Image</label>
              <div class="img-upload-zone" id="img-upload-zone" tabindex="0" aria-label="Upload item image">
                <input type="file" id="img-file-input" accept="image/*" aria-label="Choose image file">
                <div class="img-upload-icon">IMG</div>
                <div class="img-upload-title">Click or drag to upload</div>
                <div class="img-upload-hint">JPG, PNG, WEBP   max 5MB</div>
                <div class="img-preview-wrap" id="img-preview-wrap">
                  <img id="img-preview" src="" alt="Image preview">
                  <button type="button" class="img-preview-remove" id="img-remove-btn" aria-label="Remove image">x</button>
                </div>
              </div>
            </div>

            <!-- Details -->
             
            <div class="modal-section-label">Item Details</div>
            <div class="form-grid">
              <div class="form-group span-2">
                <label class="form-label required" for="item-name">Item Name</label>
                <input type="text" id="item-name" class="form-control" placeholder="e.g. Portland Cement Type I 40kg" maxlength="120" required aria-required="true">
              </div>
              <div class="form-group span-2">
                <label class="form-label required" for="item-qty-warehouse">Quantity &amp; Unit</label>
                <div class="qty-input-group">
                  <div class="qty-multi-group">
                    <div class="qty-field">
                      <label class="qty-field-label" for="item-qty-warehouse">Warehouse</label>
                      <input type="number" id="item-qty-warehouse" class="form-control" placeholder="0" min="0" step="1" required aria-required="true">
                    </div>
                    <div class="qty-field">
                      <label class="qty-field-label" for="item-qty-store">Store</label>
                      <input type="number" id="item-qty-store" class="form-control" placeholder="0" min="0" step="1" required aria-required="true">
                    </div>
                    <div class="qty-field">
                      <label class="qty-field-label" for="item-qty-iot">IoT Shelves</label>
                      <input type="number" id="item-qty-iot" class="form-control" placeholder="0" min="0" step="1" required aria-required="true">
                    </div>
                  </div>
                  <select id="item-unit" class="form-control" required aria-required="true" aria-label="Unit"><option value="">Unit</option></select>
                </div>
              </div>
              <div class="form-group span-2">
                <label class="form-label required" for="item-price">Unit Price  </label>
                <div class="price-input-wrap">
                  <span class="currency-prefix" aria-hidden="true"> </span>
                  <input type="number" id="item-price" class="form-control" placeholder="?0.00" min="0" step="0.01" required aria-required="true">
                </div>
              </div>
            </div>

            <div class="modal-section-label">Classification</div>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label required" for="item-category">Category</label>
                <select id="item-category" class="form-control" required aria-required="true"><option value="">Select Category</option></select>
              </div>
              <div class="form-group">
                <label class="form-label required" for="item-subcategory">Sub-category</label>
                <select id="item-subcategory" class="form-control" required aria-required="true" disabled><option value="">Select Sub-category</option></select>
              </div>
            </div>

          </form>
        </div>

        <div class="modal-footer">
          <span class="save-hint">* Required fields</span>
          <button type="button" class="btn btn-ghost" id="modal-cancel-btn">Cancel</button>
          <button type="button" class="btn btn-primary" id="modal-save-btn">Save Item</button>
        </div>
      </div>
    </div>


    <!--  DETAILS PANEL (compact / mobile)  -->
    <div class="details-backdrop" id="details-backdrop" role="dialog" aria-modal="true" aria-labelledby="details-panel-title">
      <div class="details-panel" id="details-panel">

        <!-- Panel Header -->
        <div class="details-header">
          <div class="details-header-title" id="details-panel-title">
            <span class="details-header-icon ui-icon icon-info" aria-hidden="true"></span>
            Item Details
          </div>
          <button class="details-close-btn" id="details-close-btn" aria-label="Close details">x</button>
        </div>

        <!-- Panel Body -->
        <div class="details-body">

          <!-- Image hero -->
          <div class="dp-image-wrap" id="dp-image">
            <!-- filled by JS -->
          </div>

          <!-- Identity -->
          <div class="dp-identity">
            <h2 class="dp-name" id="dp-name"> </h2>
            <span class="dp-id"  id="dp-id"> </span>
          </div>

          <!-- Status badge -->
          <div class="dp-status-row">
            <span class="badge badge-active" id="dp-status">Active</span>
          </div>

          <!-- Info grid -->
          <div class="dp-info-grid">
            <div class="dp-info-item">
              <span class="dp-info-label">Category</span>
              <span class="dp-info-value" id="dp-category"> </span>
            </div>
            <div class="dp-info-item">
              <span class="dp-info-label">Sub-category</span>
              <span class="dp-info-value" id="dp-subcategory"> </span>
            </div>
            <div class="dp-info-item">
              <span class="dp-info-label">Storage Type</span>
              <span class="dp-info-value" id="dp-shelf-category"> </span>
            </div>
            <div class="dp-info-item">
              <span class="dp-info-label">Unit Weight</span>
              <span class="dp-info-value" id="dp-unit-weight"> </span>
            </div>
            <div class="dp-info-item">
              <span class="dp-info-label">Warehouse Total</span>
              <span class="dp-info-value dp-qty" id="dp-warehouse-total"> </span>
            </div>
            <div class="dp-info-item">
              <span class="dp-info-label">Store Total</span>
              <span class="dp-info-value dp-qty" id="dp-store-total"> </span>
            </div>
            <div class="dp-info-item">
              <span class="dp-info-label">IoT Shelves Total</span>
              <span class="dp-info-value dp-qty" id="dp-iot-total"> </span>
            </div>
            <div class="dp-info-item">
              <span class="dp-info-label">Overall Total</span>
              <span class="dp-info-value dp-qty" id="dp-qty"> </span>
            </div>
            <div class="dp-info-item">
              <span class="dp-info-label">Weight Check</span>
              <span class="dp-info-value dp-qty" id="dp-weight-check"> </span>
            </div>
            <div class="dp-info-item">
              <span class="dp-info-label">Unit Price</span>
              <span class="dp-info-value dp-price" id="dp-price"> </span>
            </div>
          </div>
        </div>

        <!-- Panel Footer -->
        <div class="details-footer">
          <button class="btn btn-ghost details-cancel-btn" id="details-close-btn-2" onclick="DetailsPanelManager.close()">Close</button>
          <button class="btn btn-danger details-toggle-btn" id="details-toggle-btn" style="display:none;">Deactivate</button>
          <button class="btn btn-secondary details-update-btn" id="details-update-btn"><span class="btn-inline-icon ui-icon icon-edit" aria-hidden="true"></span>Update Item</button>
        </div>

      </div>
    </div>


    <!--  CONFIRM DIALOG  -->
    <div class="confirm-overlay" id="confirm-overlay" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">
      <div class="confirm-box">
        <div class="confirm-icon ui-icon icon-question" id="confirm-icon" aria-hidden="true"></div>
        <div class="confirm-title" id="confirm-title">Are you sure?</div>
        <div class="confirm-msg"   id="confirm-msg"></div>
        <div class="confirm-actions">
          <button class="btn btn-ghost"    id="confirm-cancel-btn">Cancel</button>
          <button class="btn btn-primary"  id="confirm-ok-btn">Confirm</button>
        </div>
      </div>
    </div>

    <!--  LOADING  -->
    <div class="loading-overlay" id="loading-overlay" aria-hidden="true">

      <div class="loader"></div>

    </div>

    <!--  TOASTS  -->
    <div id="toast-container" aria-live="polite" aria-atomic="false"></div>


    <!-- JS load order -->
    <script src="<?= app_url('/assets/js/modules/inventory/data.js') ?>"></script>
    <script src="<?= app_url('/assets/js/modules/inventory/category.js') ?>"></script>
    <script src="<?= app_url('/assets/js/modules/inventory/filter.js') ?>"></script>
    <script src="<?= app_url('/assets/js/modules/inventory/datatable-init.js') ?>"></script>

    <script>window.CGMI_BASE_URL = <?= json_encode(app_base_path()); ?>;</script>
    <script>window.CGMI_INVENTORY_API = <?= json_encode(['list' => app_url('/api/inventory/list.php'), 'save' => app_url('/api/inventory/save.php'), 'categories' => app_url('/api/inventory/categories.php'), 'units' => app_url('/api/inventory/units.php')]); ?>;</script>

    <script src="<?= app_url('/assets/js/modules/inventory/inventory.js') ?>"></script>
    <script src="<?= app_url('/assets/js/modules/inventory/modal.js') ?>"></script>
    <script src="<?= app_url('/assets/js/modules/inventory/app.js') ?>"></script>

    <script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script> <!-- sidebar toggle -->
  
  
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
