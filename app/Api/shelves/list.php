<?php
require_once __DIR__ . '/common.php';

try {
    $stmt = $pdo->query("
        SELECT sh.*, i.item_name, u.unit_name,
            COALESCE(wi.warehouse_item_quantity, 0) + COALESCE(si.store_item_quantity, 0) + COALESCE(sh.shelf_quantity, 0) AS total_stock_qty,
            COALESCE(wi.warehouse_item_quantity, 0) AS warehouse_qty,
            COALESCE(si.store_item_quantity, 0) AS store_qty
        FROM shelves sh
        LEFT JOIN inventory_items i ON i.item_id = sh.item_id
        LEFT JOIN units u ON u.unit_id = i.unit_id
        LEFT JOIN warehouse_items wi ON wi.item_id = sh.item_id
        LEFT JOIN store_items si ON si.item_id = sh.item_id
        ORDER BY sh.shelf_name ASC
    ");
    $items = array_map('shelf_to_frontend', $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Inventory items eligible for shelf assignment (active items)
    $inventoryStmt = $pdo->query("
        SELECT i.item_id, i.item_name, u.unit_name,
            COALESCE(wi.warehouse_item_quantity, 0) + COALESCE(si.store_item_quantity, 0) AS total_stock_qty
        FROM inventory_items i
        LEFT JOIN units u ON u.unit_id = i.unit_id
        LEFT JOIN warehouse_items wi ON wi.item_id = i.item_id
        LEFT JOIN store_items si ON si.item_id = i.item_id
        WHERE i.is_active = 1
        ORDER BY i.item_name ASC
    ");
    $inventoryOptions = array_map(static function(array $row): array {
        return [
            'id' => (int)$row['item_id'],
            'name' => $row['item_name'],
            'stockQuantity' => (int)($row['total_stock_qty'] ?? 0),
            'unit' => $row['unit_name'] ?? '',
        ];
    }, $inventoryStmt->fetchAll(PDO::FETCH_ASSOC));

    json_response(['success' => true, 'items' => $items, 'inventoryOptions' => $inventoryOptions]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load shelves: ' . $e->getMessage()], 500);
}
