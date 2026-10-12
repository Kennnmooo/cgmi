/* ============================================================
   TOAST.JS — Toast notification helper
   Exposes: window.showToast(icon, title, msg, color)
   ============================================================ */

(function () {
  'use strict';

  /**
   * Display a toast notification.
   * @param {string} icon  - CSS icon class, e.g. icon-check
   * @param {string} title - Bold heading
   * @param {string} msg   - Supporting message
   * @param {string} color - CSS color value for the left accent border
   */
  /* Route a toast to the correct on-screen stack. This helper's signature passes a
     color rather than a type, so we infer the side from the accent color: the danger
     and warning accents go BOTTOM-LEFT, everything else goes BOTTOM-RIGHT. */
  function toastStackFor(color) {
    var c = String(color || '');
    var isAlert = /accent-danger|accent-warning|#dc2626|#d97706|#d63e3e|#e8a020/i.test(c);
    var side = isAlert ? 'left' : 'right';
    var id = 'toast-stack-' + side;
    var stack = document.getElementById(id);
    if (!stack) {
      stack = document.createElement('div');
      stack.id = id;
      stack.className = 'toast-stack toast-stack-' + side;
      stack.setAttribute('aria-live', side === 'left' ? 'assertive' : 'polite');
      document.body.appendChild(stack);
    }
    return stack;
  }

  function showToast(icon, title, msg, color) {
    var container = toastStackFor(color);
    if (!container) return;

    var toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.setProperty('--toast-color', color || 'var(--blue-medium)');
    toast.innerHTML =
      '<span class="toast-icon ui-icon ' + (icon || 'icon-info') + '" aria-hidden="true"></span>' +
      '<div class="toast-body">' +
        '<div class="toast-title">' + title + '</div>' +
        '<div class="toast-msg">'   + msg   + '</div>' +
      '</div>';

    container.appendChild(toast);
    toast.addEventListener('click', function () { removeToast(toast); });
    setTimeout(function () { removeToast(toast); }, 4000);
  }

  function removeToast(toast) {
    toast.classList.add('hide');
    setTimeout(function () { toast.remove(); }, 300);
  }

  /* Expose globally so other modules can call it */
  window.showToast = showToast;

})();
