<?php
require_once __DIR__ . '/common.php';

try {
    $stmt = $pdo->query("
        SELECT 
            i.*,
            c.category_name,
            c.category_id,
            sc.sub_category_name,
            u.unit_name,
            sh.shelf_id,
            sh.shelf_name,
            sh.shelf_quantity AS iot_shelf_quantity,
            si.store_item_quantity,
            wi.warehouse_item_quantity
        FROM inventory_items i
        LEFT JOIN sub_categories sc ON sc.sub_category_id = i.sub_category_id
        LEFT JOIN categories c ON c.category_id = sc.category_id
        LEFT JOIN units u ON u.unit_id = i.unit_id
        LEFT JOIN shelves sh ON sh.item_id = i.item_id
        LEFT JOIN store_items si ON si.item_id = i.item_id
        LEFT JOIN warehouse_items wi ON wi.item_id = i.item_id
        ORDER BY i.item_name ASC
    ");

    $items = array_map('inventory_item_to_frontend', $stmt->fetchAll());
    json_response(['success' => true, 'items' => $items]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Inventory load failed: ' . $e->getMessage()
    ], 500);
}
