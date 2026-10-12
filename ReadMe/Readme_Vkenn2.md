# CGMI IMS — Inventory Management System

**Calamba General Merchandise Inc.**
Hardware & Construction Supply Operations Platform

---

## Overview

CGMI IMS is a web-based business operations system that manages inventory, point-of-sale transactions, IoT shelf monitoring, incoming/outgoing stock verification, supplier management, incident reporting, and analytics for a hardware and construction supply store.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP (procedural, no framework) |
| Database | MySQL/MariaDB via PDO (utf8mb4) |
| Server | Apache (XAMPP on Windows) |
| Frontend | Server-side PHP templates |
| JavaScript | Vanilla JS + jQuery, modular IIFE pattern |
| HTTP | Axios, native fetch() |
| Charts | Highcharts |
| Tables | DataTables (jQuery plugin) |
| CSS | Custom design system (root.css) |
| Fonts | Google Fonts (Montserrat) |

---

## Getting Started

### Prerequisites

- XAMPP (Apache + MySQL/MariaDB)
- PHP 8.2+
- Web browser

### Installation

1. Clone or copy the project to `C:\xampp\htdocs\cgmi\`
2. Start Apache and MySQL via XAMPP Control Panel
3. Import the database schema:
   - Open phpMyAdmin (`http://localhost/phpmyadmin`)
   - Create database `cgmi_ims` (utf8mb4_general_ci)
   - Import `database/cgmi_ims.sql`
4. Access the application at `http://localhost/cgmi/`

### Database Credentials

- Host: `localhost`
- User: `root`
- Password: *(empty)*
- Database: `cgmi_ims`

Configured in `app/Core/Database.php`.

---

## Modules

### Dashboard
Admin overview with Highcharts visualizations for sales and inventory metrics.

### Inventory Management
Full CRUD for inventory items including:
- Auto-generated SKU (`ITM-XXXX`)
- Dynamic categories, subcategories, and units (loaded from DB)
- Image uploads
- IoT shelf integration (warehouse qty + shelf qty = overall qty)
- Weight-based monitoring and low-stock detection

### Point of Sale (POS)
Cashier checkout with:
- Product search and cart management
- Discount application
- Fulfillment type (pickup/delivery)
- Storage location (store/warehouse)

### Shelves (IoT)
Card-grid shelf management:
- Location layers: Top, Middle, Bottom
- Status: Unused, Occupied, Maintenance
- Weight thresholds (min/max) for discrepancy detection
- Item assignment with quantity tracking

### Checker
Stock verification workflow:
- **Incoming** — Log items arriving from suppliers (manual or auto)
- **Outgoing** — Auto-generated from POS sales
- **Confirmation** — Admin/inventory staff approve or reject pending incoming items, updating stock levels

### Supplier Management
CRUD with activation/deactivation toggle and field-level validation.

### Incident Reports
Submit, review, and archive incident reports with photo evidence uploads.

### User Management
Role-based user admin with account locking on failed login attempts.

### Reports
Inventory and sales reporting with data export capabilities.

---

## User Roles

| Role | Access |
|------|--------|
| admin | Full system access, all modules |
| cashier | POS module only |
| inventory | Inventory management, incidents, incoming confirmation |
| checker | Incoming/outgoing stock verification |

---

## Project Structure (Summary)

```
cgmi/
├── api/            # Public API route stubs (thin proxies)
├── pages/          # Public page route stubs (thin proxies)
├── app/
│   ├── Core/       # Shared utilities (Auth, DB, Helpers)
│   ├── Api/        # API handler implementations
│   ├── Views/      # HTML page templates
│   └── uploads/    # User-uploaded files
├── assets/
│   ├── css/        # Stylesheets (root.css + per-module)
│   ├── js/         # JavaScript (modular per-feature)
│   └── lib/        # Vendored libraries
├── database/       # Schema files & migrations
└── ...
```

See `.kiro/steering/structure.md` for the full detailed tree.

---

## Key Patterns

- **Two-layer routing**: Public stubs (`api/`, `pages/`) proxy to implementations in `app/`
- **API responses**: `json_response(['success' => bool, ...])` 
- **Auth**: `require_login(['role1', 'role2'])` per-endpoint
- **Frontend config**: PHP views set `window.CGMI_{MODULE}_API = { ... }` for JS modules
- **Dynamic DDL**: Checker module auto-migrates missing columns/tables at runtime
- **Stock movements**: All quantity changes logged for audit trail
- **Upsert references**: Categories, subcategories, and units auto-created if not found

---

## Database

Key tables: `users`, `inventory_items`, `categories`, `sub_categories`, `units`, `shelves`, `sales`, `sale_items`, `checker_records`, `stock_movements`, `supplier`, `incident_reports`

Schema files:
- `database/cgmi_ims.sql` — Full schema
- `database/shelves.sql` — Shelves table standalone
- `database/inventory_items.sql` — Inventory items standalone
- `database/migrations/` — Incremental migration scripts

---

## Author

**Vkenn2**

---

## License

Internal use — Calamba General Merchandise Inc.
