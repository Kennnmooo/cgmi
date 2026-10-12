<?php
require_once __DIR__ . '/common.php';
require_login(['checker', 'admin', 'inventory', 'super-admin']);
ensure_checker_tables($pdo);
try {
    $stmt = $pdo->query("SELECT i.*, c.category_name, sc.sub_category_name, u.unit_name,
                                COALESCE(wi.warehouse_item_quantity, 0) AS warehouse_qty,
                                COALESCE(si.store_item_quantity, 0) AS store_qty,
                                COALESCE(sh.shelf_quantity, 0) AS shelf_qty
                         FROM inventory_items i
                         LEFT JOIN sub_categories sc ON sc.sub_category_id = i.sub_category_id
                         LEFT JOIN categories c ON c.category_id = sc.category_id
                         LEFT JOIN units u ON u.unit_id = i.unit_id
                         LEFT JOIN warehouse_items wi ON wi.item_id = i.item_id
                         LEFT JOIN store_items si ON si.item_id = i.item_id
                         LEFT JOIN shelves sh ON sh.item_id = i.item_id
                         WHERE i.is_active = 1
                         ORDER BY i.item_name ASC");
    $items = array_map('checker_inventory_item_to_frontend', $stmt->fetchAll(PDO::FETCH_ASSOC));
    json_response(['success' => true, 'items' => $items]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load inventory choices: ' . $e->getMessage()], 500);
}
