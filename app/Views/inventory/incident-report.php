<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_login(['inventory']);
?>
<!DOCTYPE html>
  <html lang="en">
  <head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CGMI.IMS   Incident Reports</title>
    <meta name="description" content="Hardware & Construction Supply Inventory Management Panel">

    <!-- Google Fonts -->
     <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />

    <!-- Design system + module CSS -->
    <link rel="stylesheet" href="<?= app_url('/assets/css/root.css') ?>">
    <link rel="stylesheet" href="<?= app_url('/assets/css/modules/incident-report-inventory/main.css') ?>">
    <link rel="stylesheet" href="<?= app_url('/assets/css/modules/incident-report-inventory/responsive.css') ?>">
    
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
            <a class="nav-item" href="<?= app_url('/pages/inventory/inventory.php') ?>"><span class="nav-icon ui-icon icon-inventory" aria-hidden="true"></span>Inventory</a>
            <a class="nav-item" href="<?= app_url('/pages/inventory/incoming-confirmation.php') ?>"><span class="nav-icon ui-icon icon-check" aria-hidden="true"></span>Incoming Confirmation</a>
            <a class="nav-item active" href="<?= app_url('/pages/inventory/incident-report.php') ?>"><span class="nav-icon ui-icon icon-incident" aria-hidden="true"></span>Incident Reports</a>
            <a class="nav-item" href="<?= app_url('/pages/inventory/shelves.php') ?>"><span class="nav-icon ui-icon icon-shelves" aria-hidden="true"></span>Shelves</a>
          <div class="nav-section-label">Settings</div>
            <a class="nav-item" href="<?= app_url('/api/auth/logout.php') ?>" aria-current="page"><span class="nav-icon ui-icon icon-logout" aria-hidden="true"></span>Log-out</a>
        </nav>

        <div class="sidebar-footer">
          <div class="user-card">
            <div class="user-avatar">U</div>
            <div class="user-info">
              <div class="user-name">User</div>
              <div class="user-role">Inventory</div>
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
              <div class="page-title">Incident Reports</div>
              <div class="page-breadcrumb"></span><span>Main > Incident Reports</span></div>
            </div>
          </div>
        <?php require __DIR__ . '/../shared/notification-bell.php'; ?>
    </header>

        <!-- Page Content -->
        <div class="page-content">

          <!--  ADD REPORT PANEL  -->
          <div class="panel-card" id="add-report-panel" role="region" aria-label="Add Incident Report">

            <!-- Panel Header -->
            <div class="panel-header">
              <div class="panel-title">
                Add Incident Report
                <span class="panel-title-badge">New Entry</span>
              </div>
            </div>

            <!-- Panel Body -->
            <div class="panel-body">

              <!-- Image Upload Field -->
              <div class="form-group">
                <label class="form-label required">Incident Image</label>

                <!-- Hidden file input for upload -->
                <input type="file" id="report-image-upload" name="report-image-upload" accept="image/*" style="display:none;" aria-hidden="true" />

                <!-- Preview (shown after selection) -->
                <div class="upload-preview-card" id="upload-preview" style="display:none;">
                  <img id="preview-img" src="" alt="Incident preview" />
                  <button type="button" class="btn btn-danger btn-xs upload-remove" id="remove-img-btn" aria-label="Remove image">&#x2715; Remove</button>
                </div>

                <!-- Two-button picker (shown before selection) -->
                <div class="upload-btn-row" id="upload-placeholder">
                  <button type="button" class="upload-option-btn" id="btn-upload-file" aria-label="Upload from device">
                    <span class="upload-option-icon">&#128193;</span>
                    <span class="upload-option-label">Upload Photo</span>
                    <span class="upload-option-hint">From your device</span>
                  </button>
                  <div class="upload-divider">or</div>
                  <button type="button" class="upload-option-btn" id="btn-take-photo" aria-label="Take a photo with camera">
                    <span class="upload-option-icon">&#128247;</span>
                    <span class="upload-option-label">Take Photo</span>
                    <span class="upload-option-hint">Use your camera</span>
                  </button>
                </div>

                <span class="hint-text">Upload an existing photo or take one directly with your camera.</span>
              </div>

              <!--             CAMERA MODAL             -->
              <div class="camera-overlay" id="camera-overlay" role="dialog" aria-modal="true" aria-label="Camera" style="display:none;">
                <div class="camera-modal">
                  <div class="camera-modal-header">
                    <span class="camera-modal-title">&#128247; Take Photo</span>
                    <button type="button" class="btn btn-ghost btn-sm" id="camera-close-btn">&#x2715; Close</button>
                  </div>
                  <div class="camera-viewfinder-wrap">
                    <video id="camera-video" autoplay playsinline muted></video>
                    <canvas id="camera-canvas" style="display:none;"></canvas>
                    <div class="camera-snap-preview" id="camera-snap-preview" style="display:none;">
                      <img id="camera-snap-img" src="" alt="Snapshot" />
                    </div>
                  </div>
                  <div class="camera-controls" id="camera-live-controls">
                    <button type="button" class="btn btn-secondary btn-sm" id="camera-flip-btn" title="Flip camera">&#x21C4; Flip</button>
                    <button type="button" class="camera-shutter" id="camera-shutter-btn" aria-label="Take photo"></button>
                    <div style="width:80px;"></div>
                  </div>
                  <div class="camera-controls" id="camera-retake-controls" style="display:none;">
                    <button type="button" class="btn btn-ghost btn-sm" id="camera-retake-btn">&#x21BA; Retake</button>
                    <button type="button" class="btn btn-primary btn-sm" id="camera-use-btn">&#x2713; Use Photo</button>
                  </div>
                </div>
              </div>

              <!-- Report Category -->
              <div class="form-group" style="margin-top: 18px;">
                <label class="form-label required" for="report-category">Report Category</label>
                <select
                  id="report-category"
                  name="report-category"
                  class="form-control"
                  aria-required="true"
                >
                  <option value="" disabled selected>Select the category this report is about </option>
                  <option value="Inventory Discrepancy">Inventory Discrepancy</option>
                  <option value="Damaged Item">Damaged Item</option>
                  <option value="Missing Item">Missing Item</option>
                  <option value="Incorrect Item">Incorrect Item</option>
                  <option value="Stock Issue">Stock Issue</option>
                  <option value="IoT Sensor Issue">IoT Sensor Issue</option>
                  <option value="Shelf Issue">Shelf Issue</option>
                  <option value="Hardware Issue">Hardware Issue</option>
                  <option value="System Issue">System Issue</option>
                  <option value="Safety Incident">Safety Incident</option>
                  <option value="Unauthorized Activity">Unauthorized Activity</option>
                  <option value="Other">Other</option>
                </select>
                <span class="hint-text">Choose the category that best describes this incident.</span>
              </div>

              <!-- Incident Title -->
              <div class="form-group" style="margin-top: 18px;">
                <label class="form-label required" for="report-title">Incident Title</label>
                <input
                  type="text"
                  id="report-title"
                  name="report-title"
                  class="form-control"
                  placeholder="Enter a short, descriptive title for the incident "
                  aria-required="true"
                  maxlength="120"
                />
                <span class="hint-text">Maximum 120 characters. Keep it concise and clear.</span>
              </div>

              <!-- Narrative Textbox -->
              <div class="form-group" style="margin-top: 18px;">
                <label class="form-label required" for="report-narrative">Narrative Report</label>
                <textarea
                  id="report-narrative"
                  name="report-narrative"
                  class="form-control"
                  rows="6"
                  placeholder="Describe the incident in detail   include what happened, who was involved, and any immediate actions taken "
                  aria-required="true"
                ></textarea>
                <span class="hint-text">Minimum 20 characters. Be as specific as possible.</span>
              </div>

              <!-- Submit -->
              <div class="panel-submit-row">
                <button type="button" class="btn btn-ghost" id="clear-report-btn">x Clear</button>
                <button type="button" class="btn btn-primary" id="submit-report-btn">Add Report</button>
              </div>

            </div><!-- /panel-body -->
          </div><!-- /add-report-panel -->

        </div><!-- /page-content -->
      </main>
    </div><!-- /app-wrapper -->

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
    <script src="<?= app_url('/assets/js/components/sidebar.js') ?>"></script> <!-- sidebar toggle -->

    <!--  ADD REPORT SCRIPTS  -->
    <script src="<?= app_url('/assets/js/modules/incident-report-inventory/toast.js') ?>"></script>   <!-- Toast notifications -->
    <script src="<?= app_url('/assets/js/modules/incident-report-inventory/upload.js') ?>"></script>  <!-- Image upload & preview -->
    <script src="<?= app_url('/assets/js/modules/incident-report-inventory/camera.js') ?>"></script>  <!-- Live camera modal -->
    <script>window.CGMI_INCIDENTS_API = <?= json_encode(['submit' => app_url('/api/incidents/submit.php')]); ?>;</script>
    <script src="<?= app_url('/assets/js/modules/incident-report-inventory/report.js') ?>"></script>  <!-- Form submit & clear -->
  
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

<script>window.CGMI_BASE_URL = <?= json_encode(app_base_path()); ?>;</script>
</body>

</html>