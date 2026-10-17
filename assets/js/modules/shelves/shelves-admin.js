(function(){
  const api = window.CGMI_SHELVES_API || {};
  let shelves = [], inventoryOptions = [];
  let editingId = null;

  const el = (id) => document.getElementById(id);
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (m) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));
  const fmt = (v) => {
    const n = Number(v);
    if (!Number.isFinite(n)) return '0';
    return n.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  };

  async function loadAll() {
    const shelvesRes = await fetch(api.list, { credentials:'same-origin' });
    const shelvesData = await shelvesRes.json();
    if (!shelvesRes.ok || !shelvesData.success) throw new Error(shelvesData.message || 'Unable to load shelves.');
    shelves = shelvesData.items || [];
    inventoryOptions = shelvesData.inventoryOptions || [];
    render();
  }

  function render() {
    el('stat-total').textContent = shelves.length;
    el('stat-occupied').textContent = shelves.filter(s => s.status === 'Occupied').length;
    el('stat-unused').textContent = shelves.filter(s => s.status === 'Unused').length;
    el('stat-maintenance').textContent = shelves.filter(s => s.status === 'Maintenance').length;
    el('shelves-count-badge').textContent = shelves.length;

    const grid = el('shelves-grid');
    const empty = el('shelves-empty');
    if (!shelves.length) {
      grid.innerHTML = '';
      grid.style.display = 'none';
      empty.style.display = 'block';
      return;
    }

    empty.style.display = 'none';
    grid.style.display = 'grid';
    grid.innerHTML = shelves.map((shelf) => `
      <div class="shelf-card shelf-card--${statusModifier(shelf.status)}" data-shelf-id="${shelf.id}">
        <div class="shelf-card-header">
          <div class="shelf-card-heading">
            <div class="shelf-card-icon ui-icon icon-shelves" aria-hidden="true"></div>
            <div>
              <div class="shelf-card-name">${esc(shelf.name)}</div>
              <div class="shelf-card-location"><span class="ui-icon icon-location" aria-hidden="true"></span>${esc(shelf.location)}</div>
            </div>
          </div>
          ${statusBadge(shelf.status)}
        </div>
        <div class="shelf-card-details">
          <div class="shelf-detail-item"><div class="shelf-detail-label">Item</div><div class="shelf-detail-value">${esc(shelf.itemName || '—')}</div></div>
          <div class="shelf-detail-item"><div class="shelf-detail-label">On Shelf</div><div class="shelf-detail-value">${shelf.quantity ? `${esc(shelf.quantity)} ${esc(shelf.unit || '')}` : '—'}</div></div>
          <div class="shelf-detail-item"><div class="shelf-detail-label">Min Weight</div><div class="shelf-detail-value">${shelf.minWeight != null ? esc(fmt(shelf.minWeight)) + ' kg' : '—'}</div></div>
          <div class="shelf-detail-item"><div class="shelf-detail-label">Max Weight</div><div class="shelf-detail-value">${shelf.maxWeight != null ? esc(fmt(shelf.maxWeight)) + ' kg' : '—'}</div></div>
        </div>
        <div class="shelf-card-actions"><button class="btn btn-secondary btn-sm shelf-details-btn" data-id="${shelf.id}"><span class="btn-inline-icon ui-icon icon-info" aria-hidden="true"></span>Details</button><button class="btn btn-secondary btn-sm shelf-update-btn" data-id="${shelf.id}"><span class="btn-inline-icon ui-icon icon-edit" aria-hidden="true"></span>Update</button></div>
      </div>`).join('');
  }

  function statusBadge(status){
    const map = { Occupied:'badge-occupied', Unused:'badge-unused', Maintenance:'badge-maintenance' };
    return `<span class="badge ${map[status] || 'badge-unused'}">${esc(status)}</span>`;
  }

  function statusModifier(status){
    const map = { Occupied:'occupied', Unused:'unused', Maintenance:'maintenance' };
    return map[status] || 'unused';
  }

  function populateFormOptions(selectedShelf=null){
    const itemSelect = el('form-item');
    itemSelect.innerHTML = '<option value="">— Select IoT shelf item —</option>' + inventoryOptions
      .filter(item => {
        // Show items not assigned to any shelf, or assigned to the current shelf being edited
        const assignedShelf = shelves.find(s => s.itemId === item.id);
        return !assignedShelf || (selectedShelf && assignedShelf.id === selectedShelf.id);
      })
      .map(item => {
        const stock = `${esc(item.stockQuantity ?? 0)} ${esc(item.unit || '')}`;
        return `<option value="${item.id}">${esc(item.name)} — Stock: ${stock}</option>`;
      })
      .join('');
  }

  function openDetails(id){
    const shelf = shelves.find(s => Number(s.id) === Number(id));
    if(!shelf) return;
    el('det-shelf-name').textContent = shelf.name;
    el('det-location').textContent = shelf.location || '—';
    el('det-item').textContent = shelf.itemName || '—';
    el('det-quantity').textContent = shelf.quantity ? `${shelf.quantity} ${shelf.unit || ''}` : '—';
    if (el('det-warehouse')) el('det-warehouse').textContent = shelf.itemName ? `${shelf.warehouseQty ?? 0} ${shelf.unit || ''}` : '—';
    el('det-min').textContent = shelf.minWeight != null ? `${fmt(shelf.minWeight)} kg` : '—';
    el('det-max').textContent = shelf.maxWeight != null ? `${fmt(shelf.maxWeight)} kg` : '—';
    el('det-status-wrap').innerHTML = statusBadge(shelf.status);
    el('details-modal-backdrop').classList.add('active');
  }

  function closeDetails(){ el('details-modal-backdrop').classList.remove('active'); }

  function openForm(id=null){
    const shelf = id ? shelves.find(s => Number(s.id) === Number(id)) : null;
    editingId = shelf ? Number(shelf.id) : null;
    populateFormOptions(shelf || null);
    el('form-modal-title').textContent = shelf ? 'Update Shelf' : 'Add New Shelf';
    el('form-modal-subtitle').textContent = shelf ? `Editing: ${shelf.name}` : 'Fill in the details to register a shelf';
    el('form-save-btn').textContent = shelf ? 'Save Changes' : 'Add Shelf';
    el('form-shelf-name').value = shelf?.name || '';
    el('form-item').value = shelf?.itemId || '';
    el('form-location').value = shelf?.location || '';
    el('form-status').value = shelf?.status || 'Unused';
    el('form-modal-backdrop').classList.add('active');
  }

  function closeForm(){ el('form-modal-backdrop').classList.remove('active'); editingId = null; }

  async function saveShelf(){
    const payload = {
      id: editingId,
      name: el('form-shelf-name').value.trim(),
      location: el('form-location').value,
      itemId: el('form-item').value || null,
      status: String(el('form-status').value || '').toLowerCase()
    };
    const response = await fetch(api.save, {
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/json','Accept':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await response.json();
    if(!response.ok || !data.success) throw new Error(data.message || 'Unable to save shelf.');
    toast(data.message || 'Shelf saved.');
    closeForm();
    await loadAll();
  }

  // Route a plain-text toast to the correct on-screen stack by type:
  // error / warning -> BOTTOM-LEFT, success (default) -> BOTTOM-RIGHT.
  function toastStackFor(type){
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

  function toast(message, type = 'success'){
    const c = toastStackFor(type);
    if (!c) return alert(message);
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
    c.appendChild(n);
    setTimeout(dismiss, type === 'error' ? 6000 : 4000);
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadAll().catch(err => toast(err.message, 'error'));
    el('add-shelf-btn')?.addEventListener('click', () => openForm());
    el('details-modal-close')?.addEventListener('click', closeDetails);
    el('details-modal-backdrop')?.addEventListener('click', (e) => { if (e.target.id === 'details-modal-backdrop') closeDetails(); });
    el('form-modal-close')?.addEventListener('click', closeForm);
    el('form-cancel-btn')?.addEventListener('click', closeForm);
    el('form-modal-backdrop')?.addEventListener('click', (e) => { if (e.target.id === 'form-modal-backdrop') closeForm(); });
    el('form-save-btn')?.addEventListener('click', () => saveShelf().catch(err => toast(err.message, 'error')));
    el('shelves-grid')?.addEventListener('click', (e) => {
      const detail = e.target.closest('.shelf-details-btn');
      const edit = e.target.closest('.shelf-update-btn');
      if(detail) openDetails(detail.dataset.id);
      if(edit) openForm(edit.dataset.id);
    });
  });
})();
