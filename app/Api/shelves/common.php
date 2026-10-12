<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['admin', 'inventory', 'super-admin']);

/**
 * Map a raw shelves DB row (joined with inventory_items + units) to frontend format.
 */
function shelf_to_frontend(array $row): array {
    $shelfQty = (int)($row['shelf_quantity'] ?? 0);
    $warehouseQty = (int)($row['warehouse_qty'] ?? 0);
    $storeQty = (int)($row['store_qty'] ?? 0);
    $minWeight = isset($row['min_weight']) && $row['min_weight'] !== null ? (float)$row['min_weight'] : null;
    $maxWeight = isset($row['max_weight']) && $row['max_weight'] !== null ? (float)$row['max_weight'] : null;

    $status = ucfirst(strtolower((string)($row['shelf_status'] ?? 'Unused')));
    $hasItem = isset($row['item_id']) && $row['item_id'] !== null;

    // Mirror the DB-level invariant (see migration 2026_08_29_shelf_quantity_status_sync)
    // so the display never drifts from stored data:
    //   1. no item assigned   -> quantity 0, never Occupied
    //   2. quantity > 0        -> status Occupied (quantity wins)
    //   3. Unused/Maintenance  -> quantity 0 (only when no positive quantity)
    if (!$hasItem) {
        $shelfQty = 0;
        if ($status === 'Occupied') {
            $status = 'Unused';
        }
    } elseif ($shelfQty > 0) {
        $status = 'Occupied';
    } elseif ($status === 'Unused' || $status === 'Maintenance') {
        $shelfQty = 0;
    }

    $stockQty = $warehouseQty + $storeQty + $shelfQty;

    return [
        'id' => (int)$row['shelf_id'],
        'name' => $row['shelf_name'] ?? '',
        'location' => $row['shelf_location'] ?? '',
        'itemId' => isset($row['item_id']) ? (int)$row['item_id'] : null,
        'itemName' => $row['item_name'] ?? null,
        'quantity' => $shelfQty,
        'stockQuantity' => $stockQty,
        'warehouseQty' => $warehouseQty,
        'storeQty' => $storeQty,
        'unit' => $row['unit_name'] ?? '',
        'minWeight' => $minWeight,
        'maxWeight' => $maxWeight,
        'lowStock' => (bool)($row['low_stock'] ?? false),
        'status' => $status,
    ];
}
