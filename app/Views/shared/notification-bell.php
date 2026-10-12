<?php
/**
 * Shared notification bell partial.
 *
 * Renders the header bell button + unread badge + dropdown panel, emits the
 * window.CGMI_NOTIFICATIONS_API endpoint config, and loads the shared
 * notifications.js component.
 *
 * Usage — inside a view's <header class="top-header">, after .header-left:
 *   <?php require __DIR__ . '/../shared/notification-bell.php'; ?>
 *
 * The partial is self-guarding: it renders nothing for roles without a bell
 * (e.g. cashier) or when no user is logged in, so it is safe to include in any
 * header unconditionally.
 */

require_once __DIR__ . '/../../Core/App.php';
require_once __DIR__ . '/../../Core/Helpers.php';

$__notifUser = (isset($user) && is_array($user)) ? $user : (function_exists('current_user') ? (current_user() ?: []) : []);
$__notifRole = (string)($__notifUser['role'] ?? '');
$__notifRoles = ['super-admin', 'admin', 'inventory', 'checker'];

if (!in_array($__notifRole, $__notifRoles, true)) {
    return; // no bell for this role / not logged in
}
?>
<div class="header-right">
  <div class="notif-panel-wrap">
    <button type="button" class="notif-bell" id="notif-bell"
            aria-label="Notifications" aria-haspopup="true" aria-expanded="false" aria-controls="notif-panel">
      <span class="ui-icon icon-bell" aria-hidden="true"></span>
      <span class="notif-badge" id="notif-badge" aria-live="polite">0</span>
    </button>

    <div class="notif-panel" id="notif-panel" role="dialog" aria-label="Notifications" aria-hidden="true">
      <div class="notif-panel-header">
        <div class="notif-panel-title">
          Notifications
          <span class="notif-title-count" id="notif-title-count" style="display:none">0</span>
        </div>
        <button type="button" class="notif-markall" id="notif-markall" disabled>
          <span class="ui-icon icon-check-double" aria-hidden="true"></span>Mark all read
        </button>
      </div>

      <ul class="notif-list" id="notif-list" aria-label="Notification list">
        <li class="notif-status">Loading notifications&hellip;</li>
      </ul>

      <div class="notif-panel-footer">
        <button type="button" class="notif-refresh" id="notif-refresh">Refresh</button>
      </div>
    </div>
  </div>
</div>

<!-- Unread-notification popup (shown once per session on first page load with unread items) -->
<div class="notif-welcome-overlay" id="notifWelcomeOverlay" role="dialog" aria-modal="true"
     aria-labelledby="notifWelcomeTitle" aria-hidden="true">
  <div class="notif-welcome-modal">
    <div class="notif-welcome-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
      </svg>
      <span class="notif-welcome-badge" id="notifWelcomeBadge" aria-hidden="true"></span>
    </div>
    <h2 class="notif-welcome-title" id="notifWelcomeTitle">You have new notifications</h2>
    <p class="notif-welcome-body">
      You have <strong id="notifWelcomeCount"></strong> unread notification(s).
      Open the bell icon to review them.
    </p>
    <div class="notif-welcome-actions">
      <button type="button" class="notif-welcome-btn-primary" id="notifWelcomeOpen">
        View Notifications
      </button>
      <button type="button" class="notif-welcome-btn-secondary" id="notifWelcomeDismiss">
        Dismiss
      </button>
    </div>
  </div>
</div>

<script>
window.CGMI_NOTIFICATIONS_API = <?= json_encode([
    'list'        => app_url('/api/notifications/list.php'),
    'unreadCount' => app_url('/api/notifications/unread-count.php'),
    'markRead'    => app_url('/api/notifications/mark-read.php'),
    'markAllRead' => app_url('/api/notifications/mark-all-read.php'),
]); ?>;
window.CGMI_CURRENT_USER = <?= json_encode([
    'id'   => (int)($__notifUser['id'] ?? 0),
    'role' => $__notifRole,
]); ?>;
</script>
<script src="<?= app_url('/assets/js/components/notifications.js') ?>"></script>
