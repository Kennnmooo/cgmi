<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/Helpers.php';

function checker_column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function checker_table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensure_sales_storage_column(PDO $pdo): void {
    // The legacy checker code targets a plural `sales` table. This DB may instead
    // use the singular `sale` table, in which case there is nothing to alter here —
    // skip silently so the page does not fatal on a missing table.
    if (!checker_table_exists($pdo, 'sales')) {
        return;
    }
    if (!checker_column_exists($pdo, 'sales', 'storage_location')) {
        $after = checker_column_exists($pdo, 'sales', 'fulfillment_type') ? ' AFTER fulfillment_type' : '';
        $pdo->exec("ALTER TABLE sales ADD COLUMN storage_location ENUM('store','warehouse') NOT NULL DEFAULT 'store'" . $after);
    }
}

function checker_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void {
    if (!checker_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function checker_ensure_inventory_storage_column(PDO $pdo): void {
    if (!checker_column_exists($pdo, 'inventory_items', 'shelf_tracking')) {
        $after = checker_column_exists($pdo, 'inventory_items', 'subcategory') ? ' AFTER subcategory' : '';
        $pdo->exec("ALTER TABLE inventory_items ADD COLUMN shelf_tracking ENUM('shelves','not_shelves') NOT NULL DEFAULT 'not_shelves'" . $after);
        if (checker_column_exists($pdo, 'inventory_items', 'shelf_id')) {
            $pdo->exec("UPDATE inventory_items SET shelf_tracking = 'shelves' WHERE shelf_id IS NOT NULL");
        }
    }
}

function ensure_checker_tables(PDO $pdo): void {
    ensure_sales_storage_column($pdo);
    checker_ensure_inventory_storage_column($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS checker_records (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        record_type ENUM('incoming','outgoing') NOT NULL,
        record_source ENUM('pos','manual') NOT NULL DEFAULT 'manual',
        inventory_item_id INT UNSIGNED NULL,
        sale_id INT UNSIGNED NULL,
        sale_item_id INT UNSIGNED NULL,
        sku VARCHAR(60) NULL,
        item_name VARCHAR(160) NOT NULL,
        quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
        unit VARCHAR(40) NULL,
        supplier VARCHAR(160) NULL,
        purpose VARCHAR(80) NULL,
        check_status VARCHAR(40) NOT NULL DEFAULT 'pending',
        approval_status VARCHAR(40) NOT NULL DEFAULT 'pending',
        notes TEXT NULL,
        confirmation_notes TEXT NULL,
        checked_by INT UNSIGNED NULL,
        confirmed_by INT UNSIGNED NULL,
        checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        confirmed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_checker_type_date (record_type, checked_at),
        INDEX idx_checker_item (inventory_item_id),
        INDEX idx_checker_sale (sale_id, sale_item_id),
        UNIQUE KEY uq_checker_outgoing_sale_item (record_type, sale_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    checker_add_column_if_missing($pdo, 'checker_records', 'record_source', "ENUM('pos','manual') NOT NULL DEFAULT 'manual' AFTER record_type");
    checker_add_column_if_missing($pdo, 'checker_records', 'supplier', "VARCHAR(160) NULL AFTER unit");
    checker_add_column_if_missing($pdo, 'checker_records', 'purpose', "VARCHAR(80) NULL AFTER supplier");
    checker_add_column_if_missing($pdo, 'checker_records', 'approval_status', "VARCHAR(40) NOT NULL DEFAULT 'pending' AFTER check_status");
    checker_add_column_if_missing($pdo, 'checker_records', 'confirmation_notes', "TEXT NULL AFTER notes");
    checker_add_column_if_missing($pdo, 'checker_records', 'confirmed_by', "INT UNSIGNED NULL AFTER checked_by");
    checker_add_column_if_missing($pdo, 'checker_records', 'confirmed_at', "DATETIME NULL AFTER checked_at");

    // Older patch used a strict ENUM for check_status. Convert to VARCHAR so cancelled/other future statuses will not break saves.
    try { $pdo->exec("ALTER TABLE checker_records MODIFY COLUMN check_status VARCHAR(40) NOT NULL DEFAULT 'pending'"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE checker_records MODIFY COLUMN approval_status VARCHAR(40) NOT NULL DEFAULT 'pending'"); } catch (Throwable $e) {}
}

function checker_inventory_item(PDO $pdo, int $itemId, bool $activeOnly = true): ?array {
    $sql = "SELECT i.*, c.category_name, sc.sub_category_name, u.unit_name
            FROM inventory_items i
            LEFT JOIN sub_categories sc ON sc.sub_category_id = i.sub_category_id
            LEFT JOIN categories c ON c.category_id = sc.category_id
            LEFT JOIN units u ON u.unit_id = i.unit_id
            WHERE i.item_id = ?" . ($activeOnly ? " AND i.is_active = 1" : "") . " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$itemId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function checker_inventory_item_to_frontend(array $row): array {
    $shelfTracking = strtolower((string)($row['shelf_tracking'] ?? 'not_shelves'));
    if (!in_array($shelfTracking, ['shelves', 'not_shelves'], true)) $shelfTracking = 'not_shelves';
    $itemId = (int)($row['item_id'] ?? $row['id'] ?? 0);
    $unit = $row['unit_name'] ?? $row['unit'] ?? '';
    $category = $row['category_name'] ?? '';
    $subcategory = $row['sub_category_name'] ?? $row['subcategory'] ?? '';
    $quantity = (int)($row['quantity'] ?? 0);
    return [
        'dbId' => $itemId,
        'id' => $itemId,
        'sku' => $row['sku'] ?? '',
        'name' => $row['item_name'] ?? '',
        'itemName' => $row['item_name'] ?? '',
        'category' => $category,
        'subcategory' => $subcategory,
        'storageType' => $shelfTracking,
        'storageTypeLabel' => $shelfTracking === 'shelves' ? 'IoT Shelves' : 'Warehouse',
        'quantity' => $quantity,
        'qty' => $quantity,
        'warehouseQty' => (int)($row['warehouse_qty'] ?? 0),
        'storeQty' => (int)($row['store_qty'] ?? 0),
        'shelfQty' => (int)($row['shelf_qty'] ?? 0),
        'unit' => $unit,
        'status' => $row['is_active'] ?? $row['status'] ?? 'active',
    ];
}

function checker_record_to_frontend(array $row): array {
    return [
        'id' => (int)($row['id'] ?? 0),
        'type' => $row['record_type'] ?? '',
        'source' => $row['record_source'] ?? 'manual',
        'itemId' => isset($row['inventory_item_id']) ? (int)$row['inventory_item_id'] : null,
        'saleId' => isset($row['sale_id']) ? (int)$row['sale_id'] : null,
        'saleItemId' => isset($row['sale_item_id']) ? (int)$row['sale_item_id'] : null,
        'sku' => $row['sku'] ?? '',
        'itemName' => $row['item_name'] ?? '',
        'quantity' => (float)($row['quantity'] ?? 0),
        'unit' => $row['unit'] ?? '',
        'supplier' => $row['supplier_name'] ?? '',
        'purpose' => $row['purpose'] ?? '',
        'status' => $row['check_status'] ?? 'pending',
        'approvalStatus' => $row['approval_status'] ?? 'pending',
        'notes' => $row['notes'] ?? '',
        'confirmationNotes' => $row['confirmation_notes'] ?? '',
        'checkedBy' => $row['checked_by_name'] ?? 'Checker',
        'confirmedBy' => $row['confirmed_by_name'] ?? '',
        'checkedAt' => $row['checked_at'] ?? null,
        'confirmedAt' => $row['confirmed_at'] ?? null,
        'createdAt' => $row['created_at'] ?? null,
    ];
}

function checker_record_stock_movement(PDO $pdo, int $inventoryItemId, string $movementType, int $quantityChange, int $quantityBefore, int $quantityAfter, ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null, ?int $createdBy = null): void {
    try {
        $stmt = $pdo->prepare('INSERT INTO stock_movements (inventory_item_id, movement_type, quantity_change, quantity_before, quantity_after, reference_type, reference_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$inventoryItemId, $movementType, $quantityChange, $quantityBefore, $quantityAfter, $referenceType, $referenceId, $notes, $createdBy]);
    } catch (Throwable $e) {
        // Stock movement history is helpful but should not block confirmed restock if an older DB lacks the table/enum.
    }

    // ── Low-stock notification (best-effort) ────────────────────────────────
    // Only when the quantity actually dropped and has reached/crossed below the
    // item's reorder level. Deduped per item so a single alert is raised until
    // it is read (see notify_user dedupe semantics).
    checker_maybe_notify_low_stock($pdo, $inventoryItemId, $quantityBefore, $quantityAfter, $createdBy);
}

/**
 * Raise a low-stock notification for an inventory item when its post-change
 * quantity has fallen to or below its reorder_level. Targets inventory + admin
 * + super-admin. Never throws.
 */
function checker_maybe_notify_low_stock(PDO $pdo, int $inventoryItemId, int $quantityBefore, int $quantityAfter, ?int $actorId = null): void {
    if ($inventoryItemId <= 0 || $quantityAfter >= $quantityBefore) {
        return; // no change or a restock — nothing to warn about
    }
    try {
        $stmt = $pdo->prepare('SELECT item_name, sku, COALESCE(reorder_level, 0) AS reorder_level FROM inventory_items WHERE item_id = ? LIMIT 1');
        $stmt->execute([$inventoryItemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $threshold = (int)$row['reorder_level'];
        if ($threshold <= 0 || $quantityAfter > $threshold) {
            return; // no threshold configured, or still above it
        }

        // Load the notification helpers lazily so the checker module has no hard
        // dependency on the notifications module for its primary flow.
        require_once __DIR__ . '/../notifications/common.php';
        if (!function_exists('notify_role')) {
            return;
        }

        $name = (string)($row['item_name'] ?? 'An item');
        $sku  = (string)($row['sku'] ?? '');
        $label = $sku !== '' ? ($name . ' (' . $sku . ')') : $name;
        $dedupe = 'low-stock:' . $inventoryItemId;
        $actor = $actorId !== null ? (int)$actorId : null;

        foreach (['inventory', 'admin', 'super-admin'] as $r) {
            $link = function_exists('notification_link_for_role') ? notification_link_for_role($r, 'inventory') : null;
            notify_role(
                $pdo,
                $r,
                'low-stock',
                'Low stock: ' . $name,
                $label . ' is low — ' . $quantityAfter . ' left (reorder level ' . $threshold . ').',
                $link,
                $dedupe,
                $actor
            );
        }
    } catch (Throwable $e) {
        // Silent — low-stock alerts must never block the primary stock update.
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// Ingoing Tables (header + line-items pattern for incoming deliveries)
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Default line-item note for an incoming delivery item, chosen from its condition
 * when the checker leaves the note blank.
 */
function ingoing_default_item_note(string $condition): string {
    switch ($condition) {
        case 'Damaged':
            return 'Item received damaged on delivery.';
        case 'Incomplete / Missing':
            return 'Delivery incomplete — quantity short or items missing.';
        case 'Accepted / Good':
        default:
            return 'Received in good condition — matches delivery.';
    }
}

/**
 * Default confirmation note recorded when an admin/inventory user processes an incoming item.
 * On approve it acknowledges the action; on reject it states the reason based on condition.
 */
function ingoing_default_confirmation_note(bool $isApprove, string $condition): string {
    if ($isApprove) {
        return $condition === 'Accepted / Good'
            ? 'Confirmed and added to warehouse stock.'
            : 'Confirmed (condition: ' . $condition . ') — returned to checker for re-checking, no stock added.';
    }
    // Rejected — state why based on the reported condition.
    switch ($condition) {
        case 'Damaged':
            return 'Rejected: item damaged and not fit for stock.';
        case 'Incomplete / Missing':
            return 'Rejected: delivery incomplete or items missing.';
        case 'Accepted / Good':
        default:
            return 'Rejected: did not pass admin/inventory verification.';
    }
}

function ensure_ingoing_tables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ingoing` (
        `ingoing_id` INT(11) NOT NULL AUTO_INCREMENT,
        `supplier_id` INT(11) NOT NULL,
        `ingoing_status` ENUM('Pending','Checked') NOT NULL DEFAULT 'Pending',
        `ingoing_notes` TEXT DEFAULT NULL,
        `created_by` INT(10) UNSIGNED DEFAULT NULL,
        `created_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `modified_by` INT(10) UNSIGNED DEFAULT NULL,
        `modified_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`ingoing_id`),
        KEY `fk_ingoing_to_supplier` (`supplier_id`),
        KEY `fk_ingoing_created_by` (`created_by`),
        KEY `fk_ingoing_modified_by` (`modified_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `ingoing_items` (
        `ingoing_item_id` INT(11) NOT NULL AUTO_INCREMENT,
        `item_id` INT(11) NOT NULL,
        `ingoing_id` INT(11) NOT NULL,
        `ingoing_quantity` INT(11) NOT NULL,
        `ingoing_item_condition` ENUM('Accepted / Good','Damaged','Incomplete / Missing') NOT NULL DEFAULT 'Accepted / Good',
        `ingoing_item_notes` TEXT DEFAULT NULL,
        `ingoing_item_status` ENUM('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending',
        `confirmed_by` INT(10) UNSIGNED DEFAULT NULL,
        `confirmed_date` TIMESTAMP NULL DEFAULT NULL,
        `confirmation_notes` TEXT DEFAULT NULL,
        PRIMARY KEY (`ingoing_item_id`),
        KEY `fk_ingoing_items_to_inventory_items` (`item_id`),
        KEY `fk_ingoing_items_to_ingoing` (`ingoing_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Ensure header status column exists for older databases and uses the Pending/Checked enum.
    checker_add_column_if_missing($pdo, 'ingoing', 'ingoing_status', "ENUM('Pending','Checked') NOT NULL DEFAULT 'Pending' AFTER supplier_id");
    // If an older DB used a different status enum (e.g. Pending/Confirmed/Rejected), coerce it to Pending/Checked.
    try { $pdo->exec("UPDATE ingoing SET ingoing_status = 'Checked' WHERE ingoing_status NOT IN ('Pending','Checked')"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE ingoing MODIFY COLUMN ingoing_status ENUM('Pending','Checked') NOT NULL DEFAULT 'Pending'"); } catch (Throwable $e) {}
    // Backfill: any delivery whose items are all processed (none Pending) should be Checked.
    try {
        $pdo->exec("UPDATE ingoing ig
                    SET ig.ingoing_status = 'Checked'
                    WHERE ig.ingoing_status <> 'Checked'
                      AND EXISTS (SELECT 1 FROM ingoing_items ii WHERE ii.ingoing_id = ig.ingoing_id)
                      AND NOT EXISTS (SELECT 1 FROM ingoing_items ii WHERE ii.ingoing_id = ig.ingoing_id AND ii.ingoing_item_status = 'Pending')");
    } catch (Throwable $e) {}

    // Ensure condition and notes columns exist for older databases
    checker_add_column_if_missing($pdo, 'ingoing_items', 'ingoing_item_condition', "ENUM('Accepted / Good','Damaged','Incomplete / Missing') NOT NULL DEFAULT 'Accepted / Good' AFTER ingoing_quantity");
    checker_add_column_if_missing($pdo, 'ingoing_items', 'ingoing_item_notes', "TEXT DEFAULT NULL AFTER ingoing_item_condition");
    checker_add_column_if_missing($pdo, 'ingoing_items', 'ingoing_item_status', "ENUM('Pending','Confirmed','Rejected') NOT NULL DEFAULT 'Pending' AFTER ingoing_item_notes");
    checker_add_column_if_missing($pdo, 'ingoing_items', 'confirmed_by', "INT(10) UNSIGNED DEFAULT NULL AFTER ingoing_item_status");
    checker_add_column_if_missing($pdo, 'ingoing_items', 'confirmed_date', "TIMESTAMP NULL DEFAULT NULL AFTER confirmed_by");
    checker_add_column_if_missing($pdo, 'ingoing_items', 'confirmation_notes', "TEXT DEFAULT NULL AFTER confirmed_date");

    // Soft-delete columns: allow super-admin to hide a delivery from all users without losing data.
    checker_add_column_if_missing($pdo, 'ingoing', 'deleted_at', "TIMESTAMP NULL DEFAULT NULL AFTER modified_date");
    checker_add_column_if_missing($pdo, 'ingoing', 'deleted_by', "INT(10) UNSIGNED DEFAULT NULL AFTER deleted_at");

    // Add FK constraints if not already present (silently skip if they exist)
    try { $pdo->exec("ALTER TABLE `ingoing` ADD CONSTRAINT `fk_ingoing_to_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplierId`)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `ingoing` ADD CONSTRAINT `fk_ingoing_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `ingoing` ADD CONSTRAINT `fk_ingoing_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `ingoing_items` ADD CONSTRAINT `fk_ingoing_items_to_ingoing` FOREIGN KEY (`ingoing_id`) REFERENCES `ingoing` (`ingoing_id`)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `ingoing_items` ADD CONSTRAINT `fk_ingoing_items_to_inventory_items` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`)"); } catch (Throwable $e) {}
}

/**
 * Transform an ingoing header row (with supplier join) to frontend format.
 */
function ingoing_to_frontend(array $row, array $items = []): array {
    return [
        'ingoingId' => (int)($row['ingoing_id'] ?? 0),
        'supplierId' => (int)($row['supplier_id'] ?? 0),
        'supplierName' => $row['supplierName'] ?? $row['supplier_name'] ?? '',
        'status' => $row['ingoing_status'] ?? 'Pending',
        'notes' => $row['ingoing_notes'] ?? '',
        'createdBy' => (int)($row['created_by'] ?? 0),
        'createdByName' => $row['created_by_name'] ?? '',
        'createdDate' => $row['created_date'] ?? null,
        'modifiedBy' => isset($row['modified_by']) ? (int)$row['modified_by'] : null,
        'modifiedByName' => $row['modified_by_name'] ?? '',
        'modifiedDate' => $row['modified_date'] ?? null,
        'items' => $items,
    ];
}

/**
 * Transform an ingoing_items row (with inventory_items join) to frontend format.
 */
function ingoing_item_to_frontend(array $row): array {
    return [
        'ingoingItemId' => (int)($row['ingoing_item_id'] ?? 0),
        'itemId' => (int)($row['item_id'] ?? 0),
        'ingoingId' => (int)($row['ingoing_id'] ?? 0),
        'quantity' => (int)($row['ingoing_quantity'] ?? 0),
        'condition' => $row['ingoing_item_condition'] ?? 'Accepted / Good',
        'notes' => $row['ingoing_item_notes'] ?? '',
        'status' => $row['ingoing_item_status'] ?? 'Pending',
        'confirmedBy' => isset($row['confirmed_by']) ? (int)$row['confirmed_by'] : null,
        'confirmedByName' => $row['confirmed_by_name'] ?? '',
        'confirmedDate' => $row['confirmed_date'] ?? null,
        'confirmationNotes' => $row['confirmation_notes'] ?? '',
        'itemName' => $row['item_name'] ?? '',
        'sku' => $row['sku'] ?? '',
        'unit' => $row['unit_name'] ?? $row['unit'] ?? '',
        'currentStock' => (int)($row['current_stock'] ?? 0),
        'supplierName' => $row['supplierName'] ?? $row['supplier_name'] ?? '',
        'createdByName' => $row['created_by_name'] ?? '',
        'createdDate' => $row['created_date'] ?? null,
    ];
}

/**
 * Load all ingoing records with their items, optionally filtered by status.
 */
function load_ingoing_records(PDO $pdo, ?string $statusFilter = null, int $limit = 300): array {
    // ingoing_status is a header-level roll-up (Pending until every item is processed, then Checked).
    // Soft-deleted records (deleted_at IS NOT NULL) are excluded from all roles.
    $conditions = ["ig.deleted_at IS NULL"];
    if ($statusFilter) $conditions[] = "ig.ingoing_status = ?";
    $where = "WHERE " . implode(" AND ", $conditions);
    $sql = "SELECT ig.*,
                   s.supplierName,
                   CONCAT(creator.first_name, ' ', creator.last_name) AS created_by_name,
                   CONCAT(modifier.first_name, ' ', modifier.last_name) AS modified_by_name
            FROM ingoing ig
            LEFT JOIN supplier s ON s.supplierId = ig.supplier_id
            LEFT JOIN users creator ON creator.id = ig.created_by
            LEFT JOIN users modifier ON modifier.id = ig.modified_by
            {$where}
            ORDER BY
              CASE ig.ingoing_status WHEN 'Pending' THEN 0 ELSE 1 END,
              ig.created_date DESC
            LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($statusFilter ? [$statusFilter] : []);
    $headers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($headers)) return [];

    // Load all items for these ingoing records
    $ids = array_column($headers, 'ingoing_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $itemSql = "SELECT ii.*, inv.item_name, inv.sku, u.unit_name,
                       COALESCE(wi.warehouse_item_quantity, 0) AS current_stock,
                       CONCAT(confirmer.first_name, ' ', confirmer.last_name) AS confirmed_by_name
                FROM ingoing_items ii
                LEFT JOIN inventory_items inv ON inv.item_id = ii.item_id
                LEFT JOIN units u ON u.unit_id = inv.unit_id
                LEFT JOIN warehouse_items wi ON wi.item_id = ii.item_id
                LEFT JOIN users confirmer ON confirmer.id = ii.confirmed_by
                WHERE ii.ingoing_id IN ({$placeholders})
                ORDER BY ii.ingoing_item_id ASC";
    $itemStmt = $pdo->prepare($itemSql);
    $itemStmt->execute($ids);
    $allItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Group items by ingoing_id
    $grouped = [];
    foreach ($allItems as $item) {
        $grouped[(int)$item['ingoing_id']][] = ingoing_item_to_frontend($item);
    }

    // Build result
    $result = [];
    foreach ($headers as $header) {
        $hId = (int)$header['ingoing_id'];
        $result[] = ingoing_to_frontend($header, $grouped[$hId] ?? []);
    }
    return $result;
}

/**
 * Load all ingoing items as a flat list with parent delivery info, filtered by per-item status.
 */
function load_ingoing_items_flat(PDO $pdo, ?string $itemStatusFilter = null, int $limit = 500): array {
    // Always exclude items whose parent delivery has been soft-deleted (deleted_at IS NOT NULL).
    $conditions = ["ig.deleted_at IS NULL"];
    if ($itemStatusFilter) $conditions[] = "ii.ingoing_item_status = ?";
    $where = "WHERE " . implode(" AND ", $conditions);
    $sql = "SELECT ii.*, inv.item_name, inv.sku, u.unit_name,
                   COALESCE(wi.warehouse_item_quantity, 0) AS current_stock,
                   s.supplierName AS supplier_name,
                   ig.created_date, ig.created_by, ig.ingoing_status AS delivery_status,
                   CONCAT(creator.first_name, ' ', creator.last_name) AS created_by_name,
                   CONCAT(confirmer.first_name, ' ', confirmer.last_name) AS confirmed_by_name
            FROM ingoing_items ii
            INNER JOIN ingoing ig ON ig.ingoing_id = ii.ingoing_id
            LEFT JOIN inventory_items inv ON inv.item_id = ii.item_id
            LEFT JOIN units u ON u.unit_id = inv.unit_id
            LEFT JOIN warehouse_items wi ON wi.item_id = ii.item_id
            LEFT JOIN supplier s ON s.supplierId = ig.supplier_id
            LEFT JOIN users creator ON creator.id = ig.created_by
            LEFT JOIN users confirmer ON confirmer.id = ii.confirmed_by
            {$where}
            ORDER BY ii.ingoing_item_id DESC
            LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($itemStatusFilter ? [$itemStatusFilter] : []);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(function ($row) {
        $item = ingoing_item_to_frontend($row);
        $item['supplierName'] = $row['supplier_name'] ?? '';
        $item['createdByName'] = $row['created_by_name'] ?? '';
        $item['createdDate'] = $row['created_date'] ?? null;
        // Header-level roll-up status of the parent delivery (Pending until every item is processed, then Checked).
        $item['deliveryStatus'] = $row['delivery_status'] ?? 'Pending';
        return $item;
    }, $rows);
}

/**
 * Load all soft-deleted ingoing records (Trash) as a flat item list.
 * Only super-admin should call this; returns records where ig.deleted_at IS NOT NULL.
 */
function load_ingoing_items_flat_deleted(PDO $pdo, int $limit = 500): array {
    $sql = "SELECT ii.*, inv.item_name, inv.sku, u.unit_name,
                   COALESCE(wi.warehouse_item_quantity, 0) AS current_stock,
                   s.supplierName AS supplier_name,
                   ig.created_date, ig.created_by, ig.ingoing_status AS delivery_status,
                   ig.deleted_at, ig.deleted_by,
                   CONCAT(creator.first_name, ' ', creator.last_name) AS created_by_name,
                   CONCAT(confirmer.first_name, ' ', confirmer.last_name) AS confirmed_by_name
            FROM ingoing_items ii
            INNER JOIN ingoing ig ON ig.ingoing_id = ii.ingoing_id
            LEFT JOIN inventory_items inv ON inv.item_id = ii.item_id
            LEFT JOIN units u ON u.unit_id = inv.unit_id
            LEFT JOIN warehouse_items wi ON wi.item_id = ii.item_id
            LEFT JOIN supplier s ON s.supplierId = ig.supplier_id
            LEFT JOIN users creator ON creator.id = ig.created_by
            LEFT JOIN users confirmer ON confirmer.id = ii.confirmed_by
            WHERE ig.deleted_at IS NOT NULL
            ORDER BY ig.deleted_at DESC, ii.ingoing_item_id DESC
            LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(function ($row) {
        $item = ingoing_item_to_frontend($row);
        $item['supplierName'] = $row['supplier_name'] ?? '';
        $item['createdByName'] = $row['created_by_name'] ?? '';
        $item['createdDate'] = $row['created_date'] ?? null;
        $item['deliveryStatus'] = $row['delivery_status'] ?? 'Pending';
        $item['deletedAt'] = $row['deleted_at'] ?? null;
        return $item;
    }, $rows);
}

// ──────────────────────────────────────────────────────────────────────────────
// Outgoing Tables (header + line-items pattern for outgoing records)
// ──────────────────────────────────────────────────────────────────────────────

function ensure_outgoing_tables(PDO $pdo): void {
    // Header: purpose is chosen ONCE per outgoing record. The header `status` is a transaction-level
    // roll-up: 'Pending' until every item has been approved/rejected, then 'Checked'.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `outgoing` (
        `outgoing_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `record_source` ENUM('Manual','POS') NOT NULL,
        `purpose` ENUM('Customer Purchase','Damaged Item Removal','For Replacement','Store Replenishment','IoT Shelves Replenishment','Emergency Release') NOT NULL,
        `created_by` INT(10) UNSIGNED NOT NULL,
        `created_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `checked_by` INT(10) UNSIGNED DEFAULT NULL,
        `checked_date` TIMESTAMP NULL DEFAULT NULL,
        `status` ENUM('Pending','Checked') NOT NULL DEFAULT 'Pending',
        PRIMARY KEY (`outgoing_id`),
        KEY `fk_outgoing_created_by` (`created_by`),
        KEY `fk_outgoing_checked_by` (`checked_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Line-items: each row carries its own source_location AND its own status/confirmation (per-item approval).
    $pdo->exec("CREATE TABLE IF NOT EXISTS `outgoing_items` (
        `outgoing_item_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `outgoing_id` INT(10) UNSIGNED NOT NULL,
        `item_id` INT(11) NOT NULL,
        `quantity` INT(10) UNSIGNED NOT NULL,
        `outgoing_notes` TEXT DEFAULT NULL,
        `outgoing_item_status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
        `source_location` ENUM('Warehouse','Store') NOT NULL DEFAULT 'Warehouse',
        `confirmed_by` INT(10) UNSIGNED DEFAULT NULL,
        `confirmed_date` DATETIME DEFAULT NULL,
        `confirmed_notes` TEXT DEFAULT NULL,
        PRIMARY KEY (`outgoing_item_id`),
        KEY `fk_outgoing_items_outgoing` (`outgoing_id`),
        KEY `fk_outgoing_items_item` (`item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Self-heal the header: ensure purpose exists; the header no longer stores status/source_location.
    checker_add_column_if_missing($pdo, 'outgoing', 'purpose', "ENUM('Customer Purchase','Damaged Item Removal','For Replacement','Store Replenishment','IoT Shelves Replenishment','Emergency Release') NOT NULL AFTER record_source");

    // Self-heal line-items: ensure per-item status, source_location, and confirmation columns exist.
    checker_add_column_if_missing($pdo, 'outgoing_items', 'outgoing_item_status', "ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER outgoing_notes");
    checker_add_column_if_missing($pdo, 'outgoing_items', 'source_location', "ENUM('Warehouse','Store') NOT NULL DEFAULT 'Warehouse' AFTER outgoing_item_status");
    checker_add_column_if_missing($pdo, 'outgoing_items', 'confirmed_by', "INT(10) UNSIGNED DEFAULT NULL AFTER source_location");
    checker_add_column_if_missing($pdo, 'outgoing_items', 'confirmed_date', "DATETIME DEFAULT NULL AFTER confirmed_by");
    checker_add_column_if_missing($pdo, 'outgoing_items', 'confirmed_notes', "TEXT DEFAULT NULL AFTER confirmed_date");

    // Normalise older source_location values that used the 'IoT Shelf' option no longer allowed.
    try { $pdo->exec("UPDATE outgoing_items SET source_location = 'Warehouse' WHERE source_location NOT IN ('Warehouse','Store')"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE outgoing_items MODIFY COLUMN source_location ENUM('Warehouse','Store') NOT NULL DEFAULT 'Warehouse'"); } catch (Throwable $e) {}

    // Ensure the header transaction-level status column exists (roll-up: Pending until all items checked).
    checker_add_column_if_missing($pdo, 'outgoing', 'status', "ENUM('Pending','Checked') NOT NULL DEFAULT 'Pending'");
    // If an older DB used a different status enum on the header, coerce it to the two allowed values.
    try { $pdo->exec("UPDATE outgoing SET status = 'Pending' WHERE status NOT IN ('Pending','Checked')"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE outgoing MODIFY COLUMN status ENUM('Pending','Checked') NOT NULL DEFAULT 'Pending'"); } catch (Throwable $e) {}
    // Backfill: any transaction whose items are all resolved (no Pending items) should be marked Checked.
    try {
        $pdo->exec("UPDATE outgoing o
                    SET o.status = 'Checked'
                    WHERE o.status <> 'Checked'
                      AND EXISTS (SELECT 1 FROM outgoing_items oi WHERE oi.outgoing_id = o.outgoing_id)
                      AND NOT EXISTS (SELECT 1 FROM outgoing_items oi WHERE oi.outgoing_id = o.outgoing_id AND oi.outgoing_item_status = 'Pending')");
    } catch (Throwable $e) {}

    // Add FK constraints if not already present
    try { $pdo->exec("ALTER TABLE `outgoing` ADD CONSTRAINT `fk_outgoing_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `outgoing` ADD CONSTRAINT `fk_outgoing_checked_by` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `outgoing_items` ADD CONSTRAINT `fk_outgoing_items_outgoing` FOREIGN KEY (`outgoing_id`) REFERENCES `outgoing` (`outgoing_id`) ON DELETE CASCADE ON UPDATE CASCADE"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `outgoing_items` ADD CONSTRAINT `fk_outgoing_items_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`item_id`) ON UPDATE CASCADE"); } catch (Throwable $e) {}
}

/**
 * Roll up an outgoing transaction's header status.
 * If every line-item has been processed (none left 'Pending'), mark the header 'Checked'
 * and stamp the reviewer; otherwise keep it 'Pending' while still recording the latest reviewer.
 */
function outgoing_rollup_status(PDO $pdo, int $outgoingId, ?int $userId): void {
    $stmt = $pdo->prepare("SELECT SUM(outgoing_item_status = 'Pending') AS pending_count FROM outgoing_items WHERE outgoing_id = ?");
    $stmt->execute([$outgoingId]);
    $pending = (int)($stmt->fetchColumn() ?: 0);

    if ($pending === 0) {
        $pdo->prepare("UPDATE outgoing SET status = 'Checked', checked_by = ?, checked_date = NOW() WHERE outgoing_id = ?")
            ->execute([$userId, $outgoingId]);
    } else {
        // Still has pending items — keep status Pending but record who last acted.
        $pdo->prepare("UPDATE outgoing SET checked_by = ?, checked_date = NOW() WHERE outgoing_id = ?")
            ->execute([$userId, $outgoingId]);
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// Return Requests (customer refunds / replacements against a POS sale item)
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Self-heal the return_request table so the checker returns panel works even on
 * older databases. Mirrors the ensure_*_tables() DDL pattern used elsewhere.
 *
 * The live schema uses the singular sale / sale_item tables; the FK to sale_item
 * is added best-effort so a missing/renamed sale table cannot block returns.
 */
function ensure_return_request_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `return_request` (
        `return_request_id` INT(11) NOT NULL AUTO_INCREMENT,
        `sale_item_id` INT(11) DEFAULT NULL,
        `return_type` ENUM('Refund','Replacement') NOT NULL,
        `return_quantity` INT(11) NOT NULL,
        `return_money` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `reason` TEXT DEFAULT NULL,
        `status` ENUM('Pending','Completed') NOT NULL DEFAULT 'Pending',
        `created_by` INT(10) UNSIGNED NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
        `approved_by` INT(10) UNSIGNED DEFAULT NULL,
        `approved_at` TIMESTAMP NULL DEFAULT NULL,
        `outgoing_item_id` INT(10) UNSIGNED DEFAULT NULL,
        PRIMARY KEY (`return_request_id`),
        KEY `fk_return_request_to_sale_item` (`sale_item_id`),
        KEY `fk_return_created_by_users` (`created_by`),
        KEY `fk_return_approved_by_users` (`approved_by`),
        KEY `fk_return_request_to_outgoing_item` (`outgoing_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Self-heal individual columns in case an older/partial table already exists.
    checker_add_column_if_missing($pdo, 'return_request', 'sale_item_id', "INT(11) DEFAULT NULL AFTER return_request_id");
    checker_add_column_if_missing($pdo, 'return_request', 'return_type', "ENUM('Refund','Replacement') NOT NULL AFTER sale_item_id");
    checker_add_column_if_missing($pdo, 'return_request', 'return_quantity', "INT(11) NOT NULL AFTER return_type");
    checker_add_column_if_missing($pdo, 'return_request', 'return_money', "DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER return_quantity");
    checker_add_column_if_missing($pdo, 'return_request', 'reason', "TEXT DEFAULT NULL AFTER return_money");
    checker_add_column_if_missing($pdo, 'return_request', 'status', "ENUM('Pending','Completed') NOT NULL DEFAULT 'Pending' AFTER reason");
    checker_add_column_if_missing($pdo, 'return_request', 'created_by', "INT(10) UNSIGNED NOT NULL AFTER status");
    checker_add_column_if_missing($pdo, 'return_request', 'created_at', "TIMESTAMP NOT NULL DEFAULT current_timestamp() AFTER created_by");
    checker_add_column_if_missing($pdo, 'return_request', 'approved_by', "INT(10) UNSIGNED DEFAULT NULL AFTER created_at");
    checker_add_column_if_missing($pdo, 'return_request', 'approved_at', "TIMESTAMP NULL DEFAULT NULL AFTER approved_by");
    checker_add_column_if_missing($pdo, 'return_request', 'outgoing_item_id', "INT(10) UNSIGNED DEFAULT NULL AFTER approved_at");

    // Best-effort FK constraints (skipped silently if they already exist or the referenced table is absent).
    try { $pdo->exec("ALTER TABLE `return_request` ADD CONSTRAINT `fk_return_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `return_request` ADD CONSTRAINT `fk_return_approved_by_users` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `return_request` ADD CONSTRAINT `fk_return_request_to_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_item` (`sale_item_id`)"); } catch (Throwable $e) {}
}

/**
 * Transform a return_request row (with joins) into camelCase frontend format.
 */
function return_request_to_frontend(array $row): array {
    return [
        'returnRequestId' => (int)($row['return_request_id'] ?? 0),
        'saleItemId'      => isset($row['sale_item_id']) ? (int)$row['sale_item_id'] : null,
        'saleId'          => isset($row['sale_id']) ? (int)$row['sale_id'] : null,
        'saleNumber'      => $row['sale_number'] ?? '',
        'itemId'          => isset($row['item_id']) ? (int)$row['item_id'] : null,
        'itemName'        => $row['item_name'] ?? '',
        'sku'             => $row['sku'] ?? '',
        'unit'            => $row['unit_name'] ?? '',
        'soldQuantity'    => isset($row['sold_quantity']) ? (int)$row['sold_quantity'] : null,
        'unitPrice'       => isset($row['unit_price']) ? (float)$row['unit_price'] : null,
        'storageLocation' => $row['storage_location'] ?? '',
        'returnType'      => $row['return_type'] ?? 'Refund',
        'returnQuantity'  => (int)($row['return_quantity'] ?? 0),
        'returnMoney'     => (float)($row['return_money'] ?? 0),
        'reason'          => $row['reason'] ?? '',
        'status'          => $row['status'] ?? 'Pending',
        'createdBy'       => isset($row['created_by']) ? (int)$row['created_by'] : null,
        'createdByName'   => $row['created_by_name'] ?? '',
        'createdAt'       => $row['created_at'] ?? null,
        'approvedBy'      => isset($row['approved_by']) ? (int)$row['approved_by'] : null,
        'approvedByName'  => $row['approved_by_name'] ?? '',
        'approvedAt'      => $row['approved_at'] ?? null,
        'outgoingItemId'  => isset($row['outgoing_item_id']) ? (int)$row['outgoing_item_id'] : null,
    ];
}

/**
 * Load return requests with sale / item / user joins, optionally filtered by status.
 * The joins are LEFT so a return still shows even if its sale item was removed.
 */
function load_return_requests(PDO $pdo, ?string $statusFilter = null, int $limit = 300): array {
    $where = $statusFilter ? "WHERE rr.status = ?" : "";
    $sql = "SELECT rr.*,
                   si.sale_id, si.item_id, si.quantity AS sold_quantity, si.unit_price, si.storage_location,
                   s.sale_number,
                   inv.item_name, inv.sku, u.unit_name,
                   CONCAT(creator.first_name, ' ', creator.last_name) AS created_by_name,
                   CONCAT(approver.first_name, ' ', approver.last_name) AS approved_by_name
            FROM return_request rr
            LEFT JOIN sale_item si ON si.sale_item_id = rr.sale_item_id
            LEFT JOIN sale s ON s.sale_id = si.sale_id
            LEFT JOIN inventory_items inv ON inv.item_id = si.item_id
            LEFT JOIN units u ON u.unit_id = inv.unit_id
            LEFT JOIN users creator ON creator.id = rr.created_by
            LEFT JOIN users approver ON approver.id = rr.approved_by
            {$where}
            ORDER BY
              CASE rr.status WHEN 'Pending' THEN 0 ELSE 1 END,
              rr.created_at DESC
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($statusFilter ? [$statusFilter] : []);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map('return_request_to_frontend', $rows);
}

/**
 * Load sold sale-item line rows for the "new return" picker.
 * Returns each sale line with item details so the checker can raise a return against it.
 */
function load_returnable_sale_items(PDO $pdo, int $limit = 300): array {
    $sql = "SELECT si.sale_item_id, si.sale_id, si.item_id, si.quantity, si.unit_price, si.storage_location,
                   s.sale_number, s.created_at AS sold_at,
                   inv.item_name, inv.sku, u.unit_name
            FROM sale_item si
            INNER JOIN sale s ON s.sale_id = si.sale_id
            LEFT JOIN inventory_items inv ON inv.item_id = si.item_id
            LEFT JOIN units u ON u.unit_id = inv.unit_id
            ORDER BY s.created_at DESC, si.sale_item_id DESC
            LIMIT {$limit}";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return array_map(function ($r) {
        return [
            'saleItemId'      => (int)$r['sale_item_id'],
            'saleId'          => (int)$r['sale_id'],
            'saleNumber'      => $r['sale_number'] ?? '',
            'itemId'          => (int)$r['item_id'],
            'itemName'        => $r['item_name'] ?? '',
            'sku'             => $r['sku'] ?? '',
            'unit'            => $r['unit_name'] ?? '',
            'quantity'        => (int)$r['quantity'],
            'unitPrice'       => (float)$r['unit_price'],
            'storageLocation' => $r['storage_location'] ?? '',
            'soldAt'          => $r['sold_at'] ?? null,
        ];
    }, $rows);
}
