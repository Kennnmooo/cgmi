# Product Overview

CGMI IMS (Calamba General Merchandise Inc. Inventory Management System) is a web-based business operations platform for a hardware and construction supply store.

## Key Modules

- **Dashboard** — Admin overview with sales/inventory charts (Highcharts), summary statistics
- **Inventory Management** — CRUD for inventory items with dynamic categories/subcategories/units, image uploads, IoT shelf integration, weight-based monitoring, stock quantity split (warehouse vs. shelf)
- **Point of Sale (POS)** — Cashier checkout with cart, discounts, fulfillment type (pickup/delivery), and storage location (store/warehouse)
- **Shelves** — IoT-enabled shelf management with card-grid UI; tracks shelf quantities, weight thresholds (min/max), location layers (Top/Middle/Bottom), low-stock detection, and compares against inventory records
- **Checker** — Incoming/outgoing stock verification workflow:
  - **Incoming** — Checker logs deliveries with supplier and per-item condition (Accepted/Good, Damaged, Incomplete/Missing) using `ingoing` + `ingoing_items` header/line-item tables
  - **Outgoing** — Manual outgoing entries use `outgoing` + `outgoing_items` header/line-item tables; purpose chosen once per transaction, source location per item; POS warehouse sales still use `checker_records`
  - **Confirmation** — Admin/inventory users approve or reject each item individually; only "Accepted / Good" items update warehouse stock; stock deducted from the item's source location (Warehouse/Store) on outgoing approval
  - **Re-checking** — Damaged or Incomplete incoming items are confirmed without stock change and routed to a "For Re-checking" tab; checker updates condition/notes to reset the item to Pending for re-approval
- **Incident Reports** — Submit, review, archive, and manage incident reports with photo uploads
- **Supplier Management** — CRUD for suppliers with activation/deactivation toggle, field-level validation, used as FK reference in checker records
- **User Management** — Role-based user admin with account locking on failed login attempts
- **Reports** — Inventory and sales reporting with export capabilities

## User Roles

| Role       | Access                                              |
|------------|-----------------------------------------------------|
| admin      | Full system access, all modules, confirmation workflow |
| cashier    | POS module only                                     |
| inventory  | Inventory management, incident reports, incoming confirmation |
| checker    | Incoming/outgoing stock verification                |

## Business Context

- Store name: Calamba General Merchandise Inc.
- Industry: Hardware & Construction Supply
- Database: `cgmi_ims`

## Key Workflows

### Inventory Item Lifecycle
1. Item created with SKU (auto-generated `ITM-XXXX`), category, subcategory, unit, price, weight
2. Optionally assigned to an IoT shelf for monitoring
3. Stock updated via POS sales (decrease) or confirmed incoming checker records (increase)
4. All quantity changes logged in `stock_movements` table

### Checker Confirmation Flow (Per-Item)
1. Checker staff logs incoming delivery with supplier and item line-items
2. Each item has a condition: `Accepted / Good`, `Damaged`, or `Incomplete / Missing`
3. Each item enters `Pending` approval status independently
4. Admin/inventory staff reviews each item individually on the confirmation page
5. On **approve** (condition = Accepted / Good): warehouse quantity increased, stock movement recorded
6. On **approve** (condition = Damaged or Incomplete / Missing): item confirmed but NO stock added — returns to checker for re-checking
7. On **reject**: item marked rejected with notes, no quantity change
8. When all items in a delivery are processed, parent delivery header auto-updates its status

### Shelf Monitoring
1. Shelves configured with item assignment, location layer, weight thresholds
2. IoT shelf quantity tracked alongside warehouse quantity
3. Overall stock = warehouse qty + shelf qty
4. Low-stock flag triggered based on thresholds
