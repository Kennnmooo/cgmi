# CGMI - Calamba General Merchandise Inc. Inventory Management System

A PHP-based inventory management system with Point of Sale (POS), checker verification, incident reporting, and IoT shelf tracking. Runs on XAMPP (Apache + MySQL).

---

## Table of Contents

- [Project Overview](#project-overview)
- [Folder Structure](#folder-structure)
- [Architecture & Routing](#architecture--routing)\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
- [Core System Files](#core-system-files)
- [Modules](#modules)
  - [Authentication](#1-authentication)
  - [Dashboard](#2-dashboard)
  - [Inventory Management](#3-inventory-management)
  - [Point of Sale (POS)](#4-point-of-sale-pos)
  - [Checker (Incoming/Outgoing Verification)](#5-checker-incomingoutgoing-verification)
  - [Shelves (IoT Shelf Tracking)](#6-shelves-iot-shelf-tracking)
  - [Incident Reports](#7-incident-reports)
  - [Reports (Sales & Inventory)](#8-reports-sales--inventory)
  - [User Management](#9-user-management)
- [Database](#database)
- [Frontend Assets](#frontend-assets)
- [User Roles](#user-roles)
- [Common Change Scenarios](#common-change-scenarios)

---

## Project Overview

| Item | Value |
|------|-------|
| Language | PHP (no framework, procedural) |
| Database | MySQL (`cgmi_ims`) via PDO |
| Server | Apache (XAMPP) |
| Frontend Libraries | jQuery, Axios, DataTables, Highcharts |
| Auth | Session-based with role enforcement |
| URL base | `http://localhost/cgmi/` |

---

## Folder Structure

```
cgmi/
├── index.php                   # Login page (entry point)
├── config/
│   └── database.php            # Database credentials (host, dbname, user, pass)
├── includes/
│   └── redirect.php            # Shim: loads app/Core/Redirect.php
│
├── app/                        # APPLICATION LOGIC (backend)
│   ├── Core/                   # Shared system files
│   │   ├── App.php             # app_url(), app_base_path() helpers
│   │   ├── Auth.php            # require_login(), get_home_for_role()
│   │   ├── Database.php        # PDO connection ($pdo global)
│   │   ├── Helpers.php         # json_response(), get_json_input(), current_user()
│   │   ├── Redirect.php        # redirect_if_logged_in()
│   │   └── Session.php         # session_start() wrapper
│   │
│   ├── Api/                    # API endpoint implementations
│   │   ├── auth/               # Login, logout, me
│   │   ├── checker/            # Incoming/outgoing verification
│   │   ├── dashboard/          # Admin dashboard summary data
│   │   ├── incidents/          # Incident report CRUD
│   │   ├── inventory/          # Inventory item CRUD
│   │   ├── pos/                # POS products & checkout
│   │   ├── reports/            # Sales & inventory reports
│   │   ├── shelves/            # IoT shelf management
│   │   └── users/              # User account CRUD
│   │
│   ├── Views/                  # Legacy view templates (being migrated to pages/)
│   └── uploads/                # Uploaded files (incident images)
│
├── api/                        # PUBLIC API PROXIES (thin wrappers)
│   ├── auth/login.php          # → requires app/Api/auth/login.php
│   ├── checker/save.php        # → requires app/Api/checker/save.php
│   ├── ... (mirrors app/Api/ structure)
│
├── pages/                      # FRONTEND PAGES (HTML views per role)
│   ├── admin/                  # Admin-only pages
│   │   ├── dashboard.php
│   │   ├── inventory-admin.php
│   │   ├── user-management.php
│   │   ├── shelves-admin.php
│   │   ├── sales-report.php
│   │   ├── inventory-report.php
│   │   ├── incident-report.php
│   │   └── incoming-confirmation.php
│   ├── cashier/
│   │   └── pos.php             # POS terminal interface
│   ├── checker/
│   │   └── checker.php         # Checker verification panel
│   └── inventory/
│       ├── inventory.php       # Inventory staff view
│       ├── shelves.php         # Shelf management
│       ├── incident-report.php # Submit incidents
│       └── incoming-confirmation.php
│
├── assets/                     # FRONTEND ASSETS
│   ├── css/
│   │   ├── root.css            # Global CSS variables & base styles
│   │   ├── index.css           # Login page styles
│   │   ├── admin/              # Admin-specific styles
│   │   ├── cashier/            # POS styles
│   │   ├── checker/            # Checker styles
│   │   ├── inventory-staff/    # Inventory staff styles
│   │   ├── global/             # Shared component styles
│   │   └── modules/            # Per-module styles
│   ├── js/
│   │   ├── index.js            # Login page JS
│   │   ├── modules/            # Per-module JavaScript
│   │   │   ├── dashboard/
│   │   │   ├── pos/
│   │   │   ├── checker/
│   │   │   ├── inventory/
│   │   │   ├── shelves/
│   │   │   ├── user-management/
│   │   │   ├── incident-report-admin/
│   │   │   ├── incident-report-inventory/
│   │   │   ├── sales-report/
│   │   │   └── inventory-report/
│   │   ├── components/         # Reusable JS components
│   │   └── global/             # Global JS utilities
│   └── lib/                    # Third-party libraries
│       ├── axios/              # HTTP client (API calls)
│       ├── jquery/             # DOM manipulation
│       ├── dataTables/         # Table rendering & pagination
│       └── highCharts/         # Charts & graphs
│
├── database/
│   ├── cgmi_ims.sql            # Full database schema dump
│   └── migrations/             # Schema migration scripts
│
├── uploads/                    # Public upload storage
├── storage/                    # App storage
├── legacy/                     # Old/deprecated code
└── docs/                       # Documentation
```

---

## Architecture & Routing

This project uses **file-based routing** (no router framework). Apache serves PHP files directly:

```
Browser Request              →  File Served
─────────────────────────────────────────────────────
GET /cgmi/index.php          →  index.php (login page)
POST /cgmi/api/auth/login.php →  api/auth/login.php → app/Api/auth/login.php
GET /cgmi/pages/admin/dashboard.php → pages/admin/dashboard.php
POST /cgmi/api/pos/checkout.php → api/pos/checkout.php → app/Api/pos/checkout.php
```

### The Proxy Pattern

The `api/` folder contains **thin proxy files** that just `require` the real implementation from `app/Api/`:

```php
// api/auth/login.php (PUBLIC - what the browser calls)
<?php
require __DIR__ . '/../../app/Api/auth/login.php';
```

```php
// app/Api/auth/login.php (IMPLEMENTATION - actual logic lives here)
<?php
require_once __DIR__ . '/../../Core/Database.php';
// ... full implementation
```

**When you need to change API logic, always edit files in `app/Api/`, NOT in `api/`.**

### The `common.php` Pattern

Each API module has a `common.php` file that:
1. Includes Core dependencies (Database, Auth, Helpers)
2. Calls `require_login([...roles...])` to enforce access
3. Defines shared helper functions for that module

Other files in the module (list.php, save.php, etc.) just `require_once 'common.php'` at the top.

---

## Core System Files

### `app/Core/Database.php`
**Purpose:** Creates the PDO database connection as a global `$pdo` variable.

- Reads credentials from `config/database.php`
- Uses `utf8mb4` charset
- Sets PDO to throw exceptions on errors

**Change here if:** You need to modify database connection settings, switch to a different DB, or update credentials.

### `app/Core/Auth.php`
**Purpose:** Authentication and authorization guard.

- `require_login(?array $roles)` — Redirects to login if not authenticated; enforces role access
- `get_home_for_role(string $role)` — Maps role → default dashboard URL

**Change here if:** You add a new user role, change role-based redirects, or modify auth behavior.

### `app/Core/App.php`
**Purpose:** URL generation utilities.

- `app_base_path()` — Auto-detects the project's base URL path (e.g., `/cgmi`)
- `app_url(string $path)` — Generates full URL relative to project root

**Change here if:** You move the project to a different folder or domain, or need to change URL generation logic.

### `app/Core/Helpers.php`
**Purpose:** Shared utility functions used across all API endpoints.

- `json_response(array $payload, int $status)` — Sends JSON response and exits
- `get_json_input()` — Parses JSON request body
- `current_user()` — Returns the logged-in user from session
- `h(string)` — HTML entity escaping
- `sanitize_filename(string)` — Cleans filenames for uploads

**Change here if:** You need to add a globally-available utility function.

### `app/Core/Session.php`
**Purpose:** Starts PHP session if not already started. Included by Auth.php and Helpers.php.

### `app/Core/Redirect.php`
**Purpose:** `redirect_if_logged_in()` — Used on the login page to skip login if already authenticated.

### `config/database.php`
**Purpose:** Database credentials (host, dbname, username, password).

```php
$host = 'localhost';
$dbname = 'cgmi_ims';
$username = 'root';
$password = '';
```

**Change here if:** You need to connect to a different database server or change credentials.

---

## Modules

### 1. Authentication

| File | Purpose |
|------|---------|
| `app/Api/auth/login.php` | Handles login POST: validates credentials, locks after 5 failed attempts for 15min, sets session |
| `app/Api/auth/logout.php` | Destroys session |
| `app/Api/auth/me.php` | Returns current logged-in user info |
| `index.php` | Login page HTML/form |
| `assets/js/index.js` | Login form JavaScript |

**Change here if:** You want to modify login behavior, account locking policy, session handling, or add features like "remember me".

---

### 2. Dashboard

| File | Purpose |
|------|---------|
| `app/Api/dashboard/summary.php` | Aggregates: active items, stock value, low stock alerts, today/month sales, recent transactions |
| `pages/admin/dashboard.php` | Admin dashboard HTML page |
| `assets/js/modules/dashboard/` | Dashboard JavaScript (charts, data loading) |

**Change here if:** You want to add/remove dashboard metrics, modify how summaries are calculated, or change the dashboard layout.

---

### 3. Inventory Management

| File | Purpose |
|------|---------|
| `app/Api/inventory/common.php` | Shared logic: auto-schema migration, `inventory_item_to_frontend()`, `next_sku()`, `ensure_category()`, stock movement recording |
| `app/Api/inventory/list.php` | GET: Lists inventory items with filtering |
| `app/Api/inventory/save.php` | POST: Create/update inventory items |
| `pages/admin/inventory-admin.php` | Admin inventory view |
| `pages/inventory/inventory.php` | Inventory staff view |
| `assets/js/modules/inventory/` | Inventory JavaScript |

**Key concepts:**
- Items have SKUs like `ITM-0001`
- Items track `shelf_tracking` (shelves vs. not_shelves) for IoT integration
- `shelf_quantity` vs `quantity` distinguishes what's on shelves vs. total stock
- `estimated_unit_weight_kg` enables weight-based IoT shelf comparison
- Categories are auto-created if they don't exist

**Change here if:** You want to add inventory fields, modify SKU format, change how stock is tracked, or adjust the item data structure.

---

### 4. Point of Sale (POS)

| File | Purpose |
|------|---------|
| `app/Api/pos/common.php` | Shared: `pos_product_row_to_frontend()`, `next_sale_number()`, `record_stock_movement()` |
| `app/Api/pos/products.php` | GET: Active products with stock > 0 for POS display |
| `app/Api/pos/checkout.php` | POST: Full checkout transaction (validates cart, deducts stock, creates sale record) |
| `pages/cashier/pos.php` | POS terminal interface |
| `assets/js/modules/pos/` | POS JavaScript (cart, scanning, checkout flow) |

**Key concepts:**
- Sale numbers: `SALE-20260804-0001` format
- Checkout uses database transactions with `FOR UPDATE` locks to prevent overselling
- Supports `fulfillment_type` (pickup/delivery) and `storage_location` (store/warehouse)
- Stock movements are recorded for audit trail

**Change here if:** You want to modify the checkout process, add payment methods, change sale number format, or adjust POS product display.

---

### 5. Checker (Incoming/Outgoing Verification)

| File | Purpose |
|------|---------|
| `app/Api/checker/common.php` | Shared: ensures `checker_records`/`ingoing`/`ingoing_items` tables exist, record formatting, inventory item lookup, `load_ingoing_items_flat()` |
| `app/Api/checker/incoming.php` | GET: Lists incoming delivery records (grouped by delivery) |
| `app/Api/checker/outgoing.php` | GET: Lists outgoing records (from POS sales) |
| `app/Api/checker/save.php` | POST: Create incoming deliveries (ingoing + ingoing_items) or outgoing records |
| `app/Api/checker/confirm-incoming.php` | POST: Confirm/reject individual items (per ingoing_item_id, updates warehouse stock for "Accepted / Good" items only) |
| `app/Api/checker/pending-incoming.php` | GET: Flat list of pending/confirmed/rejected items for confirmation page |
| `app/Api/checker/items.php` | GET: Available inventory items for checker dropdown |
| `pages/checker/checker.php` | Checker interface |
| `pages/admin/incoming-confirmation.php` | Admin incoming confirmation page |
| `pages/inventory/incoming-confirmation.php` | Inventory incoming confirmation page |
| `app/Views/shared/incoming-confirmation.php` | Shared confirmation view (used by both admin & inventory roles) |
| `assets/js/modules/checker/` | Checker JavaScript (checker.js, incoming-confirmation.js) |

**Key concepts:**
- Incoming deliveries use a header/detail pattern: `ingoing` (header) + `ingoing_items` (line items)
- Each item has a condition: `Accepted / Good`, `Damaged`, `Incomplete / Missing`
- Each item is approved/rejected individually (per-item confirmation)
- Only items with condition "Accepted / Good" get their quantity added to `warehouse_items` on approval
- Damaged/Incomplete items are confirmed without stock update — they return to checker for re-checking
- Parent `ingoing` header status auto-syncs when all items are processed
- Outgoing records use `checker_records` table (from POS or manual)
- Records have `record_source` (manual/pos) to distinguish origin

**Change here if:** You want to modify the verification workflow, add new conditions, change the per-item approval process, or adjust how stock is updated.

---

### 6. Shelves (IoT Shelf Tracking)

| File | Purpose |
|------|---------|
| `app/Api/shelves/common.php` | Shared: shelf-to-frontend formatting |
| `app/Api/shelves/list.php` | GET: Lists shelves with assigned inventory items |
| `app/Api/shelves/save.php` | POST: Create/update/assign shelves |
| `pages/admin/shelves-admin.php` | Admin shelf management |
| `pages/inventory/shelves.php` | Inventory staff shelf view |
| `assets/js/modules/shelves/` | Shelves JavaScript |

**Key concepts:**
- Shelves represent physical IoT-connected shelf units
- Items can be assigned to shelves (`shelf_id` in inventory_items)
- Weight comparison: expected weight vs. actual IoT-reported weight
- `shelf_quantity` on both `shelves` and `inventory_items` tables

**Change here if:** You want to modify shelf assignment logic, add shelf properties, or change the IoT weight comparison.

---

### 7. Incident Reports

| File | Purpose |
|------|---------|
| `app/Api/incidents/common.php` | Shared: column detection, incident number generation, formatting |
| `app/Api/incidents/list.php` | GET: Lists incident reports |
| `app/Api/incidents/submit.php` | POST: Submit new incident with optional image upload |
| `app/Api/incidents/review.php` | POST: Admin reviews/approves incidents |
| `pages/admin/incident-report.php` | Admin incident review page |
| `pages/inventory/incident-report.php` | Inventory staff incident submission |
| `assets/js/modules/incident-report-admin/` | Admin incident JS |
| `assets/js/modules/incident-report-inventory/` | Inventory incident JS |

**Key concepts:**
- Incident numbers: auto-generated sequential
- Supports image uploads (JPG, PNG, WEBP; max 5MB)
- Images stored in `uploads/incidents/`
- Status workflow: `pending` → `reviewed`/`resolved`/`dismissed`

**Change here if:** You want to change the incident workflow, add fields, modify upload rules, or change the review process.

---

### 8. Reports (Sales & Inventory)

| File | Purpose |
|------|---------|
| `app/Api/reports/sales_summary.php` | Sales report with date range, daily totals, top items |
| `app/Api/reports/sales_export.php` | Sales data export (CSV/Excel) |
| `app/Api/reports/inventory_summary.php` | Inventory report with stock levels, categories |
| `app/Api/reports/inventory_export.php` | Inventory data export |
| `pages/admin/sales-report.php` | Sales report page |
| `pages/admin/inventory-report.php` | Inventory report page |
| `assets/js/modules/sales-report/` | Sales report JavaScript (charts via Highcharts) |
| `assets/js/modules/inventory-report/` | Inventory report JavaScript |

**Change here if:** You want to add new report types, modify report calculations, add export formats, or change chart visualizations.

---

### 9. User Management

| File | Purpose |
|------|---------|
| `app/Api/users/common.php` | Shared: validation, user-to-frontend formatting |
| `app/Api/users/list.php` | GET: Lists all users |
| `app/Api/users/save.php` | POST: Create/update user accounts (passwords hashed with bcrypt) |
| `app/Api/users/toggle.php` | POST: Activate/deactivate user accounts |
| `pages/admin/user-management.php` | User management page |
| `assets/js/modules/user-management/` | User management JavaScript |

**Key concepts:**
- Passwords hashed with `password_hash(PASSWORD_DEFAULT)` (bcrypt)
- Users have roles: admin, cashier, inventory, checker
- Accounts can be toggled active/inactive
- Failed login tracking with account lock-out

**Change here if:** You want to add new roles, modify user fields, change password policy, or adjust user validation rules.

---

## Database

- **Database name:** `cgmi_ims`
- **Schema file:** `database/cgmi_ims.sql` (full dump for fresh install)
- **Migrations:** `database/migrations/` (incremental schema changes)

### Key Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts (id, full_name, username, password_hash, role, is_active, failed_attempts, locked_until) |
| `categories` | Product categories |
| `inventory_items` | Stock items (sku, item_name, price, shelf_id, shelf_quantity, shelf_tracking, unit, etc.) |
| `warehouse_items` | Warehouse stock quantities per inventory item (item_id, warehouse_item_quantity) |
| `shelves` | IoT shelf units (shelf_name, level_label, shelf_quantity) |
| `sales` | Sale transactions (sale_number, cashier, totals, fulfillment_type, storage_location) |
| `sale_items` | Individual line items per sale |
| `ingoing` | Incoming delivery headers (supplier_id, status, created_by, checked_by) |
| `ingoing_items` | Per-item line items for deliveries (item_id, quantity, condition, per-item approval status, confirmed_by) |
| `checker_records` | Outgoing stock verification records (from POS/manual) |
| `stock_movements` | Audit trail of all stock changes (quantity_before, quantity_after, movement_type) |
| `supplier` | Supplier master data (camelCase legacy naming) |
| `incident_reports` | Incident report submissions |

### Auto-Migration Pattern

The system auto-creates missing columns at runtime. Files like `app/Api/inventory/common.php` and `app/Api/checker/common.php` check for column existence using `information_schema` and add columns with `ALTER TABLE` if missing. This means the app self-heals schema differences without needing manual migrations.

---

## Frontend Assets

### CSS Structure
- `assets/css/root.css` — CSS variables, global resets, shared styles
- `assets/css/index.css` — Login page specific styles
- `assets/css/modules/` — Per-module stylesheets
- `assets/css/global/` — Shared component styles (modals, tables, forms)

### JavaScript Structure
- `assets/js/index.js` — Login page logic
- `assets/js/modules/{module}/` — Module-specific JS (one folder per feature)
- `assets/js/components/` — Reusable UI components
- `assets/js/global/` — Shared utilities

### Third-Party Libraries (`assets/lib/`)
- **Axios** — HTTP requests (API calls from frontend)
- **jQuery** — DOM manipulation
- **DataTables** — Table rendering, sorting, pagination, search
- **Highcharts** — Charts and graphs (reports, dashboard)

---

## User Roles

| Role | Access | Home Page |
|------|--------|-----------|
| `admin` | Full system access (all modules + user management + reports) | `/pages/admin/dashboard.php` |
| `cashier` | POS terminal only | `/pages/cashier/pos.php` |
| `inventory` | Inventory management, shelves, incident reports | `/pages/inventory/inventory.php` |
| `checker` | Incoming/outgoing verification | `/pages/checker/checker.php` |

---

## Common Change Scenarios

### "I want to add a new field to inventory items"
1. Add the column to `database/cgmi_ims.sql` (for fresh installs)
2. Add auto-migration in `app/Api/inventory/common.php` (look at `ensure_inventory_shelf_tracking_column()`)
3. Add the field to `inventory_item_to_frontend()` in `app/Api/inventory/common.php`
4. Update `app/Api/inventory/save.php` to accept the new field
5. Update the frontend form in `pages/admin/inventory-admin.php` or `pages/inventory/inventory.php`
6. Update JS in `assets/js/modules/inventory/`

### "I want to add a new user role"
1. Update `app/Core/Auth.php` → add role to `get_home_for_role()`
2. Create a new page in `pages/{role}/`
3. Add role to `require_login()` calls in relevant API files
4. Update `app/Api/users/common.php` validation to allow the new role value

### "I want to change the database credentials"
1. Edit `config/database.php`

### "I want to modify the login page appearance"
1. HTML: `index.php`
2. CSS: `assets/css/index.css`
3. JS: `assets/js/index.js`

### "I want to add a new API endpoint"
1. Create implementation in `app/Api/{module}/{endpoint}.php`
2. Include `common.php` from the module (or require Core files directly)
3. Create a thin proxy in `api/{module}/{endpoint}.php` that requires the implementation
4. Call it from JS: `axios.post('/cgmi/api/{module}/{endpoint}.php', data)`

### "I want to modify the dashboard cards/metrics"
1. Backend data: `app/Api/dashboard/summary.php`
2. Frontend display: `pages/admin/dashboard.php`
3. JavaScript logic: `assets/js/modules/dashboard/`

### "I want to change how stock deduction works during checkout"
1. Edit `app/Api/pos/checkout.php` — the transaction logic is all in one file

### "I want to add a new report"
1. Create `app/Api/reports/my_report.php`
2. Create proxy `api/reports/my_report.php`
3. Create page `pages/admin/my-report.php`
4. Create JS `assets/js/modules/my-report/`
5. Create CSS `assets/css/modules/my-report/`

### "I want to change the IoT shelf weight comparison logic"
1. Edit `inventory_item_to_frontend()` in `app/Api/inventory/common.php` — look for `weightMatchStatus`

### "I want to change how the checker approval flow works"
1. Edit `app/Api/checker/confirm-incoming.php` for per-item confirmation logic (condition-based restocking)
2. Edit `app/Api/checker/common.php` for record formatting, table schema, and `load_ingoing_items_flat()`
3. Edit `assets/js/modules/checker/incoming-confirmation.js` for the confirmation UI
4. Edit `app/Views/shared/incoming-confirmation.php` for the shared confirmation page layout

---

## Development Notes

- **No framework** — Pure PHP with procedural style. No Composer, no autoloading.
- **No .htaccess routing** — URLs map directly to PHP files.
- **Global `$pdo`** — Database connection is a global variable created when you `require` Database.php.
- **Self-healing schema** — The app adds missing DB columns automatically at runtime.
- **Session-based auth** — No tokens/JWT. Login sets `$_SESSION['user']`, every API checks it.
- **All API responses** are JSON via `json_response()` helper.
