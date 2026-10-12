(function () {
  function peso(value) {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0));
  }
  function number(value) {
    return new Intl.NumberFormat('en-PH').format(Number(value || 0));
  }
  function shortDate(value) {
    if (!value) return '—';
    const d = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? value : d.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  }
  function setText(selector, value) {
    const el = document.querySelector(selector);
    if (el) el.textContent = value;
  }
  function setHtml(selector, value) {
    const el = document.querySelector(selector);
    if (el) el.innerHTML = value;
  }
  function getFilterParams() {
    const start = document.getElementById('inventory-start-date')?.value || '';
    const end = document.getElementById('inventory-end-date')?.value || '';
    const params = new URLSearchParams();
    if (start) params.set('start_date', start);
    if (end) params.set('end_date', end);
    return params;
  }
  function renderCategoryBars(data) {
    const max = Math.max(...data.map(item => Number(item.stock_value || 0)), 0);
    setHtml('#category-bars', data.length ? data.map(item => {
      const amount = Number(item.stock_value || 0);
      const width = max > 0 ? Math.max(8, Math.round((amount / max) * 100)) : 8;
      return `<div class="bar-row"><div class="bar-label">${item.category_name}</div><div class="bar-track"><div class="bar-fill" style="width:${width}%"></div></div><div class="bar-value">${peso(amount)}</div></div>`;
    }).join('') : '<div class="empty-state">No inventory categories found.</div>');
  }
  async function loadReport() {
    const params = getFilterParams();
    const response = await fetch(`${window.CGMI_INVENTORY_REPORT_API}?${params.toString()}`, { credentials: 'same-origin' });
    const data = await response.json();
    if (!data.success) throw new Error(data.message || 'Unable to load inventory report');

    setText('[data-kpi="item-count"]', number(data.summary.itemCount));
    setText('[data-kpi="active-count"]', number(data.summary.activeCount));
    setText('[data-kpi="total-units"]', number(data.summary.totalUnits));
    setText('[data-kpi="stock-value"]', peso(data.summary.stockValue));
    setText('[data-kpi="low-stock-count"]', number(data.summary.lowStockCount));
    setText('[data-kpi="out-of-stock-count"]', number(data.summary.outOfStockCount));
    setText('#inventory-range-label', data.rangeLabel || 'All dates');
    setText('#inventory-filtered-range-label', data.rangeLabel || 'All dates');

    renderCategoryBars(data.byCategory || []);

    setHtml('#category-summary-body', (data.byCategory || []).length ? data.byCategory.map((item) => `
      <tr>
        <td>${item.category_name}</td>
        <td>${number(item.item_count)}</td>
        <td>${number(item.total_units)}</td>
        <td>${peso(item.stock_value)}</td>
      </tr>`).join('') : '<tr><td colspan="4" class="empty-state">No category data available.</td></tr>');

    setHtml('#low-stock-body', (data.lowStockItems || []).length ? data.lowStockItems.map((item) => `
      <tr>
        <td>${item.sku}</td>
        <td>${item.item_name}</td>
        <td>${item.category_name}</td>
        <td>${number(item.quantity)} ${item.unit || ''}</td>
        <td>${number(item.reorder_level)}</td>
        <td>${peso(item.price)}</td>
      </tr>`).join('') : '<tr><td colspan="6" class="empty-state">No low-stock items.</td></tr>');

    setHtml('#movement-body', (data.movements || []).length ? data.movements.map((row) => `
      <tr>
        <td>${shortDate(row.created_at)}</td>
        <td>${row.sku || '—'}</td>
        <td>${row.item_name || '—'}</td>
        <td>${row.movement_type || '—'}</td>
        <td>${number(row.quantity_change)}</td>
        <td>${number(row.quantity_after)}</td>
        <td>${row.reference_type || '—'}${row.reference_id ? ' #' + row.reference_id : ''}</td>
      </tr>`).join('') : '<tr><td colspan="7" class="empty-state">No stock movement activity found for the selected dates.</td></tr>');

    setHtml('#filtered-inventory-body', (data.filteredItems || []).length ? data.filteredItems.map((row) => `
      <tr>
        <td>${shortDate(row.created_at)}</td>
        <td>${row.sku || '—'}</td>
        <td>${row.item_name || '—'}</td>
        <td>${row.category_name || 'Uncategorized'}</td>
        <td>${row.movement_type || '—'}</td>
        <td>${number(row.quantity_change)}${row.unit ? ' ' + row.unit : ''}</td>
        <td>${number(row.quantity_after)}${row.unit ? ' ' + row.unit : ''}</td>
        <td>${row.reference_type || '—'}${row.reference_id ? ' #' + row.reference_id : ''}</td>
      </tr>`).join('') : '<tr><td colspan="8" class="empty-state">No filtered inventory records found for the selected dates.</td></tr>');

    setHtml('#movement-summary-body', (data.movementSummary || []).length ? data.movementSummary.map((row) => `
      <tr>
        <td>${row.movement_type}</td>
        <td>${number(row.entry_count)}</td>
        <td>${number(row.total_change)}</td>
      </tr>`).join('') : '<tr><td colspan="3" class="empty-state">No movement summary for the selected dates.</td></tr>');

    const downloadBtn = document.getElementById('inventory-download-btn');
    if (downloadBtn) {
      downloadBtn.href = `${window.CGMI_INVENTORY_EXPORT_API}?${params.toString()}`;
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('inventory-filter-form')?.addEventListener('submit', (event) => {
      event.preventDefault();
      loadReport().catch(showError);
    });
    document.getElementById('inventory-print-btn')?.addEventListener('click', () => window.print());
    loadReport().catch(showError);
  });

  function showError(error) {
    console.error(error);
    setHtml('#category-bars', `<div class="empty-state">${error.message}</div>`);
    setHtml('#category-summary-body', `<tr><td colspan="4" class="empty-state">${error.message}</td></tr>`);
    setHtml('#low-stock-body', `<tr><td colspan="6" class="empty-state">${error.message}</td></tr>`);
    setHtml('#movement-body', `<tr><td colspan="7" class="empty-state">${error.message}</td></tr>`);
    setHtml('#filtered-inventory-body', `<tr><td colspan="8" class="empty-state">${error.message}</td></tr>`);
    setHtml('#movement-summary-body', `<tr><td colspan="3" class="empty-state">${error.message}</td></tr>`);
  }
})();
