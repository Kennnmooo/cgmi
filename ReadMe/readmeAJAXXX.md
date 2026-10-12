# readmeAJAX — Converting Fetch to AJAX with Real-Time setInterval

This document lists every part of the CGMI system that uses the JavaScript `fetch()` API. You will replace each `fetch()` call with jQuery `$.ajax()` and wrap the data-loading functions inside `setInterval()` for real-time auto-refresh.

---

## Table of Contents

1. [Global Pattern: How to Convert](#global-pattern-how-to-convert)
2. [Dashboard Module](#1-dashboard-module)
3. [Inventory Module](#2-inventory-module)
4. [Shelves Module](#3-shelves-module)
5. [User Management Module](#4-user-management-module)
6. [Checker Module](#5-checker-module)
7. [Incident Reports Module](#6-incident-reports-module)
8. [POS Module](#7-pos-module)
9. [Sales Report Module](#8-sales-report-module)
10. [Inventory Report Module](#9-inventory-report-module)
11. [PHP API Endpoints (Backend)](#10-php-api-endpoints-backend)
12. [setInterval Strategy](#11-setinterval-strategy)
13. [Tips and Warnings](#12-tips-and-warnings)

---

## Global Pattern: How to Convert

### Before (fetch)

```javascript
async function loadData() {
  const response = await fetch('/api/some-endpoint.php', { credentials: 'same-origin' });
  const data = await response.json();
  if (!response.ok || !data.success) throw new Error(data.message);
  return data;
}
```

### After (jQuery AJAX)

```javascript
function loadData() {
  $.ajax({
    url: '/api/some-endpoint.php',
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      if (!data.success) { console.error(data.message); return; }
      // render data here
    },
    error: function(xhr, status, error) {
      console.error('Request failed:', error);
    }
  });
}

// Real-time auto-refresh every 5 seconds
setInterval(loadData, 5000);
```

### POST with JSON Body — Before (fetch)

```javascript
const response = await fetch('/api/save.php', {
  method: 'POST',
  credentials: 'same-origin',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(payload)
});
```

### POST with JSON Body — After (jQuery AJAX)

```javascript
$.ajax({
  url: '/api/save.php',
  method: 'POST',
  contentType: 'application/json',
  data: JSON.stringify(payload),
  dataType: 'json',
  success: function(data) { /* handle */ },
  error: function(xhr) { /* handle */ }
});
```

### POST with FormData (file upload) — Before (fetch)

```javascript
const response = await fetch('/api/inventory/save.php', {
  method: 'POST',
  body: formData,
  credentials: 'same-origin'
});
```

### POST with FormData (file upload) — After (jQuery AJAX)

```javascript
$.ajax({
  url: '/api/inventory/save.php',
  method: 'POST',
  data: formData,
  processData: false,
  contentType: false,
  dataType: 'json',
  success: function(data) { /* handle */ },
  error: function(xhr) { /* handle */ }
});
```

---

## 1. Dashboard Module

**File:** `assets/js/modules/dashboard/dashboard.js`

### Functions to Change

| Function | Current Behavior | HTTP Method | Endpoint |
|----------|-----------------|-------------|----------|
| `fetchJson(url)` | Helper that uses `fetch()` for GET requests | GET | Any URL passed |
| `loadData()` | Calls `fetchJson()` for 6 endpoints via `Promise.all()` | GET | Multiple (see below) |

### Endpoints Called (all GET)

- `ENDPOINTS.dashboard` → `/api/dashboard/summary.php`
- `ENDPOINTS.sales` → `/api/reports/sales_summary.php`
- `ENDPOINTS.inventory` → `/api/reports/inventory_summary.php`
- `ENDPOINTS.incidents` → `/api/incidents/list.php`
- `ENDPOINTS.users` → `/api/users/list.php`
- `ENDPOINTS.shelves` → `/api/shelves/list.php`

### What to Do

1. Replace `fetchJson()` with a reusable `$.ajax` GET helper
2. Replace `Promise.all()` with multiple `$.ajax` calls (or use `$.when()`)
3. Wrap `loadData()` in a `setInterval()` for real-time dashboard updates
4. Call all render functions inside the success callback

### Example Conversion

```javascript
function loadData() {
  $.when(
    $.ajax({ url: ENDPOINTS.dashboard, dataType: 'json' }),
    $.ajax({ url: ENDPOINTS.sales, dataType: 'json' }),
    $.ajax({ url: ENDPOINTS.inventory, dataType: 'json' }),
    $.ajax({ url: ENDPOINTS.incidents, dataType: 'json' }),
    $.ajax({ url: ENDPOINTS.users, dataType: 'json' }),
    $.ajax({ url: ENDPOINTS.shelves, dataType: 'json' })
  ).done(function(dashboard, sales, inventory, incidents, users, shelves) {
    // Each argument is [data, textStatus, jqXHR]
    state.dashboard = dashboard[0];
    state.sales = sales[0];
    state.inventory = inventory[0];
    state.incidents = incidents[0];
    state.users = users[0];
    state.shelves = shelves[0];
    state.salesTrend = groupSalesTrend(state.sales.dailySales || []);
    renderAll();
  }).fail(function() {
    showError(new Error('Failed to load dashboard data.'));
  });
}

// Auto-refresh every 10 seconds
setInterval(loadData, 10000);
```

---

## 2. Inventory Module

**File:** `assets/js/modules/inventory/inventory.js`

### Functions to Change

| Function | Current Behavior | HTTP Method | Endpoint |
|----------|-----------------|-------------|----------|
| `init()` | Fetch all inventory items | GET | `/api/inventory/list.php` |
| `saveItemToServer(itemData)` | Save/update item with FormData (supports image upload) | POST (FormData) | `/api/inventory/save.php` |

### What to Do

1. Replace `init()` fetch with `$.ajax` GET request
2. Replace `saveItemToServer()` with `$.ajax` POST using `processData: false` and `contentType: false` (for FormData/file upload)
3. Add `setInterval` on the list-fetching function to refresh inventory in real-time
4. Do NOT put `saveItemToServer` in setInterval — it is a user-triggered action

### Example Conversion

```javascript
function init() {
  $.ajax({
    url: endpoints.list,
    method: 'GET',
    dataType: 'json',
    headers: { 'Accept': 'application/json' },
    success: function(payload) {
      inventory = Array.isArray(payload.items) ? payload.items.map(normalizeItemTotals) : [];
      reloadTableData(inventory);
      updateStats();
    },
    error: function() {
      console.error('Unable to load inventory items.');
    }
  });
}

function saveItemToServer(itemData) {
  var formData = new FormData();
  Object.entries(itemData).forEach(function([key, value]) {
    if (value === undefined || value === null || value === '') return;
    if (key === 'imageFile' && value instanceof File) {
      formData.append('image', value);
      return;
    }
    formData.append(key, value);
  });
  if (itemData.removeImage) formData.append('removeImage', '1');

  $.ajax({
    url: endpoints.save,
    method: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(payload) {
      if (!payload.success) { alert(payload.message); return; }
      // update local array and UI
    },
    error: function() { alert('Unable to save inventory item.'); }
  });
}

// Real-time refresh
setInterval(init, 5000);
```

---

## 3. Shelves Module

**File:** `assets/js/modules/shelves/shelves-admin.js`

### Functions to Change

| Function | Current Behavior | HTTP Method | Endpoint |
|----------|-----------------|-------------|----------|
| `loadAll()` | Fetch all shelves + inventory options | GET | `/api/shelves/list.php` |
| `saveShelf()` | Create or update a shelf | POST (JSON) | `/api/shelves/save.php` |

### What to Do

1. Convert `loadAll()` from `fetch()` to `$.ajax` GET
2. Convert `saveShelf()` from `fetch()` to `$.ajax` POST with `contentType: 'application/json'`
3. Wrap `loadAll()` in `setInterval()` for real-time shelf status

### Example Conversion

```javascript
function loadAll() {
  $.ajax({
    url: api.list,
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      if (!data.success) { alert(data.message); return; }
      shelves = data.items || [];
      inventoryOptions = data.inventoryOptions || [];
      render();
    },
    error: function() { alert('Unable to load shelves.'); }
  });
}

function saveShelf() {
  var payload = {
    id: editingId,
    name: el('form-shelf-name').value.trim(),
    location: el('form-location').value.trim(),
    itemId: el('form-item').value || null,
    shelfQuantity: el('form-shelf-qty').value || null,
    status: el('form-status').value,
    minWeightThreshold: el('form-min').value || null,
    maxWeightThreshold: el('form-max').value || null
  };

  $.ajax({
    url: api.save,
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify(payload),
    dataType: 'json',
    success: function(data) {
      if (!data.success) { alert(data.message); return; }
      closeForm();
      loadAll();
    },
    error: function() { alert('Unable to save shelf.'); }
  });
}

// Real-time refresh
setInterval(loadAll, 5000);
```

---

## 4. User Management Module

**File:** `assets/js/modules/user-management/user-management.js`

### Functions to Change

| Function | Current Behavior | HTTP Method | Endpoint |
|----------|-----------------|-------------|----------|
| `fetchUsers()` | GET all users | GET | `/api/users/list.php` |
| `saveUser()` | Create or update user | POST (JSON) | `/api/users/save.php` |
| `toggleUser(id, action)` | Activate/deactivate user | POST (JSON) | `/api/users/toggle.php` |

### What to Do

1. Convert `fetchUsers()` to `$.ajax` GET
2. Convert `saveUser()` to `$.ajax` POST with JSON body
3. Convert `toggleUser()` to `$.ajax` POST with JSON body
4. Wrap `fetchUsers()` in `setInterval()` for real-time user list

### Example Conversion

```javascript
function fetchUsers() {
  $.ajax({
    url: api.list,
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      if (!data.success) { toast(data.message); return; }
      users = data.users || [];
      render();
    },
    error: function() { toast('Unable to load users.'); }
  });
}

function saveUser() {
  var payload = getPayload();
  if (!payload) return;

  $.ajax({
    url: api.save,
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify(payload),
    dataType: 'json',
    success: function(data) {
      if (!data.success) { toast(data.message); return; }
      upsertUser(data.user);
      closeModal();
      toast(data.message || 'Saved successfully.');
    },
    error: function(xhr) {
      var resp = xhr.responseJSON || {};
      if (resp.errors) {
        Object.entries(resp.errors).forEach(function([key, val]) {
          setError(key, val);
        });
      }
      toast(resp.message || 'Unable to save user.');
    }
  });
}

function toggleUser(id, action) {
  $.ajax({
    url: api.toggle,
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ id: Number(id), action: action }),
    dataType: 'json',
    success: function(data) {
      if (!data.success) { toast(data.message); return; }
      upsertUser(data.user);
      toast(data.message || 'Account updated.');
    },
    error: function() { toast('Unable to update account status.'); }
  });
}

// Real-time refresh
setInterval(fetchUsers, 5000);
```

---

## 5. Checker Module

**File:** `assets/js/modules/checker/checker.js`

### Functions to Change

| Function | Current Behavior | HTTP Method | Endpoint |
|----------|-----------------|-------------|----------|
| `getJson(url, options)` | Reusable fetch helper used by all checker functions | GET/POST | Any URL passed |
| `loadInventoryChoices()` | Load inventory items for dropdown | GET | `/api/checker/items.php` |
| `loadIncoming()` | Load incoming warehouse records | GET | `/api/checker/incoming.php` |
| `loadOutgoing()` | Load outgoing warehouse records | GET | `/api/checker/outgoing.php` |
| `saveRecord(payload)` | Save checker record (incoming/outgoing) | POST (JSON) | `/api/checker/save.php` |
| Incoming form `submit` handler | Collects items and calls `saveRecord()` | POST (JSON) | `/api/checker/save.php` |
| Outgoing form `submit` handler | Collects outgoing data and calls `saveRecord()` | POST (JSON) | `/api/checker/save.php` |

### What to Do

1. Replace `getJson()` helper with a `$.ajax`-based helper
2. Convert all `loadIncoming()`, `loadOutgoing()`, `loadInventoryChoices()` to `$.ajax` GET
3. Convert `saveRecord()` to `$.ajax` POST with JSON
4. Wrap `loadIncoming()` and `loadOutgoing()` in `setInterval()` for real-time updates

### Example Conversion

```javascript
function loadIncoming() {
  $.ajax({
    url: appBase() + '/api/checker/incoming.php',
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      window.__incomingRecords = data.items || [];
      // render incoming table
      setCounts(window.__incomingRecords, outgoingCache);
    },
    error: function() { console.error('Unable to load incoming records.'); }
  });
}

function loadOutgoing() {
  $.ajax({
    url: appBase() + '/api/checker/outgoing.php',
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      outgoingCache = data.items || [];
      // render outgoing table
      setCounts(window.__incomingRecords || [], outgoingCache);
    },
    error: function() { console.error('Unable to load outgoing records.'); }
  });
}

function saveRecord(payload) {
  $.ajax({
    url: appBase() + '/api/checker/save.php',
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify(payload),
    dataType: 'json',
    success: function(data) {
      if (!data.success) { alert(data.message); return; }
      loadIncoming();
      loadOutgoing();
      alert('Record saved.');
    },
    error: function() { alert('Unable to save checker record.'); }
  });
}

// Real-time refresh
setInterval(function() {
  loadIncoming();
  loadOutgoing();
}, 5000);
```

---

## 6. Incident Reports Module

**File:** `assets/js/modules/incident-report-admin/incident-reports.js`

### Functions to Change

| Function | Current Behavior | HTTP Method | Endpoint |
|----------|-----------------|-------------|----------|
| `loadReports()` | GET all incident reports (supports `?archived=1`) | GET | `/api/incidents/list.php` |
| `updateIncident(id, action, msg, notes)` | Approve/reject/archive/restore incident | POST (JSON) | `/api/incidents/review.php` |

### What to Do

1. Convert `loadReports()` to `$.ajax` GET
2. Convert `updateIncident()` to `$.ajax` POST with JSON body
3. Wrap `loadReports()` in `setInterval()` for real-time incident tracking

### Example Conversion

```javascript
function loadReports() {
  var url = api.list;
  if (showArchived) url += '?archived=1';

  $.ajax({
    url: url,
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      if (!data.success) { alert(data.message); return; }
      reports = data.items || [];
      renderStats(data.summary || {});
      renderReports();
      updateArchiveToggle();
    },
    error: function() { alert('Unable to load incident reports.'); }
  });
}

function updateIncident(id, action, successMessage, reviewNotes) {
  $.ajax({
    url: api.review,
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ id: Number(id), action: action, reviewNotes: reviewNotes || '' }),
    dataType: 'json',
    success: function(data) {
      if (!data.success) { alert(data.message); return; }
      // Update local reports array and re-render
      loadReports();
      toast(data.message || successMessage);
    },
    error: function() { alert('Unable to update incident.'); }
  });
}

// Real-time refresh
setInterval(loadReports, 5000);
```

---

### Incident Submit (Inventory Staff Side)

**File:** `assets/js/modules/incident-report-inventory/incident-form.js` (or inline in View)

| Function | Current Behavior | HTTP Method | Endpoint |
|----------|-----------------|-------------|----------|
| Submit handler | POST new incident with FormData (image upload) | POST (FormData) | `/api/incidents/submit.php` |

Convert with `processData: false` and `contentType: false` for file upload. Do NOT put in setInterval (user action only).

---

## 7. POS Module

**Files:**
- `assets/js/modules/pos/data.js` — Product loading
- `assets/js/modules/pos/receipt.js` — Checkout

### Functions to Change

| Function | File | HTTP Method | Endpoint |
|----------|------|-------------|----------|
| `loadProductsFromApi()` | data.js | GET | `/api/pos/products.php` |
| `confirmOrder()` | receipt.js | POST (JSON) | `/api/pos/checkout.php` |

### What to Do

1. Convert `loadProductsFromApi()` to `$.ajax` GET
2. Convert `confirmOrder()` to `$.ajax` POST with JSON
3. Wrap `loadProductsFromApi()` in `setInterval()` so product stock/availability is real-time
4. Do NOT put `confirmOrder()` in setInterval — it is triggered by user action

### Example Conversion

```javascript
function loadProductsFromApi() {
  $.ajax({
    url: window.CGMI_POS_API.products,
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      if (!data.success) { console.error(data.message); return; }
      PRODUCTS = (data.products || []).map(function(item) {
        return Object.assign({}, item, { id: Number(item.id), price: Number(item.price || 0), emoji: item.emoji || 'IT' });
      });
      // re-render product grid
      if (typeof _applyFilters === 'function') _applyFilters();
    },
    error: function() { showToast('Unable to load products.', 'error'); }
  });
}

function confirmOrder() {
  var snapshot = getCartSnapshot();
  if (snapshot.cashTendered < snapshot.total) {
    showToast('Cash amount is insufficient!', 'error');
    return;
  }

  $.ajax({
    url: window.CGMI_POS_API.checkout,
    method: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({
      items: snapshot.items.map(function(item) { return { id: item.id, qty: item.qty }; }),
      discount_amount: snapshot.totalDiscount || 0,
      cash_tendered: snapshot.cashTendered || 0,
      fulfillment_type: snapshot.fulfillmentType || 'pickup',
      storage_location: snapshot.storageLocation || 'store'
    }),
    dataType: 'json',
    success: function(data) {
      if (!data.success) { showToast(data.message, 'error'); return; }
      if (Array.isArray(data.products)) PRODUCTS = data.products;
      // Show receipt modal
      showToast(data.message || 'Sale completed successfully.', 'success');
    },
    error: function() { showToast('Checkout failed.', 'error'); }
  });
}

// Real-time product/stock refresh
setInterval(loadProductsFromApi, 5000);
```

---

## 8. Sales Report Module

**File:** `assets/js/modules/sales-report/report.js`

### Functions to Change

| Function | Current Behavior | HTTP Method | Endpoint |
|----------|-----------------|-------------|----------|
| `loadReport()` | GET sales report with date filter params | GET | `/api/reports/sales_summary.php?...` |

### What to Do

1. Convert `loadReport()` to `$.ajax` GET with query parameters
2. Wrap in `setInterval()` for real-time report updates

### Example Conversion

```javascript
function loadReport() {
  var params = getFilterParams();

  $.ajax({
    url: window.CGMI_SALES_REPORT_API + '?' + params.toString(),
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      if (!data.success) { showError(new Error(data.message)); return; }
      // Update KPIs and render charts/tables
      setText('[data-kpi="lifetime-sales"]', peso(data.summary.rangeSales));
      // ... rest of rendering
    },
    error: function() { showError(new Error('Unable to load sales report.')); }
  });
}

// Real-time refresh
setInterval(loadReport, 10000);
```

---

## 9. Inventory Report Module

**File:** `assets/js/modules/inventory-report/report.js`

### Functions to Change

| Function | Current Behavior | HTTP Method | Endpoint |
|----------|-----------------|-------------|----------|
| `loadReport()` | GET inventory report with date filter params | GET | `/api/reports/inventory_summary.php?...` |

### What to Do

1. Convert `loadReport()` to `$.ajax` GET with query parameters
2. Wrap in `setInterval()` for real-time report updates

### Example Conversion

```javascript
function loadReport() {
  var params = getFilterParams();

  $.ajax({
    url: window.CGMI_INVENTORY_REPORT_API + '?' + params.toString(),
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      if (!data.success) { showError(new Error(data.message)); return; }
      // Update KPIs, category bars, low-stock table, movements table
      setText('[data-kpi="item-count"]', number(data.summary.itemCount));
      // ... rest of rendering
    },
    error: function() { showError(new Error('Unable to load inventory report.')); }
  });
}

// Real-time refresh
setInterval(loadReport, 10000);
```

---

## 10. PHP API Endpoints (Backend)

The PHP backend does NOT need major changes. The API already returns JSON responses. However, review these points:

### All API Endpoints Summary

| Endpoint | Method | Operation | Module |
|----------|--------|-----------|--------|
| `/api/auth/login.php` | POST | Login | Auth |
| `/api/auth/logout.php` | POST | Logout | Auth |
| `/api/auth/me.php` | GET | Get current user session | Auth |
| `/api/dashboard/summary.php` | GET | Dashboard stats | Dashboard |
| `/api/inventory/list.php` | GET | List all items | Inventory |
| `/api/inventory/save.php` | POST (FormData) | Add/Update item | Inventory |
| `/api/shelves/list.php` | GET | List all shelves | Shelves |
| `/api/shelves/save.php` | POST (JSON) | Add/Update shelf | Shelves |
| `/api/users/list.php` | GET | List all users | Users |
| `/api/users/save.php` | POST (JSON) | Add/Update user | Users |
| `/api/users/toggle.php` | POST (JSON) | Activate/Deactivate | Users |
| `/api/checker/items.php` | GET | List items for dropdown | Checker |
| `/api/checker/incoming.php` | GET | List incoming records | Checker |
| `/api/checker/outgoing.php` | GET | List outgoing records | Checker |
| `/api/checker/save.php` | POST (JSON) | Save checker record | Checker |
| `/api/checker/pending-incoming.php` | GET | List pending incoming | Checker |
| `/api/checker/confirm-incoming.php` | POST (JSON) | Confirm incoming delivery | Checker |
| `/api/incidents/list.php` | GET | List incidents | Incidents |
| `/api/incidents/submit.php` | POST (FormData) | Submit incident with photo | Incidents |
| `/api/incidents/review.php` | POST (JSON) | Approve/Reject/Archive/Restore | Incidents |
| `/api/pos/products.php` | GET | List products for POS | POS |
| `/api/pos/checkout.php` | POST (JSON) | Process sale | POS |
| `/api/reports/sales_summary.php` | GET | Sales report data | Reports |
| `/api/reports/sales_export.php` | GET | Download sales CSV | Reports |
| `/api/reports/inventory_summary.php` | GET | Inventory report data | Reports |
| `/api/reports/inventory_export.php` | GET | Download inventory CSV | Reports |

### PHP Changes Needed (Minimal)

The PHP files are fine as-is. They already:
- Accept POST with `json_decode(file_get_contents('php://input'), true)` for JSON
- Accept POST with `$_POST` and `$_FILES` for FormData
- Return JSON via `json_response()`

**No changes needed on the backend** unless you want to add:
- Rate limiting (to prevent the setInterval from overloading the server)
- Caching headers for GET endpoints
- Last-modified/ETag support to reduce payload when data hasn't changed

---

## 11. setInterval Strategy

### Which Functions Get setInterval (DATA FETCHING only)

| Module | Function to Auto-Refresh | Recommended Interval |
|--------|--------------------------|---------------------|
| Dashboard | `loadData()` | 10000ms (10s) |
| Inventory | `init()` / `loadInventory()` | 5000ms (5s) |
| Shelves | `loadAll()` | 5000ms (5s) |
| User Management | `fetchUsers()` | 5000ms (5s) |
| Checker | `loadIncoming()` + `loadOutgoing()` | 5000ms (5s) |
| Incident Reports | `loadReports()` | 5000ms (5s) |
| POS Products | `loadProductsFromApi()` | 5000ms (5s) |
| Sales Report | `loadReport()` | 10000ms (10s) |
| Inventory Report | `loadReport()` | 10000ms (10s) |

### Which Functions Do NOT Get setInterval (USER ACTIONS only)

These are triggered by button clicks or form submissions. Never put them in setInterval:

- `saveItemToServer()` — Inventory save
- `saveShelf()` — Shelves save
- `saveUser()` — User save
- `toggleUser()` — User activate/deactivate
- `saveRecord()` — Checker save
- `updateIncident()` — Incident approve/reject/archive
- `confirmOrder()` — POS checkout
- Incident submit form handler

### Best Practice: Prevent Overlap

```javascript
var isLoading = false;

function loadData() {
  if (isLoading) return; // skip if previous request still running
  isLoading = true;

  $.ajax({
    url: '/api/endpoint.php',
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      // render...
    },
    error: function() {
      console.error('Load failed');
    },
    complete: function() {
      isLoading = false; // always reset
    }
  });
}

setInterval(loadData, 5000);
```

### Best Practice: Clear Interval on Page Leave

```javascript
var refreshInterval = setInterval(loadData, 5000);

// Stop polling when user leaves the page
window.addEventListener('beforeunload', function() {
  clearInterval(refreshInterval);
});

// Or stop when tab is hidden (saves resources)
document.addEventListener('visibilitychange', function() {
  if (document.hidden) {
    clearInterval(refreshInterval);
  } else {
    refreshInterval = setInterval(loadData, 5000);
    loadData(); // immediate refresh on return
  }
});
```

---

## 12. Tips and Warnings

### 1. Include jQuery

Before any AJAX code works, add jQuery to your layout/header:

```html
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
```

Or download it locally to `assets/js/vendor/jquery.min.js`.

### 2. CSRF / Session Handling

Your current code uses `credentials: 'same-origin'` in fetch. With jQuery AJAX, cookies are sent automatically for same-origin requests — no extra config needed.

### 3. File Upload (FormData)

For endpoints that accept file uploads (`/api/inventory/save.php`, `/api/incidents/submit.php`), always use:

```javascript
$.ajax({
  processData: false,
  contentType: false,
  data: formData  // FormData object
});
```

### 4. Error Handling Pattern

```javascript
$.ajax({
  // ...
  success: function(data) {
    if (!data.success) {
      // Server returned a business logic error
      alert(data.message || 'Operation failed.');
      return;
    }
    // Handle success
  },
  error: function(xhr, status, error) {
    // Network error or server 500
    var resp = xhr.responseJSON || {};
    alert(resp.message || 'Server error: ' + error);
  }
});
```

### 5. Don't Refresh While Modal/Form Is Open

When `setInterval` fires while a user is editing a form or modal is open, it can reset or cause flicker. Guard against this:

```javascript
function loadData() {
  // Skip refresh if user is actively editing
  if (document.querySelector('.modal.active, .modal.show')) return;
  // ... do ajax
}
```

### 6. Performance: Consider setTimeout Instead of setInterval

`setTimeout` is safer because it waits for the previous request to finish:

```javascript
function pollData() {
  $.ajax({
    url: '/api/endpoint.php',
    method: 'GET',
    dataType: 'json',
    success: function(data) {
      // render
    },
    complete: function() {
      // Schedule next poll AFTER this one finishes
      setTimeout(pollData, 5000);
    }
  });
}

// Start polling
pollData();
```

### 7. Files You Need to Edit (Complete List)

| # | File Path | What to Change |
|---|-----------|----------------|
| 1 | `assets/js/modules/dashboard/dashboard.js` | `fetchJson()`, `loadData()` |
| 2 | `assets/js/modules/inventory/inventory.js` | `init()`, `saveItemToServer()` |
| 3 | `assets/js/modules/shelves/shelves-admin.js` | `loadAll()`, `saveShelf()` |
| 4 | `assets/js/modules/user-management/user-management.js` | `fetchUsers()`, `saveUser()`, `toggleUser()` |
| 5 | `assets/js/modules/checker/checker.js` | `getJson()`, `loadIncoming()`, `loadOutgoing()`, `saveRecord()`, form handlers |
| 6 | `assets/js/modules/incident-report-admin/incident-reports.js` | `loadReports()`, `updateIncident()` |
| 7 | `assets/js/modules/incident-report-inventory/incident-form.js` | Submit handler (FormData) |
| 8 | `assets/js/modules/pos/data.js` | `loadProductsFromApi()` |
| 9 | `assets/js/modules/pos/receipt.js` | `confirmOrder()` |
| 10 | `assets/js/modules/sales-report/report.js` | `loadReport()` |
| 11 | `assets/js/modules/inventory-report/report.js` | `loadReport()` |

### 8. Recommended Conversion Order

1. Start with **User Management** (simplest CRUD, good practice)
2. Then **Shelves** (similar pattern)
3. Then **Inventory** (adds FormData complexity)
4. Then **Checker** (multiple sub-endpoints)
5. Then **Incident Reports** (approve/reject flow)
6. Then **POS** (checkout flow)
7. Then **Reports** (read-only, straightforward)
8. Finally **Dashboard** (multiple parallel requests, most complex)

---

End of readmeAJAX.
