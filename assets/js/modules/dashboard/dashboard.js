'use strict';
(function(){
  const ENDPOINTS = window.CGMI_DASHBOARD_ENDPOINTS || {};
  const state = { dashboard:null, inventory:null, incidents:null, users:null, shelves:null };

  const peso = (n) => new Intl.NumberFormat('en-PH', { style:'currency', currency:'PHP', maximumFractionDigits:2 }).format(Number(n||0));
  const num = (n) => new Intl.NumberFormat('en-PH').format(Number(n||0));
  const shortDate = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleString('en-PH', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit' });
  };
  const initials = (name='U') => String(name).trim().split(/\s+/).slice(0,2).map(v => v[0]||'').join('').toUpperCase() || 'U';
  const el = (id) => document.getElementById(id);
  const escapeHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

  const theme = {
    colors:['#4569AD','#16a34a','#d97706','#dc2626','#7c3aed','#0d9488','#8EA2D7','#B7C3E8'],
    chart:{ backgroundColor:'transparent', style:{ fontFamily:"Montserrat, sans-serif" } },
    title:{ text:null }, subtitle:{ text:null }, credits:{ enabled:false },
    legend:{ itemStyle:{ fontWeight:'600', fontSize:'11px', color:'#1F3F74' } },
    xAxis:{ labels:{ style:{ fontSize:'11px', color:'#5C6480' } }, lineColor:'#cdd7ef', tickColor:'#cdd7ef' },
    yAxis:{ labels:{ style:{ fontSize:'11px', color:'#5C6480' } }, gridLineColor:'#eef2fb', title:{ text:null } },
    tooltip:{ style:{ fontSize:'12px' }, borderRadius:8, shadow:true },
    plotOptions:{
      series:{ animation:{ duration:500 } },
      column:{ borderRadius:4, borderWidth:0 },
      bar:{ borderRadius:4, borderWidth:0 },
      pie:{ borderWidth:0, dataLabels:{ style:{ fontWeight:'600', fontSize:'11px' } } }
    }
  };
  const chart = (opts) => { const target = opts?.chart?.renderTo; if (target && !el(target)) return null; return Highcharts.chart(Highcharts.merge(theme, opts)); };

  function setEmpty(containerId, message){
    const node = el(containerId);
    if (node) node.innerHTML = `<div class="empty-card">${escapeHtml(message)}</div>`;
  }

  async function fetchJson(url){
    const res = await fetch(url, { credentials:'same-origin' });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.message || 'Request failed.');
    return data;
  }

  function buildKpis(){
    const dashboard = state.dashboard?.summary || {};
    const inventory = state.inventory?.summary || {};
    const shelfSummary = state.inventory?.shelfSummary || {};
    const dist = state.inventory?.distribution || {};
    const incidents = state.incidents?.summary || {};

    const net = Number(inventory.netStockChange || 0);
    const weightAlerts = Number(shelfSummary.overCapacity || 0) + Number(shelfSummary.underMin || 0);

    return [
      { label:'Total Stock Units', value:num(dashboard.totalStockUnits || inventory.totalUnits), sub:`WH ${num(dist.warehouse)} · Store ${num(dist.store)} · Shelf ${num(dist.shelf)}`, icon:'icon-inventory', color:'blue', badge:'Live', btype:'up' },
      { label:'Stock Value', value:peso(inventory.stockValue), sub:'Current inventory value', icon:'icon-tag', color:'teal', badge:'Tracked', btype:'up' },
      { label:'Low Stock Items', value:num(inventory.lowStockCount), sub:'Needs reorder', icon:'icon-incident', color:'orange', badge:inventory.lowStockCount ? 'Warn' : 'OK', btype:inventory.lowStockCount ? 'warn' : 'up' },
      { label:'Out of Stock', value:num(inventory.outOfStockCount), sub:'Items unavailable', icon:'icon-off', color:'red', badge:inventory.outOfStockCount ? 'Alert' : 'OK', btype:inventory.outOfStockCount ? 'down' : 'up' },
      { label:'Net Stock Change', value:`${net > 0 ? '+' : ''}${num(net)}`, sub:'In − out (range)', icon:'icon-reports', color:'purple', badge:net >= 0 ? 'Inflow' : 'Outflow', btype:net >= 0 ? 'up' : 'down' },
      { label:'Shelf Occupancy', value:`${Number(shelfSummary.occupancyRate || 0).toFixed(0)}%`, sub:`${num(shelfSummary.occupied)} of ${num(shelfSummary.totalShelves)} shelves`, icon:'icon-shelves', color:'blue', badge:'IoT', btype:'up' },
      { label:'Shelf Weight Alerts', value:num(weightAlerts), sub:`${num(shelfSummary.overCapacity)} over · ${num(shelfSummary.underMin)} under`, icon:'icon-weight', color:'red', badge:weightAlerts ? 'Alert' : 'OK', btype:weightAlerts ? 'down' : 'up' },
      { label:'Low-Stock Shelves', value:num(shelfSummary.lowStockShelves), sub:'Below min threshold', icon:'icon-shelves', color:'orange', badge:shelfSummary.lowStockShelves ? 'Warn' : 'OK', btype:shelfSummary.lowStockShelves ? 'warn' : 'up' },
      { label:'Active Alerts', value:num(incidents.pending), sub:`${num(incidents.approved)} resolved`, icon:'icon-incident', color:'red', badge:incidents.pending ? 'Open' : 'Clear', btype:incidents.pending ? 'down' : 'up' },
    ];
  }

  function renderCards(){
    const grid = el('metrics-grid');
    if (!grid) return;
    grid.innerHTML = buildKpis().map(k => `
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

  function renderStockStatusBars(){
    const wrap = el('stock-status-bars');
    if (!wrap) return;
    const summary = state.inventory?.summary || {};
    const low = Number(summary.lowStockCount || 0);
    const out = Number(summary.outOfStockCount || 0);
    const adequate = Math.max(Number(summary.itemCount || 0) - low - out, 0);
    const total = Math.max(adequate + low + out, 1);
    const bars = [
      { label:'Adequate', n:adequate, color:'green' },
      { label:'Low Stock', n:low, color:'orange' },
      { label:'Out of Stock', n:out, color:'red' },
    ];
    wrap.innerHTML = bars.map(b => `
      <div class="prog-wrap">
        <div class="prog-label"><span>${b.label}</span><span>${num(b.n)} items</span></div>
        <div class="prog-track"><div class="prog-fill ${b.color}" style="width:${((b.n/total)*100).toFixed(0)}%"></div></div>
      </div>`).join('');
  }

  function renderRefillList(){
    const wrap = el('refill-list');
    if (!wrap) return;
    const items = (state.inventory?.lowStockItems || []).slice(0,5);
    if (!items.length) { wrap.innerHTML = '<div class="empty-card">No refill alerts.</div>'; return; }
    wrap.innerHTML = items.map(item => {
      const qty = Number(item.quantity || 0);
      const reorder = Number(item.reorder_level || 0);
      const urgency = qty === 0 ? 'critical' : qty <= Math.max(1, reorder / 2) ? 'high' : 'medium';
      const icons = { critical:'icon-incident', high:'icon-incident', medium:'icon-inventory' };
      return `
      <div class="refill-row">
        <span class="refill-icon ui-icon ${icons[urgency]}" aria-hidden="true"></span>
        <div>
          <div class="refill-name">${escapeHtml(item.item_name || 'Item')}</div>
          <div class="refill-stock">Stock: ${num(qty)} / Reorder: ${num(reorder)} ${escapeHtml(item.unit || '')}</div>
        </div>
        <span class="refill-urg ${urgency}">${urgency.charAt(0).toUpperCase()+urgency.slice(1)}</span>
      </div>`;
    }).join('');
  }

  function normalizeShelfStatus(s){
    const status = String(s.status || '').toLowerCase();
    if (status.includes('occupied')) return 'full';
    if (status.includes('alert')) return 'partial';
    if (status.includes('unused')) return 'empty';
    if (Number(s.quantity || 0) <= 0) return 'empty';
    return Number(s.quantity || 0) <= 10 ? 'partial' : 'full';
  }

  function renderShelfGrid(){
    const grid = el('shelf-grid');
    if (!grid) return;
    const shelves = state.shelves?.items || [];
    if (!shelves.length) { grid.innerHTML = '<div class="empty-card">No shelves found.</div>'; return; }
    const label = { full:'Occupied', partial:'Alert', empty:'Empty' };
    grid.innerHTML = shelves.slice(0,6).map(s => {
      const cls = normalizeShelfStatus(s);
      return `
      <div class="shelf-cell ${cls}">
        <div class="shelf-id">${escapeHtml(s.code || s.name || 'Shelf')}</div>
        <div class="shelf-wt">${Number(s.estimatedWeight || 0).toFixed(1)} kg</div>
        <div class="shelf-st">${label[cls]}</div>
      </div>`;
    }).join('');
  }

  function renderIncidentList(){
    const wrap = el('incident-list');
    if (!wrap) return;
    const items = (state.incidents?.items || []).slice(0,6);
    if (!items.length) { wrap.innerHTML = '<div class="empty-card">No incidents logged.</div>'; return; }
    const dotMap = { Pending:'orange', Approved:'green', Rejected:'red' };
    const pillMap = { Pending:'orange', Approved:'green', Rejected:'red' };
    wrap.innerHTML = items.map(inc => `
      <div class="inc-item">
        <div class="inc-dot ${dotMap[inc.status] || 'blue'}"></div>
        <div class="inc-body">
          <div class="inc-title">${escapeHtml(inc.title || inc.refId || 'Incident')}</div>
          <div class="inc-meta">${escapeHtml(shortDate(inc.date))} · ${escapeHtml(inc.reportedBy || 'System')}</div>
        </div>
        <span class="pill ${pillMap[inc.status] || 'blue'}">${escapeHtml(inc.status || 'Pending')}</span>
      </div>`).join('');
  }

  function renderActivityList(){
    const wrap = el('act-list');
    if (!wrap) return;
    const incActs = (state.incidents?.items || []).slice(0,5).map(r => ({
      name: r.reportedBy || 'Reporter',
      action: `Filed incident ${r.refId || ''} — ${r.status || 'Pending'}`,
      time: shortDate(r.date),
      sort: new Date(r.date).getTime() || 0,
    }));
    const all = [...incActs].sort((a,b) => b.sort - a.sort).slice(0,6);
    if (!all.length) { wrap.innerHTML = '<div class="empty-card">No recent activity.</div>'; return; }
    wrap.innerHTML = all.map(a => `
      <div class="act-row">
        <div class="act-av">${escapeHtml(initials(a.name))}</div>
        <div>
          <div class="act-user">${escapeHtml(a.name)}</div>
          <div class="act-action">${escapeHtml(a.action)}</div>
        </div>
        <div class="act-time">${escapeHtml(a.time)}</div>
      </div>`).join('');
  }

  function renderCharts(){
    const byCategory = (state.inventory?.byCategory || []).slice(0,8);
    const lowByCategory = {};
    (state.inventory?.lowStockItems || []).forEach(item => {
      const key = item.category_name || 'Uncategorized';
      lowByCategory[key] = (lowByCategory[key] || 0) + 1;
    });

    const summary = state.inventory?.summary || {};
    const stockPie = [
      { name:'Adequate', y: Math.max(Number(summary.itemCount || 0) - Number(summary.lowStockCount || 0) - Number(summary.outOfStockCount || 0), 0), color:'#16a34a' },
      { name:'Low Stock', y: Number(summary.lowStockCount || 0), color:'#d97706' },
      { name:'Out of Stock', y: Number(summary.outOfStockCount || 0), color:'#dc2626' },
    ];

    const shelves = (state.shelves?.items || []).slice(0,8);
    const alertTypes = [
      { name:'Low Stock', y:Number(summary.lowStockCount || 0) },
      { name:'Out of Stock', y:Number(summary.outOfStockCount || 0) },
      { name:'Pending Incidents', y:Number(state.incidents?.summary?.pending || 0) },
      { name:'Rejected Incidents', y:Number(state.incidents?.summary?.rejected || 0) },
    ];

    const last7 = Array.from({length:7}, (_,i) => {
      const d = new Date(); d.setHours(0,0,0,0); d.setDate(d.getDate() - (6-i));
      return d;
    });
    const resolvedMap = new Map(last7.map(d => [d.toISOString().slice(0,10), { resolved:0, unresolved:0 }]));
    (state.incidents?.items || []).forEach(item => {
      const key = new Date(item.date || '').toISOString?.().slice(0,10);
      if (!key || !resolvedMap.has(key)) return;
      if (item.status === 'Approved') resolvedMap.get(key).resolved += 1;
      else resolvedMap.get(key).unresolved += 1;
    });
    const resolvedCats = Array.from(resolvedMap.keys()).map(key => new Date(`${key}T00:00:00`).toLocaleDateString('en-PH', { weekday:'short' }));
    const resolvedSeries = Array.from(resolvedMap.values());

    const roles = {};
    (state.users?.users || []).forEach(u => {
      roles[u.role || 'Unknown'] = (roles[u.role || 'Unknown'] || 0) + 1;
    });
    const roleEntries = Object.entries(roles);

    chart({ chart:{ type:'column', renderTo:'ch-stock-levels', height:300 }, xAxis:{ categories: byCategory.map(i => i.category_name) }, yAxis:{ title:{ text:'Units' } }, tooltip:{ shared:true }, series:[{ name:'Current Stock', data: byCategory.map(i => Number(i.total_units || 0)), color:'#4569AD' }] });
    chart({ chart:{ type:'column', renderTo:'ch-movers', height:260 }, xAxis:{ categories: byCategory.map(i => i.category_name) }, tooltip:{ shared:true }, series:[{ name:'Units in Stock', data: byCategory.map(i => Number(i.total_units || 0)), color:'#4569AD' }, { name:'Low Stock Items', data: byCategory.map(i => Number(lowByCategory[i.category_name] || 0)), color:'#d97706' }] });
    chart({ chart:{ type:'pie', renderTo:'ch-stock-pie', height:220 }, tooltip:{ pointFormat:'<b>{point.y} items</b>' }, series:[{ name:'Status', data: stockPie, innerSize:'55%', dataLabels:{ format:'{point.name}: {point.y}' } }] });
    // Shelf weight utilization: estimated load vs the shelf's min/max band.
    // Prefer the real per-shelf weights from the inventory report; fall back to
    // the shelves list estimate if unavailable.
    const shelfWeights = state.inventory?.shelfWeights || [];
    if (shelfWeights.length) {
      chart({
        chart:{ type:'column', renderTo:'ch-shelf-weight', height:280 },
        xAxis:{ categories: shelfWeights.map(w => w.shelf_name) },
        yAxis:{ title:{ text:'kg' }, min:0 },
        tooltip:{ shared:true, valueSuffix:' kg' },
        series:[
          { name:'Est. Load', type:'column', data: shelfWeights.map(w => Number(w.est_weight || 0)), color:'#7c3aed' },
          { name:'Min', type:'line', data: shelfWeights.map(w => (w.min_weight === null ? null : Number(w.min_weight))), color:'#d97706', dashStyle:'ShortDash', marker:{ enabled:false } },
          { name:'Max', type:'line', data: shelfWeights.map(w => (w.max_weight === null ? null : Number(w.max_weight))), color:'#dc2626', dashStyle:'ShortDash', marker:{ enabled:false } },
        ]
      });
    } else {
      chart({ chart:{ type:'bar', renderTo:'ch-shelf-weight', height:260 }, xAxis:{ categories: shelves.map(s => s.code || s.name || 'Shelf') }, yAxis:{ title:{ text:'kg' } }, tooltip:{ valueSuffix:' kg' }, series:[{ name:'Estimated Weight', data: shelves.map(s => Number(s.estimatedWeight || 0)), color:'#7c3aed' }] });
    }

    // Stock distribution by physical location (warehouse / store / IoT shelf).
    const dist = state.inventory?.distribution || {};
    const distData = [
      { name:'Warehouse', y:Number(dist.warehouse || 0), color:'#4569AD' },
      { name:'Store', y:Number(dist.store || 0), color:'#0d9488' },
      { name:'IoT Shelf', y:Number(dist.shelf || 0), color:'#7c3aed' },
    ];
    chart({
      chart:{ type:'pie', renderTo:'ch-distribution', height:260 },
      tooltip:{ pointFormat:'<b>{point.y} units</b> ({point.percentage:.1f}%)' },
      series:[{ name:'Units', innerSize:'55%', data: distData.some(d => d.y > 0) ? distData : [{ name:'No stock', y:1, color:'#B7C3E8' }], dataLabels:{ format:'{point.name}: {point.y}' } }]
    });

    // Supplier delivery quality (stacked: good / damaged / incomplete).
    const sq = state.inventory?.supplierQuality || [];
    chart({
      chart:{ type:'bar', renderTo:'ch-supplier-quality', height:280 },
      xAxis:{ categories: sq.length ? sq.map(s => s.supplier_name) : ['No deliveries'] },
      yAxis:{ title:{ text:'Items' }, min:0 },
      plotOptions:{ series:{ stacking:'normal' } },
      series:[
        { name:'Good', data: sq.map(s => Number(s.good_items || 0)), color:'#16a34a' },
        { name:'Damaged', data: sq.map(s => Number(s.damaged_items || 0)), color:'#dc2626' },
        { name:'Incomplete', data: sq.map(s => Number(s.incomplete_items || 0)), color:'#d97706' },
      ]
    });
    chart({ chart:{ type:'bar', renderTo:'ch-alert-types', height:260 }, xAxis:{ categories: alertTypes.map(a => a.name) }, series:[{ name:'Count', data: alertTypes.map(a => a.y), color:'#dc2626' }] });
    chart({ chart:{ type:'column', renderTo:'ch-resolved', height:260 }, xAxis:{ categories: resolvedCats }, plotOptions:{ column:{ stacking:'normal' } }, series:[{ name:'Resolved', data: resolvedSeries.map(v => v.resolved), color:'#16a34a' }, { name:'Unresolved', data: resolvedSeries.map(v => v.unresolved), color:'#d97706' }] });

    // Incidents by category — count of incident reports grouped by report_category.
    const incidentCategories = {};
    (state.incidents?.items || []).forEach(inc => {
      const key = (inc.category && String(inc.category).trim()) || 'Uncategorized';
      incidentCategories[key] = (incidentCategories[key] || 0) + 1;
    });
    const incidentCategoryData = Object.entries(incidentCategories)
      .map(([name, y]) => ({ name, y }))
      .sort((a, b) => b.y - a.y);
    chart({
      chart:{ type:'pie', renderTo:'ch-incident-category', height:260 },
      tooltip:{ pointFormat:'<b>{point.y} incident(s)</b> ({point.percentage:.1f}%)' },
      series:[{
        name:'Incidents',
        data: incidentCategoryData.length ? incidentCategoryData : [{ name:'No incidents', y:1, color:'#B7C3E8' }],
        dataLabels:{ format:'{point.name}: {point.y}' }
      }]
    });
    // Users by role — prefer the governance endpoint's authoritative counts
    // (super-admin), else derive from the users list.
    const govRoles = state.governance?.users?.byRole || [];
    const roleCats = govRoles.length ? govRoles.map(r => r.role) : roleEntries.map(([role]) => role);
    const roleData = govRoles.length ? govRoles.map(r => Number(r.total || 0)) : roleEntries.map(([,count]) => count);
    chart({ chart:{ type:'bar', renderTo:'ch-user-actions', height:260 }, xAxis:{ categories: roleCats }, series:[{ name:'Users', data: roleData, color:'#4569AD' }] });

    // Super-admin only: data-quality flags column.
    const dq = state.governance?.dataQuality;
    if (dq) {
      chart({
        chart:{ type:'column', renderTo:'ch-data-quality', height:260 },
        xAxis:{ categories:['Inactive w/ Stock', 'Occupied, No Item', 'Missing Unit/Category'] },
        yAxis:{ title:{ text:'Count' }, min:0 },
        series:[{ name:'Flags', colorByPoint:true, data:[
          { y:Number(dq.inactiveWithStock || 0), color:'#dc2626' },
          { y:Number(dq.occupiedNoItem || 0), color:'#d97706' },
          { y:Number(dq.missingRefs || 0), color:'#7c3aed' },
        ] }]
      });
    }
  }

  // Super-admin governance: stock-change audit trail list.
  function renderAuditList(){
    const wrap = el('audit-list');
    if (!wrap) return;
    const rows = (state.governance?.auditTrail || []).slice(0,10);
    if (!rows.length) { wrap.innerHTML = '<div class="empty-card">No stock-change actions recorded.</div>'; return; }
    const dot = (t) => t === 'Incoming Confirmed' ? 'green' : 'blue';
    wrap.innerHTML = rows.map(r => `
      <div class="act-row">
        <div class="act-av">${escapeHtml(initials(r.actor || 'System'))}</div>
        <div>
          <div class="act-user">${escapeHtml(r.actor || 'System')} <span class="pill ${dot(r.action_type)}">${escapeHtml(r.action_type || '')}</span></div>
          <div class="act-action">${escapeHtml(r.item_name || 'Item')} · ${num(r.quantity || 0)} units</div>
        </div>
        <div class="act-time">${escapeHtml(shortDate(r.action_date))}</div>
      </div>`).join('');
  }

  // Super-admin governance: account security watch list.
  function renderSecurityList(){
    const wrap = el('security-list');
    if (!wrap) return;
    const rows = (state.governance?.users?.securityAccounts || []).slice(0,10);
    if (!rows.length) { wrap.innerHTML = '<div class="empty-card">No security flags.</div>'; return; }
    wrap.innerHTML = rows.map(r => {
      const locked = r.locked_until && new Date(String(r.locked_until).replace(' ','T')).getTime() > Date.now();
      return `
      <div class="refill-row">
        <span class="refill-icon ui-icon icon-users" aria-hidden="true"></span>
        <div>
          <div class="refill-name">${escapeHtml(r.full_name || r.username || 'User')} · ${escapeHtml(r.role || '')}</div>
          <div class="refill-stock">Failed attempts: ${num(r.failed_attempts || 0)}${locked ? ' · Locked' : ''}</div>
        </div>
        <span class="refill-urg ${locked ? 'critical' : 'medium'}">${locked ? 'Locked' : 'Watch'}</span>
      </div>`;
    }).join('');
  }

  // Realtime Fetching Part. For 30 seconds
  let refreshTimer = null;

  async function loadData(){
    const [dashboard, inventory, incidents, users, shelves] = await Promise.all([
      fetchJson(ENDPOINTS.dashboard),
      fetchJson(ENDPOINTS.inventory),
      fetchJson(ENDPOINTS.incidents),
      fetchJson(ENDPOINTS.users),
      fetchJson(ENDPOINTS.shelves),
    ]);
    Object.assign(state, { dashboard, inventory, incidents, users, shelves });

    // Governance data is super-admin only; the endpoint is present in the view
    // config for that role. Failure here must not break the rest of the board.
    if (ENDPOINTS.governance) {
      try { state.governance = await fetchJson(ENDPOINTS.governance); }
      catch (e) { state.governance = null; }
    }
  }

  async function refreshDashboard(){
    try {
      await loadData();
      renderCards();
      renderStockStatusBars();
      renderRefillList();
      renderShelfGrid();
      renderIncidentList();
      renderActivityList();
      renderAuditList();
      renderSecurityList();
      renderCharts();
      // Charts re-created during a refresh may sit in a hidden tab panel (0 size);
      // reflow so the currently visible panel's charts fill their containers.
      requestAnimationFrame(() => {
        if (window.Highcharts && Array.isArray(Highcharts.charts)) {
          Highcharts.charts.forEach(c => { if (c) { try { c.reflow(); } catch (e) {} } });
        }
      });
      console.log('[Dashboard] Refreshing at', new Date().toLocaleTimeString());
    } catch (err) {
      console.error('Dashboard refresh failed:', err);
    }
  }

  // just change this okay. 
  function startPolling(intervalMs = 30000){
    stopPolling();
    refreshDashboard();
    refreshTimer = setInterval(refreshDashboard, intervalMs);
  }

  function stopPolling(){
    if (refreshTimer) {
      clearInterval(refreshTimer);
      refreshTimer = null;
    }
  }

  function bindReportNav(){
  // its own report panel and hides the rest. A white indicator slides to the
  // active button.
  function bindReportNav(){
    const nav = el('report-nav');
    const slider = el('report-nav-slider');
    const buttons = Array.from(document.querySelectorAll('.report-nav-btn[data-target]'));
    if (!nav || !buttons.length) return;

    const panels = Array.from(document.querySelectorAll('.report-panel'));

    // Move the white pill behind the given button.
    const moveSlider = (btn) => {
      if (!slider || !btn) return;
      slider.style.width = `${btn.offsetWidth}px`;
      slider.style.transform = `translateX(${btn.offsetLeft - nav.clientLeft}px)`;
      nav.classList.add('slider-ready');
    };

    // Charts rendered while their panel was hidden have zero size; once a panel
    // becomes visible, ask Highcharts to recalculate so they fill the space.
    const reflowCharts = () => {
      if (!window.Highcharts || !Array.isArray(Highcharts.charts)) return;
      Highcharts.charts.forEach(c => { if (c) { try { c.reflow(); } catch (e) {} } });
    };

    let current = buttons.find(b => b.classList.contains('active')) || buttons[0];

    const setActive = (id) => {
      const btn = buttons.find(b => b.dataset.target === id);
      if (!btn) return;
      buttons.forEach(b => b.classList.toggle('active', b === btn));
      panels.forEach(p => p.classList.toggle('active', p.id === id));
      current = btn;
      moveSlider(btn);
      requestAnimationFrame(reflowCharts);
    };

    buttons.forEach(btn => {
      btn.addEventListener('click', () => setActive(btn.dataset.target));
    });

    // Show the initial panel and position the slider once the layout is ready.
    setActive(current.dataset.target);
    requestAnimationFrame(() => moveSlider(current));
    // Fonts can load after first paint and shift button widths — realign then.
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => moveSlider(current));
    let resizeRaf = null;
    window.addEventListener('resize', () => {
      if (resizeRaf) cancelAnimationFrame(resizeRaf);
      resizeRaf = requestAnimationFrame(() => moveSlider(current));
    });
  }

  function showError(error){
    console.error(error);
    ['metrics-grid','refill-list','shelf-grid','incident-list','act-list','stock-status-bars'].forEach((id) => {
      const node = el(id);
      if (!node) return;
      node.innerHTML = `<div class="empty-card">${escapeHtml(error.message)}</div>`;
    });
    ['ch-stock-levels','ch-movers','ch-stock-pie','ch-shelf-weight','ch-alert-types','ch-resolved','ch-incident-category','ch-user-actions'].forEach((id) => setEmpty(id, error.message));
  }

  document.addEventListener('DOMContentLoaded', () => {
    bindReportNav();
    startPolling(30000); // refresh every 30 seconds
  });

  // Pause polling when tab is hidden, resume when visible
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopPolling();
    } else {
      startPolling(30000); 
    }
  });
})();
