# Project Structure

```
cgmi/
├── index.php                  # Login page (entry point)
├── index.html                 # Static fallback
│
├── api/                       # Public API route stubs (thin proxies)
│   ├── auth/                  #   login, logout, me
│   ├── checker/               #   incoming, outgoing, confirm-incoming, confirm-outgoing, pending-incoming, rechecking, recheck-submit, items, save, suppliers
│   ├── dashboard/             #   summary
│   ├── incidents/             #   list, submit, review
│   ├── inventory/             #   list, save, categories, units
│   ├── pos/                   #   checkout, products
│   ├── reports/               #   sales/inventory summary & export
│   ├── shelves/               #   list, save
│   ├── suppliers/             #   list, save, delete, toggle
│   └── users/                 #   list, save, toggle
│
├── pages/                     # Public page route stubs (thin proxies)
│   ├── admin/                 #   dashboard, inventory, shelves, reports, users, incidents, suppliers
│   ├── cashier/               #   pos
│   ├── checker/               #   checker
│   └── inventory/             #   inventory, incidents
│
├── app/                       # Application logic (private)
│   ├── Core/                  #   Shared utilities
│   │   ├── App.php            #     Base path & URL helpers (app_url())
│   │   ├── Auth.php           #     require_login(), role routing
│   │   ├── Database.php       #     PDO connection ($pdo)
│   │   ├── Helpers.php        #     json_response, get_json_input, h(), current_user
│   │   ├── Redirect.php       #     redirect_if_logged_in
│   │   └── Session.php        #     session_start wrapper
│   ├── Api/                   #   API handler implementations
│   │   ├── auth/              #     login, logout, me
│   │   ├── checker/           #     common, incoming, outgoing, confirm-incoming, confirm-outgoing, pending-incoming, rechecking, recheck-submit, items, save, suppliers
│   │   ├── dashboard/         #     summary
│   │   ├── incidents/         #     common, list, submit, review
│   │   ├── inventory/         #     common, list, save, categories, units
│   │   ├── pos/               #     common, checkout, products
│   │   ├── reports/           #     inventory_summary, inventory_export, sales_summary, sales_export
│   │   ├── shelves/           #     common, list, save
│   │   ├── suppliers/         #     common, list, save, delete, toggle
│   │   └── users/             #     common, list, save, toggle
│   ├── Views/                 #   Full HTML page templates
│   │   ├── admin/
│   │   ├── cashier/
│   │   ├── checker/
│   │   ├── inventory/
│   │   └── shared/            #   Shared views (incoming-confirmation.php used by admin & inventory)
│   └── uploads/               #   User-uploaded files (incidents, etc.)
│
├── assets/                    # Frontend static assets
│   ├── css/                   #   Stylesheets (root.css + per-module dirs)
│   ├── js/                    #   JavaScript (per-module dirs + components)
│   │   ├── auth/              #     Login page JS
│   │   ├── components/        #     Shared components (sidebar.js)
│   │   ├── modules/           #     Per-module JS (see below)
│   │   └── index.js           #     Global entry
│   └── lib/                   #   Third-party libraries (axios, jquery, etc.)
│
├── database/                  # Database schema & migrations
│   ├── cgmi_ims.sql           #   Full schema dump
│   ├── shelves.sql            #   Standalone shelves table schema
│   ├── inventory_items.sql    #   Standalone inventory_items table schema
│   ├── warehouse_items.sql    #   Standalone warehouse_items table schema
│   ├── ingoing.sql            #   Incoming delivery headers table schema
│   ├── ingoing_items.sql      #   Incoming delivery line items table schema
│   └── migrations/            #   Incremental migration scripts
│
├── includes/                  # Legacy include wrappers (proxy to app/Core)
├── config/                    # Configuration files
├── storage/                   # Application storage
├── uploads/                   # Public uploads directory
└── legacy/                    # Deprecated/old code
```

## JavaScript Module Organization

```
assets/js/modules/
├── admin/                     # Admin-specific JS
├── checker/                   # checker.js, incoming-confirmation.js
├── dashboard/                 # Dashboard charts
├── incident-report-admin/     # Admin incident views
├── incident-report-inventory/ # Inventory role incident views
├── inventory/                 # Multi-file modular architecture
│   ├── data.js                #   Dynamic reference data loader (categories, units)
│   ├── category.js            #   Category/subcategory dropdown management
│   ├── inventory.js           #   InventoryManager IIFE (CRUD operations)
│   ├── filter.js              #   FilterManager IIFE (search, tabs, sort)
│   ├── modal.js               #   ModalManager IIFE (add/edit modal, image upload)
│   ├── app.js                 #   Main orchestrator + DetailsPanelManager + toast/confirm
│   └── datatable-init.js      #   DataTable initialization
├── inventory-report/          # Inventory reporting
├── pos/                       # POS module
├── sales-report/              # Sales reporting
├── shelves/                   # shelves-admin.js (card-grid UI)
└── user-management/           # User admin
```

## Routing Pattern

The project uses a two-layer routing approach:

1. **Public stubs** (`api/` and `pages/`) — Single-line files that `require` the real implementation
2. **Implementations** (`app/Api/` and `app/Views/`) — Actual logic, hidden from direct URL guessing

Example: `api/inventory/list.php` → requires → `app/Api/inventory/list.php`

## Module Organization

Each API module follows this pattern:
- `common.php` — Shared includes (Database, Helpers, Auth), role check via `require_login()`, module-specific helper functions, data transformation helpers (`*_to_frontend()`)
- `{action}.php` — Individual endpoint logic (requires `common.php`)

## Frontend JS Module Pattern

Each JS module directory uses one or more of these patterns:
- **IIFE modules** — `const ModuleName = (function() { ... })();` for encapsulation
- **Global API config** — Endpoints passed from PHP views via `window.CGMI_{MODULE}_API = { ... }`
- **Reference data** — Loaded once at startup via `loadReferenceData()` into globals (`CATEGORIES`, `UNITS`)
- **No bundler** — Scripts loaded sequentially via `<script>` tags in view files

## Conventions

- Public-facing files are thin proxies with a single `require` statement
- Business logic lives exclusively in `app/`
- Each View file handles its own auth check at the top
- CSS and JS are organized in directories matching the role/module they serve
- No package manager (npm/composer) — libraries are vendored in `assets/lib/`
- API helper functions prefixed by module name (e.g., `checker_record_to_frontend()`, `shelf_to_frontend()`)
- `FormData` used for endpoints that accept file uploads (inventory images, incident photos)
