# CGMI IMS — Latest Update Notes

This document covers all significant changes made to the system since the previous stable state.

---

## Summary

The main focus of this update was a full rework of the **Checker module** — both incoming and outgoing workflows were redesigned to use a proper header + line-item table architecture with per-item confirmation, pagination, better UX modals, and a re-checking flow for damaged/incomplete deliveries. The **Inventory** and **Shelves** modules also received several fixes and improvements.

---

## Checker Module

### Incoming Deliveries — Full Redesign

**New table architecture:**  
Incoming deliveries now use a `ingoing` (header) + `ingoing_items` (line-items) pattern replacing the old single-row `checker_records` approach.

| Table | Purpose |
|-------|---------|
| `ingoing` | Delivery header — supplier, status (Pending/Checked), notes, creator |
| `ingoing_items` | One row per delivered item — quantity, condition, per-item approval status, confirmer |

**Per-item condition tracking:**  
Each incoming item carries a condition field with three possible values:
- `Accepted / Good` — item is in order, stock will be added on approval
- `Damaged` — item received damaged
- `Incomplete / Missing` — delivery was short or items were missing

**Per-item confirmation flow:**  
Items are now approved or rejected individually instead of as a whole delivery. When an admin or inventory user confirms:
- `Accepted / Good` → quantity added to `warehouse_items`, stock movement recorded
- `Damaged` or `Incomplete / Missing` → item confirmed with no stock change, returned to checker for re-checking
- `Rejected` → no stock change, rejection note recorded

**Delivery header roll-up status:**  
The `ingoing.ingoing_status` (`Pending` / `Checked`) auto-updates once every line-item has been processed (none left in `Pending` state).

**Re-checking flow:**  
Damaged or incomplete items land in a new **"For Re-checking"** tab on the checker page. The checker can update the condition and notes, which resets the item back to `Pending` and puts the parent delivery back to `Pending` for re-approval.

New endpoints:
- `api/checker/rechecking.php` — Lists confirmed items with non-good conditions
- `api/checker/recheck-submit.php` — Updates condition/notes and resets item to Pending

**Incoming delivery UI changes:**
- Inline form replaced with a modal dialog (matches outgoing modal style)
- Modal widened to 760 px for multi-item rows
- Items column in the deliveries table truncated to 2 with a "+N more items" indicator
- Details modal shows full item list (read-only)
- Paginated table with user-adjustable page size (10/25/50/100)
- Filter tabs: All / Pending / Checked
- A **"Pending Incoming Checks"** KPI stat card added to the checker page

**Confirmation page (admin & inventory):**
- Redesigned to show one row per delivery transaction with a Details modal for per-item confirm/reject
- Mandatory rejection note enforced (server-side 422 + client-side required field)
- Status column added (Pending/Checked badge) with filter tabs: All / Pending / Checked
- Native `window.confirm/prompt/alert` replaced with an in-page action modal + toast notifications

**Idempotency (duplicate submission protection):**  
A `checker_idempotency` table is auto-created. Each form submission carries a unique token; repeated POSTs with the same token are rejected with HTTP 409 to prevent double-saves from spam-clicking or network retries.

---

### Outgoing Deliveries — Full Redesign

**New table architecture:**  
Manual outgoing records now use a `outgoing` (header) + `outgoing_items` (line-items) pattern.

| Table | Purpose |
|-------|---------|
| `outgoing` | Transaction header — purpose (once per record), status (Pending/Checked), creator |
| `outgoing_items` | One row per item — quantity, source location (per item), per-item approval status, confirmer |

**Purpose and source location:**
- `purpose` is chosen **once per outgoing transaction** at the header level (modal header dropdown)
- `source_location` (`Warehouse` / `Store`) is chosen **per item row** in the modal
- Purpose constraints auto-lock the available source locations:
  - Store Replenishment → source must be Warehouse
  - IoT Shelves Replenishment → source can be Warehouse or Store (not IoT Shelf)

**Stock is not deducted immediately.** Items are saved as `Pending`. Stock only changes after a checker or admin confirms each item via the review flow.

**Outgoing confirmation (per-item):**  
Each item in an outgoing transaction has its own Confirm/Reject button inside a review modal:
- Approve → deducts from the item's `source_location` (Warehouse or Store), adds to destination if purpose is a replenishment type, logs stock movement
- Reject → no stock change, rejection note recorded
- `outgoing.status` rolls up to `Checked` once all items are processed

**Outgoing UI changes:**
- Table shows one row per transaction with a Review button
- Items/SKU columns truncated to first 2 with "+N more items" indicator
- Filter tabs: All / Pending / Approved / Rejected / Checked
  - All/Pending tabs show per-transaction grouped rows
  - Approved/Rejected tabs show a flat per-item view
- Paginated table with user-adjustable page size (10/25/50/100)
- Confirm/Reject modals replace native browser `confirm()/prompt()` dialogs
- Toast notifications replace `alert()` calls throughout the checker module

**Duplicate item validation:**  
The same inventory item can appear in one outgoing transaction only once per source location. A second row for the same item + same source is rejected both client-side and server-side with a clear error.

**Stock display in item dropdown:**  
When adding items to an outgoing entry, the stock hint updates based on the selected source location (Warehouse qty / Store qty). The quantity input max is capped at `min(stock, QTY_MAX)`.

**Quantity limits (incoming & outgoing):**  
All checker forms enforce a quantity range of 1–100,000. Validation uses browser native `setCustomValidity/reportValidity` for consistent UX across all entry forms. Server-side constant `CHECKER_QTY_MAX = 100000` enforces the same limit on the backend.

**Inventory item field — searchable input:**  
The item `<select>` dropdown in checker forms was replaced with a `<datalist>`-backed text input so checkers can type to search by name instead of scrolling a long list.

---

## Inventory Module

**Duplicate item name restriction:**  
`save.php` now rejects an item whose name already exists within the same subcategory. The modal highlights the name field with an error on conflict.

**Optional quantity fields:**  
Quantity fields are no longer required — a blank value saves as zero instead of blocking the save.

**Storage type filter — "Store" option added:**  
The inventory filter dropdown now includes a "Store" option alongside "IoT Shelf" and "Warehouse". The DataTable custom search filter was updated to use a `storageLocations` array per item so items present in multiple locations appear under every relevant filter tab.

**Custom pagination bar:**  
The DataTable's built-in pagination was replaced with a custom bar matching the checker outgoing table style (Showing X–Y of Z, Rows selector, Prev/Page/Next).

---

## Shelves Module

**Shelf quantity / status synchronization:**  
- Shelf quantity is now forced to `0` whenever a shelf has no assigned item or its status is not `Occupied`
- A DB trigger migration (`2026_08_29_shelf_quantity_status_sync`) keeps quantity and status in sync:
  - No item or Unused/Maintenance status → quantity forced to 0
  - Any positive quantity → status auto-set to Occupied (quantity wins)
- The same OR-logic is applied at read time in the shelves API transform so the display is always consistent

---

## Database Changes

### New Tables

| Table | Purpose |
|-------|---------|
| `ingoing` | Incoming delivery headers |
| `ingoing_items` | Per-item incoming delivery line items |
| `outgoing` | Manual outgoing transaction headers |
| `outgoing_items` | Per-item outgoing transaction line items |
| `checker_idempotency` | Duplicate-submission protection (idempotency tokens) |
| `store_items` | Store stock quantities per item (mirrors `warehouse_items`) |

### Modified Tables

| Table | Change |
|-------|--------|
| `ingoing_items` | Added: `ingoing_item_condition`, `ingoing_item_notes`, `ingoing_item_status`, `confirmed_by`, `confirmed_date`, `confirmation_notes` |
| `ingoing` | Added: `ingoing_status` (Pending/Checked roll-up) |
| `outgoing_items` | Added: `outgoing_item_status`, `source_location`, `confirmed_by`, `confirmed_date`, `confirmed_notes` |
| `outgoing` | Added: `status` (Pending/Checked roll-up), `checked_by`, `checked_date` |

All changes include auto-migration so existing databases self-heal without manual SQL.

---

## API Endpoints Added

| Endpoint | Method | Role | Description |
|----------|--------|------|-------------|
| `api/checker/rechecking.php` | GET | checker, admin | Lists confirmed ingoing items with Damaged or Incomplete condition |
| `api/checker/recheck-submit.php` | POST | checker, admin | Updates item condition/notes and resets to Pending |
| `api/checker/confirm-outgoing.php` | POST | checker, admin | Approves or rejects a single outgoing line item |

---

## Files Changed (Key Files)

### Backend
- `app/Api/checker/common.php` — Added `ensure_outgoing_tables()`, `ensure_ingoing_tables()`, `outgoing_rollup_status()`, idempotency helpers, default note helpers, `load_ingoing_items_flat()`
- `app/Api/checker/save.php` — Rewritten incoming (ingoing tables) and manual outgoing (outgoing tables) branches; idempotency guard; QTY_MAX constant
- `app/Api/checker/confirm-incoming.php` — Rewritten for per-item confirmation with condition-based stock logic
- `app/Api/checker/confirm-outgoing.php` — New; per-item outgoing approval with source-location-based stock deduction
- `app/Api/checker/pending-incoming.php` — Rewritten to return flat per-item lists
- `app/Api/checker/outgoing.php` — Rewritten to load from outgoing/outgoing_items tables
- `app/Api/checker/rechecking.php` — New
- `app/Api/checker/recheck-submit.php` — New
- `app/Api/inventory/common.php` — `inventory_item_to_frontend()` updated with storageLocations, duplicate-name helper
- `app/Api/inventory/save.php` — Duplicate name check, optional quantity fields
- `app/Api/shelves/save.php` — Shelf quantity reset on unoccupied/no-item shelves
- `app/Api/shelves/common.php` — Quantity zeroed at read time for unoccupied shelves

### Frontend
- `assets/js/modules/checker/checker.js` — Full rewrite of outgoing + incoming sections; re-check modal; toast system; pagination; per-item modals; idempotency tokens; datalist item input; QTY_MAX validation
- `assets/js/modules/checker/incoming-confirmation.js` — Rewritten for per-item flat data; delivery-grouped display; in-page action modal; toast notifications
- `assets/js/modules/inventory/datatable-init.js` — Custom pagination; storageLocations filter
- `assets/css/checker/checker.css` — Modal layouts, badge styles, pagination bar, confirm/reject button styles, review modal styles

---

## How to Apply to an Existing Database

No manual SQL is needed. All schema changes self-heal via the `ensure_*_tables()` functions that run on every request. Simply deploy the updated files and the missing columns/tables will be created automatically on the next page load.

If you want to apply changes manually, refer to `database/migrations/` for any available migration scripts.
