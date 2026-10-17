/* ============================================================
   DATATABLE-INIT.JS — DataTables initialization
   Supports dual modes:
        Desktop (full columns): image, name, category, qty, price, status, actions
        Compact (≤680px):       image, name, actions (Details + Update)
   ============================================================ */

let dtInstance      = null;  // DataTables API instance
let isCompactMode   = false; // current layout mode

/* ─── Role helpers ───────────────────────────────────────────
   Permissions are enforced authoritatively on the server; these
   helpers only tailor the UI so users don't see actions they
   cannot perform.
        super-admin : may activate / deactivate items
        admin       : may edit items including quantities
        inventory   : may edit item info but not quantities
   ─────────────────────────────────────────────────────────── */
function currentUserRole() {
  return ((window.CGMI_USER || {}).role || '').toString().toLowerCase();
}
function canManageInventoryStatus() {
  return currentUserRole() === 'super-admin';
}
function canEditInventoryQuantity() {
  return ['super-admin', 'admin'].includes(currentUserRole());
}

/* ─── Breakpoints for layout switching ──────────────────── */
const COMPACT_BREAKPOINT = 680;   // ≤ 680px  → mobile compact (3 cols)
const TABLET_BREAKPOINT  = 1376;  // ≤ 1376px → tablet mode   (3 cols, same structure)

/* ─── Pagination defaults (shared by init + custom pagination bar) ─── */
const INVENTORY_PAGE_SIZE   = 10;
const INVENTORY_LENGTH_MENU = [10, 25, 50, 100];

/* ============================================================
   CUSTOM SEARCH FILTER
   Runs for every DataTables draw; respects status, category, and storage type
   ============================================================ */
$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
  if (settings.nTable.id !== 'inventory-table') return true;

  const state = FilterManager.getState();
  const row = settings.aoData?.[dataIndex]?._aData || {};

  // Search mode filtering (name or sku)
  if (state.search) {
    const term = state.search.toLowerCase();
    if (state.searchMode === 'sku') {
      if (!(row.sku || '').toLowerCase().includes(term)) return false;
    } else {
      if (!(row.name || '').toLowerCase().includes(term)) return false;
    }
  }

  if (state.statusFilter !== 'all' && row.status !== state.statusFilter) return false;
  if (state.category && row.category !== state.category) return false;
  if (state.subcategory && row.subcategory !== state.subcategory) return false;

  // Storage type filter
  if (state.shelfTracking) {
    const locations = row.storageLocations || [];
    if (state.shelfTracking === 'shelves' && !locations.includes('IoT Shelves')) return false;
    if (state.shelfTracking === 'warehouse' && !locations.includes('Warehouse')) return false;
    if (state.shelfTracking === 'store' && !locations.includes('Store')) return false;
  }

  return true;
});

/* ============================================================
   COLUMN DEFINITIONS
   ============================================================ */

/** Full (desktop) column set */
function getFullColumns() {
  return [
    {
      title: '', data: null, orderable: false, searchable: false, width: '56px',
      render: (data) => renderImageCell(data),
    },
    {
      title: 'Item Name', data: 'name',
      render: (data, type, row) => type === 'display'
        ? `<div class="item-name-cell">
             <span class="item-name">${escapeHtml(data)}</span>
             <span class="item-id">${escapeHtml(row.id)}</span>
           </div>`
        : data,
    },
    {
      title: 'Category', data: 'category',
      render: (data, type, row) => type === 'display'
        ? `<div class="category-cell">
             <span class="category-name">${escapeHtml(data)}</span>
             <span class="subcategory-name">${escapeHtml(row.subcategory)}</span>
           </div>`
        : data,
    },
    {
      title: 'Warehouse Total', data: 'warehouseQty', width: '110px',
      render: (data, type, row) => type === 'display' ? renderQtyTotalCell(data, row.unit) : data,
    },
    {
      title: 'Store Total', data: 'storeQty', width: '100px',
      render: (data, type, row) => type === 'display' ? renderQtyTotalCell(data, row.unit) : data,
    },
    {
      title: 'Shelves Total', data: 'iotShelfQty', width: '110px',
      render: (data, type, row) => type === 'display' ? renderQtyTotalCell(data, row.unit) : data,
    },
    {
      title: 'Overall Total', data: 'overallQty', width: '100px',
      render: (data, type, row) => type === 'display' ? renderQtyTotalCell(data, row.unit) : data,
    },
    {
      title: 'Weight Check', data: 'monitoringStatus', width: '145px', orderable: true,
      render: (data, type, row) => type === 'display' ? renderWeightCheckCell(row) : (data || ''),
    },
    { title: 'CatRaw',     data: 'category',      visible: false, searchable: true },
    { title: 'SubRaw',     data: 'subcategory',   visible: false, searchable: true },
    {
      title: 'Unit Price', data: 'unitPrice', width: '95px',
      render: (data, type) => type === 'display'
        ? `<span class="price-val">₱${formatPrice(data)}</span>`
        : data,
    },
    {
      title: 'Status', data: 'status', width: '84px', orderable: false,
      render: (data, type) => type === 'display'
        ? `<span class="badge ${data === 'Active' ? 'badge-active' : 'badge-inactive'}" data-status="${data}">${data}</span>`
        : data,
      createdCell: (td, cellData) => { td.dataset.status = cellData; },
    },
    {
      title: 'Actions', data: null, orderable: false, searchable: false, width: '155px',
      render: (data) => {
        const isActive = data.status === 'Active';
        // Only super-admin can activate/deactivate items.
        const toggleBtn = canManageInventoryStatus()
          ? (isActive
              ? `<button class="action-btn btn-deactivate" data-id="${data.id}"><span class="btn-inline-icon ui-icon icon-incident" aria-hidden="true"></span>Deactivate</button>`
              : `<button class="action-btn btn-reactivate" data-id="${data.id}"><span class="btn-inline-icon ui-icon icon-check" aria-hidden="true"></span>Reactivate</button>`)
          : '';
        // The Update button is only shown for active items. Inactive items
        // cannot be edited until they are reactivated.
        const updateBtn = isActive
          ? `<button class="action-btn btn-update" data-id="${data.id}"><span class="btn-inline-icon ui-icon icon-edit" aria-hidden="true"></span>Update</button>`
          : '';
        return `<div class="actions-cell">
           ${updateBtn}
           ${toggleBtn}
         </div>`;
      },
    },
  ];
}

/** Tablet column set (681px–1024px): Image, Name, Overall Qty, Actions */
function getTabletColumns() {
  return [
    {
      title: 'Image', data: null, orderable: false, searchable: false, width: '52px',
      render: (data) => renderImageCell(data),
    },
    {
      title: 'Name', data: 'name',
      render: (data, type, row) => type === 'display'
        ? `<div class="item-name-cell">
             <span class="item-name">${escapeHtml(data)}</span>
             <span class="item-id">${escapeHtml(row.id)}</span>
           </div>`
        : data,
    },
    {
      title: 'Overall', data: 'overallQty', width: '110px',
      render: (data, type, row) => type === 'display' ? renderQtyTotalCell(data, row.unit) : data,
    },
    { title: 'CatRaw',    data: 'category',      visible: false, searchable: true },
    { title: 'SubRaw',    data: 'subcategory',   visible: false, searchable: true },
    { title: 'Status',    data: 'status',        visible: false, searchable: false },
    {
      title: 'Actions', data: null, orderable: false, searchable: false, width: '155px',
      render: (data) => {
        const detailsBtn = `<button class="action-btn btn-details" data-id="${data.id}"><span class="btn-inline-icon ui-icon icon-info" aria-hidden="true"></span>Details</button>`;
        const updateBtn = data.status === 'Active'
          ? `<button class="action-btn btn-update" data-id="${data.id}"><span class="btn-inline-icon ui-icon icon-edit" aria-hidden="true"></span>Update</button>`
          : '';
        return `<div class="actions-cell actions-cell--compact">${updateBtn}${detailsBtn}</div>`;
      },
    },
  ];
}

/** Compact (mobile) column set */
function getCompactColumns() {
  return [
    {
      title: 'Image', data: null, orderable: false, searchable: false, width: '52px',
      render: (data) => renderImageCell(data),
    },
    {
      title: 'Name', data: 'name',
      render: (data, type, row) => type === 'display'
        ? `<div class="item-name-cell">
             <span class="item-name">${escapeHtml(data)}</span>
             <span class="item-id">${escapeHtml(row.id)}</span>
           </div>`
        : data,
    },
    {
      title: 'Overall', data: 'overallQty', width: '92px',
      render: (data, type, row) => type === 'display' ? renderQtyTotalCell(data, row.unit) : data,
    },
    { title: 'CatRaw',    data: 'category',      visible: false, searchable: true },
    { title: 'SubRaw',    data: 'subcategory',   visible: false, searchable: true },
    { title: 'Status',    data: 'status',        visible: false, searchable: false },
    {
      title: 'Actions', data: null, orderable: false, searchable: false, width: '140px',
      render: (data) => {
        const detailsBtn = `<button class="action-btn btn-details" data-id="${data.id}"><span class="btn-inline-icon ui-icon icon-info" aria-hidden="true"></span>Details</button>`;
        // The Edit button is only shown for active items. Inactive items
        // cannot be edited until they are reactivated.
        const updateBtn = data.status === 'Active'
          ? `<button class="action-btn btn-update" data-id="${data.id}"><span class="btn-inline-icon ui-icon icon-edit" aria-hidden="true"></span>Update</button>`
          : '';
        return `<div class="actions-cell actions-cell--compact">${updateBtn}${detailsBtn}</div>`;
      },
    },
  ];
}

/* ============================================================
   DESTROY HELPER  ← NEW
   Safely tears down any existing DataTable instance and wipes
   the generated thead/tbody so DT starts from a clean <table>.
   This prevents the "Cannot read properties of undefined (reading
   'sClass')" crash caused by stale column state in DataTable.settings
   after panel switching changes the DOM.
   ============================================================ */
function _destroyExistingTable() {
  /* 1. Destroy via stored reference */
  if (dtInstance !== null) {
    try { dtInstance.destroy(); } catch (e) { /* ignore if already gone */ }
    dtInstance = null;
  }
  /* 2. Belt-and-suspenders: catch any stale registration DT still holds */
  if ($.fn.DataTable.isDataTable('#inventory-table')) {
    try { $('#inventory-table').DataTable().destroy(); } catch (e) { /* ignore */ }
  }
  /* 3. Wipe generated markup — critical to reset column count */
  $('#inventory-table').empty();
}

/* ============================================================
   LAYOUT MODE HELPERS
   Three modes: 'full' (> 1024px), 'tablet' (681–1024px), 'compact' (≤ 680px)
   ============================================================ */
function _getLayoutMode() {
  const w = window.innerWidth;
  if (w <= COMPACT_BREAKPOINT) return 'compact';
  if (w <= TABLET_BREAKPOINT)  return 'tablet';
  return 'full';
}

function _getColumns(mode) {
  if (mode === 'compact') return getCompactColumns();
  if (mode === 'tablet')  return getTabletColumns();
  return getFullColumns();
}

/* ============================================================
   INIT DataTable
   ============================================================ */
function initDataTable() {
  const mode    = _getLayoutMode();
  isCompactMode = (mode !== 'full');

  /* ── FIX: always destroy before reinitialising ── */
  _destroyExistingTable();

  dtInstance = $('#inventory-table').DataTable({
    data:       [],
    autoWidth:  false,
    pageLength: INVENTORY_PAGE_SIZE,
    lengthMenu: INVENTORY_LENGTH_MENU,
    order:      [[1, 'asc']],
    /* Render only the table body; pagination is drawn by our custom bar
       so it matches the Outgoing deliveries layout. */
    dom:        '<"dt-container"t>',
    columns:    _getColumns(mode),
    language: {
      emptyTable:  '<div class="empty-state"><div class="empty-state-icon ui-icon icon-inventory" aria-hidden="true"></div><div class="empty-state-title">No items found</div><div class="empty-state-desc">Try adjusting filters or add a new item.</div></div>',
      zeroRecords: '<div class="empty-state"><div class="empty-state-icon ui-icon icon-search" aria-hidden="true"></div><div class="empty-state-title">No matching items</div><div class="empty-state-desc">Try a different search or filter.</div></div>',
    },
    drawCallback () { updateStats(); renderInventoryPagination(dtInstance); },
    createdRow   (row, data) {
      row.dataset.itemId = data.id;
      row.dataset.status = data.status;
    },
  });

  /* Watch resize → switch modes */
  _attachResizeWatcher();

  return dtInstance;
}

/* ============================================================
   RESPONSIVE RESIZE WATCHER
   Destroys & rebuilds the DataTable when crossing either breakpoint.
   Guard flag prevents duplicate listeners when initDataTable is
   called again on panel re-entry.
   ============================================================ */
let _resizeTimer             = null;
let _resizeWatcherAttached   = false;  // ← FIX: prevent stacking listeners
let _currentLayoutMode       = 'full'; // tracks active mode across resizes

function _attachResizeWatcher() {
  if (_resizeWatcherAttached) return;
  _resizeWatcherAttached = true;
  _currentLayoutMode     = _getLayoutMode();

  window.addEventListener('resize', () => {
    clearTimeout(_resizeTimer);
    _resizeTimer = setTimeout(() => {
      const newMode = _getLayoutMode();
      if (newMode !== _currentLayoutMode) {
        _switchTableMode(newMode);
      }
    }, 120);
  });
}

function _switchTableMode(mode) {
  if (!dtInstance) return;

  _currentLayoutMode = mode;
  isCompactMode      = (mode !== 'full');

  const currentData = InventoryManager.getAll();

  _destroyExistingTable();

  dtInstance = $('#inventory-table').DataTable({
    data:       currentData,
    autoWidth:  false,
    pageLength: INVENTORY_PAGE_SIZE,
    lengthMenu: INVENTORY_LENGTH_MENU,
    order:      [[1, 'asc']],
    dom:        '<"dt-container"t>',
    columns:    _getColumns(mode),
    language: {
      emptyTable:  '<div class="empty-state"><div class="empty-state-icon ui-icon icon-inventory" aria-hidden="true"></div><div class="empty-state-title">No items found</div><div class="empty-state-desc">Try adjusting filters or add a new item.</div></div>',
      zeroRecords: '<div class="empty-state"><div class="empty-state-icon ui-icon icon-search" aria-hidden="true"></div><div class="empty-state-title">No matching items</div><div class="empty-state-desc">Try a different search or filter.</div></div>',
    },
    drawCallback () { updateStats(); FilterManager.reattach(dtInstance); renderInventoryPagination(dtInstance); },
    createdRow   (row, data) {
      row.dataset.itemId = data.id;
      row.dataset.status = data.status;
    },
  });

  FilterManager.reattach(dtInstance);
  FilterManager.applyFilters();
}

/* ============================================================
   CUSTOM PAGINATION BAR
   Mirrors the Outgoing deliveries table layout: a left-aligned
   "Showing X–Y of Z" label and right-aligned controls (Rows
   selector, Prev, Page X / Y indicator, Next). Driven by the
   DataTables page API so sorting/filtering stay intact.
   ============================================================ */
function renderInventoryPagination(dt) {
  const controls = document.getElementById('inventory-pagination');
  if (!controls || !dt) return;

  const info = dt.page.info(); // { page, pages, start, end, recordsDisplay, ... }
  const total = info.recordsDisplay;

  if (!total) {
    controls.innerHTML = '';
    return;
  }

  const from = info.start + 1;
  const to = info.end;
  const currentPage = info.page + 1; // DataTables pages are 0-indexed
  const pageCount = Math.max(1, info.pages);
  const pageSize = dt.page.len();

  controls.innerHTML = `
    <div class="pagination-info">Showing ${from}–${to} of ${total}</div>
    <div class="pagination-controls">
      <label class="page-size-label">Rows:
        <select id="inventory-page-size">
          ${INVENTORY_LENGTH_MENU.map(n => `<option value="${n}" ${n === pageSize ? 'selected' : ''}>${n}</option>`).join('')}
        </select>
      </label>
      <button type="button" class="btn btn-ghost small-btn" id="inventory-prev-page" ${currentPage <= 1 ? 'disabled' : ''}>Prev</button>
      <span class="page-indicator">Page ${currentPage} / ${pageCount}</span>
      <button type="button" class="btn btn-ghost small-btn" id="inventory-next-page" ${currentPage >= pageCount ? 'disabled' : ''}>Next</button>
    </div>`;

  document.getElementById('inventory-page-size').addEventListener('change', (e) => {
    const size = parseInt(e.target.value, 10) || INVENTORY_PAGE_SIZE;
    dt.page.len(size).draw(false);
  });
  document.getElementById('inventory-prev-page').addEventListener('click', () => {
    dt.page('previous').draw(false);
  });
  document.getElementById('inventory-next-page').addEventListener('click', () => {
    dt.page('next').draw(false);
  });
}

/* ============================================================
   HELPERS
   ============================================================ */
function renderQtyTotalCell(data, unit) {
  const qty = Number.isFinite(Number(data)) ? Number(data) : 0;
  return `<div class="qty-cell">
    <span class="qty-val ${qty === 0 ? 'qty-out' : qty <= 10 ? 'qty-low' : ''}">${qty}</span>
    <span class="qty-unit">${escapeHtml(unit || '')}</span>
  </div>`;
}

function renderWeightCheckCell(row) {
  const status = row.monitoringStatus || 'no_stock';
  const label = row.monitoringLabel || 'No Stock';
  const description = row.monitoringDescription || '';

  if (status === 'monitored') {
    const shelfInfo = row.shelfName ? `Shelf: ${escapeHtml(row.shelfName)}` : '';
    return `<div class="weight-check-cell weight-check-match" title="${escapeHtml(description + (shelfInfo ? ' | ' + shelfInfo : ''))}">
      <span class="ui-icon icon-check" aria-hidden="true"></span>
      <span class="weight-check-text">
        <strong>${escapeHtml(label)}</strong>
        <small>Warehouse: ${formatNumber(row.warehouseQty)}</small>
        <small>Store: ${formatNumber(row.storeQty)}</small>
        <small>IoT Shelves: ${formatNumber(row.iotShelfQty)}</small>
      </span>
    </div>`;
  }

  if (status === 'partial') {
    return `<div class="weight-check-cell weight-check-partial" title="${escapeHtml(description)}">
      <span class="ui-icon icon-inventory" aria-hidden="true"></span>
      <span class="weight-check-text">
        <strong>${escapeHtml(label)}</strong>
        <small>Warehouse: ${formatNumber(row.warehouseQty)}</small>
        <small>Store: ${formatNumber(row.storeQty)}</small>
        <small>IoT Shelves: ${formatNumber(row.iotShelfQty)}</small>
      </span>
    </div>`;
  }

  return `<div class="weight-check-cell weight-check-na" title="${escapeHtml(description)}">
    <span class="ui-icon icon-incident" aria-hidden="true"></span>
    <span class="weight-check-text">
      <strong>${escapeHtml(label)}</strong>
      <small>No stock in any location</small>
    </span>
  </div>`;
}

function renderImageCell(data) {
  if (data.image) {
    return `<div class="item-img-wrap"><img src="${data.image}" alt="${escapeHtml(data.name)}" loading="lazy"></div>`;
  }
  return `<div class="item-img-placeholder"><span class="ui-icon icon-box" aria-hidden="true"></span></div>`;
}

function getDataTableInstance() { return dtInstance; }

function reloadTableData(items) {
  if (!dtInstance) return;
  dtInstance.clear();
  dtInstance.rows.add(items);
  dtInstance.draw();
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function formatPrice(val) {
  return Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function formatNumber(val) {
  const num = Number(val);
  if (!Number.isFinite(num)) return '0';
  return num.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
