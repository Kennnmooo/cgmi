(function () {
  function peso(value) {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0));
  }
  function number(value) {
    return new Intl.NumberFormat('en-PH').format(Number(value || 0));
  }
  function setText(selector, value) {
    const el = document.querySelector(selector);
    if (el) el.textContent = value;
  }
  function setHtml(selector, value) {
    const el = document.querySelector(selector);
    if (el) el.innerHTML = value;
  }

  function renderCategoryBars(rows) {
    const max = Math.max(...rows.map(item => Number(item.stock_value || 0)), 0);
    setHtml('#category-bars', rows.length ? rows.map((row) => {
      const amount = Number(row.stock_value || 0);
      const width = max > 0 ? Math.max(8, Math.round((amount / max) * 100)) : 8;
      return `<div class="bar-row"><div class="bar-label">${row.category_name}</div><div class="bar-track"><div class="bar-fill" style="width:${width}%"></div></div><div class="bar-value">${peso(amount)}</div></div>`;
    }).join('') : '<div class="empty-state">No category data yet.</div>');
  }

  async function loadReport() {
    const response = await fetch(window.CGMI_INVENTORY_REPORT_API, { credentials: 'same-origin' });
    const data = await response.json();
    if (!data.success) throw new Error(data.message || 'Unable to load inventory report');

    setText('[data-kpi="item-count"]', number(data.summary.itemCount));
    setText('[data-kpi="active-count"]', number(data.summary.activeCount));
    setText('[data-kpi="total-units"]', number(data.summary.totalUnits));
    setText('[data-kpi="stock-value"]', peso(data.summary.stockValue));
    setText('[data-kpi="low-stock-count"]', number(data.summary.lowStockCount));
    setText('[data-kpi="out-of-stock-count"]', number(data.summary.outOfStockCount));

    renderCategoryBars(data.byCategory);

    setHtml('#category-summary-body', data.byCategory.length ? data.byCategory.map((row) => `
      <tr>
        <td>${row.category_name}</td>
        <td>${number(row.item_count)}</td>
        <td>${number(row.total_units)}</td>
        <td>${peso(row.stock_value)}</td>
      </tr>`).join('') : '<tr><td colspan="4" class="empty-state">No category summary yet.</td></tr>');

    setHtml('#low-stock-body', data.lowStockItems.length ? data.lowStockItems.map((item) => `
      <tr>
        <td>${item.sku}</td>
        <td>${item.item_name}</td>
        <td>${item.category_name}</td>
        <td>${number(item.quantity)} ${item.unit}</td>
        <td>${number(item.reorder_level)}</td>
        <td>${peso(item.price)}</td>
      </tr>`).join('') : '<tr><td colspan="6" class="empty-state">No low-stock items.</td></tr>');
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadReport().catch((error) => {
      console.error(error);
      setHtml('#category-summary-body', `<tr><td colspan="4" class="empty-state">${error.message}</td></tr>`);
      setHtml('#low-stock-body', `<tr><td colspan="6" class="empty-state">${error.message}</td></tr>`);
      setHtml('#category-bars', `<div class="empty-state">${error.message}</div>`);
    });
  });
})();
