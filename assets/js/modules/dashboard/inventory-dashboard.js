'use strict';
/*
 * Inventory-role dashboard.
 * Fetches a single summary endpoint and renders KPI cards plus Highcharts
 * analytics for four areas: Inventory, Shelves, Incoming Confirmation, and
 * Incident Reports.
 */
(function () {
  const CONFIG = window.CGMI_INVENTORY_DASHBOARD_API || {};
  const ENDPOINT = CONFIG.summary;
  let state = null;

  // ── Formatting helpers ────────────────────────────────────────────────────
  const peso = (n) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(Number(n || 0));
  const num = (n) => new Intl.NumberFormat('en-PH').format(Number(n || 0));
  const el = (id) => document.getElementById(id);
  const escapeHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
  const shortDate = (value) => {
    if (!value) return '—';
    const d = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  };

  // ── Highcharts shared theme (mirrors the admin dashboard) ──────────────────
  const theme = {
    colors: ['#4569AD', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0d9488', '#8EA2D7', '#B7C3E8'],
    chart: { backgroundColor: 'transparent', style: { fontFamily: 'Montserrat, sans-serif' } },
    title: { text: null }, subtitle: { text: null }, credits: { enabled: false },
    legend: { itemStyle: { fontWeight: '600', fontSize: '11px', color: '#1F3F74' } },
    xAxis: { labels: { style: { fontSize: '11px', color: '#5C6480' } }, lineColor: '#cdd7ef', tickColor: '#cdd7ef' },
    yAxis: { labels: { style: { fontSize: '11px', color: '#5C6480' } }, gridLineColor: '#eef2fb', title: { text: null } },
    tooltip: { style: { fontSize: '12px' }, borderRadius: 8, shadow: true },
    plotOptions: {
      series: { animation: { duration: 500 } },
      column: { borderRadius: 4, borderWidth: 0 },
      bar: { borderRadius: 4, borderWidth: 0 },
      pie: { borderWidth: 0, dataLabels: { style: { fontWeight: '600', fontSize: '11px' } } }
    }
  };
  const chart = (opts) => {
    const target = opts?.chart?.renderTo;
    if (!target || !el(target) || typeof Highcharts === 'undefined') return null;
    return Highcharts.chart(Highcharts.merge(theme, opts));
  };

  async function fetchJson(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.message || 'Request failed.');
    return data;
  }

  // ── KPI cards ──────────────────────────────────────────────────────────────
  function buildKpis() {
    const inv = state.inventory || {};
    const sh = state.shelves || {};
    const inc = state.incoming || {};
    const ir = state.incidents || {};
    return [
      { label: 'Active Items', value: num(inv.activeItems), sub: `${num(inv.totalItems)} total SKUs`, icon: 'icon-inventory', color: 'blue', badge: 'Live', btype: 'up' },
      { label: 'Total Stock Units', value: num(inv.totalStockUnits), sub: `WH ${num((inv.distribution || {}).warehouse)} · Store ${num((inv.distribution || {}).store)} · Shelf ${num((inv.distribution || {}).shelf)}`, icon: 'icon-inventory', color: 'purple', badge: 'Live', btype: 'up' },
      { label: 'Stock Value', value: peso(inv.stockValue), sub: 'Current inventory value', icon: 'icon-tag', color: 'teal', badge: 'Tracked', btype: 'up' },
      { label: 'Low Stock Items', value: num(inv.lowStock), sub: 'At/under reorder level', icon: 'icon-incident', color: 'orange', badge: inv.lowStock ? 'Warn' : 'OK', btype: inv.lowStock ? 'warn' : 'up' },
      { label: 'Out of Stock', value: num(inv.outOfStock), sub: 'Items unavailable', icon: 'icon-off', color: 'red', badge: inv.outOfStock ? 'Alert' : 'OK', btype: inv.outOfStock ? 'down' : 'up' },
      { label: 'Shelf Occupancy', value: `${Number(sh.occupancyRate || 0).toFixed(0)}%`, sub: `${num(sh.occupied)} of ${num(sh.totalShelves)} shelves`, icon: 'icon-shelves', color: 'blue', badge: 'Live', btype: 'up' },
      { label: 'Low-Stock Shelves', value: num(sh.lowStockShelves), sub: 'Below min threshold', icon: 'icon-shelves', color: 'orange', badge: sh.lowStockShelves ? 'Warn' : 'OK', btype: sh.lowStockShelves ? 'warn' : 'up' },
      { label: 'Weight Alerts', value: num(Number(sh.overCapacity || 0) + Number(sh.underMin || 0)), sub: `${num(sh.overCapacity)} over · ${num(sh.underMin)} under`, icon: 'icon-weight', color: 'red', badge: (Number(sh.overCapacity || 0) + Number(sh.underMin || 0)) ? 'Alert' : 'OK', btype: (Number(sh.overCapacity || 0) + Number(sh.underMin || 0)) ? 'down' : 'up' },
      { label: 'Pending Incoming', value: num(inc.pendingItems), sub: `${num(inc.confirmedItems)} confirmed`, icon: 'icon-check', color: 'green', badge: inc.pendingItems ? 'Open' : 'Clear', btype: inc.pendingItems ? 'down' : 'up' },
      { label: 'Deliveries to Check', value: num(inc.pending), sub: `${num(inc.totalDeliveries)} total deliveries`, icon: 'icon-check', color: 'purple', badge: inc.pending ? 'Open' : 'Clear', btype: inc.pending ? 'warn' : 'up' },
      { label: 'Open Incidents', value: num(ir.pending), sub: `${num(ir.totalReports)} reports total`, icon: 'icon-incident', color: 'red', badge: ir.pending ? 'Open' : 'Clear', btype: ir.pending ? 'down' : 'up' },
    ];
  }

  function renderCards() {
    const grid = el('metrics-grid');
    if (!grid) return;
    grid.innerHTML = buildKpis().map((k) => `
      <div class="metric-card ${k.color}">
        <div class="metric-top">
          <div class="metric-icon ${k.color} ui-icon ${k.icon}" aria-hidden="true"></div>
          <span class="metric-badge ${k.btype}">${escapeHtml(k.badge)}</span>
        </div>
        <div class="metric-value">${escapeHtml(k.value)}</div>
        <div class="metric-label">${escapeHtml(k.label)}</div>
        <div class="metric-sub">${escapeHtml(k.sub)}</div>
      </div>`).join('');
  }

  // ── Progress bars: stock status overview ───────────────────────────────────
  function renderStockStatusBars() {
    const wrap = el('stock-status-bars');
    if (!wrap) return;
    const inv = state.inventory || {};
    const low = Number(inv.lowStock || 0);
    const out = Number(inv.outOfStock || 0);
    const adequate = Math.max(Number(inv.activeItems || 0) - low - out, 0);
    const total = Math.max(adequate + low + out, 1);
    const bars = [
      { label: 'Adequate', n: adequate, color: 'green' },
      { label: 'Low Stock', n: low, color: 'orange' },
      { label: 'Out of Stock', n: out, color: 'red' },
    ];
    wrap.innerHTML = bars.map((b) => `
      <div class="prog-wrap">
        <div class="prog-label"><span>${b.label}</span><span>${num(b.n)} items</span></div>
        <div class="prog-track"><div class="prog-fill ${b.color}" style="width:${((b.n / total) * 100).toFixed(0)}%"></div></div>
      </div>`).join('');
  }

  // ── Refill / low-stock list ────────────────────────────────────────────────
  function renderRefillList() {
    const wrap = el('refill-list');
    if (!wrap) return;
    const items = (state.inventory?.lowStockItems || []).slice(0, 6);
    if (!items.length) { wrap.innerHTML = '<div class="empty-card">No refill alerts.</div>'; return; }
    wrap.innerHTML = items.map((item) => {
      const qty = Number(item.quantity || 0);
      const reorder = Number(item.reorder_level || 0);
      const urgency = qty === 0 ? 'critical' : qty <= Math.max(1, reorder / 2) ? 'high' : 'medium';
      return `
      <div class="refill-row">
        <span class="refill-icon ui-icon icon-inventory" aria-hidden="true"></span>
        <div>
          <div class="refill-name">${escapeHtml(item.item_name || 'Item')}</div>
          <div class="refill-stock">Stock: ${num(qty)} / Reorder: ${num(reorder)} ${escapeHtml(item.unit || '')}</div>
        </div>
        <span class="refill-urg ${urgency}">${urgency.charAt(0).toUpperCase() + urgency.slice(1)}</span>
      </div>`;
    }).join('');
  }

  // ── Low-stock shelves list ─────────────────────────────────────────────────
  function renderShelfList() {
    const wrap = el('shelf-alert-list');
    if (!wrap) return;
    const items = (state.shelves?.lowStockShelves || []).slice(0, 6);
    if (!items.length) { wrap.innerHTML = '<div class="empty-card">No low-stock shelves.</div>'; return; }
    wrap.innerHTML = items.map((s) => `
      <div class="refill-row">
        <span class="refill-icon ui-icon icon-shelves" aria-hidden="true"></span>
        <div>
          <div class="refill-name">${escapeHtml(s.shelf_name || 'Shelf')}${s.item_name ? ' · ' + escapeHtml(s.item_name) : ''}</div>
          <div class="refill-stock">${escapeHtml(s.shelf_location || '')} · Qty: ${num(s.shelf_quantity || 0)}</div>
        </div>
        <span class="refill-urg high">Low</span>
      </div>`).join('');
  }

  // ── Pending incoming list ──────────────────────────────────────────────────
  function renderPendingIncoming() {
    const tbody = el('pending-incoming-tbody');
    if (!tbody) return;
    const items = (state.incoming?.pendingList || []).slice(0, 8);
    if (!items.length) { tbody.innerHTML = '<tr><td colspan="4" class="empty-card">No pending incoming items.</td></tr>'; return; }
    const condClass = (c) => c === 'Accepted / Good' ? 'green' : c === 'Damaged' ? 'red' : 'orange';
    tbody.innerHTML = items.map((it) => `
      <tr>
        <td style="font-weight:600">${escapeHtml(it.item_name || 'Item')}</td>
        <td>${num(it.ingoing_quantity || 0)}</td>
        <td><span class="pill ${condClass(it.ingoing_item_condition)}">${escapeHtml(it.ingoing_item_condition || '')}</span></td>
        <td>${escapeHtml(it.supplier_name || '—')}</td>
      </tr>`).join('');
  }

  // ── Recent incidents list ──────────────────────────────────────────────────
  function renderIncidentList() {
    const wrap = el('incident-list');
    if (!wrap) return;
    const items = (state.incidents?.recent || []).slice(0, 6);
    if (!items.length) { wrap.innerHTML = '<div class="empty-card">No incident reports.</div>'; return; }
    const statusClass = (s) => s === 'approved' ? 'green' : s === 'rejected' ? 'red' : 'orange';
    wrap.innerHTML = items.map((r) => `
      <div class="incident-row">
        <span class="incident-icon ui-icon icon-incident" aria-hidden="true"></span>
        <div class="incident-main">
          <div class="incident-title">${escapeHtml(r.reportTitle || r.reportNo || 'Incident')}</div>
          <div class="incident-meta">${escapeHtml(r.report_category || '')} · ${shortDate(r.createdAt)}</div>
        </div>
        <span class="pill ${statusClass(r.reportStatus)}">${escapeHtml(r.reportStatus || 'pending')}</span>
      </div>`).join('');
  }

  // ── Charts ─────────────────────────────────────────────────────────────────
  function renderCharts() {
    const inv = state.inventory || {};
    const sh = state.shelves || {};
    const inc = state.incoming || {};
    const ir = state.incidents || {};

    // Stock units by category (column).
    const byCat = inv.byCategory || [];
    chart({
      chart: { type: 'column', renderTo: 'ch-stock-levels', height: 300 },
      xAxis: { categories: byCat.map((c) => c.category_name) },
      yAxis: { title: { text: 'Units' } },
      tooltip: { shared: true },
      series: [{ name: 'Stock Units', data: byCat.map((c) => Number(c.total_units || 0)), color: '#4569AD' }]
    });

    // Stock status donut.
    const low = Number(inv.lowStock || 0), out = Number(inv.outOfStock || 0);
    const adequate = Math.max(Number(inv.activeItems || 0) - low - out, 0);
    chart({
      chart: { type: 'pie', renderTo: 'ch-stock-pie', height: 240 },
      tooltip: { pointFormat: '<b>{point.y} items</b>' },
      series: [{
        name: 'Status', innerSize: '55%',
        data: [
          { name: 'Adequate', y: adequate, color: '#16a34a' },
          { name: 'Low Stock', y: low, color: '#d97706' },
          { name: 'Out of Stock', y: out, color: '#dc2626' },
        ],
        dataLabels: { format: '{point.name}: {point.y}' }
      }]
    });

    // Shelf status breakdown (pie).
    chart({
      chart: { type: 'pie', renderTo: 'ch-shelf-status', height: 240 },
      tooltip: { pointFormat: '<b>{point.y} shelves</b> ({point.percentage:.0f}%)' },
      series: [{
        name: 'Shelves',
        data: [
          { name: 'Occupied', y: Number(sh.occupied || 0), color: '#16a34a' },
          { name: 'Unused', y: Number(sh.unused || 0), color: '#8EA2D7' },
          { name: 'Maintenance', y: Number(sh.maintenance || 0), color: '#d97706' },
        ].filter((s) => s.y > 0).length ? [
          { name: 'Occupied', y: Number(sh.occupied || 0), color: '#16a34a' },
          { name: 'Unused', y: Number(sh.unused || 0), color: '#8EA2D7' },
          { name: 'Maintenance', y: Number(sh.maintenance || 0), color: '#d97706' },
        ] : [{ name: 'No shelves', y: 1, color: '#B7C3E8' }],
        dataLabels: { format: '{point.name}: {point.y}' }
      }]
    });

    // Shelves by location layer (bar).
    const byLoc = sh.byLocation || [];
    chart({
      chart: { type: 'bar', renderTo: 'ch-shelf-location', height: 240 },
      xAxis: { categories: byLoc.map((l) => l.shelf_location) },
      yAxis: { title: { text: 'Shelves' } },
      series: [{ name: 'Shelves', data: byLoc.map((l) => Number(l.total || 0)), color: '#7c3aed' }]
    });

    // Incoming item condition breakdown (column).
    chart({
      chart: { type: 'column', renderTo: 'ch-incoming-condition', height: 260 },
      xAxis: { categories: ['Accepted / Good', 'Damaged', 'Incomplete / Missing'] },
      yAxis: { title: { text: 'Items' } },
      series: [{
        name: 'Items', colorByPoint: true,
        data: [
          { y: Number(inc.goodItems || 0), color: '#16a34a' },
          { y: Number(inc.damagedItems || 0), color: '#dc2626' },
          { y: Number(inc.incompleteItems || 0), color: '#d97706' },
        ]
      }]
    });

    // Incoming confirmation status (pie).
    chart({
      chart: { type: 'pie', renderTo: 'ch-incoming-status', height: 260 },
      tooltip: { pointFormat: '<b>{point.y} items</b>' },
      series: [{
        name: 'Items',
        data: [
          { name: 'Pending', y: Number(inc.pendingItems || 0), color: '#d97706' },
          { name: 'Confirmed', y: Number(inc.confirmedItems || 0), color: '#16a34a' },
          { name: 'Rejected', y: Number(inc.rejectedItems || 0), color: '#dc2626' },
        ].some((s) => s.y > 0) ? [
          { name: 'Pending', y: Number(inc.pendingItems || 0), color: '#d97706' },
          { name: 'Confirmed', y: Number(inc.confirmedItems || 0), color: '#16a34a' },
          { name: 'Rejected', y: Number(inc.rejectedItems || 0), color: '#dc2626' },
        ] : [{ name: 'No items', y: 1, color: '#B7C3E8' }],
        dataLabels: { format: '{point.name}: {point.y}' }
      }]
    });

    // Incidents by status (column).
    chart({
      chart: { type: 'column', renderTo: 'ch-incident-status', height: 260 },
      xAxis: { categories: ['Pending', 'Approved', 'Rejected'] },
      yAxis: { title: { text: 'Reports' } },
      series: [{
        name: 'Reports', colorByPoint: true,
        data: [
          { y: Number(ir.pending || 0), color: '#d97706' },
          { y: Number(ir.approved || 0), color: '#16a34a' },
          { y: Number(ir.rejected || 0), color: '#dc2626' },
        ]
      }]
    });

    // Incidents by category (pie).
    const byCatInc = ir.byCategory || [];
    chart({
      chart: { type: 'pie', renderTo: 'ch-incident-category', height: 260 },
      tooltip: { pointFormat: '<b>{point.y} incident(s)</b> ({point.percentage:.1f}%)' },
      series: [{
        name: 'Incidents',
        data: byCatInc.length ? byCatInc.map((c) => ({ name: c.category || 'Uncategorized', y: Number(c.total || 0) })) : [{ name: 'No incidents', y: 1, color: '#B7C3E8' }],
        dataLabels: { format: '{point.name}: {point.y}' }
      }]
    });

    // ── Inventory: stock distribution by physical location (donut) ────────────
    const dist = inv.distribution || {};
    const distData = [
      { name: 'Warehouse', y: Number(dist.warehouse || 0), color: '#4569AD' },
      { name: 'Store', y: Number(dist.store || 0), color: '#0d9488' },
      { name: 'IoT Shelf', y: Number(dist.shelf || 0), color: '#7c3aed' },
    ];
    chart({
      chart: { type: 'pie', renderTo: 'ch-distribution', height: 260 },
      tooltip: { pointFormat: '<b>{point.y} units</b> ({point.percentage:.1f}%)' },
      series: [{
        name: 'Units', innerSize: '55%',
        data: distData.some((d) => d.y > 0) ? distData : [{ name: 'No stock', y: 1, color: '#B7C3E8' }],
        dataLabels: { format: '{point.name}: {point.y}' }
      }]
    });

    // ── Inventory: outgoing stock by purpose (column) ─────────────────────────
    const byPurpose = state.outgoingByPurpose || [];
    chart({
      chart: { type: 'column', renderTo: 'ch-outgoing-purpose', height: 260 },
      xAxis: { categories: byPurpose.length ? byPurpose.map((p) => p.purpose) : ['No outgoing'] },
      yAxis: { title: { text: 'Units' } },
      tooltip: { valueSuffix: ' units' },
      series: [{ name: 'Units Out', colorByPoint: true, data: byPurpose.length ? byPurpose.map((p) => Number(p.total_qty || 0)) : [0] }]
    });

    // ── Inventory: supplier delivery quality (stacked bar) ────────────────────
    const sq = state.supplierQuality || [];
    chart({
      chart: { type: 'bar', renderTo: 'ch-supplier-quality', height: 280 },
      xAxis: { categories: sq.length ? sq.map((s) => s.supplier_name) : ['No deliveries'] },
      yAxis: { title: { text: 'Items' }, min: 0 },
      plotOptions: { series: { stacking: 'normal' } },
      series: [
        { name: 'Good', data: sq.map((s) => Number(s.good_items || 0)), color: '#16a34a' },
        { name: 'Damaged', data: sq.map((s) => Number(s.damaged_items || 0)), color: '#dc2626' },
        { name: 'Incomplete', data: sq.map((s) => Number(s.incomplete_items || 0)), color: '#d97706' },
      ]
    });

    // ── Shelves: per-shelf weight utilization vs min/max band (column+line) ───
    const weights = sh.weights || [];
    chart({
      chart: { type: 'column', renderTo: 'ch-shelf-weight', height: 300 },
      xAxis: { categories: weights.length ? weights.map((w) => w.shelf_name) : ['No assigned shelves'] },
      yAxis: { title: { text: 'kg' }, min: 0 },
      tooltip: { shared: true, valueSuffix: ' kg' },
      series: [
        { name: 'Est. Load', type: 'column', data: weights.map((w) => Number(w.est_weight || 0)), color: '#7c3aed' },
        { name: 'Min', type: 'line', data: weights.map((w) => (w.min_weight === null ? null : Number(w.min_weight))), color: '#d97706', dashStyle: 'ShortDash', marker: { enabled: false } },
        { name: 'Max', type: 'line', data: weights.map((w) => (w.max_weight === null ? null : Number(w.max_weight))), color: '#dc2626', dashStyle: 'ShortDash', marker: { enabled: false } },
      ]
    });
  }

  // ── Shelf health bars: occupancy + threshold breaches ──────────────────────
  function renderShelfHealthBars() {
    const wrap = el('shelf-health-bars');
    if (!wrap) return;
    const sh = state.shelves || {};
    const total = Math.max(Number(sh.totalShelves || 0), 1);
    const rows = [
      { label: 'Occupied', n: Number(sh.occupied || 0), color: 'green' },
      { label: 'Low Stock', n: Number(sh.lowStockShelves || 0), color: 'orange' },
      { label: 'Over Capacity', n: Number(sh.overCapacity || 0), color: 'red' },
      { label: 'Under Min Weight', n: Number(sh.underMin || 0), color: 'orange' },
      { label: 'Maintenance', n: Number(sh.maintenance || 0), color: 'blue' },
    ];
    wrap.innerHTML = rows.map((b) => `
      <div class="prog-wrap">
        <div class="prog-label"><span>${b.label}</span><span>${num(b.n)} shelves</span></div>
        <div class="prog-track"><div class="prog-fill ${b.color}" style="width:${((b.n / total) * 100).toFixed(0)}%"></div></div>
      </div>`).join('');
  }

  // ── Report category tabs ─────────────────────────────────────────────────
  // Each button shows only its own report panel and hides the rest, with a
  // white indicator that slides to the active button (mirrors the admin design).
  function reflowCharts() {
    if (typeof Highcharts === 'undefined' || !Array.isArray(Highcharts.charts)) return;
    Highcharts.charts.forEach((c) => { if (c) { try { c.reflow(); } catch (e) {} } });
  }

  function bindReportNav() {
    const nav = el('report-nav');
    const slider = el('report-nav-slider');
    const buttons = Array.from(document.querySelectorAll('.report-nav-btn[data-target]'));
    if (!nav || !buttons.length) return;
    const panels = Array.from(document.querySelectorAll('.report-panel'));

    const moveSlider = (btn) => {
      if (!slider || !btn) return;
      slider.style.width = `${btn.offsetWidth}px`;
      slider.style.transform = `translateX(${btn.offsetLeft - nav.clientLeft}px)`;
      nav.classList.add('slider-ready');
    };

    let current = buttons.find((b) => b.classList.contains('active')) || buttons[0];

    const setActive = (id) => {
      const btn = buttons.find((b) => b.dataset.target === id);
      if (!btn) return;
      buttons.forEach((b) => b.classList.toggle('active', b === btn));
      panels.forEach((p) => p.classList.toggle('active', p.id === id));
      current = btn;
      moveSlider(btn);
      requestAnimationFrame(reflowCharts);
    };

    buttons.forEach((btn) => btn.addEventListener('click', () => setActive(btn.dataset.target)));

    setActive(current.dataset.target);
    requestAnimationFrame(() => moveSlider(current));
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => moveSlider(current));
    let resizeRaf = null;
    window.addEventListener('resize', () => {
      if (resizeRaf) cancelAnimationFrame(resizeRaf);
      resizeRaf = requestAnimationFrame(() => moveSlider(current));
    });
  }

  // ── Load + polling ──────────────────────────────────────────────────────────
  let refreshTimer = null;

  async function refreshDashboard() {
    if (!ENDPOINT) { showError('Dashboard endpoint is not configured.'); return; }
    try {
      state = await fetchJson(ENDPOINT);
      renderCards();
      renderStockStatusBars();
      renderRefillList();
      renderShelfList();
      renderShelfHealthBars();
      renderPendingIncoming();
      renderIncidentList();
      renderCharts();
      // Charts recreated during a refresh may sit in a hidden tab panel (0 size);
      // reflow so the currently visible panel's charts fill their containers.
      requestAnimationFrame(reflowCharts);
    } catch (err) {
      showError(err.message || 'Dashboard load failed.');
    }
  }

  function showError(message) {
    console.error('[Inventory Dashboard]', message);
    ['metrics-grid', 'refill-list', 'shelf-alert-list', 'incident-list', 'stock-status-bars'].forEach((id) => {
      const node = el(id);
      if (node) node.innerHTML = `<div class="empty-card">${escapeHtml(message)}</div>`;
    });
    const tbody = el('pending-incoming-tbody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="4" class="empty-card">${escapeHtml(message)}</td></tr>`;
  }

  function startPolling(intervalMs) {
    stopPolling();
    refreshDashboard();
    refreshTimer = setInterval(refreshDashboard, intervalMs || 30000);
  }
  function stopPolling() {
    if (refreshTimer) { clearInterval(refreshTimer); refreshTimer = null; }
  }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopPolling();
    else startPolling(30000);
  });

  document.addEventListener('DOMContentLoaded', () => {
    bindReportNav();
    startPolling(30000);
  });
})();
