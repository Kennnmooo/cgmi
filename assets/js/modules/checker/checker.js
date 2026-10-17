(() => {
  const $ = (id) => document.getElementById(id);
  const appBase = () => {
    const path = window.location.pathname;
    const marker = '/pages/';
    const idx = path.indexOf(marker);
    return idx >= 0 ? path.slice(0, idx) : '';
  };
  const api = (path) => `${appBase()}${path}`;
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const fmtDate = (value) => value ? new Date(String(value).replace(' ', 'T')).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
  const statusText = (status) => String(status || 'pending').replaceAll('_', ' ');
  const purposeText = (purpose) => String(purpose || 'Manual Entry').replaceAll('_', ' ');
  const badge = (status) => `<span class="badge ${escapeHtml(String(status || 'pending').toLowerCase())}">${escapeHtml(statusText(status))}</span>`;
  const sourceBadge = (source) => `<span class="source-badge ${escapeHtml(source)}">${source === 'pos' ? 'POS' : 'Manual'}</span>`;
  const qtyText = (item) => `${Number(item.quantity ?? item.qty ?? 0)} ${item.unit || ''}`.trim();

  // Floating toast card — replaces native window dialogs (alert) for outgoing warehouse feedback.
  const TOAST_META = {
    success: { color: 'var(--accent-success, #16a34a)', icon: '\u2713', title: 'Success' },
    error:   { color: 'var(--accent-danger, #dc2626)',  icon: '\u26A0', title: 'Error' },
    warning: { color: 'var(--accent-warning, #d97706)', icon: '\u26A0', title: 'Warning' },
    info:    { color: 'var(--blue-medium, #4569AD)',     icon: '\u2139', title: 'Notice' },
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

  // Allowed quantity range for checker rows (incoming deliveries and manual outgoing).
  const QTY_MIN = 1;      // quantities must be greater than zero
  const QTY_MAX = 100000; // reject anything greater than 100,000

  let outgoingCache = [];
  let inventoryChoices = [];
  let supplierChoices = [];

  // Idempotency token for outgoing saves — guards against duplicate submissions
  // (mirrors the checker_idempotency table used server-side). A fresh token is
  // generated each time the outgoing modal opens and after every successful save.
  const makeToken = () => (window.crypto && crypto.randomUUID)
    ? crypto.randomUUID()
    : `tok-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  let outgoingToken = makeToken();

  async function getJson(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', ...options });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch { throw new Error('Unexpected server response.'); }
    if (!res.ok || data.success === false) throw new Error(data.message || 'Request failed.');
    return data;
  }

  // Runs an async submit while locking a button so rapid double-clicks can't fire twice.
  async function withSubmitLock(button, fn) {
    if (!button) return fn();
    if (button.disabled) return; // already submitting
    const originalText = button.textContent;
    button.disabled = true;
    button.dataset.loading = '1';
    const original = originalText;
    button.textContent = 'Saving…';
    try {
      return await fn();
    } finally {
      button.disabled = false;
      delete button.dataset.loading;
      button.textContent = original;
    }
  }

  async function loadInventoryChoices() {
    const data = await getJson(api('/api/checker/items.php'));
    inventoryChoices = Array.isArray(data.items) ? data.items : [];
    ensureItemDatalist(); // (re)build the shared item name list for searchable inputs
  }

  async function loadSupplierChoices() {
    const data = await getJson(api('/api/checker/suppliers.php'));
    supplierChoices = Array.isArray(data.suppliers) ? data.suppliers : [];
    const select = $('incoming-supplier');
    select.innerHTML = '<option value="">Select supplier</option>' +
      supplierChoices.map(s => `<option value="${escapeHtml(s.supplierId)}">${escapeHtml(s.supplierName)}</option>`).join('');
  }

  function itemById(id) {
    return inventoryChoices.find(item => String(item.dbId || item.id) === String(id));
  }

  const itemLabel = (item) => `${item.name || item.itemName || ''}`;

  // Resolve a typed label back to an inventory item (case-insensitive, exact match on name).
  function itemByLabel(label) {
    const needle = String(label || '').trim().toLowerCase();
    if (!needle) return null;
    return inventoryChoices.find(item => itemLabel(item).trim().toLowerCase() === needle) || null;
  }

  function itemOptionsHtml(selectedId = '') {
    const options = inventoryChoices.map(item => {
      const id = item.dbId || item.id;
      const selected = String(id) === String(selectedId) ? 'selected' : '';
      const label = itemLabel(item);
      return `<option value="${escapeHtml(id)}" ${selected}>${escapeHtml(label)}</option>`;
    }).join('');
    return `<option value="">Select inventory item</option>${options}`;
  }

  // Shared <datalist> of item names so the row's text input can be typed or picked from a dropdown.
  const ITEM_DATALIST_ID = 'checker-item-options';
  function ensureItemDatalist() {
    let dl = document.getElementById(ITEM_DATALIST_ID);
    if (!dl) {
      dl = document.createElement('datalist');
      dl.id = ITEM_DATALIST_ID;
      document.body.appendChild(dl);
    }
    dl.innerHTML = inventoryChoices.map(item => `<option value="${escapeHtml(itemLabel(item))}"></option>`).join('');
    return dl;
  }

  // Builds the searchable item field: a text input (type or pick) backed by a hidden id field.
  function itemSearchFieldHtml(selectedId = '') {
    const selected = itemById(selectedId);
    const displayValue = selected ? escapeHtml(itemLabel(selected)) : '';
    return `<input class="row-item-search" type="text" list="${ITEM_DATALIST_ID}" placeholder="Type or select an item" autocomplete="off" required value="${displayValue}">
            <input class="row-item-id" type="hidden" value="${escapeHtml(selected ? (selected.dbId || selected.id) : '')}">`;
  }

  // Sync the hidden id field from whatever the user typed/picked in the search input.
  function syncRowItemId(row) {
    const searchInput = row.querySelector('.row-item-search');
    const idInput = row.querySelector('.row-item-id');
    if (!searchInput || !idInput) return null;
    const match = itemByLabel(searchInput.value);
    idInput.value = match ? (match.dbId || match.id) : '';
    // Flag an unmatched, non-empty entry so the user gets native validation feedback.
    if (searchInput.value.trim() && !match) {
      searchInput.setCustomValidity('Please choose an item from the list.');
    } else {
      searchInput.setCustomValidity('');
    }
    return match;
  }

  function updateRowUnit(row) {
    const select = row.querySelector('.row-item-id');
    const selected = itemById(select?.value || '');
    const unitInput = row.querySelector('.row-unit');
    const stockEl = row.querySelector('.row-stock-hint');
    const qtyInput = row.querySelector('.row-qty');
    if (unitInput) unitInput.value = selected ? (selected.unit || '') : '';

    // Incoming rows have no source-location select: they ADD stock, so the quantity
    // must not be capped to the item's current stock. Only outgoing rows cap qty to
    // the available stock at the chosen source location.
    const isOutgoing = row.classList.contains('outgoing-item-row');

    if (stockEl) {
      if (!selected) {
        stockEl.textContent = 'Choose an item to auto-fill its unit.';
      } else if (!isOutgoing) {
        // Incoming: show current stock for reference; quantity is bounded only by the
        // fixed allowed range (not by current stock, since deliveries ADD stock).
        const currentStock = (selected.warehouseQty ?? 0) + (selected.storeQty ?? 0);
        stockEl.textContent = `Current stock: ${currentStock} ${selected.unit || ''}`;
        if (qtyInput) {
          qtyInput.max = QTY_MAX;
          if (Number(qtyInput.value) > QTY_MAX) qtyInput.value = QTY_MAX;
        }
      } else {
        const sourceSelect = row.querySelector('.row-source-location');
        const source = sourceSelect ? sourceSelect.value : '';
        let availableQty = 0;
        let stockLabel = '';
        if (source === 'Warehouse') {
          availableQty = selected.warehouseQty ?? 0;
          stockLabel = `Warehouse stock: ${availableQty} ${selected.unit || ''}`;
        } else if (source === 'Store') {
          availableQty = selected.storeQty ?? 0;
          stockLabel = `Store stock: ${availableQty} ${selected.unit || ''}`;
        } else {
          availableQty = (selected.warehouseQty ?? 0) + (selected.storeQty ?? 0);
          stockLabel = `Total stock: ${availableQty} ${selected.unit || ''}`;
        }

        // Show a warning on the stock hint and flag the qty input when it already
        // exceeds available stock so the user gets immediate feedback while typing.
        const requestedQty = qtyInput ? parseInt(qtyInput.value, 10) : 0;
        const isOverStock = Number.isFinite(requestedQty) && requestedQty > 0 && requestedQty > availableQty;
        if (isOverStock) {
          stockLabel += ` — ⚠ Only ${availableQty} available`;
          stockEl.style.color = 'var(--accent-danger, #dc2626)';
        } else {
          stockEl.style.color = '';
        }
        stockEl.textContent = stockLabel;

        if (qtyInput) {
          qtyInput.max = QTY_MAX;
          if (Number(qtyInput.value) > QTY_MAX) qtyInput.value = QTY_MAX;
          // Reflect the over-stock state in the input's validity so the browser
          // highlights the field. The message is cleared on the input event when
          // the user edits the value (handled below in createOutgoingManualRow).
          if (isOverStock) {
            qtyInput.setCustomValidity(`Only ${availableQty} available in ${source}.`);
          } else {
            // Only clear if the current custom message is an over-stock one (don't
            // wipe unrelated messages set elsewhere, e.g. the range validators).
            if (qtyInput.validity.customError && qtyInput.validationMessage.startsWith('Only ')) {
              qtyInput.setCustomValidity('');
            }
          }
        }
      }
    }
  }

  let recheckingCache = [];

  function setCounts(incomingRecords = [], outgoingItems = []) {
    $('incoming-count').textContent = incomingRecords.length;
    // Count incoming deliveries still awaiting confirmation (header status Pending).
    $('incoming-pending-count').textContent = incomingRecords.filter(r => String(r.status).toLowerCase() === 'pending').length;
    $('outgoing-count').textContent = outgoingItems.length;
    // Count every outgoing entry whose status is Pending (transactions + POS rows).
    $('pending-count').textContent = outgoingItems.filter(i => String(i.status).toLowerCase() === 'pending').length;
    $('rechecking-count').textContent = recheckingCache.length;
  }

  function createIncomingRow(values = {}) {
    const row = document.createElement('div');
    row.className = 'item-row incoming-item-row';
    row.innerHTML = `
      <div class="form-group item-select-group"><label>Inventory Item</label>${itemSearchFieldHtml(values.inventoryItemId || values.itemId || '')}<small class="row-stock-hint">Type or select an item to auto-fill its unit.</small></div>
      <div class="form-group"><label>Qty</label><input class="row-qty" type="number" min="${QTY_MIN}" max="${QTY_MAX}" step="1" required value="${escapeHtml(values.quantity || '')}"></div>
      <div class="form-group"><label>Unit</label><input class="row-unit" type="text" placeholder="Auto" readonly required value="${escapeHtml(values.unit || '')}"></div>
      <div class="form-group"><label>Condition</label><select class="row-condition"><option value="Accepted / Good">Accepted / Good</option><option value="Damaged">Damaged</option><option value="Incomplete / Missing">Incomplete / Missing</option></select></div>
      <div class="form-group row-note"><label>Note / Description</label><textarea class="row-notes" rows="2" placeholder="Example: 2 sacks damaged; for replacement.">${escapeHtml(values.notes || '')}</textarea></div>
      <button type="button" class="remove-row" title="Remove item">×</button>`;
    row.querySelector('.row-condition').value = values.condition || 'Accepted / Good';
    const incQty = row.querySelector('.row-qty');
    if (incQty) incQty.addEventListener('input', () => {
      const msg = qtyValidityMessage(incQty.value, false);
      incQty.setCustomValidity(msg);
      if (msg) incQty.reportValidity();
    });
    const incSearch = row.querySelector('.row-item-search');
    incSearch.addEventListener('input', () => { syncRowItemId(row); updateRowUnit(row); });
    incSearch.addEventListener('change', () => { syncRowItemId(row); updateRowUnit(row); });
    row.querySelector('.remove-row').addEventListener('click', () => {
      if (document.querySelectorAll('#incoming-items-list .item-row').length > 1) row.remove();
      else showToast('At least one delivered item is required.', 'warning');
    });
    $('incoming-items-list').appendChild(row);
    updateRowUnit(row);
  }

  // Purpose is chosen ONCE (header-level). Constrain each row's source location based on it.
  function applyPurposeConstraints() {
    const purpose = $('outgoing-purpose')?.value || 'Customer Purchase';
    document.querySelectorAll('#outgoing-items-list .item-row').forEach(row => {
      const sourceSelect = row.querySelector('.row-source-location');
      if (!sourceSelect) return;
      if (purpose === 'Store Replenishment') {
        // Replenishing the store must draw from the warehouse.
        sourceSelect.value = 'Warehouse';
        sourceSelect.disabled = true;
      } else {
        sourceSelect.disabled = false;
      }
      updateRowUnit(row);
    });
  }

  // Prevent picking the same inventory item in more than one outgoing row.
  // Items chosen in other rows are disabled in each row's dropdown.
  function refreshOutgoingItemOptions() {
    // With searchable text inputs, duplicate selection is enforced at submit time
    // (see the duplicate-item guard in the outgoing form handler), so there are no
    // <option> elements to enable/disable here.
  }

  function createOutgoingManualRow(values = {}) {
    const row = document.createElement('div');
    row.className = 'item-row outgoing-item-row';
    row.innerHTML = `
      <div class="form-group item-select-group"><label>Inventory Item</label>${itemSearchFieldHtml(values.inventoryItemId || values.itemId || '')}<small class="row-stock-hint">Type or select an item to auto-fill its unit.</small></div>
      <div class="form-group"><label>Qty</label><input class="row-qty" type="number" min="${QTY_MIN}" max="${QTY_MAX}" step="1" required value="${escapeHtml(values.quantity || '')}"></div>
      <div class="form-group"><label>Unit</label><input class="row-unit" type="text" placeholder="Auto" readonly required value="${escapeHtml(values.unit || '')}"></div>
      <div class="row-second-line">
        <div class="form-group"><label>Source Location</label><select class="row-source-location"><option value="Warehouse">Warehouse</option><option value="Store">Store</option></select></div>
      </div>
      <div class="form-group row-note"><label>Note / Description</label><textarea class="row-notes" rows="2" placeholder="Example: Transfer to store display area.">${escapeHtml(values.notes || '')}</textarea></div>
      <button type="button" class="remove-row" title="Remove item">×</button>`;
    if (values.sourceLocation) row.querySelector('.row-source-location').value = values.sourceLocation;
    const outQty = row.querySelector('.row-qty');
    if (outQty) outQty.addEventListener('input', () => {
      const msg = qtyValidityMessage(outQty.value, false);
      outQty.setCustomValidity(msg);
      if (msg) outQty.reportValidity();
      updateRowUnit(row);
    });
    const outSearch = row.querySelector('.row-item-search');
    outSearch.addEventListener('input', () => { syncRowItemId(row); updateRowUnit(row); refreshOutgoingItemOptions(); });
    outSearch.addEventListener('change', () => { syncRowItemId(row); updateRowUnit(row); refreshOutgoingItemOptions(); });
    row.querySelector('.row-source-location').addEventListener('change', () => updateRowUnit(row));
    row.querySelector('.remove-row').addEventListener('click', () => {
      if (document.querySelectorAll('#outgoing-items-list .item-row').length > 1) {
        row.remove();
        refreshOutgoingItemOptions();
      } else {
        showToast('At least one outgoing item is required.', 'warning');
      }
    });
    $('outgoing-items-list').appendChild(row);
    updateRowUnit(row);
    // Apply the current header purpose constraint to this newly added row.
    applyPurposeConstraints();
    // An item already chosen in another row cannot be chosen again here.
    refreshOutgoingItemOptions();
  }

  function collectRows(containerId, type) {
    return Array.from($(containerId).querySelectorAll('.item-row')).map(row => {
      const itemId = row.querySelector('.row-item-id')?.value || '';
      const selected = itemById(itemId);
      const base = {
        inventoryItemId: itemId,
        itemId,
        itemName: selected ? (selected.name || selected.itemName || '') : '',
        quantity: row.querySelector('.row-qty')?.value || '',
        unit: selected ? (selected.unit || '') : (row.querySelector('.row-unit')?.value.trim() || ''),
        notes: row.querySelector('.row-notes')?.value.trim() || ''
      };
      if (type === 'incoming') {
        base.condition = row.querySelector('.row-condition')?.value || 'Accepted / Good';
      } else {
        base.status = row.querySelector('.row-status')?.value || 'released';
      }
      if (type === 'outgoing') base.sourceLocation = row.querySelector('.row-source-location')?.value || 'Warehouse';
      return base;
    }).filter(i => i.inventoryItemId || i.quantity || i.notes);
  }

  // Compute the range/emptiness message for a single qty value, or '' when it's valid.
  // `allowFractional` is true for POS releases (which permit decimals).
  function qtyValidityMessage(rawValue, allowFractional = false) {
    const raw = String(rawValue ?? '').trim();
    if (raw === '') return 'Please fill out this field.';
    const qty = Number(raw);
    if (!Number.isFinite(qty)) return 'Please enter a valid number.';
    if (!allowFractional && !Number.isInteger(qty)) return 'Please enter a whole number.';
    if (qty < QTY_MIN) return 'Value must be greater than 0.';
    if (qty > QTY_MAX) return `Value must be less than ${QTY_MAX.toLocaleString()}.`;
    return '';
  }

  // Validate every qty <input> inside a container using the native Constraint Validation
  // API. On the first offending field it sets a custom message and pops the browser's
  // validation bubble (same UX as leaving a required field empty), then returns false so
  // the caller aborts the submit. Returns true when all quantities are valid.
  function validateQuantityInputs(containerId) {
    const inputs = Array.from($(containerId).querySelectorAll('.row-qty'));
    for (const input of inputs) {
      const message = qtyValidityMessage(input.value, false);
      input.setCustomValidity(message);
      if (message) {
        input.reportValidity();
        return false;
      }
    }
    return true;
  }

  // Same native-bubble validation for a single qty input (e.g. the POS release field).
  function validateSingleQtyInput(input, allowFractional = false) {
    if (!input) return true;
    const message = qtyValidityMessage(input.value, allowFractional);
    input.setCustomValidity(message);
    if (message) {
      input.reportValidity();
      return false;
    }
    return true;
  }

  // Feature flag: trash actions only available when the view opts-in via data attribute.
  const checkerTrashEnabled = !!document.body.getAttribute('data-checker-trash-enabled');

  let incomingFilter = 'all';
  // Pagination state for the incoming deliveries table (mirrors the outgoing table).
  let incomingPage = 1;
  let incomingPageSize = 10;
  // Trash pagination (separate state so switching tabs doesn't reset the other).
  let incomingTrashPage = 1;
  let incomingTrashPageSize = 10;

  function buildIncomingRow(r, isTrash = false) {
    const items = r.items || [];
    const MAX_PREVIEW = 2;
    const shownItems = items.slice(0, MAX_PREVIEW);
    const moreCount = items.length - shownItems.length;
    const itemsList = shownItems.map(i =>
      `<div class="item-line">${escapeHtml(i.itemName)} — ${i.quantity} ${escapeHtml(i.unit)}</div>`
    ).join('') + (moreCount > 0 ? `<div class="item-line more-items">+${moreCount} more item${moreCount > 1 ? 's' : ''}</div>` : '');
    const totalQty = items.reduce((sum, i) => sum + (i.quantity || 0), 0);
    const detailsBtn = `<button type="button" class="btn btn-secondary small-btn" data-incoming-details="${r.ingoingId}"><span class="btn-inline-icon ui-icon icon-info" aria-hidden="true"></span>Details</button>`;

    let action;
    if (isTrash) {
      // Trash row: restore or permanently delete.
      action = `<div class="row-actions">
        ${detailsBtn}
        <button type="button" class="btn btn-ghost small-btn" data-incoming-restore="${r.ingoingId}">Restore</button>
        <button type="button" class="btn btn-danger small-btn" data-incoming-purge="${r.ingoingId}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete</button>
      </div>`;
    } else if (checkerTrashEnabled && r.status === 'Checked') {
      // Checked row: allow moving to trash (super-admin only).
      action = `<div class="row-actions">
        ${detailsBtn}
        <button type="button" class="btn btn-danger small-btn" data-incoming-trash="${r.ingoingId}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete</button>
      </div>`;
    } else {
      action = detailsBtn;
    }

    return `<tr>
      <td>${fmtDate(r.createdDate)}</td>
      <td>${escapeHtml(r.supplierName || '—')}</td>
      <td><div class="items-cell">${itemsList || '—'}</div><span class="items-count-mobile">${items.length}</span></td>
      <td>${totalQty}</td>
      <td>${badge(r.status)}</td>
      <td>${escapeHtml(r.createdByName || 'Checker')}</td>
      <td>${action}</td>
    </tr>`;
  }

  function renderIncomingTable(records) {
    const isTrashTab = incomingFilter === 'Trash';

    if (isTrashTab) {
      // Render the trash tbody, hide the normal tbody.
      const trashRecords = window.__incomingTrashedRecords || [];
      renderIncomingTrashTable(trashRecords);
      return;
    }

    // Show normal tbody, hide trash tbody.
    const normalTbody = $('incoming-table');
    const trashTbody  = $('incoming-trash-table');
    if (normalTbody) normalTbody.style.display = '';
    if (trashTbody)  trashTbody.style.display  = 'none';

    const filtered = incomingFilter === 'all' ? records : records.filter(r => r.status === incomingFilter);
    const rowsHtml = filtered.map(r => buildIncomingRow(r, false));

    const total = rowsHtml.length;
    const tbody = normalTbody;

    if (!total) {
      const label = incomingFilter === 'all' ? '' : incomingFilter.toLowerCase() + ' ';
      tbody.innerHTML = `<tr><td colspan="7" class="empty">No ${label}incoming delivery records.</td></tr>`;
      renderIncomingPagination(0, 0, 0);
      return;
    }

    const pageCount = Math.max(1, Math.ceil(total / incomingPageSize));
    if (incomingPage > pageCount) incomingPage = pageCount;
    if (incomingPage < 1) incomingPage = 1;

    const start = (incomingPage - 1) * incomingPageSize;
    const end = Math.min(start + incomingPageSize, total);
    tbody.innerHTML = rowsHtml.slice(start, end).join('');
    renderIncomingPagination(start + 1, end, total);
  }

  function renderIncomingTrashTable(trashRecords) {
    const normalTbody = $('incoming-table');
    const trashTbody  = $('incoming-trash-table');
    if (normalTbody) normalTbody.style.display = 'none';
    if (trashTbody)  trashTbody.style.display  = '';

    const rowsHtml = trashRecords.map(r => buildIncomingRow(r, true));
    const total = rowsHtml.length;

    if (!total) {
      trashTbody.innerHTML = `<tr><td colspan="7" class="empty">Trash is empty.</td></tr>`;
      renderIncomingPagination(0, 0, 0);
      return;
    }

    const pageCount = Math.max(1, Math.ceil(total / incomingTrashPageSize));
    if (incomingTrashPage > pageCount) incomingTrashPage = pageCount;
    if (incomingTrashPage < 1) incomingTrashPage = 1;

    const start = (incomingTrashPage - 1) * incomingTrashPageSize;
    const end = Math.min(start + incomingTrashPageSize, total);
    trashTbody.innerHTML = rowsHtml.slice(start, end).join('');
    renderIncomingPagination(start + 1, end, total, true);
  }

  function renderIncomingPagination(from, to, total, isTrash = false) {
    const controls = $('incoming-pagination');
    if (!controls) return;
    if (!total) {
      controls.innerHTML = '';
      return;
    }
    const pageSize = isTrash ? incomingTrashPageSize : incomingPageSize;
    const pageCount = Math.max(1, Math.ceil(total / pageSize));
    const currentPage = isTrash ? incomingTrashPage : incomingPage;
    controls.innerHTML = `
      <div class="pagination-info">Showing ${from}–${to} of ${total}</div>
      <div class="pagination-controls">
        <label class="page-size-label">Rows:
          <select id="incoming-page-size">
            ${[10, 25, 50, 100].map(n => `<option value="${n}" ${n === pageSize ? 'selected' : ''}>${n}</option>`).join('')}
          </select>
        </label>
        <button type="button" class="btn btn-ghost small-btn" id="incoming-prev-page" ${currentPage <= 1 ? 'disabled' : ''}>Prev</button>
        <span class="page-indicator">Page ${currentPage} / ${pageCount}</span>
        <button type="button" class="btn btn-ghost small-btn" id="incoming-next-page" ${currentPage >= pageCount ? 'disabled' : ''}>Next</button>
      </div>`;

    $('incoming-page-size').addEventListener('change', (e) => {
      const val = parseInt(e.target.value, 10) || 10;
      if (isTrash) { incomingTrashPageSize = val; incomingTrashPage = 1; renderIncomingTrashTable(window.__incomingTrashedRecords || []); }
      else         { incomingPageSize = val; incomingPage = 1; renderIncomingTable(window.__incomingRecords || []); }
    });
    $('incoming-prev-page').addEventListener('click', () => {
      if (isTrash) { if (incomingTrashPage > 1) { incomingTrashPage--; renderIncomingTrashTable(window.__incomingTrashedRecords || []); } }
      else         { if (incomingPage > 1)       { incomingPage--;       renderIncomingTable(window.__incomingRecords || []); } }
    });
    $('incoming-next-page').addEventListener('click', () => {
      if (isTrash) { if (incomingTrashPage < pageCount) { incomingTrashPage++; renderIncomingTrashTable(window.__incomingTrashedRecords || []); } }
      else         { if (incomingPage < pageCount)       { incomingPage++;       renderIncomingTable(window.__incomingRecords || []); } }
    });
  }

  async function loadIncoming() {
    const data = await getJson(api('/api/checker/incoming.php'));
    const records = data.records || [];
    window.__incomingRecords = records;
    window.__incomingTrashedRecords = data.trashed || [];
    // Update all tab count badges.
    const inAllCount     = records.length;
    const inPendingCount = records.filter(r => String(r.status).toLowerCase() === 'pending').length;
    const inCheckedCount = records.filter(r => String(r.status).toLowerCase() === 'checked').length;
    const inTrashCount   = window.__incomingTrashedRecords.length;
    const setInCount = (id, n) => { const el = $(id); if (el) el.textContent = n || ''; };
    setInCount('incoming-all-count',          inAllCount);
    setInCount('incoming-pending-tab-count',  inPendingCount);
    setInCount('incoming-checked-count',      inCheckedCount);
    setInCount('incoming-trash-count',        inTrashCount);
    renderIncomingTable(records);
    setCounts(records, outgoingCache);
  }

  // ── Incoming delivery details modal (read-only) ──
  function openIncomingDetails(ingoingId) {
    // Check both live and trashed records caches.
    const allRecords = [...(window.__incomingRecords || []), ...(window.__incomingTrashedRecords || [])];
    const rec = allRecords.find(r => String(r.ingoingId) === String(ingoingId));
    if (!rec) return;
    const items = rec.items || [];
    const totalQty = items.reduce((sum, i) => sum + (i.quantity || 0), 0);

    $('incoming-details-title').textContent = `Delivery — ${rec.supplierName || 'Unknown supplier'}`;
    $('incoming-details-subtitle').innerHTML =
      `<span>${fmtDate(rec.createdDate)}</span>` +
      `<span class="detail-sep">·</span><span>${items.length} item(s) &nbsp;·&nbsp; ${totalQty} total qty</span>` +
      `<span class="detail-sep">·</span><span>Checked by: <strong>${escapeHtml(rec.createdByName || 'Checker')}</strong></span>` +
      `<span class="detail-sep">·</span>${badge(rec.status)}`;

    const body = $('incoming-details-body');
    body.innerHTML = items.map(it => {
      const confNote = it.confirmationNotes ? `<div class="muted review-conf-note"><span class="note-label">Review note:</span> ${escapeHtml(it.confirmationNotes)}</div>` : '';
      return `<div class="review-item">
        <div class="review-item-main">
          <div><strong>${escapeHtml(it.itemName)}</strong>${it.sku ? ` <span class="muted">(${escapeHtml(it.sku)})</span>` : ''}</div>
          <div class="muted">${it.quantity} ${escapeHtml(it.unit)}    Condition: ${escapeHtml(it.condition || 'Accepted / Good')}</div>
          ${it.notes ? `<div class="muted review-item-note"><span class="note-label">Note:</span> ${escapeHtml(it.notes)}</div>` : ''}
          ${confNote}
        </div>
        <div class="review-item-action"><div class="review-item-status">${badge(it.status)}${it.confirmedByName ? ` <span class="muted">by ${escapeHtml(it.confirmedByName)}</span>` : ''}</div></div>
      </div>`;
    }).join('') || '<p class="empty">No items in this delivery.</p>';

    $('incoming-details-modal').classList.add('show');
  }

  function closeIncomingDetails() {
    $('incoming-details-modal').classList.remove('show');
  }

  // ── Trash / Restore / Purge actions (super-admin only) ──
  async function trashIncoming(ingoingId) {
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
        body: JSON.stringify({ action: 'trash', ingoingId: Number(ingoingId) })
      });
      showToast('Delivery moved to Trash.', 'success');
      await loadIncoming();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async function restoreIncoming(ingoingId) {
    try {
      await getJson(api('/api/checker/delete-incoming.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'restore', ingoingId: Number(ingoingId) })
      });
      showToast('Delivery restored.', 'success');
      await loadIncoming();
      // Stay on trash tab after restore so user can see the updated list.
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async function purgeIncoming(ingoingId) {
    const confirmed = await confirmModal({
      title: 'Delete Permanently',
      message: 'Permanently delete this delivery and all its line-items from the database? This cannot be undone.',
      confirmText: 'Delete Permanently',
      cancelText: 'Cancel',
      danger: true,
    });
    if (!confirmed) return;
    try {
      await getJson(api('/api/checker/delete-incoming.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'purge', ingoingId: Number(ingoingId) })
      });
      showToast('Delivery permanently deleted.', 'success');
      await loadIncoming();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // Promise-based confirmation modal — dynamically injected into the DOM, matching
  // the incoming-confirmation page pattern.
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

  let outgoingFilter = 'all';
  // Separate pagination state for the outgoing trash tab.
  let outgoingTrashPage = 1;
  let outgoingTrashPageSize = 10;

  async function loadOutgoing() {
    const data = await getJson(api('/api/checker/outgoing.php'));
    outgoingCache = data.items || [];
    window.__outgoingTrashedRecords = data.trashed || [];
    // Update all tab count badges.
    const allCount     = outgoingCache.length;
    const pendingCount = outgoingCache.filter(i => String(i.status).toLowerCase() === 'pending').length;
    const checkedCount = outgoingCache.filter(i => String(i.status).toLowerCase() === 'checked').length;
    const trashCount   = window.__outgoingTrashedRecords.length;
    const setCount = (id, n) => { const el = $(id); if (el) el.textContent = n || ''; };
    setCount('outgoing-all-count',     allCount);
    setCount('outgoing-pending-count', pendingCount);
    setCount('outgoing-checked-count', checkedCount);
    setCount('outgoing-trash-count',   trashCount);
    renderOutgoingTable();
    setCounts(window.__incomingRecords || [], outgoingCache);
  }

  // Pagination state (page size is user-adjustable via the page-size selector).
  let outgoingPage = 1;
  let outgoingPageSize = 10;

  function renderOutgoingTable() {
    if (outgoingFilter === 'Trash') {
      renderOutgoingTrashTable(window.__outgoingTrashedRecords || []);
      return;
    }
    // Show normal tbody, hide trash tbody.
    const normalTbody = $('outgoing-table');
    const trashTbody  = $('outgoing-trash-table');
    if (normalTbody) normalTbody.style.display = '';
    if (trashTbody)  trashTbody.style.display  = 'none';
    // Build the full list of row-HTML strings for the active filter, then paginate.
    const rowsHtml = buildOutgoingTransactionRows();
    paintOutgoing(rowsHtml);
  }

  function renderOutgoingTrashTable(trashRecords) {
    const normalTbody = $('outgoing-table');
    const trashTbody  = $('outgoing-trash-table');
    if (normalTbody) normalTbody.style.display = 'none';
    if (trashTbody)  trashTbody.style.display  = '';

    const rowsHtml = trashRecords.map(i => buildOutgoingRow(i, true));
    const total = rowsHtml.length;

    if (!total) {
      trashTbody.innerHTML = `<tr><td colspan="7" class="empty">Trash is empty.</td></tr>`;
      renderOutgoingPagination(0, 0, 0, true);
      return;
    }

    const pageCount = Math.max(1, Math.ceil(total / outgoingTrashPageSize));
    if (outgoingTrashPage > pageCount) outgoingTrashPage = pageCount;
    if (outgoingTrashPage < 1) outgoingTrashPage = 1;

    const start = (outgoingTrashPage - 1) * outgoingTrashPageSize;
    const end = Math.min(start + outgoingTrashPageSize, total);
    trashTbody.innerHTML = rowsHtml.slice(start, end).join('');
    renderOutgoingPagination(start + 1, end, total, true);
  }

  // Slices the row list to the current page and renders it plus the pagination controls.
  function paintOutgoing(rowsHtml) {
    const total = rowsHtml.length;
    const tbody = $('outgoing-table');

    if (!total) {
      const label = outgoingFilter === 'all' ? '' : outgoingFilter.toLowerCase() + ' ';
      tbody.innerHTML = `<tr><td colspan="7" class="empty">No ${label}outgoing warehouse items${outgoingFilter === 'all' ? ' yet' : ''}.</td></tr>`;
      renderOutgoingPagination(0, 0, 0);
      return;
    }

    const pageCount = Math.max(1, Math.ceil(total / outgoingPageSize));
    if (outgoingPage > pageCount) outgoingPage = pageCount;
    if (outgoingPage < 1) outgoingPage = 1;

    const start = (outgoingPage - 1) * outgoingPageSize;
    const end = Math.min(start + outgoingPageSize, total);
    tbody.innerHTML = rowsHtml.slice(start, end).join('');
    renderOutgoingPagination(start + 1, end, total);
  }

  function renderOutgoingPagination(from, to, total, isTrash = false) {
    const controls = $('outgoing-pagination');
    if (!controls) return;
    if (!total) {
      controls.innerHTML = '';
      return;
    }
    const pageSize = isTrash ? outgoingTrashPageSize : outgoingPageSize;
    const pageCount = Math.max(1, Math.ceil(total / pageSize));
    const currentPage = isTrash ? outgoingTrashPage : outgoingPage;
    controls.innerHTML = `
      <div class="pagination-info">Showing ${from}–${to} of ${total}</div>
      <div class="pagination-controls">
        <label class="page-size-label">Rows:
          <select id="outgoing-page-size">
            ${[10, 25, 50, 100].map(n => `<option value="${n}" ${n === pageSize ? 'selected' : ''}>${n}</option>`).join('')}
          </select>
        </label>
        <button type="button" class="btn btn-ghost small-btn" id="outgoing-prev-page" ${currentPage <= 1 ? 'disabled' : ''}>Prev</button>
        <span class="page-indicator">Page ${currentPage} / ${pageCount}</span>
        <button type="button" class="btn btn-ghost small-btn" id="outgoing-next-page" ${currentPage >= pageCount ? 'disabled' : ''}>Next</button>
      </div>`;

    $('outgoing-page-size').addEventListener('change', (e) => {
      const val = parseInt(e.target.value, 10) || 10;
      if (isTrash) { outgoingTrashPageSize = val; outgoingTrashPage = 1; renderOutgoingTrashTable(window.__outgoingTrashedRecords || []); }
      else         { outgoingPageSize = val; outgoingPage = 1; renderOutgoingTable(); }
    });
    $('outgoing-prev-page').addEventListener('click', () => {
      if (isTrash) { if (outgoingTrashPage > 1) { outgoingTrashPage--; renderOutgoingTrashTable(window.__outgoingTrashedRecords || []); } }
      else         { if (outgoingPage > 1)       { outgoingPage--;       renderOutgoingTable(); } }
    });
    $('outgoing-next-page').addEventListener('click', () => {
      if (isTrash) { if (outgoingTrashPage < pageCount) { outgoingTrashPage++; renderOutgoingTrashTable(window.__outgoingTrashedRecords || []); } }
      else         { if (outgoingPage < pageCount)       { outgoingPage++;       renderOutgoingTable(); } }
    });
  }

  // Builds a single outgoing transaction <tr>. isTrash=true renders restore/purge actions.
  function buildOutgoingRow(i, isTrash = false) {
    // Legacy flat POS rows (from checker_records / sale_items) — read-only, no delete.
    if (i.source === 'pos' && !i.outgoingId) {
      const sourceInfo = `<strong>${escapeHtml(i.saleNumber || ('Sale #' + i.saleId))}</strong><br>${sourceBadge('pos')}`;
      const itemsCell = `<div class="items-cell"><div class="item-line">${escapeHtml(i.itemName)}${i.sku ? ` <span class="muted">(${escapeHtml(i.sku)})</span>` : ''}</div></div>`;
      return `<tr>
        <td>${sourceInfo}</td>
        <td>${fmtDate(i.date || i.soldAt || i.checkedAt)}</td>
        <td>${escapeHtml(purposeText(i.purpose))}</td>
        <td>${escapeHtml(i.sku || '—')}</td>
        <td>${itemsCell}</td>
        <td>${badge(i.status)}</td>
        <td>${badge(i.status)}</td>
      </tr>`;
    }

    const items = i.items || [];
    const MAX_PREVIEW = 2;
    const shownItems = items.slice(0, MAX_PREVIEW);
    const moreCount = items.length - shownItems.length;
    const itemsList = shownItems.map(it => `<div class="item-line">${escapeHtml(it.itemName)} — ${it.quantity} ${escapeHtml(it.unit)} <span class="muted">(${escapeHtml(it.sourceLocation)})</span></div>`).join('')
      + (moreCount > 0 ? `<div class="item-line more-items">+${moreCount} more item${moreCount > 1 ? 's' : ''}</div>` : '');
    const skuList = shownItems.map(it => `<div class="item-line">${escapeHtml(it.sku || '—')}</div>`).join('')
      + (moreCount > 0 ? `<div class="item-line more-items">&nbsp;</div>` : '');

    let action;
    if (isTrash) {
      // Trash row: restore or permanently delete.
      action = `<div class="row-actions">
        <button type="button" class="btn btn-ghost small-btn" data-outgoing-restore="${i.outgoingId}">Restore</button>
        <button type="button" class="btn btn-danger small-btn" data-outgoing-purge="${i.outgoingId}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete</button>
      </div>`;
    } else if (checkerTrashEnabled && i.status === 'Checked') {
      // Checked row: Review + Delete (move to trash).
      const statusCell = `${badge('Checked')}`;
      action = `<div class="row-actions">
        <button type="button" class="btn btn-secondary small-btn" data-outgoing-review="${i.outgoingId}"><span class="btn-inline-icon ui-icon icon-info" aria-hidden="true"></span>Details</button>
        <button type="button" class="btn btn-danger small-btn" data-outgoing-trash="${i.outgoingId}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete</button>
      </div>`;
      return `<tr>
        <td>${sourceBadge(i.source === 'pos' ? 'pos' : 'manual')}</td>
        <td>${fmtDate(i.date)}</td>
        <td>${escapeHtml(purposeText(i.purpose))}</td>
        <td><div class="items-cell">${skuList || '—'}</div></td>
        <td><div class="items-cell">${itemsList || '—'}</div></td>
        <td>${statusCell}</td>
        <td>${action}</td>
      </tr>`;
    } else {
      // Pending row: Review only.
      const statusCell = (i.pendingCount ?? 0) > 0
        ? `${badge('Pending')} <span class="muted">${i.pendingCount}/${i.itemCount} pending</span>`
        : `${badge('Checked')}`;
      action = `<button type="button" class="btn btn-secondary small-btn" data-outgoing-review="${i.outgoingId}"><span class="btn-inline-icon ui-icon icon-info" aria-hidden="true"></span>Details</button>`;
      return `<tr>
        <td>${sourceBadge(i.source === 'pos' ? 'pos' : 'manual')}</td>
        <td>${fmtDate(i.date)}</td>
        <td>${escapeHtml(purposeText(i.purpose))}</td>
        <td><div class="items-cell">${skuList || '—'}</div></td>
        <td><div class="items-cell">${itemsList || '—'}</div></td>
        <td>${statusCell}</td>
        <td>${action}</td>
      </tr>`;
    }

    // Trash row rendering (falls through from the isTrash branch above).
    const statusCell = badge(i.status || 'Checked');
    return `<tr>
      <td>${sourceBadge(i.source === 'pos' ? 'pos' : 'manual')}</td>
      <td>${fmtDate(i.date)}</td>
      <td>${escapeHtml(purposeText(i.purpose))}</td>
      <td><div class="items-cell">${skuList || '—'}</div></td>
      <td><div class="items-cell">${itemsList || '—'}</div></td>
      <td>${statusCell}</td>
      <td>${action}</td>
    </tr>`;
  }

  // All/Pending/Checked tabs: per-TRANSACTION rows. Returns an array of <tr> strings.
  function buildOutgoingTransactionRows() {
    const list = outgoingFilter === 'all'
      ? outgoingCache
      : outgoingCache.filter(i => String(i.status) === outgoingFilter);
    return list.map(i => buildOutgoingRow(i, false));
  }

  // ── Trash / Restore / Purge actions for outgoing (super-admin only) ──
  async function trashOutgoing(outgoingId) {
    const confirmed = await confirmModal({
      title: 'Move to Trash',
      message: 'Move this outgoing record to Trash? You can restore it later from the Trash tab.',
      confirmText: 'Move to Trash',
      cancelText: 'Cancel',
      danger: true,
    });
    if (!confirmed) return;
    try {
      await getJson(api('/api/checker/delete-outgoing.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'trash', outgoingId: Number(outgoingId) })
      });
      showToast('Outgoing record moved to Trash.', 'success');
      await loadOutgoing();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async function restoreOutgoing(outgoingId) {
    try {
      await getJson(api('/api/checker/delete-outgoing.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'restore', outgoingId: Number(outgoingId) })
      });
      showToast('Outgoing record restored.', 'success');
      await loadOutgoing();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async function purgeOutgoing(outgoingId) {
    const confirmed = await confirmModal({
      title: 'Delete Permanently',
      message: 'Permanently delete this outgoing record and all its line-items? This cannot be undone.',
      confirmText: 'Delete Permanently',
      cancelText: 'Cancel',
      danger: true,
    });
    if (!confirmed) return;
    try {
      await getJson(api('/api/checker/delete-outgoing.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'purge', outgoingId: Number(outgoingId) })
      });
      showToast('Outgoing record permanently deleted.', 'success');
      await loadOutgoing();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async function saveRecord(payload) {
    return getJson(api('/api/checker/save.php'), { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify(payload) });
  }

  $('incoming-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = e.target.querySelector('button[type="submit"]');
    await withSubmitLock(submitBtn, async () => {
      try {
        const items = collectRows('incoming-items-list', 'incoming');
        if (!items.length) throw new Error('Add at least one delivered item.');
        if (items.some(item => !item.inventoryItemId)) throw new Error('Please choose an inventory item for every delivered item.');
        if (!validateQuantityInputs('incoming-items-list')) return;
        await saveRecord({ type:'incoming', supplier:$('incoming-supplier').value, items });
        e.target.reset();
        $('incoming-items-list').innerHTML = '';
        createIncomingRow();
        closeIncomingModal();
        await loadIncoming();
        showToast(`${items.length} delivered item check(s) saved for admin/inventory confirmation.`, 'success');
      } catch (err) { showToast(err.message, 'error'); }
    });
  });

  function setManualFields(isManual) {
    document.querySelectorAll('.manual-only').forEach(el => el.style.display = isManual ? 'block' : 'none');
    document.querySelectorAll('.pos-only').forEach(el => el.style.display = isManual ? 'none' : 'flex');
    const modal = $('outgoing-form');
    if (modal) modal.classList.toggle('manual-mode', isManual);
    $('outgoing-qty').required = !isManual;
    if (isManual && !document.querySelector('#outgoing-items-list .item-row')) createOutgoingManualRow();
  }

  function openOutgoingForPos(item) {
    $('outgoing-mode').value = 'pos';
    $('outgoing-sale-item-id').value = item.saleItemId;
    $('outgoing-max-qty').value = item.requestedQuantity || item.quantity;
    $('outgoing-qty').value = item.quantity || item.requestedQuantity;
    $('outgoing-status').value = item.status && item.status !== 'pending' ? item.status : 'released';
    $('outgoing-notes').value = item.notes || '';
    $('outgoing-modal-title').textContent = 'Outgoing POS Warehouse Check';
    $('outgoing-modal-subtitle').textContent = `${item.itemName}    Requested: ${item.requestedQuantity || item.quantity} ${item.unit}`;

    setManualFields(false);
    $('outgoing-modal').classList.add('show');
  }

  function openManualOutgoing() {
    $('outgoing-mode').value = 'manual';
    $('outgoing-sale-item-id').value = '';
    $('outgoing-max-qty').value = '';
    $('outgoing-qty').value = '';
    $('outgoing-status').value = 'released';
    $('outgoing-notes').value = '';
    $('outgoing-purpose').value = 'Customer Purchase';
    $('outgoing-items-list').innerHTML = '';
    createOutgoingManualRow();
    applyPurposeConstraints();

    $('outgoing-modal-title').textContent = 'Manual Outgoing Entry';
    $('outgoing-modal-subtitle').textContent = 'Choose one or more inventory items for outgoing warehouse records.';
    setManualFields(true);
    $('outgoing-modal').classList.add('show');
  }

  document.addEventListener('click', (e) => {
    const tab = e.target.closest('.tab-btn');
    if (tab) {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      $(`${tab.dataset.tab}-panel`).classList.add('active');
    }
    const outgoingFilterTab = e.target.closest('.outgoing-filter-tab');
    if (outgoingFilterTab) {
      document.querySelectorAll('.outgoing-filter-tab').forEach(b => b.classList.remove('active'));
      outgoingFilterTab.classList.add('active');
      outgoingFilter = outgoingFilterTab.dataset.outgoingFilter;
      outgoingPage = 1; // reset to first page when the filter changes
      outgoingTrashPage = 1;
      renderOutgoingTable();
    } else {
      const filterTab = e.target.closest('.filter-tab');
      if (filterTab) {
        // Scope active state to the incoming filter group only.
        filterTab.parentElement.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
        filterTab.classList.add('active');
        incomingFilter = filterTab.dataset.filter;
        incomingPage = 1; // reset to first page when the filter changes
        renderIncomingTable(window.__incomingRecords || []);
      }
    }
    const btn = e.target.closest('.check-btn');
    if (btn) {
      const item = outgoingCache.find(x => String(x.saleItemId) === String(btn.dataset.id));
      if (!item) return;
      openOutgoingForPos(item);
    }
    const reviewBtn = e.target.closest('[data-outgoing-review]');
    if (reviewBtn) {
      openOutgoingReview(reviewBtn.dataset.outgoingReview);
    }
    const detailsBtn = e.target.closest('[data-incoming-details]');
    if (detailsBtn) {
      openIncomingDetails(detailsBtn.dataset.incomingDetails);
    }
    // Trash / restore / purge buttons (super-admin only, checker page).
    const trashBtn = e.target.closest('[data-incoming-trash]');
    if (trashBtn) { trashIncoming(trashBtn.dataset.incomingTrash); return; }
    const restoreBtn = e.target.closest('[data-incoming-restore]');
    if (restoreBtn) { restoreIncoming(restoreBtn.dataset.incomingRestore); return; }
    const purgeBtn = e.target.closest('[data-incoming-purge]');
    if (purgeBtn) { purgeIncoming(purgeBtn.dataset.incomingPurge); return; }
    // Outgoing trash / restore / purge (super-admin only).
    const outTrashBtn = e.target.closest('[data-outgoing-trash]');
    if (outTrashBtn) { trashOutgoing(outTrashBtn.dataset.outgoingTrash); return; }
    const outRestoreBtn = e.target.closest('[data-outgoing-restore]');
    if (outRestoreBtn) { restoreOutgoing(outRestoreBtn.dataset.outgoingRestore); return; }
    const outPurgeBtn = e.target.closest('[data-outgoing-purge]');
    if (outPurgeBtn) { purgeOutgoing(outPurgeBtn.dataset.outgoingPurge); return; }
    const reviewActionBtn = e.target.closest('[data-review-action][data-review-item-id]');
    if (reviewActionBtn) {
      confirmOutgoing(reviewActionBtn.dataset.reviewItemId, reviewActionBtn.dataset.reviewAction);
    }
  });

  function openIncomingModal() {
    $('incoming-form').reset();
    $('incoming-items-list').innerHTML = '';
    createIncomingRow();
    $('incoming-modal').classList.add('show');
  }
  function closeIncomingModal() {
    $('incoming-modal').classList.remove('show');
    $('incoming-form').reset();
    $('incoming-items-list').innerHTML = '';
    createIncomingRow();
  }

  $('incoming-add-row').addEventListener('click', () => createIncomingRow());
  $('toggle-incoming-form').addEventListener('click', openIncomingModal);
  $('incoming-modal-close').addEventListener('click', closeIncomingModal);
  $('incoming-modal-cancel').addEventListener('click', closeIncomingModal);
  $('incoming-modal').addEventListener('click', e => { if (e.target.id === 'incoming-modal') closeIncomingModal(); });

  // Incoming details modal close handlers
  $('incoming-details-close').addEventListener('click', closeIncomingDetails);
  $('incoming-details-done').addEventListener('click', closeIncomingDetails);
  $('incoming-details-modal').addEventListener('click', e => { if (e.target.id === 'incoming-details-modal') closeIncomingDetails(); });
  $('outgoing-add-row').addEventListener('click', () => createOutgoingManualRow());
  $('outgoing-purpose').addEventListener('change', applyPurposeConstraints);
  $('manual-outgoing-open').addEventListener('click', openManualOutgoing);

  function closeModal(){ $('outgoing-modal').classList.remove('show'); }
  $('modal-close').addEventListener('click', closeModal);
  $('modal-cancel').addEventListener('click', closeModal);
  $('outgoing-modal').addEventListener('click', e => { if (e.target.id === 'outgoing-modal') closeModal(); });

  // Outgoing review modal close handlers
  $('outgoing-review-close').addEventListener('click', closeOutgoingReview);
  $('outgoing-review-done').addEventListener('click', closeOutgoingReview);
  $('outgoing-review-modal').addEventListener('click', e => { if (e.target.id === 'outgoing-review-modal') closeOutgoingReview(); });

  // Outgoing item action modal (confirm/reject) handlers
  $('outgoing-action-form').addEventListener('submit', submitOutgoingAction);
  $('outgoing-action-close').addEventListener('click', closeOutgoingAction);
  $('outgoing-action-cancel').addEventListener('click', closeOutgoingAction);
  $('outgoing-action-modal').addEventListener('click', e => { if (e.target.id === 'outgoing-action-modal') closeOutgoingAction(); });

  $('outgoing-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = e.target.querySelector('button[type="submit"]');
    await withSubmitLock(submitBtn, async () => {
    try {
      const isManual = $('outgoing-mode').value === 'manual';
      const payload = isManual
        ? (() => {
            const items = collectRows('outgoing-items-list', 'outgoing');
            const purpose = $('outgoing-purpose')?.value || 'Customer Purchase';
            return { type:'outgoing', source:'manual', purpose, items, idempotencyKey: outgoingToken };
          })()
        : { type:'outgoing', source:'pos', saleItemId:$('outgoing-sale-item-id').value, quantity:$('outgoing-qty').value, purpose:'POS Warehouse Release', status:$('outgoing-status').value, notes:$('outgoing-notes').value, idempotencyKey: outgoingToken };
      if (isManual && !payload.items.length) throw new Error('Add at least one outgoing item.');
      if (isManual && payload.items.some(item => !item.inventoryItemId)) throw new Error('Please choose an inventory item for every outgoing item.');
      if (isManual) {
        if (!validateQuantityInputs('outgoing-items-list')) return;
      } else {
        // POS release allows fractional quantities, so only enforce the numeric range.
        if (!validateSingleQtyInput($('outgoing-qty'), true)) return;
      }
      if (isManual) {
        // Reject duplicate items that use the same source location.
        // The same item is only allowed more than once if each entry uses a different source location.
        const seen = new Set();
        for (const item of payload.items) {
          const key = `${item.inventoryItemId}|${item.sourceLocation || 'Warehouse'}`;
          if (seen.has(key)) {
            const name = item.itemName || 'This item';
            throw new Error(`Duplicate item: "${name}" is listed more than once with the same source location (${item.sourceLocation || 'Warehouse'}). Use a different source location for the duplicate.`);
          }
          seen.add(key);
        }
      }
      // ── Client-side stock availability check ─────────────────────────────
      // Walk every outgoing row and confirm the requested qty does not exceed the
      // cached available stock for the chosen source location. This mirrors the
      // server-side check in save.php and gives the user immediate feedback
      // (native validation bubble on the offending field) before the round-trip.
      if (isManual) {
        const rows = Array.from($('outgoing-items-list').querySelectorAll('.outgoing-item-row'));
        for (const row of rows) {
          const idInput   = row.querySelector('.row-item-id');
          const qtyInput  = row.querySelector('.row-qty');
          const srcSelect = row.querySelector('.row-source-location');
          if (!idInput || !qtyInput || !srcSelect) continue;

          const item   = itemById(idInput.value);
          const qty    = parseInt(qtyInput.value, 10);
          const source = srcSelect.value;
          if (!item || !Number.isFinite(qty) || qty <= 0) continue;

          const available = source === 'Warehouse'
            ? (item.warehouseQty ?? 0)
            : (item.storeQty ?? 0);

          if (qty > available) {
            const name = item.name || item.itemName || 'This item';
            const msg  = `Only ${available} available in ${source}.`;
            qtyInput.setCustomValidity(msg);
            qtyInput.reportValidity();
            // Also refresh the hint text so the warning is visible in the row.
            updateRowUnit(row);
            return; // abort — do not call saveRecord
          }
        }
      }
      // ─────────────────────────────────────────────────────────────────────

      await saveRecord(payload);
      outgoingToken = makeToken(); // rotate token so the next save is treated as a new submission
      closeModal();
      await loadOutgoing();
      showToast(isManual ? `${payload.items.length} outgoing item(s) saved.` : 'Outgoing check saved.', 'success');
    } catch (err) { showToast(err.message, 'error'); }
    });
  });

  let reviewOutgoingId = null;

  function currentReviewTransaction() {
    // Grouped outgoing transactions carry an outgoingId regardless of whether the
    // source is 'manual' or 'pos'. Match on outgoingId so POS releases are reviewable.
    return outgoingCache.find(i => i.outgoingId != null && String(i.outgoingId) === String(reviewOutgoingId));
  }

  function renderOutgoingReview() {
    const txn = currentReviewTransaction();
    const body = $('outgoing-review-body');
    if (!txn) { closeOutgoingReview(); return; }

    $('outgoing-review-title').textContent = `Outgoing — ${purposeText(txn.purpose)}`;
    $('outgoing-review-subtitle').innerHTML =
      `<span>${fmtDate(txn.date)}</span>` +
      `<span class="detail-sep">·</span><span>${txn.itemCount} item(s)</span>` +
      (txn.pendingCount > 0
        ? `<span class="detail-sep">·</span><span style="color:var(--accent-warning)">${txn.pendingCount} pending</span>`
        : `<span class="detail-sep">·</span><span style="color:var(--accent-success,#16a34a)">All confirmed</span>`) +
      (txn.createdBy ? `<span class="detail-sep">·</span><span>By: <strong>${escapeHtml(txn.createdBy)}</strong></span>` : '') +
      `<span class="detail-sep">·</span>${badge(txn.status)}`;

    body.innerHTML = (txn.items || []).map(it => {
      const statusRow = `<div class="review-item-status">${badge(it.status)}${it.confirmedBy ? ` <span class="muted">by ${escapeHtml(it.confirmedBy)}</span>` : ''}</div>`;
      const actions = it.status === 'Pending'
        ? `<div class="confirm-actions">
             <button class="confirm-btn" data-review-action="approve" data-review-item-id="${it.outgoingItemId}">Confirm</button>
             <button class="reject-btn" data-review-action="reject" data-review-item-id="${it.outgoingItemId}">Reject</button>
           </div>`
        : statusRow;
      const confNote = it.confirmationNotes ? `<div class="muted review-conf-note"><span class="note-label">Review note:</span> ${escapeHtml(it.confirmationNotes)}</div>` : '';
      return `<div class="review-item">
        <div class="review-item-main">
          <div><strong>${escapeHtml(it.itemName)}</strong>${it.sku ? ` <span class="muted">(${escapeHtml(it.sku)})</span>` : ''}</div>
          <div class="muted">${it.quantity} ${escapeHtml(it.unit)}    Source: ${escapeHtml(it.sourceLocation)}</div>
          ${it.notes ? `<div class="muted review-item-note"><span class="note-label">Note:</span> ${escapeHtml(it.notes)}</div>` : ''}
          ${confNote}
        </div>
        <div class="review-item-action">${actions}</div>
      </div>`;
    }).join('') || '<p class="empty">No items in this transaction.</p>';
  }

  function openOutgoingReview(outgoingId) {
    reviewOutgoingId = outgoingId;
    renderOutgoingReview();
    $('outgoing-review-modal').classList.add('show');
  }

  function closeOutgoingReview() {
    reviewOutgoingId = null;
    $('outgoing-review-modal').classList.remove('show');
  }

  function findOutgoingItem(outgoingItemId) {
    for (const rec of outgoingCache) {
      // Only grouped transactions (manual or POS) carry an items array.
      if (!Array.isArray(rec.items)) continue;
      const found = rec.items.find(it => String(it.outgoingItemId) === String(outgoingItemId));
      if (found) return found;
    }
    return null;
  }

  // Opens the action modal for a single outgoing item instead of native confirm/prompt dialogs.
  function confirmOutgoing(outgoingItemId, action) {
    const item = findOutgoingItem(outgoingItemId);
    if (!item) return;
    const isApprove = action === 'approve';

    $('outgoing-action-item-id').value = outgoingItemId;
    $('outgoing-action-type').value = action;
    $('outgoing-action-title').textContent = isApprove ? 'Confirm Item' : 'Reject Item';
    $('outgoing-action-subtitle').textContent = isApprove
      ? 'Approving will deduct stock from the source location.'
      : 'Rejecting will not change any stock.';
    $('outgoing-action-note-label').textContent = isApprove ? 'Note (optional)' : 'Reason for rejection';
    $('outgoing-action-notes').value = '';
    $('outgoing-action-notes').placeholder = isApprove
      ? 'Leave blank to use "Quantity verified and approved".'
      : 'Describe why this item is being rejected.';
    $('outgoing-action-summary').innerHTML = `
      <div><strong>${escapeHtml(item.itemName)}</strong>${item.sku ? ` <span class="muted">(${escapeHtml(item.sku)})</span>` : ''}</div>
      <div class="muted">${item.quantity} ${escapeHtml(item.unit)}    Source: ${escapeHtml(item.sourceLocation || 'N/A')}</div>`;

    const submitBtn = $('outgoing-action-submit');
    submitBtn.textContent = isApprove ? 'Confirm' : 'Reject';
    submitBtn.classList.toggle('btn-danger', !isApprove);
    submitBtn.classList.toggle('btn-primary', isApprove);

    $('outgoing-action-modal').classList.add('show');
  }

  function closeOutgoingAction() {
    $('outgoing-action-modal').classList.remove('show');
  }

  async function submitOutgoingAction(e) {
    e.preventDefault();
    const outgoingItemId = $('outgoing-action-item-id').value;
    const action = $('outgoing-action-type').value;
    const notes = $('outgoing-action-notes').value.trim();
    if (!outgoingItemId || !action) return;

    const submitBtn = $('outgoing-action-submit');
    submitBtn.disabled = true;
    try {
      const data = await getJson(api('/api/checker/confirm-outgoing.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ outgoingItemId: Number(outgoingItemId), action, notes })
      });
      closeOutgoingAction();
      await loadOutgoing();
      // Re-render the open review modal with fresh data (or close if the transaction is gone).
      if (reviewOutgoingId !== null) renderOutgoingReview();
      showToast(data.message || 'Done.', 'success');
    } catch (err) {
      showToast(err.message, 'error');
    } finally {
      submitBtn.disabled = false;
    }
  }

  let recheckingFilter = 'all';

  async function loadRechecking() {
    try {
      const data = await getJson(api('/api/checker/rechecking.php'));
      recheckingCache = data.items || [];
      window.__recheckingTrashedRecords = data.trashed || [];
      // Update tab count badges.
      const setRC = (id, n) => { const el = $(id); if (el) el.textContent = n || ''; };
      setRC('recheck-all-count',   recheckingCache.length);
      setRC('recheck-trash-count', window.__recheckingTrashedRecords.length);
      renderRecheckingTable();
      setCounts(window.__incomingRecords || [], outgoingCache);
    } catch (err) {
      $('rechecking-table').innerHTML = `<tr><td colspan="8" class="empty">Error loading: ${escapeHtml(err.message)}</td></tr>`;
    }
  }

  function buildRecheckRow(item, isTrash = false) {
    const actions = isTrash
      ? `<div class="row-actions">
           <button class="btn btn-ghost small-btn" data-restore-recheck-id="${item.ingoingItemId}">Restore</button>
           <button class="btn btn-danger small-btn" data-purge-recheck-id="${item.ingoingItemId}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete</button>
         </div>`
      : `<div class="row-actions">
           <button class="btn btn-secondary small-btn recheck-btn" data-recheck-id="${item.ingoingItemId}"><span class="btn-inline-icon ui-icon icon-refresh" aria-hidden="true"></span>Re-check</button>
           ${checkerTrashEnabled ? `<button class="btn btn-danger small-btn" data-delete-recheck-id="${item.ingoingItemId}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete</button>` : ''}
         </div>`;
    return `<tr>
      <td>${fmtDate(item.confirmedDate)}</td>
      <td>${escapeHtml(item.supplierName || '—')}</td>
      <td><strong>${escapeHtml(item.itemName)}</strong>${item.sku ? `<br><span class="muted">${escapeHtml(item.sku)}</span>` : ''}</td>
      <td>${item.quantity} ${escapeHtml(item.unit)}</td>
      <td>${badge(item.condition)}</td>
      <td>${escapeHtml(item.confirmedByName || '—')}</td>
      <td class="note-cell">${escapeHtml(item.notes || item.confirmationNotes || '—')}</td>
      <td>${actions}</td>
    </tr>`;
  }

  function renderRecheckingTable() {
    if (recheckingFilter === 'Trash') {
      renderRecheckingTrashTable(window.__recheckingTrashedRecords || []);
      return;
    }
    const normalTbody = $('rechecking-table');
    const trashTbody  = $('rechecking-trash-table');
    if (normalTbody) normalTbody.style.display = '';
    if (trashTbody)  trashTbody.style.display  = 'none';

    if (!recheckingCache.length) {
      normalTbody.innerHTML = '<tr><td colspan="8" class="empty">No items for re-checking.</td></tr>';
      return;
    }
    normalTbody.innerHTML = recheckingCache.map(item => buildRecheckRow(item, false)).join('');
  }

  function renderRecheckingTrashTable(trashRecords) {
    const normalTbody = $('rechecking-table');
    const trashTbody  = $('rechecking-trash-table');
    if (normalTbody) normalTbody.style.display = 'none';
    if (trashTbody)  trashTbody.style.display  = '';

    if (!trashRecords.length) {
      trashTbody.innerHTML = '<tr><td colspan="8" class="empty">Trash is empty.</td></tr>';
      return;
    }
    trashTbody.innerHTML = trashRecords.map(item => buildRecheckRow(item, true)).join('');
  }

  async function deleteRecheckItem(ingoingItemId) {
    const confirmed = await confirmModal({
      title: 'Move to Trash',
      message: 'Move this re-check item to Trash? You can restore it later from the Trash tab.',
      confirmText: 'Move to Trash',
      cancelText: 'Cancel',
      danger: true,
    });
    if (!confirmed) return;
    try {
      await getJson(api('/api/checker/delete-recheck-item.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'trash', ingoingItemId: Number(ingoingItemId) })
      });
      showToast('Re-check item moved to Trash.', 'success');
      await loadRechecking();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async function restoreRecheckItem(ingoingItemId) {
    try {
      await getJson(api('/api/checker/delete-recheck-item.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'restore', ingoingItemId: Number(ingoingItemId) })
      });
      showToast('Re-check item restored.', 'success');
      await loadRechecking();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  async function purgeRecheckItem(ingoingItemId) {
    const confirmed = await confirmModal({
      title: 'Delete Permanently',
      message: 'Permanently delete this re-check item? This cannot be undone.',
      confirmText: 'Delete Permanently',
      cancelText: 'Cancel',
      danger: true,
    });
    if (!confirmed) return;
    try {
      await getJson(api('/api/checker/delete-recheck-item.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'purge', ingoingItemId: Number(ingoingItemId) })
      });
      showToast('Re-check item permanently deleted.', 'success');
      await loadRechecking();
    } catch (err) {
      showToast(err.message, 'error');
    }
  }

  // --- Re-check modal logic ---
  function openRecheckModal(item) {
    $('recheck-item-id').value = item.ingoingItemId;
    $('recheck-condition').value = '';
    $('recheck-notes').value = '';
    $('recheck-modal-subtitle').textContent = `${item.itemName} — ${item.quantity} ${item.unit} — Current: ${item.condition}`;
    $('recheck-modal').classList.add('show');
  }

  function closeRecheckModal() {
    $('recheck-modal').classList.remove('show');
  }

  $('recheck-modal-close')?.addEventListener('click', closeRecheckModal);
  $('recheck-modal-cancel')?.addEventListener('click', closeRecheckModal);
  $('recheck-modal')?.addEventListener('click', e => { if (e.target.id === 'recheck-modal') closeRecheckModal(); });

  document.addEventListener('click', (e) => {
    const recheckBtn = e.target.closest('.recheck-btn');
    if (recheckBtn) {
      const itemId = recheckBtn.dataset.recheckId;
      const item = recheckingCache.find(i => String(i.ingoingItemId) === String(itemId));
      if (item) openRecheckModal(item);
    }
    const deleteRecheckBtn = e.target.closest('[data-delete-recheck-id]');
    if (deleteRecheckBtn) { deleteRecheckItem(deleteRecheckBtn.dataset.deleteRecheckId); return; }
    const restoreRecheckBtn = e.target.closest('[data-restore-recheck-id]');
    if (restoreRecheckBtn) { restoreRecheckItem(restoreRecheckBtn.dataset.restoreRecheckId); return; }
    const purgeRecheckBtn = e.target.closest('[data-purge-recheck-id]');
    if (purgeRecheckBtn) { purgeRecheckItem(purgeRecheckBtn.dataset.purgeRecheckId); return; }
    // Recheck filter tabs
    const recheckFilterTab = e.target.closest('.recheck-filter-tab');
    if (recheckFilterTab) {
      document.querySelectorAll('.recheck-filter-tab').forEach(b => b.classList.remove('active'));
      recheckFilterTab.classList.add('active');
      recheckingFilter = recheckFilterTab.dataset.recheckFilter;
      renderRecheckingTable();
      return;
    }
  });

  $('recheck-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = e.target.querySelector('button[type="submit"]');
    await withSubmitLock(submitBtn, async () => {
      try {
        const ingoingItemId = $('recheck-item-id').value;
        const condition = $('recheck-condition').value;
        const notes = $('recheck-notes').value.trim();
        const data = await getJson(api('/api/checker/recheck-submit.php'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ ingoingItemId: Number(ingoingItemId), condition, notes })
        });
        closeRecheckModal();
        showToast(data.message || 'Re-check submitted.', 'success');
        await loadRechecking();
      } catch (err) {
        showToast(err.message, 'error');
      }
    });
  });

  (async function start() {
    try {
      await Promise.all([loadInventoryChoices(), loadSupplierChoices()]);
      createIncomingRow();
      setManualFields(false);
      await Promise.all([loadIncoming(), loadOutgoing(), loadRechecking()]);
    } catch (err) {
      showToast(err.message, 'error');
    }
  })();
})();
