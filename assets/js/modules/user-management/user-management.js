(function(){
  const api = window.CGMI_USERS_API || {};
  const context = window.CGMI_USERS_CONTEXT || {};
  const manageableRoles = (context.manageableRoles || ['inventory', 'checker']).map(r => String(r).toLowerCase());
  // canEdit: super-admins may edit user info; plain admins may only activate/deactivate.
  const canEdit = context.canEdit !== false ? true : !!context.canEdit;
  let users = [];
  let editingId = null;
  let currentFilter = 'active';
  const isActiveStatus = (u) => String(u.status).toLowerCase() === 'active';
  // An admin may only manage a subset of roles (Checker, Inventory Manager).
  // Buttons for accounts outside that subset are hidden; the backend enforces this too.
  const canManage = (u) => manageableRoles.includes(String(roleMap[u.role] || u.role).toLowerCase());

  const el = (id) => document.getElementById(id);
  const roleMap = { 'Admin': 'admin', 'Super Admin': 'super-admin', 'Inventory Manager': 'inventory', 'Cashier': 'cashier', 'Checker': 'checker', admin: 'admin', 'super-admin': 'super-admin', 'super_admin': 'super-admin', inventory: 'inventory', cashier: 'cashier', checker: 'checker' };
  const roleLabel = (role) => ({ admin:'Admin', 'super-admin':'Super Admin', inventory:'Inventory Manager', cashier:'Cashier', checker:'Checker' }[String(role).toLowerCase()] || role || '—');
  const escapeHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (m) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));

  async function fetchUsers() {
    const response = await fetch(api.list, { credentials: 'same-origin' });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load users.');
    users = data.users || [];
    render();
  }
  function render() {
    const grid = el('user-cards-grid');
    const empty = el('empty-state');
    const activeCount = users.filter(isActiveStatus).length;
    const inactiveCount = users.length - activeCount;
    el('stat-total').textContent = users.length;
    el('stat-active').textContent = activeCount;
    el('stat-deactivated').textContent = inactiveCount;

    // Filter by the selected status tab, then sort so active accounts group first.
    let visible = users.slice();
    if (currentFilter === 'active') visible = visible.filter(isActiveStatus);
    else if (currentFilter === 'inactive') visible = visible.filter(u => !isActiveStatus(u));
    visible.sort((a, b) => {
      const byStatus = (isActiveStatus(a) ? 0 : 1) - (isActiveStatus(b) ? 0 : 1);
      if (byStatus !== 0) return byStatus;
      return String(a.fullName).localeCompare(String(b.fullName));
    });

    el('user-count-badge').textContent = `${visible.length} user${visible.length === 1 ? '' : 's'}`;
    if (!visible.length) { grid.innerHTML = ''; empty.style.display = 'block'; return; }
    empty.style.display = 'none';
    grid.innerHTML = visible.map((user) => `
      <article class="user-card-item" data-id="${user.id}">
        <div class="user-card-head"><div class="user-avatar-lg">${initials(user.fullName || user.username)}</div><div><div class="user-card-name">${escapeHtml(user.fullName)}</div><div class="user-card-username">@${escapeHtml(user.username)}</div></div></div>
        <div class="user-card-meta">
          <span class="badge ${String(user.status).toLowerCase() === 'active' ? 'badge-active' : 'badge-inactive'}">${String(user.status).toLowerCase() === 'active' ? 'Active' : 'Deactivated'}</span>
          <span class="badge">${escapeHtml(roleLabel(user.role))}</span>
        </div>
        <div class="user-card-details">
          <div><strong>Last login:</strong> ${escapeHtml(user.lastLoginAt || '—')}</div>
          <div><strong>Failed attempts:</strong> ${escapeHtml(user.failedAttempts ?? 0)}</div>
        </div>
        <div class="user-card-actions">
          ${canManage(user) ? `
          ${canEdit ? `<button class="btn btn-secondary btn-sm action-edit" data-id="${user.id}"><span class="btn-inline-icon ui-icon icon-edit" aria-hidden="true"></span>Update</button>` : ''}
          <button class="btn ${String(user.status).toLowerCase() === 'active' ? 'btn-danger' : 'btn-primary'} btn-sm action-toggle" data-id="${user.id}" data-action="${String(user.status).toLowerCase() === 'active' ? 'deactivate' : 'activate'}">${String(user.status).toLowerCase() === 'active' ? '<span class="btn-inline-icon ui-icon icon-incident" aria-hidden="true"></span>Deactivate' : 'Activate'}</button>
          ` : `<span class="user-card-locked" title="You do not have permission to manage this account">Restricted</span>`}
        </div>
      </article>`).join('');
  }
  function initials(name){ return String(name||'U').split(/\s+/).slice(0,2).map(p=>p[0]||'').join('').toUpperCase() || 'U'; }
  function openModal(user=null){
    editingId = user ? Number(user.id) : null;
    el('modal-icon').className = 'modal-icon ui-icon ' + (user ? 'icon-edit' : 'icon-users');
    el('modal-icon').textContent = '';
    el('modal-icon').setAttribute('aria-hidden','true');
    el('modal-title-text').textContent = user ? 'Edit User' : 'Add User';
    el('modal-subtitle').textContent = user ? 'Update the selected user account.' : 'Fill in the details to create a new account.';
    el('modal-save-btn').textContent = user ? 'Save Changes' : 'Save Account';
    clearErrors();
    el('input-firstname').value = user ? (user.firstName || (user.fullName || '').split(' ').slice(0,-1).join(' ')) : '';
    el('input-lastname').value = user ? (user.lastName || (user.fullName || '').split(' ').slice(-1).join(' ')) : '';
    el('input-username').value = user?.username || '';
    el('input-password').value = '';
    // Pre-select the user's current role. user.role may arrive as a label
    // ("Super Admin") or a db key ("super_admin"); normalize via roleMap→roleLabel
    // so it always matches one of the dropdown option values, then allow changing it.
    el('input-role').value = user ? roleLabel(roleMap[user.role] || user.role) : '';
    el('user-modal').classList.add('active');
  }
  function closeModal(){ el('user-modal').classList.remove('active'); editingId = null; }
  function clearErrors(){ ['firstname','lastname','username','password','role'].forEach(k => { const err=el(`err-${k}`); if (err) err.textContent=''; }); document.querySelectorAll('.form-control.error').forEach(n => n.classList.remove('error')); }
  function setError(field,msg){ const input=el(`input-${field}`); const err=el(`err-${field}`); input?.classList.add('error'); if(err) err.textContent=msg; }
  function getPayload(){
    const firstname = el('input-firstname').value.trim();
    const lastname = el('input-lastname').value.trim();
    const username = el('input-username').value.trim();
    const password = el('input-password').value;
    const roleValue = roleMap[el('input-role').value] || '';
    clearErrors();
    let valid = true;
    if (!firstname) { setError('firstname','First name is required.'); valid = false; }
    if (!lastname) { setError('lastname','Last name is required.'); valid = false; }
    if (!username) { setError('username','Username is required.'); valid = false; }
    if (!editingId && !password) { setError('password','Password is required.'); valid = false; }
    if (!roleValue) { setError('role','Role is required.'); valid = false; }
    if (!valid) return null;
    return { id: editingId, firstName: firstname, lastName: lastname, username, password, role: roleValue };
  }
  async function saveUser(){
    const payload = getPayload(); if (!payload) return;
    const response = await fetch(api.save, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify(payload) });
    const data = await response.json();
    if (response.status === 422 && data.errors) {
      Object.entries(data.errors).forEach(([key,val]) => {
        if (key === 'full_name' || key === 'first_name' || key === 'last_name') return;
        if (['firstname','lastname','username','password','role'].includes(key)) setError(key, val);
      });
      throw new Error(data.message || 'Please correct the highlighted fields.');
    }
    if (!response.ok || !data.success) throw new Error(data.message || 'Unable to save user.');
    upsertUser(data.user); closeModal(); toast(data.message || 'Saved successfully.');
  }
  async function toggleUser(id, action){
    const response = await fetch(api.toggle, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify({ id:Number(id), action }) });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || 'Unable to update account status.');
    upsertUser(data.user); toast(data.message || 'Account updated.');
  }
  function upsertUser(user){ const i = users.findIndex(u => Number(u.id) === Number(user.id)); if(i >= 0) users[i] = user; else users.push(user); users.sort((a,b)=> String(a.fullName).localeCompare(String(b.fullName))); render(); }
  // Route a plain-text toast to the correct on-screen stack by type:
  // error / warning -> BOTTOM-LEFT, success (default) -> BOTTOM-RIGHT.
  function toastStackFor(type){ const side = /^(error|warning|reject|rejected|danger|fail)/i.test(String(type || '')) ? 'left' : 'right'; const id = 'toast-stack-' + side; let stack = document.getElementById(id); if (!stack) { stack = document.createElement('div'); stack.id = id; stack.className = 'toast-stack toast-stack-' + side; stack.setAttribute('aria-live', side === 'left' ? 'assertive' : 'polite'); document.body.appendChild(stack); } return stack; }
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
    n.innerHTML = `<div class="toast-icon" style="color:${meta.color}" aria-hidden="true">${meta.icon}</div><div class="toast-body"><div class="toast-title">${meta.title}</div><div class="toast-msg">${message.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div></div>`;
    const dismiss = () => { n.classList.add('hide'); n.addEventListener('animationend', () => n.remove(), { once: true }); };
    n.addEventListener('click', dismiss);
    c.appendChild(n);
    setTimeout(dismiss, type === 'error' ? 6000 : 4000);
  }

  let confirmCallback = null;
  function openConfirm({ title, message, confirmText = 'Confirm', danger = false }, onConfirm){
    confirmCallback = onConfirm;
    const overlay = el('confirm-overlay');
    if (!overlay) { if (window.confirm(message)) onConfirm(); return; }
    if (el('confirm-title')) el('confirm-title').textContent = title || 'Are you sure?';
    if (el('confirm-msg')) el('confirm-msg').textContent = message || '';
    const okBtn = el('confirm-ok-btn');
    if (okBtn) {
      okBtn.textContent = confirmText;
      okBtn.classList.toggle('btn-danger', !!danger);
      okBtn.classList.toggle('btn-primary', !danger);
    }
    overlay.classList.add('active');
  }
  function closeConfirm(){ const overlay = el('confirm-overlay'); if (overlay) overlay.classList.remove('active'); confirmCallback = null; }

  document.addEventListener('DOMContentLoaded', () => {
    fetchUsers().catch(err => toast(err.message, 'error'));
    el('add-user-btn')?.addEventListener('click', () => openModal());
    el('modal-close-btn')?.addEventListener('click', closeModal);
    el('modal-cancel-btn')?.addEventListener('click', closeModal);
    el('user-modal')?.addEventListener('click', (e) => { if (e.target.id === 'user-modal') closeModal(); });
    el('modal-save-btn')?.addEventListener('click', () => saveUser().catch(err => toast(err.message, 'error')));
    el('pw-toggle')?.addEventListener('click', () => { const input = el('input-password'); input.type = input.type === 'password' ? 'text' : 'password'; });
    el('user-cards-grid')?.addEventListener('click', (e) => {
      const editBtn = e.target.closest('.action-edit');
      const toggleBtn = e.target.closest('.action-toggle');
      if (editBtn) { const user = users.find(u => Number(u.id) === Number(editBtn.dataset.id)); if (user) openModal(user); }
      if (toggleBtn) {
        const id = toggleBtn.dataset.id;
        const action = toggleBtn.dataset.action;
        const user = users.find(u => Number(u.id) === Number(id));
        const name = user ? (user.fullName || user.username) : 'this account';
        const deactivating = action === 'deactivate';
        openConfirm({
          title: deactivating ? 'Deactivate Account?' : 'Activate Account?',
          message: deactivating
            ? `Are you sure you want to deactivate ${name}? They will no longer be able to log in.`
            : `Are you sure you want to activate ${name}? They will regain access to the system.`,
          confirmText: deactivating ? 'Deactivate' : 'Activate',
          danger: deactivating
        }, () => {
          closeConfirm();
          toggleUser(id, action).catch(err => toast(err.message, 'error'));
        });
      }
    });

    document.querySelectorAll('.status-tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        currentFilter = tab.dataset.status || 'all';
        document.querySelectorAll('.status-tab').forEach((t) => {
          const isSel = t === tab;
          t.classList.toggle('active', isSel);
          t.setAttribute('aria-selected', isSel ? 'true' : 'false');
        });
        render();
      });
    });

    el('confirm-cancel-btn')?.addEventListener('click', closeConfirm);
    el('confirm-overlay')?.addEventListener('click', (e) => { if (e.target.id === 'confirm-overlay') closeConfirm(); });
    el('confirm-ok-btn')?.addEventListener('click', () => { const cb = confirmCallback; if (typeof cb === 'function') cb(); });
  });
})();
