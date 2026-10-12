/* ============================================================
   INVENTORY.JS — Inventory data management (CRUD operations)
   ============================================================ */

const InventoryManager = (() => {
  let inventory = [];
  const endpoints = {
    list: (window.CGMI_INVENTORY_API && window.CGMI_INVENTORY_API.list) || '',
    save: (window.CGMI_INVENTORY_API && window.CGMI_INVENTORY_API.save) || '',
  };

  async function init() {
    const response = await fetch(endpoints.list, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error('Unable to load inventory items.');
    }

    const payload = await response.json();
    inventory = Array.isArray(payload.items) ? payload.items.map(normalizeItemTotals) : [];
    return getAll();
  }

  function getAll() {
    return [...inventory];
  }

  function getItemById(id) {
    return inventory.find(item => String(item.dbId) === String(id) || String(item.id) === String(id));
  }

  async function addItem(itemData) {
    const savedItem = await saveItemToServer(itemData);
    inventory.push(normalizeItemTotals(savedItem));
    reloadTableData(inventory);
    updateStats();
    return savedItem;
  }

  async function updateItem(id, itemData) {
    const savedItem = normalizeItemTotals(await saveItemToServer({ ...itemData, dbId: id }));
    const index = inventory.findIndex(item => String(item.dbId) === String(savedItem.dbId));
    if (index === -1) {
      inventory.unshift(savedItem);
    } else {
      inventory[index] = savedItem;
    }
    reloadTableData(inventory);
    updateStats();
    return savedItem;
  }

  async function saveItemToServer(itemData) {
    const formData = new FormData();
    Object.entries(itemData).forEach(([key, value]) => {
      if (value === undefined || value === null || value === '') return;
      if (key === 'imageFile' && value instanceof File) {
        formData.append('image', value);
        return;
      }
      formData.append(key, value);
    });
    if (itemData.removeImage) {
      formData.append('removeImage', '1');
    }

    const response = await fetch(endpoints.save, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    });

    const payload = await response.json().catch(() => ({ success: false, message: 'Unexpected server response.' }));

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Unable to save inventory item.');
    }

    return normalizeItemTotals(payload.item);
  }

  function normalizeItemTotals(item) {
    const normalized = { ...(item || {}) };

    // The API computes these from satellite tables:
    // warehouseQty = warehouse_items.warehouse_item_quantity
    // storeQty = store_items.store_item_quantity
    // iotShelfQty = shelves.shelf_quantity
    // overallQty = warehouseQty + storeQty + iotShelfQty
    const overallQty = Number.isFinite(Number(normalized.overallQty))
      ? Number(normalized.overallQty)
      : Number(normalized.qty || 0);
    const iotShelfQty = Number.isFinite(Number(normalized.iotShelfQty))
      ? Number(normalized.iotShelfQty)
      : 0;
    const warehouseQty = Number.isFinite(Number(normalized.warehouseQty))
      ? Number(normalized.warehouseQty)
      : Math.max(0, overallQty - iotShelfQty);

    normalized.overallQty = overallQty;
    normalized.qty = overallQty;
    normalized.iotShelfQty = iotShelfQty;
    normalized.warehouseQty = warehouseQty;
    normalized.storeQty = Number.isFinite(Number(normalized.storeQty))
      ? Number(normalized.storeQty)
      : 0;

    return normalized;
  }

  async function toggleStatus(id, newStatus) {
    const item = inventory.find(i => String(i.dbId) === String(id) || String(i.id) === String(id));
    if (!item) throw new Error('Item not found.');

    const itemData = {
      dbId: item.dbId || id,
      name: item.name,
      category: item.category,
      subcategory: item.subcategory,
      shelfTracking: item.shelfTracking || 'not_shelves',
      warehouseQty: item.warehouseQty ?? 0,
      storeQty: item.storeQty ?? 0,
      iotShelfQty: item.iotShelfQty ?? 0,
      unit: item.unit,
      unitPrice: item.unitPrice,
      unitWeightKg: item.unitWeightKg !== null && item.unitWeightKg !== undefined ? item.unitWeightKg : '',
      status: newStatus,
    };

    const savedItem = normalizeItemTotals(await saveItemToServer(itemData));
    const index = inventory.findIndex(i => String(i.dbId) === String(savedItem.dbId));
    if (index === -1) {
      inventory.unshift(savedItem);
    } else {
      inventory[index] = savedItem;
    }
    reloadTableData(inventory);
    updateStats();
    return savedItem;
  }

  function getCounts() {
    const total = inventory.length;
    const active = inventory.filter(i => i.status === 'Active').length;
    const inactive = inventory.filter(i => i.status === 'Inactive').length;
    const lowStock = inventory.filter(i => i.status === 'Active' && i.qty > 0 && i.qty <= 10).length;
    const outOfStock = inventory.filter(i => i.qty === 0).length;

    return { total, active, inactive, lowStock, outOfStock };
  }

  return { init, getAll, getItemById, addItem, updateItem, toggleStatus, getCounts, normalizeItemTotals };
})();

function updateStats() {
  const counts = InventoryManager.getCounts();

  const setEl = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };

  setEl('stat-total', counts.total);
  setEl('stat-active', counts.active);
  setEl('stat-inactive', counts.inactive);
  setEl('stat-lowstock', counts.lowStock);
  setEl('header-total', counts.total);
}
