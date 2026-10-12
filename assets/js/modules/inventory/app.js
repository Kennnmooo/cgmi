/* ============================================================
   APP.JS — Main orchestrator + Details Panel + Toast + Confirm
   ============================================================ */

document.addEventListener('DOMContentLoaded', async () => {

  /* ── 1. Load reference data (categories, units) from DB ── */
  try {
    await loadReferenceData();
  } catch (e) {
    console.warn('[App] Reference data load issue.', e);
  }

  /* ── 2. Init DataTable ──────────────────────────────────── */
  const dt = initDataTable();

  /* ── 3. Load inventory data ─────────────────────────────── */
  try {
    showLoading();
    await InventoryManager.init();
    reloadTableData(InventoryManager.getAll());
  } catch (error) {
    console.error('[App] Inventory load failed.', error);
    showToast('Load Failed', error.message || 'Unable to load inventory items.', 'error', 5000);
  } finally {
    hideLoading();
  }

  /* ── 4. Init FilterManager ──────────────────────────────── */
  FilterManager.init(dt, {
    searchInput:    document.getElementById('search-input'),
    searchModeBtns: document.querySelectorAll('.search-mode-btn'),
    statusTabs:     document.querySelectorAll('.status-tab'),
    catSelect:      document.getElementById('filter-category'),
    subSelect:      document.getElementById('filter-subcategory'),
    shelfSelect:    document.getElementById('filter-shelf-tracking'),
  });

  /* ── 5. Init Modal (add/edit) ───────────────────────────── */
  ModalManager.init();

  /* ── 6. Init Details Panel ──────────────────────────────── */
  DetailsPanelManager.init();

  /* ── 7. Populate filter dropdowns ──────────────────────── */
  const filterCatSelect = document.getElementById('filter-category');
  const filterSubSelect = document.getElementById('filter-subcategory');
  populateFilterCategoryDropdown(filterCatSelect);
  populateFilterSubcategoryDropdown(filterSubSelect, '');
  bindFilterCategorySubcategoryLink(filterCatSelect, filterSubSelect, () => FilterManager.applyFilters());

  /* ── 8. Sort dropdown ───────────────────────────────────── */
  initSortDropdown(
    document.getElementById('sort-btn'),
    document.getElementById('sort-dropdown'),
    document.querySelectorAll('.sort-option'),
  );

  /* ── 9. "Add Item" buttons ──────────────────────────────── */
  ['add-item-btn', 'add-item-btn-2'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => ModalManager.openAdd());
  });

  /* ── 10. Delegated table click handler ──────────────────── */
  document.getElementById('inventory-table').addEventListener('click', async (e) => {
    /* Update button */
    const updateBtn = e.target.closest('.btn-update');
    if (updateBtn) {
      const item = InventoryManager.getItemById(updateBtn.dataset.id);
      item ? ModalManager.openEdit(item) : showToast('Error', 'Item not found.', 'error');
      return;
    }
    /* Deactivate button */
    const deactivateBtn = e.target.closest('.btn-deactivate');
    if (deactivateBtn) {
      if (!canManageInventoryStatus()) {
        showToast('Not Allowed', 'Only a super admin can deactivate items.', 'error');
        return;
      }
      const item = InventoryManager.getItemById(deactivateBtn.dataset.id);
      if (!item) { showToast('Error', 'Item not found.', 'error'); return; }
      const confirmed = await showConfirm({
        title: 'Deactivate Item',
        message: `Are you sure you want to deactivate "${item.name}"? It will be moved to the Inactive list.`,
        confirmText: 'Deactivate',
        iconClass: 'icon-incident',
      });
      if (!confirmed) return;
      try {
        showLoading();
        await InventoryManager.toggleStatus(item.dbId || item.id, 'Inactive');
        showToast('Item Deactivated', `"${item.name}" has been deactivated.`, 'success');
      } catch (error) {
        showToast('Error', error.message || 'Unable to deactivate item.', 'error', 5000);
      } finally {
        hideLoading();
      }
      return;
    }
    /* Reactivate button */
    const reactivateBtn = e.target.closest('.btn-reactivate');
    if (reactivateBtn) {
      if (!canManageInventoryStatus()) {
        showToast('Not Allowed', 'Only a super admin can reactivate items.', 'error');
        return;
      }
      const item = InventoryManager.getItemById(reactivateBtn.dataset.id);
      if (!item) { showToast('Error', 'Item not found.', 'error'); return; }
      const confirmed = await showConfirm({
        title: 'Reactivate Item',
        message: `Are you sure you want to reactivate "${item.name}"? It will be moved to the Active list.`,
        confirmText: 'Reactivate',
        iconClass: 'icon-check',
      });
      if (!confirmed) return;
      try {
        showLoading();
        await InventoryManager.toggleStatus(item.dbId || item.id, 'Active');
        showToast('Item Reactivated', `"${item.name}" has been reactivated.`, 'success');
      } catch (error) {
        showToast('Error', error.message || 'Unable to reactivate item.', 'error', 5000);
      } finally {
        hideLoading();
      }
      return;
    }
    /* Details button (compact mode) */
    const detailsBtn = e.target.closest('.btn-details');
    if (detailsBtn) {
      const item = InventoryManager.getItemById(detailsBtn.dataset.id);
      item ? DetailsPanelManager.open(item) : showToast('Error', 'Item not found.', 'error');
      return;
    }
  });

  /* ── 11. Keyboard shortcuts ─────────────────────────────── */
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); document.getElementById('search-input')?.focus(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') { e.preventDefault(); ModalManager.openAdd(); }
  });

  /* ── 12. Initial stats ──────────────────────────────────── */
  updateStats();

  console.log('[App] Inventory panel v3 ready.');
});


/* ============================================================
   DETAILS PANEL MANAGER
   Shows full item info in a slide-up panel (mobile / compact)
   with an "Update" button that opens the edit modal.
   ============================================================ */
const DetailsPanelManager = (() => {
  let backdrop, panel, closeBtn, updateBtn;

  function init() {
    backdrop  = document.getElementById('details-backdrop');
    panel     = document.getElementById('details-panel');
    closeBtn  = document.getElementById('details-close-btn');
    updateBtn = document.getElementById('details-update-btn');

    closeBtn?.addEventListener('click', close);
    backdrop?.addEventListener('click', (e) => { if (e.target === backdrop) close(); });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && backdrop?.classList.contains('active')) close();
    });
  }

  function open(item) {
    if (!backdrop || !panel) return;

    /* Populate image */
    const imgEl = document.getElementById('dp-image');
    if (item.image) {
      imgEl.innerHTML = `<img src="${item.image}" alt="${_esc(item.name)}">`;
    } else {
      imgEl.innerHTML = `<span class="dp-emoji ui-icon icon-box" aria-hidden="true"></span>`;
    }

    /* Populate text fields */
    _set('dp-name',        item.name);
    _set('dp-id',          item.id);
    _set('dp-category',    item.category);
    _set('dp-subcategory', item.subcategory);
    _set('dp-shelf-category', item.storageTypeLabel || item.shelfCategory || 'Warehouse');
    _set('dp-unit-weight', item.unitWeightKg ? `${formatNumber(item.unitWeightKg)} kg / ${_esc(item.unit || 'unit')}` : '—');
    _set('dp-warehouse-total', `${item.warehouseQty ?? 0} <span class="dp-unit">${_esc(item.unit)}</span>`);
    _set('dp-store-total', `${item.storeQty ?? 0} <span class="dp-unit">${_esc(item.unit)}</span>`);
    _set('dp-iot-total', `${item.iotShelfQty ?? 0} <span class="dp-unit">${_esc(item.unit)}</span>`);
    _set('dp-qty',         `${item.overallQty ?? item.qty} <span class="dp-unit">${_esc(item.unit)}</span>`);
    _set('dp-weight-check', renderDetailsWeightCheck(item));
    _set('dp-price',       `₱${formatPrice(item.unitPrice)}`);

    const statusEl = document.getElementById('dp-status');
    if (statusEl) {
      statusEl.className = `badge ${item.status === 'Active' ? 'badge-active' : 'badge-inactive'}`;
      statusEl.innerHTML = `${item.status}`;
    }

    /* Wire update button */
    if (updateBtn) {
      updateBtn.onclick = () => {
        close();
        setTimeout(() => ModalManager.openEdit(item), 220);
      };
    }

    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    backdrop?.classList.remove('active');
    document.body.style.overflow = '';
  }

  function _set(id, html) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = html;
  }

  function _esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function renderDetailsWeightCheck(item) {
    const status = item.weightMatchStatus || 'not_applicable';
    const label = item.weightMatchLabel || (status === 'match' ? 'Match' : status === 'discrepancy' ? 'Discrepancy' : 'Warehouse only');
    const diff = Number.isFinite(Number(item.shelfWeightDifferenceKg)) ? Number(item.shelfWeightDifferenceKg) : null;
    const diffQty = Number.isFinite(Number(item.shelfQtyDifference)) ? Number(item.shelfQtyDifference) : null;
    const inventoryQty = Number.isFinite(Number(item.iotShelfQty)) ? Number(item.iotShelfQty) : null;
    const shelfQty = Number.isFinite(Number(item.amountOnShelvesQty ?? item.actualShelfQty)) ? Number(item.amountOnShelvesQty ?? item.actualShelfQty) : null;
    const expectedWeight = Number.isFinite(Number(item.expectedShelfWeightKg ?? item.iotShelfWeightKg)) ? Number(item.expectedShelfWeightKg ?? item.iotShelfWeightKg) : null;
    const actualWeight = Number.isFinite(Number(item.actualShelfWeightKg)) ? Number(item.actualShelfWeightKg) : null;

    if (status === 'missing_weight') return `${_esc(label)} <span class="dp-unit">Unit weight needed</span>`;
    if (status === 'not_applicable') return `${_esc(label)} <span class="dp-unit">Not for IoT shelf</span>`;

    const diffText = status === 'match'
      ? '0 kg difference'
      : `${formatNumber(diffQty || 0)} ${_esc(item.unit || 'unit')} / ${formatNumber(diff || 0)} kg difference`;
    const detail = [`${_esc(diffText)}`];
    if (inventoryQty !== null) detail.push(`Inventory IoT: ${_esc(formatNumber(inventoryQty))} ${_esc(item.unit || '')}`);
    if (shelfQty !== null) detail.push(`Shelves: ${_esc(formatNumber(shelfQty))} ${_esc(item.unit || '')}`);
    if (expectedWeight !== null && actualWeight !== null) detail.push(`${_esc(formatNumber(expectedWeight))} kg vs ${_esc(formatNumber(actualWeight))} kg`);
    return `${_esc(label)} <span class="dp-unit">${detail.join('    ')}</span>`;
  }

  return { init, open, close };
})();


/* ============================================================
   TOAST NOTIFICATION SYSTEM
   ============================================================ */
function showToast(title, message, type = 'info', duration = 3500) {
  const container = _toastStackFor(type);
  if (!container) return;

  const icons  = { success: 'icon-check', error: 'icon-incident', warning: 'icon-incident', info: 'icon-info' };
  const colors = {
    success: 'var(--accent-success)',
    error:   'var(--accent-danger)',
    warning: 'var(--accent-warning)',
    info:    'var(--blue-medium)',
  };

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.style.setProperty('--toast-color', colors[type] || colors.info);
  toast.innerHTML = `
    <div class="toast-icon ui-icon ${icons[type] || 'icon-info'}" aria-hidden="true"></div>
    <div class="toast-body">
      <div class="toast-title">${title}</div>
      ${message ? `<div class="toast-msg">${message}</div>` : ''}
    </div>`;

  container.appendChild(toast);
  const timer = setTimeout(() => _dismissToast(toast), duration);
  toast.addEventListener('click', () => { clearTimeout(timer); _dismissToast(toast); });
}

/* Route a toast to the correct on-screen stack by type:
   error / warning / rejected / danger -> BOTTOM-LEFT, everything else -> BOTTOM-RIGHT.
   Stacks are created lazily and shared across all toasts on the page. */
function _toastStackFor(type) {
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

function _dismissToast(toast) {
  toast.classList.add('hide');
  toast.addEventListener('animationend', () => toast.remove(), { once: true });
}


/* ============================================================
   CONFIRMATION DIALOG
   ============================================================ */
function showConfirm(options = {}) {
  return new Promise((resolve) => {
    const overlay   = document.getElementById('confirm-overlay');
    const iconEl    = document.getElementById('confirm-icon');
    const titleEl   = document.getElementById('confirm-title');
    const msgEl     = document.getElementById('confirm-msg');
    const okBtn     = document.getElementById('confirm-ok-btn');
    const cancelBtn = document.getElementById('confirm-cancel-btn');

    iconEl.className = 'confirm-icon ui-icon ' + (options.iconClass || (options.icon === '!' ? 'icon-incident' : 'icon-question'));
    iconEl.textContent = '';
    iconEl.setAttribute('aria-hidden','true');
    titleEl.textContent   = options.title || 'Are you sure?';
    msgEl.textContent     = options.message || '';
    okBtn.textContent     = options.confirmText || 'Confirm';
    cancelBtn.textContent = options.cancelText || 'Cancel';

    overlay.classList.add('active');

    const done = (val) => { overlay.classList.remove('active'); cleanup(); resolve(val); };
    const handleKey = (e) => { if (e.key === 'Enter') done(true); if (e.key === 'Escape') done(false); };
    function cleanup() {
      okBtn.removeEventListener('click', okHandler);
      cancelBtn.removeEventListener('click', cancelHandler);
      document.removeEventListener('keydown', handleKey);
    }
    const okHandler     = () => done(true);
    const cancelHandler = () => done(false);
    okBtn.addEventListener('click', okHandler);
    cancelBtn.addEventListener('click', cancelHandler);
    document.addEventListener('keydown', handleKey);
  });
}


/* ============================================================
   LOADING OVERLAY
   ============================================================ */
function showLoading() { document.getElementById('loading-overlay')?.classList.add('active'); }
function hideLoading() { document.getElementById('loading-overlay')?.classList.remove('active'); }

function formatNumber(val) {
  const n = Number(val);
  if (!Number.isFinite(n)) return '0';
  return n.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
