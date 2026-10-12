<?php
require_once __DIR__ . '/common.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    // ── Role-based permissions ────────────────────────────────────────────
    // super-admin : full access, including activate/deactivate items.
    // admin       : add / update items including quantities (no de/reactivate).
    // inventory   : add / update item information only — cannot change quantity
    //               and cannot activate/deactivate items.
    $actor = current_user();
    $actorRole = strtolower(trim($actor['role'] ?? ''));
    $canManageStatus = $actorRole === 'super-admin';
    $canEditQuantity = in_array($actorRole, ['super-admin', 'admin'], true);

    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $subcategory = trim($_POST['subcategory'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $warehouseQty = filter_var($_POST['warehouseQty'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $storeQty = filter_var($_POST['storeQty'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $iotShelfQty = filter_var($_POST['iotShelfQty'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $unitPrice = filter_var($_POST['unitPrice'] ?? null, FILTER_VALIDATE_FLOAT);
    $unitWeightRaw = $_POST['unitWeightKg'] ?? '';
    $unitWeightRaw = is_string($unitWeightRaw) ? trim($unitWeightRaw) : $unitWeightRaw;
    $unitWeightKg = ($unitWeightRaw === '' || $unitWeightRaw === null) ? null : filter_var($unitWeightRaw, FILTER_VALIDATE_FLOAT);
    $statusInput = strtolower(trim($_POST['status'] ?? 'active'));
    $isActive = $statusInput === 'inactive' ? 0 : 1;
    $dbId = isset($_POST['dbId']) && $_POST['dbId'] !== '' ? (int)$_POST['dbId'] : null;
    $removeImage = !empty($_POST['removeImage']);

    // Validate quantities
    if ($warehouseQty === false) $warehouseQty = 0;
    if ($storeQty === false) $storeQty = 0;
    if ($iotShelfQty === false) $iotShelfQty = 0;

    if ($name === '' || $category === '' || $subcategory === '' || $unit === '' || $unitPrice === false || $unitPrice < 0) {
        json_response(['success' => false, 'message' => 'Please complete all required item fields.'], 422);
    }

    if ($unitWeightKg === false || ($unitWeightKg !== null && $unitWeightKg < 0)) {
        json_response(['success' => false, 'message' => 'Please enter a valid unit weight in kilograms.'], 422);
    }

    // Resolve foreign keys
    $categoryId = resolve_category_id($pdo, $category);
    $subCategoryId = resolve_subcategory_id($pdo, $subcategory, $categoryId);
    $unitId = resolve_unit_id($pdo, $unit);

    // Prevent duplicate items: reject an item whose name already exists within
    // the same subcategory (case-insensitive). Excludes the item being edited.
    if (inventory_item_name_exists($pdo, $name, $subCategoryId, $dbId)) {
        json_response([
            'success' => false,
            'message' => 'An item with this name already exists in the selected category. Please use a different name.'
        ], 409);
    }

    $imagePath = null;
    $existing = null;

    if ($dbId !== null) {
        $check = $pdo->prepare('SELECT * FROM inventory_items WHERE item_id = ? LIMIT 1');
        $check->execute([$dbId]);
        $existing = $check->fetch();

        if (!$existing) {
            json_response(['success' => false, 'message' => 'Inventory item not found.'], 404);
        }

        $imagePath = $existing['item_img'];
    }

    // Handle image removal
    if ($removeImage && $imagePath) {
        $absolute = dirname(__DIR__, 3) . '/' . ltrim($imagePath, '/');
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        $imagePath = null;
    }

    // Handle image upload
    if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            json_response(['success' => false, 'message' => 'Image upload failed.'], 422);
        }

        $tmp = $_FILES['image']['tmp_name'];
        $mime = mime_content_type($tmp);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if (!isset($allowed[$mime])) {
            json_response(['success' => false, 'message' => 'Only JPG, PNG, and WEBP images are allowed.'], 422);
        }

        if (($_FILES['image']['size'] ?? 0) > 5 * 1024 * 1024) {
            json_response(['success' => false, 'message' => 'Image must be 5MB or smaller.'], 422);
        }

        $uploadDir = dirname(__DIR__, 3) . '/uploads/items';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $baseName = sanitize_filename(pathinfo($_FILES['image']['name'], PATHINFO_FILENAME));
        $filename = strtolower(($dbId ?: 'new') . '-' . $baseName . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime]);
        $dest = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $dest)) {
            json_response(['success' => false, 'message' => 'Unable to save uploaded image.'], 500);
        }

        // Remove old image
        if ($imagePath) {
            $oldAbsolute = dirname(__DIR__, 3) . '/' . ltrim($imagePath, '/');
            if (is_file($oldAbsolute)) {
                @unlink($oldAbsolute);
            }
        }

        $imagePath = 'uploads/items/' . $filename;
    }

    $userId = $actor ? (int)$actor['id'] : null;

    // Enforce status permission: only super-admin may activate/deactivate.
    // For admin/inventory, keep the item's existing active state (new items
    // default to active) so the flag can't be flipped via a crafted request.
    if (!$canManageStatus) {
        $isActive = $existing !== null ? (int)!empty($existing['is_active']) : 1;
    }

    // When an item is deactivated, its stock is automatically zeroed out across
    // every location (warehouse, store, and IoT shelf). Deactivated items are
    // treated as no longer held in inventory, so their quantities must reflect 0.
    if ($isActive === 0) {
        $warehouseQty = 0;
        $storeQty = 0;
        $iotShelfQty = 0;
    }

    if ($dbId === null) {
        // INSERT new item
        $sku = next_sku($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO inventory_items 
                (item_name, sku, sub_category_id, unit_id, item_img, is_active, unit_price, unit_weight_kg, created_by, modified_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $sku, $subCategoryId, $unitId, $imagePath,
            $isActive, $unitPrice, $unitWeightKg, $userId, $userId
        ]);
        $dbId = (int)$pdo->lastInsertId();
    } else {
        // UPDATE existing item
        $stmt = $pdo->prepare("
            UPDATE inventory_items SET
                item_name = ?,
                sub_category_id = ?,
                unit_id = ?,
                item_img = ?,
                is_active = ?,
                unit_price = ?,
                unit_weight_kg = ?,
                modified_by = ?
            WHERE item_id = ?
        ");
        $stmt->execute([
            $name, $subCategoryId, $unitId, $imagePath,
            $isActive, $unitPrice, $unitWeightKg, $userId, $dbId
        ]);
    }

    // Quantity handling is gated by role. The inventory role may edit item
    // information but must not alter stock quantities. On an existing item we
    // preserve the stored quantities; on a brand-new item created by an
    // inventory user, quantities start at 0. Only super-admin and admin can
    // set/adjust quantities.
    if ($canEditQuantity) {
        // Upsert warehouse_items quantity
        $stmt = $pdo->prepare("
            INSERT INTO warehouse_items (item_id, warehouse_item_quantity)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE warehouse_item_quantity = VALUES(warehouse_item_quantity)
        ");
        $stmt->execute([$dbId, $warehouseQty]);

        // Upsert store_items quantity
        $stmt = $pdo->prepare("
            INSERT INTO store_items (item_id, store_item_quantity)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE store_item_quantity = VALUES(store_item_quantity)
        ");
        $stmt->execute([$dbId, $storeQty]);

        // Update shelf quantity if item has an assigned shelf
        $shelfCheck = $pdo->prepare("SELECT shelf_id FROM shelves WHERE item_id = ? LIMIT 1");
        $shelfCheck->execute([$dbId]);
        $shelfRow = $shelfCheck->fetch();
        if ($shelfRow) {
            $stmt = $pdo->prepare("UPDATE shelves SET shelf_quantity = ?, modified_by = ? WHERE item_id = ?");
            $stmt->execute([$iotShelfQty, $userId, $dbId]);
        }
    } else {
        // Inventory role: ensure quantity rows exist for new items (seeded at 0)
        // but never overwrite existing stock levels.
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO warehouse_items (item_id, warehouse_item_quantity)
            VALUES (?, 0)
        ");
        $stmt->execute([$dbId]);

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO store_items (item_id, store_item_quantity)
            VALUES (?, 0)
        ");
        $stmt->execute([$dbId]);
    }

    // Fetch the saved item with joins for response
    $fetch = $pdo->prepare("
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
        WHERE i.item_id = ?
        LIMIT 1
    ");
    $fetch->execute([$dbId]);
    $item = $fetch->fetch();

    json_response([
        'success' => true,
        'message' => 'Inventory item saved successfully.',
        'item' => inventory_item_to_frontend($item)
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Save failed: ' . $e->getMessage()
    ], 500);
}
