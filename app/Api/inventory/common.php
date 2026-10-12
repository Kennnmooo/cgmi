<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['admin', 'inventory', 'super-admin']);

/**
 * Transform a raw DB row into the frontend item shape.
 *
 * inventory_items columns:
 *   item_id, sub_category_id, unit_id, item_name, sku,
 *   item_img, reorder_level, is_active, created_by, created_date,
 *   modified_by, modified_date, unit_price, unit_weight_kg
 *
 * Joined from shelves (via shelves.item_id = inventory_items.item_id):
 *   shelf_id, shelf_name, iot_shelf_quantity
 *
 * Joined from store_items (via store_items.item_id = inventory_items.item_id):
 *   store_item_quantity
 *
 * Joined from warehouse_items (via warehouse_items.item_id = inventory_items.item_id):
 *   warehouse_item_quantity
 *
 * Logic:
 *   - warehouseQty = warehouse_items.warehouse_item_quantity
 *   - storeQty = store_items.store_item_quantity
 *   - iotShelfQty = shelves.shelf_quantity (quantity on the IoT shelf)
 *   - overallQty = warehouseQty + storeQty + iotShelfQty
 */
function inventory_item_to_frontend(array $row): array {
    $isActive = !empty($row['is_active']);
    $unitWeightKg = isset($row['unit_weight_kg']) && $row['unit_weight_kg'] !== null
        ? (float)$row['unit_weight_kg']
        : null;

    // IoT shelf data (from LEFT JOIN shelves)
    $hasShelf = !empty($row['shelf_id']);
    $iotShelfQty = $hasShelf ? (int)($row['iot_shelf_quantity'] ?? 0) : 0;
    $shelfName = $hasShelf ? ($row['shelf_name'] ?? '') : null;

    // Store quantity (from LEFT JOIN store_items)
    $storeQty = (int)($row['store_item_quantity'] ?? 0);

    // Warehouse quantity (from LEFT JOIN warehouse_items)
    $warehouseQty = (int)($row['warehouse_item_quantity'] ?? 0);

    // Overall = warehouse + store + IoT shelf
    $overallQty = $warehouseQty + $storeQty + $iotShelfQty;

    // Monitoring status for Weight Check column
    $hasWarehouse = $warehouseQty > 0;
    $hasStore = $storeQty > 0;
    $locations = [];
    if ($hasWarehouse) $locations[] = 'Warehouse';
    if ($hasStore) $locations[] = 'Store';
    if ($hasShelf) $locations[] = 'IoT Shelves';

    if ($hasShelf) {
        $monitoringStatus = 'monitored';
        $monitoringLabel = 'Monitored';
        $monitoringDescription = count($locations) > 0
            ? 'Tracked in: ' . implode(', ', $locations) . '.'
            : 'Assigned to IoT shelf but no stock available.';
    } elseif ($overallQty === 0) {
        $monitoringStatus = 'no_stock';
        $monitoringLabel = 'No Stock';
        $monitoringDescription = 'This item has no stock in any location.';
    } elseif (count($locations) > 0) {
        $monitoringStatus = 'partial';
        $monitoringLabel = implode(' + ', $locations);
        $monitoringDescription = 'Stored in: ' . implode(', ', $locations) . '. Not on IoT shelf.';
    } else {
        $monitoringStatus = 'no_stock';
        $monitoringLabel = 'No Stock';
        $monitoringDescription = 'This item has no stock in any location.';
    }

    return [
        'dbId'                  => (int)$row['item_id'],
        'id'                    => $row['sku'],
        'sku'                   => $row['sku'],
        'name'                  => $row['item_name'],
        'category'              => $row['category_name'] ?? '',
        'categoryId'            => (int)($row['category_id'] ?? 0),
        'subcategory'           => $row['sub_category_name'] ?? '',
        'subcategoryId'         => (int)($row['sub_category_id'] ?? 0),
        'warehouseQty'          => $warehouseQty,
        'storeQty'              => $storeQty,
        'iotShelfQty'           => $iotShelfQty,
        'overallQty'            => $overallQty,
        'qty'                   => $overallQty,
        'unit'                  => $row['unit_name'] ?? '',
        'unitId'                => (int)($row['unit_id'] ?? 0),
        'unitPrice'             => (float)($row['unit_price'] ?? 0),
        'unitWeightKg'          => $unitWeightKg,
        'status'                => $isActive ? 'Active' : 'Inactive',
        'image'                 => !empty($row['item_img']) ? app_url('/' . ltrim($row['item_img'], '/')) : null,
        'lowStock'              => (int)($row['low_stock'] ?? 0),
        'hasShelf'              => $hasShelf,
        'shelfId'               => $hasShelf ? (int)$row['shelf_id'] : null,
        'shelfName'             => $shelfName,
        'storageType'           => $hasShelf ? 'shelves' : ($hasWarehouse ? 'warehouse' : ($hasStore ? 'store' : 'warehouse')),
        'storageLocations'      => $locations,
        'monitoringStatus'      => $monitoringStatus,
        'monitoringLabel'       => $monitoringLabel,
        'monitoringDescription' => $monitoringDescription,
        'emoji'                 => 'IT',
    ];
}

/**
 * Check whether an inventory item with the given name already exists within
 * the same subcategory. Comparison is case-insensitive and ignores surrounding
 * whitespace. When editing, pass $excludeId to skip the item being updated.
 *
 * @param PDO      $pdo
 * @param string   $name          Item name to check.
 * @param int|null $subCategoryId  Subcategory scope for the uniqueness check.
 * @param int|null $excludeId      item_id to exclude (the item being edited).
 * @return bool True if a duplicate exists.
 */
function inventory_item_name_exists(PDO $pdo, string $name, ?int $subCategoryId, ?int $excludeId = null): bool {
    $name = trim($name);
    if ($name === '') return false;

    $sql = 'SELECT 1 FROM inventory_items
            WHERE LOWER(item_name) = LOWER(?)
              AND sub_category_id <=> ?';
    $params = [$name, $subCategoryId];

    if ($excludeId !== null) {
        $sql .= ' AND item_id <> ?';
        $params[] = $excludeId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

/**
 * Generate the next SKU in format ITM-XXXX.
 */
function next_sku(PDO $pdo): string {
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(sku, 5) AS UNSIGNED)) AS max_num FROM inventory_items WHERE sku LIKE 'ITM-%'");
    $max = (int)($stmt->fetchColumn() ?: 0);
    return 'ITM-' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
}

/**
 * Resolve a sub_category_id from a sub_category_name (or create if not found).
 */
function resolve_subcategory_id(PDO $pdo, string $subcategoryName, int $categoryId): ?int {
    if ($subcategoryName === '') return null;
    $stmt = $pdo->prepare('SELECT sub_category_id FROM sub_categories WHERE sub_category_name = ? AND category_id = ? LIMIT 1');
    $stmt->execute([$subcategoryName, $categoryId]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;
    $ins = $pdo->prepare('INSERT INTO sub_categories (sub_category_name, category_id) VALUES (?, ?)');
    $ins->execute([$subcategoryName, $categoryId]);
    return (int)$pdo->lastInsertId();
}

/**
 * Resolve a category_id from a category_name (or create if not found).
 */
function resolve_category_id(PDO $pdo, string $categoryName): ?int {
    if ($categoryName === '') return null;
    $stmt = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ? LIMIT 1');
    $stmt->execute([$categoryName]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;
    $ins = $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)');
    $ins->execute([$categoryName]);
    return (int)$pdo->lastInsertId();
}

/**
 * Resolve a unit_id from a unit_name (or create if not found).
 */
function resolve_unit_id(PDO $pdo, string $unitName): ?int {
    if ($unitName === '') return null;
    $stmt = $pdo->prepare('SELECT unit_id FROM units WHERE unit_name = ? LIMIT 1');
    $stmt->execute([$unitName]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;
    $ins = $pdo->prepare('INSERT INTO units (unit_name) VALUES (?)');
    $ins->execute([$unitName]);
    return (int)$pdo->lastInsertId();
}
