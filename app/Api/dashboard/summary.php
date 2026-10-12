<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_once __DIR__ . '/../../Core/Database.php';
require_login(['admin', 'super-admin']);

/**
 * Dashboard summary endpoint.
 *
 * Built against the live cgmi_ims schema:
 *   - Inventory     : `inventory_items` (item_id, unit_price, reorder_level, is_active ...) — no stored quantity
 *   - Stock is split across `warehouse_items`, `store_items`, and `shelves.shelf_quantity`.
 *   - There is no stored per-item price/quantity column, so stock value is derived.
 */
try {
    $summary = [
        'activeItems' => 0,
        'totalStockUnits' => 0,
        'lowStockItems' => 0,
        'stockValue' => 0.0,
    ];

    // Inventory KPIs. Overall quantity per item = warehouse + store + shelf.
    // A low-stock item is an active item whose overall quantity is at or below
    // its reorder_level (reorder_level 0 counts only when the item is out).
    $inv = $pdo->query("
        SELECT
            COUNT(*) AS active_items,
            COALESCE(SUM(overall_qty), 0) AS total_stock_units,
            COALESCE(SUM(overall_qty * unit_price), 0) AS stock_value,
            COALESCE(SUM(CASE WHEN overall_qty <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock_items
        FROM (
            SELECT
                i.item_id,
                i.unit_price,
                i.reorder_level,
                COALESCE(w.warehouse_item_quantity, 0)
                    + COALESCE(st.store_item_quantity, 0)
                    + COALESCE(sh.shelf_qty, 0) AS overall_qty
            FROM inventory_items i
            LEFT JOIN warehouse_items w ON w.item_id = i.item_id
            LEFT JOIN store_items st ON st.item_id = i.item_id
            LEFT JOIN (
                SELECT item_id, COALESCE(SUM(shelf_quantity), 0) AS shelf_qty
                FROM shelves
                WHERE item_id IS NOT NULL
                GROUP BY item_id
            ) sh ON sh.item_id = i.item_id
            WHERE i.is_active = 1
        ) t
    ")->fetch();

    if ($inv) {
        $summary['activeItems'] = (int) $inv['active_items'];
        $summary['totalStockUnits'] = (int) $inv['total_stock_units'];
        $summary['stockValue'] = (float) $inv['stock_value'];
        $summary['lowStockItems'] = (int) $inv['low_stock_items'];
    }

    // Low-stock items (active items at/under their reorder level), lowest first.
    $lowStock = $pdo->query("
        SELECT
            i.sku,
            i.item_name,
            i.reorder_level,
            u.unit_name AS unit,
            c.category_name AS category_name,
            COALESCE(w.warehouse_item_quantity, 0)
                + COALESCE(st.store_item_quantity, 0)
                + COALESCE(sh.shelf_qty, 0) AS quantity
        FROM inventory_items i
        LEFT JOIN units u ON u.unit_id = i.unit_id
        LEFT JOIN sub_categories sc ON sc.sub_category_id = i.sub_category_id
        LEFT JOIN categories c ON c.category_id = sc.category_id
        LEFT JOIN warehouse_items w ON w.item_id = i.item_id
        LEFT JOIN store_items st ON st.item_id = i.item_id
        LEFT JOIN (
            SELECT item_id, COALESCE(SUM(shelf_quantity), 0) AS shelf_qty
            FROM shelves WHERE item_id IS NOT NULL GROUP BY item_id
        ) sh ON sh.item_id = i.item_id
        WHERE i.is_active = 1
        HAVING quantity <= i.reorder_level
        ORDER BY quantity ASC, i.item_name ASC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    json_response([
        'success' => true,
        'summary' => $summary,
        'lowStock' => $lowStock,
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Dashboard load failed: ' . $e->getMessage(),
    ], 500);
}
