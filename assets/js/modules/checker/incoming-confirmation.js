(() => {
  const $ = (id) => document.getElementById(id);
  const appBase = () => {
    const path = window.location.pathname;
    const marker = '/pages/';
    const idx = path.indexOf(marker);
    return idx >= 0 ? path.slice(0, idx) : '';
  };
  const api = (path) => `${appBase()}${path}`;
  const escapeHtml = (value) => {
    const map = {'&':'&amp;','<':'&lt;','>':'&gt;','\'':'&#39;','"':'&quot;'};
    return String(value ?? '').replace(/[&<>'"]/g, c => map[c]);
  };
  const fmtDate = (value) => value ? new Date(String(value).replace(' ', 'T')).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
  const badge = (status) => {
    const s = String(status || 'Pending');
    const cls = s.toLowerCase();
    return `<span class="badge ${escapeHtml(cls)}">${escapeHtml(s)}</span>`;
  };

  // Floating toast card — replaces native window dialogs (alert) for feedback.
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

  function showToast(message, type = 'info', title = null) {
    const container = toastStackFor(type);
    if (!container) { console.log(`[${type}] ${message}`); return; }
    const meta = TOAST_META[type] || TOAST_META.info;
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.setProperty('--toast-color', meta.color);
    toast.innerHTML = `
      <div class="toast-icon" style="color:${meta.color}">${meta.icon}</div>
      <div class="toast-body">
        <div class="toast-title">${escapeHtml(title || meta.title)}</div>
        <div class="toast-msg">${escapeHtml(message)}</div>
      </div>`;
    const dismiss = () => {
      toast.classList.add('hide');
      toast.addEventListener('animationend', () => toast.remove(), { once: true });
    };
    toast.addEventListener('click', dismiss);
    container.appendChild(toast);
    setTimeout(dismiss, type === 'error' ? 6000 : 4000);
  }

  // In-app confirmation pop-up (replaces the native window.confirm dialog).
  // Returns a Promise that resolves true when confirmed, false otherwise.
  function confirmModal({ title = 'Are you sure?', message = '', confirmText = 'Confirm', cancelText = 'Cancel', danger = true } = {}) {
    return new Promise((resolve) => {
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop show confirm-modal-backdrop';
      backdrop.innerHTML = `
        <div class="modal confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
          <div class="modal-header">
            <div>
              <h3 id="confirm-modal-title">${escapeHtml(title)}</h3>
            </div>
            <button type="button" class="confirm-modal-x" aria-label="Close">&#x2715;</button>
          </div>
          <div class="confirm-modal-body">${escapeHtml(message)}</div>
          <div class="modal-actions">
            <button type="button" class="btn btn-ghost" data-confirm-cancel>${escapeHtml(cancelText)}</button>
            <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-confirm-ok>${escapeHtml(confirmText)}</button>
          </div>
        </div>`;

      const close = (result) => {
        backdrop.removeEventListener('click', onBackdrop);
        document.removeEventListener('keydown', onKey);
        backdrop.remove();
        resolve(result);
      };
      const onBackdrop = (e) => { if (e.target === backdrop) close(false); };
      const onKey = (e) => { if (e.key === 'Escape') close(false); };

      backdrop.querySelector('[data-confirm-ok]').addEventListener('click', () => close(true));
      backdrop.querySelector('[data-confirm-cancel]').addEventListener('click', () => close(false));
      backdrop.querySelector('.confirm-modal-x').addEventListener('click', () => close(false));
      backdrop.addEventListener('click', onBackdrop);
      document.addEventListener('keydown', onKey);

      document.body.appendChild(backdrop);
      backdrop.querySelector('[data-confirm-ok]').focus();
    });
  }

  let pendingItems = [];
  let confirmedItems = [];
  let rejectedItems = [];
  let allItems = [];       // combined pending + confirmed + rejected (per-item), for grouping by delivery
  let activeTab = 'all';

  // ── Server-backed Trash (super-admin) ────────────────────────────────────────
  // Trash/restore/purge actions call the delete-incoming API which updates the DB.
  // The server returns trashed items in the 'trashed' key of pending-incoming.php,
  // so all roles see consistent data — deleted records never appear for admin/inventory.
  const trashEnabled = !!document.body.getAttribute('data-incoming-trash-enabled');

  // Migrate away from legacy localStorage keys — clear them once so old "deleted"
  // IDs stored client-side don't create confusion now that the DB drives state.
  (function migrateLegacyLocalStorage() {
    try {
      localStorage.removeItem('cgmi.incomingConfirmation.trashedIds');
      localStorage.removeItem('cgmi.incomingConfirmation.purgedIds');
    } catch { /* ignore */ }
  })();

  // Trash items are returned from the server (data.trashed) and stored here.
  let trashedItems = [];

  // No-op stubs kept so any stray references don't throw (can be removed later).
  function loadTrashedIds() { /* server-driven — no localStorage needed */ }
  const isTrashed = () => false; // normal items are never trashed; trash tab uses trashedItems
  const isPurged  = () => false;

  async function getJson(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', ...options });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch { throw new Error('Unexpected server response.'); }
    if (!res.ok || data.success === false) throw new Error(data.message || 'Request failed.');
    return data;
  }

  // --- Tab switching ---
  function initTabs() {
    const tabs = document.querySelectorAll('.panel-tab[data-tab]');
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        if (target === activeTab) return;
        activeTab = target;
        tabs.forEach(t => {
          t.classList.remove('active');
          t.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');
        document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.add('hidden'));
        const activePanel = $(`tab-panel-${target}`);
        if (activePanel) activePanel.classList.remove('hidden');
      });
    });
  }

  // --- Counts (delivery/transaction level, by header roll-up status) ---
  function setCounts() {
    const groups = groupByTransaction(allItems);
    const total = groups.length;
    const pending = groups.filter(g => String(g.deliveryStatus).toLowerCase() === 'pending').length;
    const checked = total - pending;
    const trashed = groupByTransaction(trashedItems).length;

    const set = (id, val) => { const el = $(id); if (el) el.textContent = val; };
    set('pending-count', pending);
    set('checked-count', checked);
    set('total-count', total);
    set('tab-all-count', total);
    set('tab-pending-count', pending);
    set('tab-checked-count', checked);
    set('tab-trash-count', trashed);
  }

  // --- Group a flat item list into transactions keyed by ingoingId ---
  // Preserves the incoming order of transactions (arrays already sorted newest-first).
  function groupByTransaction(items) {
    const groups = [];
    const index = new Map();
    (items || []).forEach(item => {
      const key = String(item.ingoingId ?? '0');
      let group = index.get(key);
      if (!group) {
        group = {
          ingoingId: item.ingoingId,
          supplierName: item.supplierName,
          createdByName: item.createdByName,
          createdDate: item.createdDate,
          deliveryStatus: item.deliveryStatus || 'Pending',
          items: [],
        };
        index.set(key, group);
        groups.push(group);
      }
      group.items.push(item);
    });
    return groups;
  }

  // Build one delivery (transaction) row mirroring the checker "Incoming Deliveries" table:
  // Date, Supplier, Items preview (+N more), Total Qty, actor column, then a Details button.
  function deliveryRow(group, actorName, mode) {
    const items = group.items || [];
    const MAX_PREVIEW = 2;
    const shown = items.slice(0, MAX_PREVIEW);
    const moreCount = items.length - shown.length;
    const itemsList = shown.map(i =>
      `<div class="item-line">${escapeHtml(i.itemName)} — ${i.quantity} ${escapeHtml(i.unit)}</div>`
    ).join('')
      + (moreCount > 0 ? `<div class="item-line more-items">+${moreCount} more item${moreCount > 1 ? 's' : ''}</div>` : '');
    const totalQty = items.reduce((sum, i) => sum + (i.quantity || 0), 0);
    const detailsBtn = `<button type="button" class="btn btn-secondary small-btn" data-txn-details="${group.ingoingId}"><span class="btn-inline-icon ui-icon icon-info" aria-hidden="true"></span>Details</button>`;

    let action = detailsBtn;
    if (trashEnabled && mode === 'trash') {
      // Trash tab: restore back to the normal tabs, or permanently remove from the UI (never from the DB).
      action = `<div class="row-actions">
        ${detailsBtn}
        <button type="button" class="btn btn-ghost small-btn" data-txn-restore="${group.ingoingId}">Restore</button>
        <button type="button" class="btn btn-danger small-btn" data-txn-purge="${group.ingoingId}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete</button>
      </div>`;
    } else if (trashEnabled && String(group.deliveryStatus).toLowerCase() === 'checked') {
      // Reviewed (Checked) deliveries can be moved to Trash. Pending ones cannot.
      action = `<div class="row-actions">
        ${detailsBtn}
        <button type="button" class="btn btn-danger small-btn" data-txn-delete="${group.ingoingId}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete</button>
      </div>`;
    }

    return `<tr>
      <td class="col-hide-mobile">${fmtDate(group.createdDate)}</td>
      <td>${escapeHtml(group.supplierName || '—')}</td>
      <td class="col-hide-mobile"><div class="items-cell">${itemsList || '—'}</div></td>
      <td>${totalQty}</td>
      <td>${badge(group.deliveryStatus)}</td>
      <td class="col-hide-mobile">${escapeHtml(actorName || '—')}</td>
      <td>${action}</td>
    </tr>`;
  }

  // Generic paginated delivery-table renderer shared by all three tabs.
  function renderDeliveryTable(cfg) {
    const groups = cfg.groups();
    const tbody = $(cfg.tbodyId);

    if (!groups.length) {
      tbody.innerHTML = `<tr><td colspan="7" class="empty">${cfg.emptyLabel}</td></tr>`;
      renderPagination(cfg, 0, 0, 0);
      return;
    }

    const total = groups.length;
    const pageCount = Math.max(1, Math.ceil(total / cfg.state.pageSize));
    if (cfg.state.page > pageCount) cfg.state.page = pageCount;
    if (cfg.state.page < 1) cfg.state.page = 1;

    const start = (cfg.state.page - 1) * cfg.state.pageSize;
    const end = Math.min(start + cfg.state.pageSize, total);
    tbody.innerHTML = groups.slice(start, end).map(g => deliveryRow(g, cfg.actor(g), cfg.mode)).join('');
    renderPagination(cfg, start + 1, end, total);
  }

  function renderPagination(cfg, from, to, total) {
    const controls = $(cfg.paginationId);
    if (!controls) return;
    if (!total) { controls.innerHTML = ''; return; }

    const pageCount = Math.max(1, Math.ceil(total / cfg.state.pageSize));
    controls.innerHTML = `
      <div class="pagination-info">Showing ${from}–${to} of ${total}</div>
      <div class="pagination-controls">
        <label class="page-size-label">Rows:
          <select data-page-size>
            ${[10, 25, 50, 100].map(n => `<option value="${n}" ${n === cfg.state.pageSize ? 'selected' : ''}>${n}</option>`).join('')}
          </select>
        </label>
        <button type="button" class="btn btn-ghost small-btn" data-page-prev ${cfg.state.page <= 1 ? 'disabled' : ''}>Prev</button>
        <span class="page-indicator">Page ${cfg.state.page} / ${pageCount}</span>
        <button type="button" class="btn btn-ghost small-btn" data-page-next ${cfg.state.page >= pageCount ? 'disabled' : ''}>Next</button>
      </div>`;

    controls.querySelector('[data-page-size]').addEventListener('change', (e) => {
      cfg.state.pageSize = parseInt(e.target.value, 10) || 10;
      cfg.state.page = 1;
      renderDeliveryTable(cfg);
    });
    controls.querySelector('[data-page-prev]').addEventListener('click', () => {
      if (cfg.state.page > 1) { cfg.state.page--; renderDeliveryTable(cfg); }
    });
    controls.querySelector('[data-page-next]').addEventListener('click', () => {
      if (cfg.state.page < pageCount) { cfg.state.page++; renderDeliveryTable(cfg); }
    });
  }

  // Delivery groups filtered by header roll-up status ('pending' | 'checked' | null for all).
  // allItems only contains non-deleted records (server filters them out).
  function deliveryGroups(statusFilter) {
    const groups = groupByTransaction(allItems);
    if (!statusFilter) return groups;
    return groups.filter(g => String(g.deliveryStatus).toLowerCase() === statusFilter);
  }

  // Deliveries the super-admin has moved to Trash — populated from server's data.trashed.
  function trashGroups() {
    return groupByTransaction(trashedItems);
  }

  // Per-tab render configs (pagination state lives here). All tabs share the
  // combined delivery list and differ only by header-status filter.
  const actorOf = (g) => g.createdByName || 'Checker';
  const tabConfigs = {
    all: {
      groups: () => deliveryGroups(null),
      tbodyId: 'all-confirm-table', paginationId: 'all-pagination',
      emptyLabel: 'No incoming deliveries found.',
      actor: actorOf, state: { page: 1, pageSize: 10 },
    },
    pending: {
      groups: () => deliveryGroups('pending'),
      tbodyId: 'pending-confirm-table', paginationId: 'pending-pagination',
      emptyLabel: 'No pending deliveries found.',
      actor: actorOf, state: { page: 1, pageSize: 10 },
    },
    checked: {
      groups: () => deliveryGroups('checked'),
      tbodyId: 'checked-confirm-table', paginationId: 'checked-pagination',
      emptyLabel: 'No checked deliveries found.',
      actor: actorOf, state: { page: 1, pageSize: 10 },
    },
    trash: {
      groups: () => trashGroups(),
      tbodyId: 'trash-confirm-table', paginationId: 'trash-pagination',
      emptyLabel: 'Trash is empty.',
      actor: actorOf, state: { page: 1, pageSize: 10 }, mode: 'trash',
    },
  };

  function renderRows() {
    renderDeliveryTable(tabConfigs.all);
    renderDeliveryTable(tabConfigs.pending);
    renderDeliveryTable(tabConfigs.checked);
    // The Trash tab/panel only exists on the super-admin view.
    if (trashEnabled && $(tabConfigs.trash.tbodyId)) renderDeliveryTable(tabConfigs.trash);
  }

  async function loadRecords() {
    ['all-confirm-table', 'pending-confirm-table', 'checked-confirm-table'].forEach(id => {
      const el = $(id);
      if (el) el.innerHTML = '<tr><td colspan="7" class="empty">Loading&hellip;</td></tr>';
    });
    const data = await getJson(api('/api/checker/pending-incoming.php'));
    pendingItems = data.pending || [];
    confirmedItems = data.confirmed || [];
    rejectedItems = data.rejected || [];
    allItems = [...pendingItems, ...confirmedItems, ...rejectedItems];
    // Super-admin receives trashed items from the server; other roles get an empty array.
    trashedItems = data.trashed || [];
    setCounts();
    renderRows();
    // If the details modal is open, refresh its contents to reflect the latest state.
    if (openDetailsId != null) openDetails(openDetailsId);
  }

  // ── Delivery details modal (per-item confirm/reject for the active tab) ──
  let openDetailsId = null;

  function findGroup(ingoingId) {
    // Search both normal and trashed items so the Details button works in the Trash tab.
    const all = groupByTransaction([...allItems, ...trashedItems]);
    return all.find(g => String(g.ingoingId) === String(ingoingId));
  }

  function openDetails(ingoingId) {
    const group = findGroup(ingoingId);
    if (!group) { closeDetails(); return; }
    openDetailsId = ingoingId;

    const items = group.items || [];
    const totalQty = items.reduce((sum, i) => sum + (i.quantity || 0), 0);
    $('confirm-details-title').textContent = `Delivery    ${group.supplierName || 'Unknown supplier'}`;
    $('confirm-details-subtitle').textContent =
      `${fmtDate(group.createdDate)}    ${items.length} item(s)    ${totalQty} total qty    ${group.createdByName || 'Checker'}`;

    const body = $('confirm-details-body');
    // Mobile-only summary strip: shows the columns hidden from the table on small screens.
    const mobileMeta = `<div class="modal-meta-mobile">
      <div class="modal-meta-row"><span class="modal-meta-label">Date</span><span class="modal-meta-value">${fmtDate(group.createdDate)}</span></div>
      <div class="modal-meta-row"><span class="modal-meta-label">Checker</span><span class="modal-meta-value">${escapeHtml(group.createdByName || '—')}</span></div>
    </div>`;
    body.innerHTML = mobileMeta + (items.map(it => {
      const isPending = String(it.status).toLowerCase() === 'pending';
      const note = it.notes ? `<div class="muted review-item-note"><span class="note-label">Note:</span> ${escapeHtml(it.notes)}</div>` : '';
      const confNote = it.confirmationNotes ? `<div class="muted review-conf-note"><span class="note-label">Review note:</span> ${escapeHtml(it.confirmationNotes)}</div>` : '';
      const rightSide = isPending
        ? `<div class="confirm-actions">
             <button class="confirm-btn" data-action="approve" data-item-id="${it.ingoingItemId}">Confirm</button>
             <button class="reject-btn" data-action="reject" data-item-id="${it.ingoingItemId}">Reject</button>
           </div>`
        : `<div class="review-item-status">${badge(it.status)}${it.confirmedByName ? ` <span class="muted">by ${escapeHtml(it.confirmedByName)}</span>` : ''}</div>`;
      return `<div class="review-item">
        <div class="review-item-main">
          <div><strong>${escapeHtml(it.itemName)}</strong>${it.sku ? ` <span class="muted">(${escapeHtml(it.sku)})</span>` : ''}</div>
          <div class="muted">${it.quantity} ${escapeHtml(it.unit)}    Condition: ${escapeHtml(it.condition || 'Accepted / Good')}</div>
          ${note}
          ${confNote}
        </div>
        <div class="review-item-action">${rightSide}</div>
      </div>`;
    }).join('') || '<p class="empty">No items in this delivery.</p>');

    $('confirm-details-modal').classList.add('show');
  }

  function closeDetails() {
    openDetailsId = null;
    $('confirm-details-modal').classList.remove('show');
  }

  // ── Confirm/Reject action modal (replaces native confirm()/prompt()/alert()) ──
  function openActionModal(ingoingItemId, action) {
    const item = [...pendingItems].find(i => String(i.ingoingItemId) === String(ingoingItemId));
    if (!item) return;
    const isApprove = action === 'approve';

    $('confirm-action-item-id').value = ingoingItemId;
    $('confirm-action-type').value = action;
    $('confirm-action-title').textContent = isApprove ? 'Confirm Item' : 'Reject Item';
    $('confirm-action-subtitle').textContent = isApprove
      ? 'Approving adds the delivered quantity to warehouse stock (Accepted / Good only).'
      : 'Rejecting will not change any stock.';
    $('confirm-action-note-label').textContent = isApprove ? 'Note (optional)' : 'Reason for rejection (required)';
    const notesEl = $('confirm-action-notes');
    notesEl.value = '';
    notesEl.required = !isApprove;
    notesEl.placeholder = isApprove
      ? 'Leave blank to use a default confirmation note.'
      : 'Describe why this item is being rejected.';
    $('confirm-action-summary').innerHTML = `
      <div><strong>${escapeHtml(item.itemName)}</strong>${item.sku ? ` <span class="muted">(${escapeHtml(item.sku)})</span>` : ''}</div>
      <div class="muted">${item.quantity} ${escapeHtml(item.unit)}    Condition: ${escapeHtml(item.condition || 'Accepted / Good')}</div>
      <div class="muted">Supplier: ${escapeHtml(item.supplierName || '—')}</div>`;

    const submitBtn = $('confirm-action-submit');
    submitBtn.textContent = isApprove ? 'Confirm' : 'Reject';
    submitBtn.classList.toggle('btn-danger', !isApprove);
    submitBtn.classList.toggle('btn-primary', isApprove);

    $('confirm-action-modal').classList.add('show');
  }

  function closeActionModal() {
    $('confirm-action-modal').classList.remove('show');
  }

  async function submitActionModal(e) {
    e.preventDefault();
    const ingoingItemId = $('confirm-action-item-id').value;
    const action = $('confirm-action-type').value;
    const notes = $('confirm-action-notes').value.trim();
    if (!ingoingItemId || !action) return;

    // A confirmation note is mandatory when rejecting an item.
    if (action === 'reject' && notes === '') {
      showToast('A confirmation note is required when rejecting an item.', 'warning');
      $('confirm-action-notes').focus();
      return;
    }

    const submitBtn = $('confirm-action-submit');
    submitBtn.disabled = true;
    try {
      const data = await getJson(api('/api/checker/confirm-incoming.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ ingoingItemId: Number(ingoingItemId), action, notes }),
      });
      closeActionModal();
      await loadRecords();
      showToast(data.message || 'Confirmation saved.', 'success');
    } catch (err) {
      showToast(err.message, 'error');
    } finally {
      submitBtn.disabled = false;
    }
  }

  // ── Server-backed Trash actions (super-admin) ────────────────────────────────
  // Each action calls the delete-incoming API and reloads from the server so all
  // roles immediately see the correct state.

  async function trashDelivery(ingoingId) {
    if (!trashEnabled) return;
    const group = findGroup(ingoingId) || groupByTransaction(trashedItems).find(g => String(g.ingoingId) === String(ingoingId));
    const confirmed = await confirmModal({
      title: 'Move to Trash',
      message: 'Move this delivery to Trash? You can restore it later from the Trash tab.',
      confirmText: 'Move to Trash',
      cancelText: 'Cancel',
      danger: true,
    });
    if (!confirmed) return;
    try {
      await getJson(api('/api/checker/delete-incoming.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'trash', ingoingId: Number(ingoingId) }),
      });
      if (openDetailsId != null && String(openDetailsId) === String(ingoingId)) closeDetails();
      await loadRecords();
      showToast(`Delivery from ${group?.supplierName || 'supplier'} moved to Trash.`, 'success');
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async function restoreDelivery(ingoingId) {
    if (!trashEnabled) return;
    try {
      await getJson(api('/api/checker/delete-incoming.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'restore', ingoingId: Number(ingoingId) }),
      });
      await loadRecords();
      showToast('Delivery restored.', 'success');
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async function purgeDelivery(ingoingId) {
    if (!trashEnabled) return;
    const ok = await confirmModal({
      title: 'Delete Permanently',
      message: 'Permanently delete this delivery and all its line-items from the database? This cannot be undone.',
      confirmText: 'Delete Permanently',
      cancelText: 'Cancel',
    });
    if (!ok) return;
    try {
      await getJson(api('/api/checker/delete-incoming.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'purge', ingoingId: Number(ingoingId) }),
      });
      if (openDetailsId != null && String(openDetailsId) === String(ingoingId)) closeDetails();
      await loadRecords();
      showToast('Delivery permanently deleted.', 'success');
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // Delegated clicks: open details, or open the confirm/reject action modal for a pending item.
  document.addEventListener('click', (e) => {
    const detailsBtn = e.target.closest('[data-txn-details]');
    if (detailsBtn) { openDetails(detailsBtn.dataset.txnDetails); return; }
    const deleteBtn = e.target.closest('[data-txn-delete]');
    if (deleteBtn) { trashDelivery(deleteBtn.dataset.txnDelete); return; }
    const restoreBtn = e.target.closest('[data-txn-restore]');
    if (restoreBtn) { restoreDelivery(restoreBtn.dataset.txnRestore); return; }
    const purgeBtn = e.target.closest('[data-txn-purge]');
    if (purgeBtn) { purgeDelivery(purgeBtn.dataset.txnPurge); return; }
    const actionBtn = e.target.closest('[data-action][data-item-id]');
    if (actionBtn) { openActionModal(actionBtn.dataset.itemId, actionBtn.dataset.action); return; }
  });

  $('confirm-details-close')?.addEventListener('click', closeDetails);
  $('confirm-details-done')?.addEventListener('click', closeDetails);
  $('confirm-details-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'confirm-details-modal') closeDetails();
  });

  $('confirm-action-form')?.addEventListener('submit', submitActionModal);
  $('confirm-action-close')?.addEventListener('click', closeActionModal);
  $('confirm-action-cancel')?.addEventListener('click', closeActionModal);
  $('confirm-action-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'confirm-action-modal') closeActionModal();
  });

  $('refresh-btn')?.addEventListener('click', () => loadRecords().catch(err => showToast(err.message, 'error')));

  initTabs();
  loadRecords().catch(err => showToast(err.message, 'error'));
})();
