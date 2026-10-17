(function () {
  const api = window.CGMI_INCIDENTS_API || {};
  const el = (id) => document.getElementById(id);

  let activeReports = [];
  let sortMode = 'latest';
  let activeTab = 'pending';
  let dateFilter = '';       // YYYY-MM-DD string or empty

  // ── UI-only Trash mode (super-admin) ─────────────────────────────────────────
  // When enabled (via window.CGMI_INCIDENTS_TRASH on the super-admin view) the
  // "Archive" action is replaced by a "Delete" action that moves a reviewed report
  // to a Trash tab. Nothing is deleted from the database: the set of trashed report
  // IDs lives in the browser (localStorage) and is re-applied as a filter on every
  // render, since the report lists are always refetched from the server.
  const trashEnabled = window.CGMI_INCIDENTS_TRASH === true;
  const TRASH_STORAGE_KEY = 'cgmi.incidentReports.trashedIds';
  // Reports the super-admin has "permanently" removed from the Trash view. Kept in the
  // database, but filtered out of every tab (including Trash) so they don't reappear
  // after a refresh. This is what makes "Delete Permanently" actually stick.
  const PURGED_STORAGE_KEY = 'cgmi.incidentReports.purgedIds';
  const trashedIds = new Set();
  const purgedIds = new Set();
  function readIdSet(key, set) {
    try {
      const raw = localStorage.getItem(key);
      const parsed = raw ? JSON.parse(raw) : null;
      if (Array.isArray(parsed)) parsed.forEach((id) => set.add(String(id)));
    } catch { /* ignore corrupt storage */ }
  }
  function loadTrashedIds() {
    if (!trashEnabled) return;
    readIdSet(TRASH_STORAGE_KEY, trashedIds);
    readIdSet(PURGED_STORAGE_KEY, purgedIds);
  }
  function persistTrashedIds() {
    if (!trashEnabled) return;
    try { localStorage.setItem(TRASH_STORAGE_KEY, JSON.stringify([...trashedIds])); } catch { /* ignore */ }
  }
  function persistPurgedIds() {
    if (!trashEnabled) return;
    try { localStorage.setItem(PURGED_STORAGE_KEY, JSON.stringify([...purgedIds])); } catch { /* ignore */ }
  }
  const isTrashed = (id) => trashedIds.has(String(id));
  const isPurged = (id) => purgedIds.has(String(id));

  const esc = (v) =>
    String(v ?? '').replace(/[&<>"']/g, (m) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[m]));

  function statusClass(status) {
    if (status === 'Pending') return 'badge-pending';
    if (status === 'Rejected') return 'badge-rejected';
    return 'badge-approved';
  }

  async function loadActiveReports() {
    const response = await fetch(api.list, { credentials: 'same-origin' });
    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Unable to load incident reports.');
    }

    activeReports = data.items || [];
    renderStats();
    updateTabCounts();
    renderReports();
  }

  // Reports shown in the normal (non-trash) tabs. In trash mode, trashed reports are
  // hidden from these tabs and stats and appear only in the Trash tab.
  function visibleReports() {
    if (!trashEnabled) return activeReports;
    // Exclude both trashed (in Trash tab) and purged (permanently removed from the UI).
    return activeReports.filter((r) => !isTrashed(r.id) && !isPurged(r.id));
  }

  // Reports the super-admin has moved to Trash (still present in the database).
  // Purged reports are excluded so they stay gone after a refresh.
  function trashedReports() {
    return trashEnabled ? activeReports.filter((r) => isTrashed(r.id) && !isPurged(r.id)) : [];
  }

  function renderStats() {
    const visible = visibleReports();
    const pending = visible.filter((r) => r.status === 'Pending').length;
    const approved = visible.filter((r) => r.status === 'Approved').length;
    const rejected = visible.filter((r) => r.status === 'Rejected').length;

    el('stat-total').textContent = visible.length;
    el('stat-approved').textContent = approved;
    el('stat-pending').textContent = pending;
    el('stat-rejected').textContent = rejected;
    el('reports-count-badge').textContent = visible.length;
  }

  function updateTabCounts() {
    const visible = visibleReports();
    el('tab-count-pending').textContent = visible.filter((r) => r.status === 'Pending').length;
    el('tab-count-approved').textContent = visible.filter((r) => r.status === 'Approved').length;
    el('tab-count-rejected').textContent = visible.filter((r) => r.status === 'Rejected').length;
    if (trashEnabled && el('tab-count-trash')) {
      el('tab-count-trash').textContent = trashedReports().length;
    }
  }

  function currentReports() {
    let items;
    if (trashEnabled && activeTab === 'trash') {
      items = trashedReports();
    } else {
      const statusMap = {
        'pending': 'Pending',
        'approved': 'Approved',
        'rejected': 'Rejected'
      };
      const targetStatus = statusMap[activeTab];
      // In trash mode the status tabs must not include reports moved to Trash.
      items = visibleReports().filter((r) => r.status === targetStatus);
    }

    // Apply date filter if set
    if (dateFilter) {
      items = items.filter((r) => {
        if (!r.date) return false;
        // The server sends an offset-aware ISO string (e.g. "2024-10-01T00:30:00+08:00").
        // Slice the date portion directly from the string instead of calling toISOString(),
        // which converts to UTC first and shifts early-morning Manila-timezone records
        // back to the previous calendar day.
        const reportDate = r.date.slice(0, 10);
        return reportDate === dateFilter;
      });
    }

    return items;
  }

  function sortedReports() {
    const items = [...currentReports()];

    if (sortMode === 'oldest') {
      items.sort((a, b) => new Date(a.date) - new Date(b.date));
    } else {
      items.sort((a, b) => new Date(b.date) - new Date(a.date));
    }

    return items;
  }

  // Build the action buttons for a report. `size` is '' or 'btn-sm' (card vs modal).
  function actionButtons(report, size) {
    const sz = size ? ` ${size}` : '';
    const buttons = [];

    // Trash tab (super-admin): restore or permanently remove from the UI.
    if (trashEnabled && activeTab === 'trash') {
      buttons.push(`<button class="btn btn-secondary${sz} action-restore" data-id="${report.id}"><span class="btn-inline-icon ui-icon icon-reports" aria-hidden="true"></span>Restore</button>`);
      buttons.push(`<button class="btn btn-danger${sz} action-purge" data-id="${report.id}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete Permanently</button>`);
      return buttons.join('');
    }

    if (report.status === 'Pending') {
      buttons.push(`<button class="btn btn-primary${sz} action-approve" data-id="${report.id}">Confirm</button>`);
      buttons.push(`<button class="btn btn-danger${sz} action-reject" data-id="${report.id}">Reject</button>`);
    }

    // Reviewed (approved/rejected) reports can be acted on.
    if (report.status === 'Approved' || report.status === 'Rejected') {
      if (trashEnabled) {
        // Super-admin: Delete moves the report to Trash (UI only).
        buttons.push(`<button class="btn btn-danger${sz} action-delete" data-id="${report.id}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete</button>`);
      }
    }

    return buttons.join('');
  }

  function cardActionButtons(report) {
    return actionButtons(report, 'btn-sm');
  }

  function renderReports() {
    const grid = el('reports-grid');
    const items = sortedReports();

    const emptyMessages = {
      'pending': 'No pending incident reports.',
      'approved': 'No approved incident reports.',
      'rejected': 'No rejected incident reports.',
      'trash': 'Trash is empty.'
    };

    grid.innerHTML = items.length
      ? items.map((report) => `
        <article class="report-card" data-id="${report.id}">
          <div class="report-card-media">
            ${report.image
              ? `<img src="${report.image}" alt="${esc(report.title)}">`
              : '<div class="card-img-placeholder ui-icon icon-incident" aria-hidden="true"></div>'}
          </div>
          <div class="report-card-body">
            <div class="report-card-top">
              <span class="badge ${statusClass(report.status)}">${esc(report.status)}</span>
              <span class="report-ref">${esc(report.refId)}</span>
            </div>
            <h3 class="report-title">${esc(report.title)}</h3>
            ${report.category ? `<span class="report-category-tag">${esc(report.category)}</span>` : ''}
            <p class="report-desc">${esc(report.description)}</p>
            <div class="report-meta">${esc(report.reportedBy || '—')} · ${new Date(report.date).toLocaleString('en-PH')}</div>
            <div class="report-card-actions">${cardActionButtons(report)}</div>
          </div>
        </article>`).join('')
      : `<div class="empty-state">${emptyMessages[activeTab] || 'No incident reports found.'}</div>`;
  }

  function modalActionButtons(report) {
    return actionButtons(report, '');
  }

  function openModal(id) {
    const report = activeReports.find((r) => Number(r.id) === Number(id));
    if (!report) return;

    el('modal-content').innerHTML = `
      <div class="report-modal">
        <div class="report-modal-media">
          ${report.image ? `<img src="${report.image}" alt="${esc(report.title)}">` : '<div class="modal-img-placeholder ui-icon icon-incident" aria-hidden="true"></div>'}
        </div>
        <div class="report-modal-body">
          <button class="modal-close-btn" id="report-modal-close">x</button>
          <div class="report-card-top">
            <span class="badge ${statusClass(report.status)}">${esc(report.status)}</span>
            <span class="report-ref">${esc(report.refId)}</span>
          </div>
          <h2>${esc(report.title)}</h2>
          ${report.category ? `<span class="report-category-tag">${esc(report.category)}</span>` : ''}
          <p>${esc(report.narrative)}</p>
          <div class="report-meta">
            Reported by: ${esc(report.reportedBy || '—')}<br>
            Reviewed by: ${esc(report.reviewedBy || '—')}<br>
            Review notes: ${esc(report.reviewNotes || '—')}
          </div>
          <div class="report-card-actions">${modalActionButtons(report)}</div>
        </div>
      </div>
    `;

    el('modal-backdrop').classList.add('active');

    document.getElementById('report-modal-close')?.addEventListener('click', closeModal);
    document.querySelector('#modal-content .action-approve')?.addEventListener('click', (e) => approveReport(e.currentTarget.dataset.id));
    document.querySelector('#modal-content .action-reject')?.addEventListener('click', (e) => rejectReport(e.currentTarget.dataset.id));
    document.querySelector('#modal-content .action-delete')?.addEventListener('click', (e) => deleteReport(e.currentTarget.dataset.id));
    document.querySelector('#modal-content .action-restore')?.addEventListener('click', (e) => restoreReport(e.currentTarget.dataset.id));
    document.querySelector('#modal-content .action-purge')?.addEventListener('click', (e) => purgeReport(e.currentTarget.dataset.id));
  }

  function closeModal() {
    el('modal-backdrop').classList.remove('active');
  }

  async function updateIncident(id, action, successMessage, reviewNotes = '') {
    const response = await fetch(api.review, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        id: Number(id),
        action,
        reviewNotes
      })
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.message || successMessage);
    }

    // Update the report in active list
    const index = activeReports.findIndex((r) => Number(r.id) === Number(data.item.id));
    if (index >= 0) {
      activeReports[index] = data.item;
    }

    renderStats();
    updateTabCounts();
    renderReports();
    closeModal();

    toast(data.message || successMessage, 'success');
  }

  async function approveReport(id) {
    try {
      await updateIncident(id, 'approve', 'Report approved.', 'Approved by admin');
    } catch (error) {
      toast(error.message || 'Unable to approve report.', 'error');
    }
  }

  async function rejectReport(id) {
    try {
      await updateIncident(id, 'reject', 'Report rejected.', 'Rejected by admin');
    } catch (error) {
      toast(error.message || 'Unable to reject report.', 'error');
    }
  }

  // ── UI-only Trash actions (super-admin) ──────────────────────────────────────
  // Move a reviewed report to Trash. This never calls the server; the record stays
  // in the database and is simply hidden from the normal tabs.
  function deleteReport(id) {
    if (!trashEnabled) return;
    trashedIds.add(String(id));
    persistTrashedIds();
    renderStats();
    updateTabCounts();
    renderReports();
    closeModal();
    toast('Report moved to Trash.', 'success');
  }

  // Restore a report from Trash back to the normal tabs.
  function restoreReport(id) {
    if (!trashEnabled) return;
    trashedIds.delete(String(id));
    persistTrashedIds();
    renderStats();
    updateTabCounts();
    renderReports();
    closeModal();
    toast('Report restored.', 'success');
  }

  // Permanently remove a report from the Trash view. The database record is kept;
  // this only forgets the trashed marker, so the report reappears in the normal
  // tabs on the next server refresh.
  async function purgeReport(id) {
    if (!trashEnabled) return;
    const ok = await confirmModal({
      title: 'Delete Permanently',
      message: 'Permanently remove this report from Trash? The database record is kept; it will reappear in the normal tabs on next refresh.',
      confirmText: 'Delete Permanently',
      cancelText: 'Cancel',
    });
    if (!ok) return;
    // Permanently hide it: remove from Trash and remember it as purged so it never
    // reappears in any tab after a refresh (the DB record is intentionally kept).
    trashedIds.delete(String(id));
    purgedIds.add(String(id));
    persistTrashedIds();
    persistPurgedIds();
    renderStats();
    updateTabCounts();
    renderReports();
    closeModal();
    toast('Report permanently removed from Trash.', 'success');
  }

  function setActiveTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.reports-tab').forEach((btn) => {
      const isActive = btn.dataset.tab === tab;
      btn.classList.toggle('active', isActive);
      btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    renderReports();
  }

  // In-app confirmation pop-up (replaces the native window.confirm dialog).
  // Matches the incoming-confirmation delete modal: left-aligned header with an X,
  // message body, and Cancel / Delete Permanently buttons in the footer. Resolves true/false.
  function confirmModal({ title = 'Are you sure?', message = '', confirmText = 'Confirm', cancelText = 'Cancel', danger = true } = {}) {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'confirm-modal-overlay';
      overlay.innerHTML = `
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
          <div class="confirm-modal-header">
            <h3 id="confirm-modal-title">${esc(title)}</h3>
            <button type="button" class="confirm-modal-x" aria-label="Close">&#x2715;</button>
          </div>
          <div class="confirm-modal-body">${esc(message)}</div>
          <div class="confirm-modal-actions">
            <button type="button" class="btn btn-ghost" data-confirm-cancel>${esc(cancelText)}</button>
            <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-confirm-ok>${esc(confirmText)}</button>
          </div>
        </div>`;

      const close = (result) => {
        overlay.removeEventListener('click', onOverlay);
        document.removeEventListener('keydown', onKey);
        overlay.remove();
        resolve(result);
      };
      const onOverlay = (e) => { if (e.target === overlay) close(false); };
      const onKey = (e) => { if (e.key === 'Escape') close(false); };

      overlay.querySelector('[data-confirm-ok]').addEventListener('click', () => close(true));
      overlay.querySelector('[data-confirm-cancel]').addEventListener('click', () => close(false));
      overlay.querySelector('.confirm-modal-x').addEventListener('click', () => close(false));
      overlay.addEventListener('click', onOverlay);
      document.addEventListener('keydown', onKey);

      document.body.appendChild(overlay);
      overlay.querySelector('[data-confirm-ok]').focus();
    });
  }

  // Typed toast matching the incoming-confirmation style: white card with a colored
  // left border, colored icon, bold title, and message below.
  const TOAST_META = {
    success: { color: 'var(--accent-success, #16a34a)', icon: '\u2713', title: 'Success' },
    error:   { color: 'var(--accent-danger, #dc2626)',  icon: '\u26A0', title: 'Error' },
    warning: { color: 'var(--accent-warning, #d97706)', icon: '\u26A0', title: 'Heads up' },
    info:    { color: 'var(--blue-medium, #4569AD)',    icon: '\u2139', title: 'Notice' },
  };
  // Route a toast to the correct on-screen stack by type:
  // error / warning / rejected / danger -> BOTTOM-LEFT, everything else -> BOTTOM-RIGHT.
  function toastStackFor(type) {
    const side = /^(error|warning|reject|rejected|danger|fail)/i.test(String(type || '')) ? 'left' : 'right';
    const id = 'toast-stack-' + side;
    let stack = document.getElementById(id);
    if (!stack) {
      stack = document.createElement('div');
      stack.id = id;
      stack.className = 'toast-stack toast-stack-' + side;
      stack.setAttribute('aria-live', side === 'left' ? 'assertive' : 'polite');
      document.body.appendChild(stack);
    }
    return stack;
  }

  function toast(message, type = 'info', title = null) {
    const c = toastStackFor(type);
    if (!c) return alert(message);

    const meta = TOAST_META[type] || TOAST_META.info;
    const n = document.createElement('div');
    n.className = 'toast show';
    n.style.setProperty('--toast-color', meta.color);
    n.innerHTML = `
      <div class="toast-icon" style="color:${meta.color}" aria-hidden="true">${meta.icon}</div>
      <div class="toast-body">
        <div class="toast-title">${esc(title || meta.title)}</div>
        <div class="toast-msg">${esc(message)}</div>
      </div>`;
    const dismiss = () => {
      n.classList.remove('show');
      n.classList.add('hide');
      setTimeout(() => n.remove(), 300);
    };
    n.addEventListener('click', dismiss);
    c.appendChild(n);
    setTimeout(dismiss, type === 'error' ? 6000 : 4000);
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadTrashedIds();
    loadActiveReports().catch((err) => toast(err.message, 'error'));

    // Tab click handlers
    document.querySelectorAll('.reports-tab').forEach((btn) => {
      btn.addEventListener('click', () => {
        setActiveTab(btn.dataset.tab);
      });
    });

    el('reports-grid')?.addEventListener('click', (e) => {
      const approveBtn = e.target.closest('.action-approve');
      const rejectBtn = e.target.closest('.action-reject');
      const deleteBtn = e.target.closest('.action-delete');
      const restoreBtn = e.target.closest('.action-restore');
      const purgeBtn = e.target.closest('.action-purge');

      if (approveBtn) { approveReport(approveBtn.dataset.id); return; }
      if (rejectBtn) { rejectReport(rejectBtn.dataset.id); return; }
      if (deleteBtn) { deleteReport(deleteBtn.dataset.id); return; }
      if (restoreBtn) { restoreReport(restoreBtn.dataset.id); return; }
      if (purgeBtn) { purgeReport(purgeBtn.dataset.id); return; }

      // Click anywhere on the card opens the modal
      const card = e.target.closest('.report-card');
      if (card) openModal(card.dataset.id);
    });

    el('modal-backdrop')?.addEventListener('click', (e) => {
      if (e.target.id === 'modal-backdrop') closeModal();
    });

    el('sort-btn')?.addEventListener('click', () => {
      sortMode = sortMode === 'latest' ? 'oldest' : 'latest';
      el('sort-label').textContent = sortMode === 'latest' ? 'Latest First' : 'Oldest First';
      renderReports();
    });

    el('refresh-btn')?.addEventListener('click', () => {
      const btn = el('refresh-btn');
      btn.classList.add('is-loading');
      loadActiveReports()
        .catch((err) => toast(err.message, 'error'))
        .finally(() => btn.classList.remove('is-loading'));
    });

    // Date filter
    el('date-filter')?.addEventListener('change', (e) => {
      dateFilter = e.target.value || '';
      el('clear-date-filter').style.display = dateFilter ? 'inline-flex' : 'none';
      renderReports();
    });

    el('clear-date-filter')?.addEventListener('click', () => {
      dateFilter = '';
      el('date-filter').value = '';
      el('clear-date-filter').style.display = 'none';
      renderReports();
    });

  });
})();
