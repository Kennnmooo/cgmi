(function () {
  const api = window.CGMI_SUPPLIERS_API || {};
  let suppliers = [];
  let editingId = null;
  let activeTable = null;
  let inactiveTable = null;
  let confirmCallback = null;

  const el = (id) => document.getElementById(id);
  const escapeHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));

  // ─── DataTables ──────────────────────────────────────────────
  function initDataTables() {
    // Custom paging control: [Prev] [Page X/Y] [Next] — matches outgoing
    // warehouse table, with the indicator sitting BETWEEN the two buttons.
    // Registered once via DataTable.feature.register; guarded so re-init is safe.
    const DT = $.fn.dataTable;
    if (DT.feature && !DT.ext.features.supplierPaging) {
      DT.feature.register('supplierPaging', function (settings) {
        const dtApi = new DT.Api(settings);

        const wrap = document.createElement('div');
        wrap.className = 'dt-supplier-paging';

        const prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'dt-page-btn dt-page-prev';
        prev.textContent = 'Prev';

        const indicator = document.createElement('span');
        indicator.className = 'dt-page-indicator';

        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'dt-page-btn dt-page-next';
        next.textContent = 'Next';

        prev.addEventListener('click', () => { if (!prev.disabled) dtApi.page('previous').draw('page'); });
        next.addEventListener('click', () => { if (!next.disabled) dtApi.page('next').draw('page'); });

        const update = () => {
          const info = dtApi.page.info();
          const pages = info.pages || 0;
          const current = pages ? info.page + 1 : 0;
          indicator.textContent = `Page ${current} / ${pages}`;
          prev.disabled = info.page <= 0;
          next.disabled = pages === 0 || info.page >= pages - 1;
          prev.classList.toggle('disabled', prev.disabled);
          next.classList.toggle('disabled', next.disabled);
        };
        dtApi.on('draw', update);
        update();

        wrap.appendChild(prev);
        wrap.appendChild(indicator);
        wrap.appendChild(next);
        return wrap;
      });
    }

    const commonConfig = {
      order: [[0, 'asc']],
      pageLength: 10,
      lengthMenu: [10, 25, 50, 100],
      language: {
        search: 'Search:',
        lengthMenu: 'Rows: _MENU_',
      },
      // Layout mirrors the outgoing warehouse table: search on top,
      // info on the bottom-left, and the "Rows:" selector grouped with the
      // custom [Prev] [Page X/Y] [Next] control on the bottom-right.
      layout: {
        topStart: null,
        topEnd: null,
        bottomStart: 'info',
        bottomEnd: ['pageLength', 'supplierPaging'],
      },
    };

    activeTable = $('#supplier-table-active').DataTable({
      ...commonConfig,
      language: { ...commonConfig.language, emptyTable: 'No active suppliers.' },
      columns: [
        { data: 'name', width: '30%' },
        { data: 'address', defaultContent: '—', width: '35%' },
        { data: 'phone', defaultContent: '—', width: '15%' },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data) {
            return `<div class="action-btns">
              <button class="btn btn-secondary small-btn action-details mobile-only-btn" data-id="${data.id}"><span class="btn-inline-icon ui-icon icon-info" aria-hidden="true"></span>Details</button>
              <button class="btn btn-secondary small-btn action-edit" data-id="${data.id}"><span class="btn-inline-icon ui-icon icon-edit" aria-hidden="true"></span>Update</button>
              <button class="btn btn-danger small-btn action-toggle desktop-only-btn" data-id="${data.id}" data-action="deactivate"><span class="btn-inline-icon ui-icon icon-incident" aria-hidden="true"></span>Deactivate</button>
            </div>`;
          }
        }
      ],
    });

    inactiveTable = $('#supplier-table-inactive').DataTable({
      ...commonConfig,
      language: { ...commonConfig.language, emptyTable: 'No inactive suppliers.' },
      columns: [
        { data: 'name', width: '30%' },
        { data: 'address', defaultContent: '—', width: '35%' },
        { data: 'phone', defaultContent: '—', width: '15%' },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function (data) {
            return `<div class="action-btns">
              <button class="btn btn-primary small-btn action-toggle" data-id="${data.id}" data-action="activate">Activate</button>
              <button class="btn btn-danger small-btn action-remove" data-id="${data.id}"><span class="btn-inline-icon ui-icon icon-empty" aria-hidden="true"></span>Delete</button>
            </div>`;
          }
        }
      ],
    });

    // Delegate action clicks on active table
    $('#supplier-table-active tbody').on('click', '.action-edit', function () {
      const id = Number($(this).data('id'));
      const supplier = suppliers.find(s => s.id === id);
      if (supplier) openModal(supplier);
    });

    $('#supplier-table-active tbody').on('click', '.action-toggle', function () {
      const id = Number($(this).data('id'));
      const action = $(this).data('action');
      toggleSupplier(id, action);
    });

    $('#supplier-table-active tbody').on('click', '.action-details', function () {
      const id = Number($(this).data('id'));
      const supplier = suppliers.find(s => s.id === id);
      if (supplier) openDetailsModal(supplier);
    });

    // Delegate action clicks on inactive table
    $('#supplier-table-inactive tbody').on('click', '.action-edit', function () {
      const id = Number($(this).data('id'));
      const supplier = suppliers.find(s => s.id === id);
      if (supplier) openModal(supplier);
    });

    $('#supplier-table-inactive tbody').on('click', '.action-toggle', function () {
      const id = Number($(this).data('id'));
      const action = $(this).data('action');
      toggleSupplier(id, action);
    });

    $('#supplier-table-inactive tbody').on('click', '.action-remove', function () {
      const id = Number($(this).data('id'));
      const supplier = suppliers.find(s => s.id === id);
      if (supplier) removeFromView(supplier);
    });
  }

  function refreshTables() {
    const active   = suppliers.filter(s =>  s.isActive);
    const inactive = suppliers.filter(s => !s.isActive);

    activeTable.clear();
    activeTable.rows.add(active);
    activeTable.draw();

    if (inactiveTable) {
      inactiveTable.clear();
      inactiveTable.rows.add(inactive);
      inactiveTable.draw();
    }

    updateStats();
  }

  function updateStats() {
    const total    = suppliers.length;
    const active   = suppliers.filter(s => s.isActive).length;
    const inactive = total - active;
    el('stat-total').textContent  = total;
    el('stat-active').textContent = active;
    if (el('stat-inactive')) el('stat-inactive').textContent = inactive;
    el('supplier-count-badge').textContent = `${total} supplier${total === 1 ? '' : 's'}`;
  }

  // ─── Tabs ────────────────────────────────────────────────────
  function initTabs() {
    document.querySelectorAll('.supplier-tabs .tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.supplier-tabs .tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        el(`${btn.dataset.tab}-panel`).classList.add('active');
        // Recalculate columns when switching tabs (DataTables needs this for hidden tables)
        if (btn.dataset.tab === 'inactive') {
          inactiveTable.columns.adjust();
          const q = el('supplier-search-input')?.value || '';
          inactiveTable.search(q).draw();
        } else {
          activeTable.columns.adjust();
          const q = el('supplier-search-input')?.value || '';
          activeTable.search(q).draw();
        }
      });
    });
  }

  // ─── API calls ───────────────────────────────────────────────
  async function fetchSuppliers() {
    const response = await fetch(api.list, { credentials: 'same-origin' });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load suppliers.');
    suppliers = data.suppliers || [];
    refreshTables();
  }

  async function saveSupplier() {
    const payload = getPayload();
    if (!payload) return;
    const response = await fetch(api.save, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (response.status === 422 && data.errors) {
      Object.entries(data.errors).forEach(([key, val]) => {
        if (['name', 'address', 'phone'].includes(key)) setError(key, val);
      });
      throw new Error(data.message || 'Please correct the highlighted fields.');
    }
    if (!response.ok || !data.success) throw new Error(data.message || 'Unable to save supplier.');
    upsertSupplier(data.supplier);
    closeModal();
    toast(data.message || 'Saved successfully.');
  }

  async function toggleSupplier(id, action) {
    const response = await fetch(api.toggle, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ id, action }),
    });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Unable to update supplier status.');
    upsertSupplier(data.supplier);
    toast(data.message || 'Supplier updated.');
  }

  function upsertSupplier(supplier) {
    const idx = suppliers.findIndex(s => s.id === supplier.id);
    if (idx >= 0) suppliers[idx] = supplier;
    else suppliers.push(supplier);
    refreshTables();
  }

  function removeFromView(supplier) {
    if (!confirm(`Remove "${supplier.name}" from the list? Are you sure?`)) return;
    suppliers = suppliers.filter(s => s.id !== supplier.id);
    refreshTables();
    toast(`"${supplier.name}" removed .`);
  }

  // ─── Details Modal (mobile portrait) ─────────────────────────
  function openDetailsModal(supplier) {
    el('details-modal-name').textContent          = supplier.name    || '—';
    el('details-modal-address').textContent       = supplier.address || '—';
    el('details-modal-phone').textContent         = supplier.phone   || '—';
    el('details-modal-edit-btn').dataset.id       = supplier.id;
    el('details-modal-deactivate-btn').dataset.id = supplier.id;
    el('supplier-details-modal').classList.add('active');
  }

  function closeDetailsModal() {
    el('supplier-details-modal').classList.remove('active');
  }

  // ─── Modal ──────────────────────────────────────────────────
  function openModal(supplier = null) {
    editingId = supplier ? supplier.id : null;
    el('modal-title-text').textContent = supplier ? 'Edit Supplier' : 'Add Supplier';
    el('modal-subtitle').textContent = supplier ? 'Update the supplier details below.' : 'Fill in the details to add a new supplier.';
    el('modal-save-btn').textContent = supplier ? 'Save Changes' : 'Save Supplier';
    clearErrors();
    el('input-name').value = supplier ? supplier.name : '';
    el('input-address').value = supplier ? supplier.address : '';
    el('input-phone').value = supplier ? supplier.phone : '';
    el('supplier-modal').classList.add('active');
  }

  function closeModal() {
    el('supplier-modal').classList.remove('active');
    editingId = null;
  }

  function clearErrors() {
    ['name', 'address', 'phone'].forEach(k => {
      const err = el(`err-${k}`);
      if (err) err.textContent = '';
      const input = el(`input-${k}`);
      if (input) input.classList.remove('error');
    });
  }

  function setError(field, msg) {
    const input = el(`input-${field}`);
    const err = el(`err-${field}`);
    if (input) input.classList.add('error');
    if (err) err.textContent = msg;
  }

  function getPayload() {
    const name = el('input-name').value.trim();
    const address = el('input-address').value.trim();
    const phone = el('input-phone').value.trim();
    clearErrors();
    let valid = true;
    if (!name) { setError('name', 'Supplier name is required.'); valid = false; }
    if (!address) { setError('address', 'Address is required.'); valid = false; }
    if (!phone) { setError('phone', 'Phone number is required.'); valid = false; }
    else if (phone.length > 11) { setError('phone', 'Phone number must not exceed 11 digits.'); valid = false; }
    if (!valid) return null;
    return { id: editingId, name, address, phone };
  }

  // ─── Confirm dialog ─────────────────────────────────────────
  function closeConfirm() {
    el('confirm-overlay').classList.remove('active');
    confirmCallback = null;
  }

  // ─── Toast ──────────────────────────────────────────────────
  // Route a plain-text toast to the correct on-screen stack by type:
  // error / warning -> BOTTOM-LEFT, success (default) -> BOTTOM-RIGHT.
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

  const TOAST_META = {
    success: { color: 'var(--accent-success, #16a34a)', icon: '✓', title: 'Success' },
    error:   { color: 'var(--accent-danger, #dc2626)',  icon: '✕', title: 'Error' },
    warning: { color: 'var(--accent-warning, #d97706)', icon: '⚠', title: 'Warning' },
    info:    { color: 'var(--blue-medium, #4569AD)',     icon: 'ℹ', title: 'Notice' },
  };

  function toast(message, type = 'success') {
    const container = toastStackFor(type);
    if (!container) { alert(message); return; }
    const meta = TOAST_META[type] || TOAST_META.success;
    const n = document.createElement('div');
    n.className = 'toast';
    n.style.setProperty('--toast-color', meta.color);
    n.innerHTML = `
      <div class="toast-icon" style="color:${meta.color}" aria-hidden="true">${meta.icon}</div>
      <div class="toast-body">
        <div class="toast-title">${meta.title}</div>
        <div class="toast-msg">${message.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>
      </div>`;
    const dismiss = () => {
      n.classList.add('hide');
      n.addEventListener('animationend', () => n.remove(), { once: true });
    };
    n.addEventListener('click', dismiss);
    container.appendChild(n);
    setTimeout(dismiss, type === 'error' ? 6000 : 4000);
  }

  // ─── Init ───────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    initDataTables();
    initTabs();
    fetchSuppliers().catch(err => toast(err.message, 'error'));

    // Custom search input → filter the active DataTable tab
    el('supplier-search-input')?.addEventListener('input', function () {
      const q = this.value;
      const activeTab = document.querySelector('.supplier-tabs .tab-btn.active')?.dataset.tab;
      if (activeTab === 'inactive') inactiveTable.search(q).draw();
      else activeTable.search(q).draw();
    });

    // Modal events
    el('add-supplier-btn')?.addEventListener('click', () => openModal());
    el('modal-close-btn')?.addEventListener('click', closeModal);
    el('modal-cancel-btn')?.addEventListener('click', closeModal);
    el('supplier-modal')?.addEventListener('click', (e) => { if (e.target.id === 'supplier-modal') closeModal(); });
    el('modal-save-btn')?.addEventListener('click', () => saveSupplier().catch(err => toast(err.message, 'error')));

    // Details modal events
    el('details-modal-close-btn')?.addEventListener('click', closeDetailsModal);
    el('supplier-details-modal')?.addEventListener('click', (e) => { if (e.target.id === 'supplier-details-modal') closeDetailsModal(); });
    el('details-modal-edit-btn')?.addEventListener('click', function () {
      const id = Number(this.dataset.id);
      const supplier = suppliers.find(s => s.id === id);
      closeDetailsModal();
      if (supplier) openModal(supplier);
    });
    el('details-modal-deactivate-btn')?.addEventListener('click', function () {
      const id = Number(this.dataset.id);
      closeDetailsModal();
      toggleSupplier(id, 'deactivate').catch(err => toast(err.message, 'error'));
    });

    // Confirm events
    el('confirm-cancel-btn')?.addEventListener('click', closeConfirm);
    el('confirm-overlay')?.addEventListener('click', (e) => { if (e.target.id === 'confirm-overlay') closeConfirm(); });
    el('confirm-ok-btn')?.addEventListener('click', () => { if (confirmCallback) confirmCallback(); closeConfirm(); });
  });
})();
