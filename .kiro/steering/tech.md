# Tech Stack

## Backend

- **Language:** PHP (procedural, no framework)
- **Database:** MySQL/MariaDB via PDO (utf8mb4 charset)
- **Server:** Apache (XAMPP on Windows)
- **Session:** Native PHP sessions for auth state

## Frontend

- **Rendering:** Server-side PHP templates (no SPA framework)
- **JavaScript:** Vanilla JS with jQuery for DOM manipulation, modular IIFE pattern
- **HTTP Requests:** Axios for AJAX calls; native `fetch()` for reference data loading
- **Charts:** Highcharts for dashboard visualizations
- **Tables:** DataTables (jQuery plugin) for sortable/searchable tables
- **Fonts:** Google Fonts (Montserrat)
- **CSS:** Custom CSS with a shared `root.css` design system (no preprocessor, no utility framework)

## Libraries (bundled in `assets/lib/`)

- jQuery
- Axios
- DataTables
- Highcharts

## Database

- MySQL/MariaDB database named `cgmi_ims`
- Schema file at `database/cgmi_ims.sql`
- Standalone table schemas: `database/shelves.sql`, `database/inventory_items.sql`, `database/ingoing.sql`, `database/ingoing_items.sql`, `database/warehouse_items.sql`
- Migrations in `database/migrations/`
- PDO with `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, prepared statements

### Key Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts and roles |
| `inventory_items` | Product catalog with stock quantities |
| `categories` | Item categories |
| `sub_categories` | Subcategories linked to categories |
| `units` | Measurement units |
| `shelves` | IoT shelf definitions (location, weight, capacity) |
| `warehouse_items` | Warehouse stock quantities per item |
| `store_items` | Store stock quantities per item (mirrors `warehouse_items`) |
| `sales` / `sale_items` | POS transaction records |
| `ingoing` | Incoming delivery headers (supplier, `ingoing_status` Pending/Checked roll-up, notes, creator) |
| `ingoing_items` | Per-item line items for incoming deliveries (qty, condition, `ingoing_item_status`, confirmer, confirmation notes) |
| `outgoing` | Manual outgoing transaction headers (purpose once per record, `status` Pending/Checked roll-up) |
| `outgoing_items` | Per-item outgoing line items (qty, `source_location` Warehouse/Store, `outgoing_item_status`, confirmer) |
| `checker_records` | Legacy POS outgoing verification records (from POS warehouse sales) |
| `checker_idempotency` | Duplicate-submission protection — stores processed idempotency tokens |
| `stock_movements` | Audit log of all quantity changes |
| `supplier` | Supplier master data (camelCase legacy naming) |
| `incident_reports` | Incident tracking with photo uploads |

## Common Patterns

- API endpoints return JSON via `json_response(['success' => bool, ...])` helper
- Input parsed via `get_json_input()` (reads `php://input` or falls back to `$_POST`)
- Authentication checked per-endpoint with `require_login(['role1', 'role2'])`
- HTML output escaped with `h()` helper (htmlspecialchars wrapper)
- SKU format: `ITM-XXXX` (zero-padded sequential)
- Sale number format: `SALE-YYYYMMDD-XXXX`

### Frontend API URL Configuration

JS modules receive their API endpoint URLs via global window objects set by the PHP view:

```javascript
window.CGMI_INVENTORY_API = { list: '...', save: '...', categories: '...', units: '...' };
window.CGMI_SHELVES_API   = { list: '...', save: '...' };
```

### Dynamic DDL / Auto-Migration

The checker module uses a runtime DDL pattern via `ensure_checker_tables()` in `app/Api/checker/common.php`. On every request it:
1. Checks if required columns/tables exist using `information_schema`
2. Creates missing tables or adds missing columns automatically
3. Converts column types if needed (e.g., ENUM → VARCHAR)

This allows the app to self-heal against older database states without requiring manual migrations.

### Upsert Pattern for Reference Data

The inventory module auto-creates categories, subcategories, and units if they don't exist:
- `resolve_category_id($pdo, $name)` — Find or create category
- `resolve_subcategory_id($pdo, $name, $categoryId)` — Find or create subcategory
- `resolve_unit_id($pdo, $name)` — Find or create unit

### Validation Error Pattern

Suppliers (and similar modules) return structured field-level errors:
```json
{"success": false, "errors": {"fieldName": "Error message"}}
```

### Stock Movement Logging

All inventory quantity changes are recorded in `stock_movements` table via `checker_record_stock_movement()`. Records include: item, type, quantity before/after, reference type/id, and user. Failures are silently caught to avoid blocking the primary operation.

## Running the Application

- Requires XAMPP (Apache + MySQL) running
- Accessible at `http://localhost/cgmi/`
- No build step for frontend — plain CSS and JS served directly
- Database credentials hardcoded in `app/Core/Database.php` (localhost, root, no password)
- Import `database/cgmi_ims.sql` to set up the schema
- Additional standalone schemas in `database/` (shelves.sql, inventory_items.sql) for reference
