/* ============================================================
 * Notification bell component (shared across all panels)
 * ------------------------------------------------------------
 * Reads endpoint URLs from window.CGMI_NOTIFICATIONS_API (set by
 * app/Views/shared/notification-bell.php) and drives the bell markup
 * rendered by that same partial:
 *
 *   #notif-bell            the bell button
 *   #notif-badge           unread count badge
 *   #notif-panel           the dropdown panel
 *   #notif-list            <ul> the items render into
 *   #notif-markall         "Mark all as read" button
 *   #notif-refresh         manual refresh button
 *
 * Behavior: badge polling, open/close (click, outside-click, Esc),
 * render read/unread, mark one/all read, click-to-navigate, empty state.
 * ============================================================ */
(function () {
  'use strict';

  var api = window.CGMI_NOTIFICATIONS_API;
  if (!api || !api.list) {
    return; // partial not present on this page
  }

  var bell    = document.getElementById('notif-bell');
  var badge   = document.getElementById('notif-badge');
  var panel   = document.getElementById('notif-panel');
  var list    = document.getElementById('notif-list');
  var markAll = document.getElementById('notif-markall');
  var refresh = document.getElementById('notif-refresh');
  var titleCount = document.getElementById('notif-title-count');

  if (!bell || !panel || !list) {
    return;
  }

  var POLL_MS = 60000; // background badge refresh cadence
  var state = { items: [], unread: 0, open: false, loading: false, loaded: false };

  /* ---- helpers ---------------------------------------------------------- */

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function request(url, options) {
    var opts = Object.assign({ credentials: 'same-origin', headers: { 'Accept': 'application/json' } }, options || {});
    return fetch(url, opts).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok || data.success === false) {
          throw new Error((data && data.message) || ('Request failed: ' + res.status));
        }
        return data;
      });
    });
  }

  function setUnread(count) {
    state.unread = Math.max(0, count | 0);
    if (state.unread > 0) {
      badge.textContent = state.unread > 99 ? '99+' : String(state.unread);
      badge.classList.add('show');
    } else {
      badge.classList.remove('show');
      // User has read everything — reset the seen counter so the next
      // new notification will trigger the popup again.
      sessionStorage.removeItem('cgmi_notif_last_seen');
    }
    if (titleCount) {
      titleCount.textContent = String(state.unread);
      titleCount.style.display = state.unread > 0 ? '' : 'none';
    }
    if (markAll) {
      markAll.disabled = state.unread === 0;
    }
  }

  /* ---- rendering -------------------------------------------------------- */

  function renderStatus(html) {
    list.innerHTML = '<li class="notif-status">' + html + '</li>';
  }

  function renderEmpty() {
    list.innerHTML =
      '<li class="notif-empty">' +
      '<span class="ui-icon icon-bell-off" aria-hidden="true"></span>' +
      '<div class="notif-empty-title">You\u2019re all caught up</div>' +
      '<div>No notifications right now.</div>' +
      '</li>';
  }

  function itemMarkup(n) {
    var unreadClass = n.isRead ? '' : ' is-unread';
    var link = n.link ? ' data-link="' + escapeHtml(n.link) + '"' : '';
    var msg = n.message ? '<p class="notif-item-msg">' + escapeHtml(n.message) + '</p>' : '';
    return '' +
      '<li class="notif-item' + unreadClass + '" role="button" tabindex="0"' +
      ' data-id="' + (n.id | 0) + '" data-type="' + escapeHtml(n.type) + '"' + link + '>' +
        '<span class="notif-dot" aria-hidden="true"></span>' +
        '<span class="notif-icon"><span class="ui-icon ' + escapeHtml(n.icon || 'icon-info') + '" aria-hidden="true"></span></span>' +
        '<span class="notif-body">' +
          '<span class="notif-row-top">' +
            '<span class="notif-item-title">' + escapeHtml(n.title) + '</span>' +
            '<span class="notif-time">' + escapeHtml(n.timeAgo || '') + '</span>' +
          '</span>' +
          msg +
          '<span class="notif-type-tag">' + escapeHtml(n.typeLabel || n.type || 'System') + '</span>' +
        '</span>' +
      '</li>';
  }

  function renderList() {
    if (!state.items.length) {
      renderEmpty();
      return;
    }
    list.innerHTML = state.items.map(itemMarkup).join('');
  }

  /* ---- data ------------------------------------------------------------- */

  function loadList() {
    if (state.loading) { return; }
    state.loading = true;
    if (!state.loaded) { renderStatus('Loading notifications\u2026'); }
    request(api.list).then(function (data) {
      state.items = Array.isArray(data.notifications) ? data.notifications : [];
      state.loaded = true;
      setUnread(data.unreadCount || 0);
      renderList();
    }).catch(function () {
      renderStatus('Could not load notifications. Try again.');
    }).finally(function () {
      state.loading = false;
    });
  }

  function pollCount() {
    if (!api.unreadCount) { return; }
    request(api.unreadCount).then(function (data) {
      setUnread(data.unreadCount || 0);
    }).catch(function () { /* silent */ });
  }

  function markOneRead(id) {
    if (!api.markRead || !id) { return Promise.resolve(); }
    return request(api.markRead, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ id: id })
    }).then(function (data) {
      var item = state.items.find(function (n) { return (n.id | 0) === (id | 0); });
      if (item) { item.isRead = true; }
      setUnread(typeof data.unreadCount === 'number' ? data.unreadCount : Math.max(0, state.unread - 1));
    }).catch(function () { /* silent */ });
  }

  function markAllRead() {
    if (!api.markAllRead || state.unread === 0) { return; }
    request(api.markAllRead, { method: 'POST', headers: { 'Accept': 'application/json' } })
      .then(function () {
        state.items.forEach(function (n) { n.isRead = true; });
        setUnread(0);
        renderList();
      }).catch(function () { /* silent */ });
  }

  /* ---- open / close ----------------------------------------------------- */

  function openPanel() {
    state.open = true;
    panel.classList.add('show');
    bell.classList.add('is-open');
    bell.setAttribute('aria-expanded', 'true');
    panel.setAttribute('aria-hidden', 'false');
    loadList(); // always refresh on open
  }

  function closePanel() {
    state.open = false;
    panel.classList.remove('show');
    bell.classList.remove('is-open');
    bell.setAttribute('aria-expanded', 'false');
    panel.setAttribute('aria-hidden', 'true');
  }

  function togglePanel() {
    if (state.open) { closePanel(); } else { openPanel(); }
  }

  /* ---- events ----------------------------------------------------------- */

  bell.addEventListener('click', function (e) {
    e.stopPropagation();
    togglePanel();
  });

  // Keep clicks inside the panel from closing it via the document handler.
  panel.addEventListener('click', function (e) { e.stopPropagation(); });

  document.addEventListener('click', function () {
    if (state.open) { closePanel(); }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && state.open) { closePanel(); }
  });

  if (markAll) {
    markAll.addEventListener('click', function (e) { e.preventDefault(); markAllRead(); });
  }
  if (refresh) {
    refresh.addEventListener('click', function (e) { e.preventDefault(); state.loaded = false; loadList(); });
  }

  function handleActivate(target) {
    var li = target.closest ? target.closest('.notif-item') : null;
    if (!li) { return; }
    var id = parseInt(li.getAttribute('data-id'), 10);
    var link = li.getAttribute('data-link');
    var wasUnread = li.classList.contains('is-unread');

    var done = wasUnread ? markOneRead(id) : Promise.resolve();
    if (wasUnread) {
      li.classList.remove('is-unread');
    }
    if (link) {
      // Navigate after the read state is recorded (best-effort).
      done.finally(function () { window.location.href = link; });
    }
  }

  list.addEventListener('click', function (e) { handleActivate(e.target); });
  list.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      handleActivate(e.target);
    }
  });

  /* ---- init ------------------------------------------------------------- */

  /* Welcome popup logic:
     - Uses localStorage (persists across reloads) keyed by user ID.
     - Stores the highest notification ID that was shown in the popup.
     - Only shows the popup if there are unread notifications with IDs
       greater than the last stored ID — meaning genuinely new ones the
       user hasn't been alerted about yet.
     - When the user marks all notifications as read, the stored ID is
       cleared so future new notifications will trigger the popup again. */
  var userId = (window.CGMI_CURRENT_USER && window.CGMI_CURRENT_USER.id)
    ? String(window.CGMI_CURRENT_USER.id) : 'guest';
  var WELCOME_KEY = 'cgmi_notif_popup_seen_' + userId;

  function getLastPopupId() {
    return parseInt(localStorage.getItem(WELCOME_KEY) || '0', 10);
  }

  function setLastPopupId(id) {
    localStorage.setItem(WELCOME_KEY, String(id));
  }

  function showWelcomePopup(count) {
    var overlay = document.getElementById('notifWelcomeOverlay');
    if (!overlay) { return; }

    var countEl    = document.getElementById('notifWelcomeCount');
    var badgeEl    = document.getElementById('notifWelcomeBadge');
    var openBtn    = document.getElementById('notifWelcomeOpen');
    var dismissBtn = document.getElementById('notifWelcomeDismiss');

    if (countEl) { countEl.textContent = count > 99 ? '99+' : String(count); }
    if (badgeEl) { badgeEl.textContent = count > 99 ? '99+' : String(count); }

    overlay.removeAttribute('aria-hidden');
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        overlay.classList.add('show');
      });
    });

    function dismiss() {
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden', 'true');
    }

    if (openBtn) {
      openBtn.addEventListener('click', function () {
        dismiss();
        setTimeout(function () { openPanel(); }, 0);
      }, { once: true });
    }
    if (dismissBtn) {
      dismissBtn.addEventListener('click', dismiss, { once: true });
    }
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) { dismiss(); }
    });
  }

  setUnread(0);
  // On first load, fetch the full notification list to check for new unread
  // items the user hasn't been alerted about via the popup yet.
  (function initialPoll() {
    if (!api.list) { return; }
    request(api.list).then(function (data) {
      var notifications = Array.isArray(data.notifications) ? data.notifications : [];
      var count = typeof data.unreadCount === 'number' ? data.unreadCount : 0;
      setUnread(count);
      state.items   = notifications;
      state.loaded  = true;

      if (count === 0) { return; }

      // Find the highest ID among unread notifications.
      var maxUnreadId = 0;
      notifications.forEach(function (n) {
        if (!n.isRead && (n.id | 0) > maxUnreadId) {
          maxUnreadId = n.id | 0;
        }
      });

      // Only show popup if there are unread notifications with IDs higher
      // than the last time we showed the popup for this user.
      if (maxUnreadId > getLastPopupId()) {
        setLastPopupId(maxUnreadId);
        showWelcomePopup(count);
      }
    }).catch(function () {
      // Fallback: just update the badge silently.
      if (api.unreadCount) {
        request(api.unreadCount).then(function (d) {
          setUnread(d.unreadCount || 0);
        }).catch(function () {});
      }
    });
  })();
  setInterval(function () { if (!state.open) { pollCount(); } }, POLL_MS);
})();
