<?php
require_once __DIR__ . '/common.php';
require_login(['checker', 'admin', 'super-admin']);

// Maximum quantity allowed per checker line item (mirrors the frontend QTY_MAX).
if (!defined('CHECKER_QTY_MAX')) define('CHECKER_QTY_MAX', 100000);
ensure_checker_tables($pdo);
ensure_ingoing_tables($pdo);
ensure_outgoing_tables($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Method not allowed.'], 405);

function checker_normalize_status(string $status, string $type): string {
    $status = strtolower(trim($status ?: 'pending'));
    $allowed = ['received','released','damaged','replacement','missing','pending','ok','cancelled'];
    if (!in_array($status, $allowed, true)) return $type === 'incoming' ? 'received' : 'released';
    return $status;
}

function checker_item_value(array $item, string $key, $fallback = '') {
    return $item[$key] ?? $fallback;
}

function checker_item_id_from_payload(array $item): int {
    foreach (['inventoryItemId', 'itemId', 'dbId', 'id'] as $key) {
        if (isset($item[$key]) && (int)$item[$key] > 0) return (int)$item[$key];
    }
    return 0;
}

try {
    $data = get_json_input();
    $type = strtolower(trim((string)($data['type'] ?? '')));
    if (!in_array($type, ['incoming', 'outgoing'], true)) json_response(['success' => false, 'message' => 'Invalid checker record type.'], 422);

    $user = current_user();
    $userId = $user['id'] ?? null;
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : null;

    $pdo->beginTransaction();
    $saved = 0;

    if ($type === 'incoming') {
        // ── Uses ingoing + ingoing_items tables (header + line-items) ──
        if ($items === null) {
            $items = [[
                'inventoryItemId' => $data['inventoryItemId'] ?? $data['itemId'] ?? 0,
                'quantity' => $data['quantity'] ?? 0,
            ]];
        }

        $supplierId = (int)($data['supplier'] ?? 0);
        if ($supplierId <= 0) json_response(['success' => false, 'message' => 'Please select a supplier.'], 422);

        // Validate all items before inserting
        $validatedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $itemId = checker_item_id_from_payload($item);
            $qty = (int)checker_item_value($item, 'quantity', 0);

            if ($itemId <= 0) json_response(['success' => false, 'message' => 'Please choose an inventory item for every delivered item.'], 422);
            if ($qty <= 0) json_response(['success' => false, 'message' => 'Quantity must be greater than zero for every delivered item.'], 422);
            if ($qty > CHECKER_QTY_MAX) json_response(['success' => false, 'message' => 'Quantity cannot exceed ' . CHECKER_QTY_MAX . ' for any delivered item.'], 422);

            $inventoryItem = checker_inventory_item($pdo, $itemId, true);
            if (!$inventoryItem) json_response(['success' => false, 'message' => 'Selected inventory item was not found or is inactive.'], 422);

            $validatedItems[] = [
                'item_id' => (int)($inventoryItem['item_id'] ?? $inventoryItem['id']),
                'quantity' => $qty,
                'condition' => trim((string)checker_item_value($item, 'condition', 'Accepted / Good')),
                'notes' => trim((string)checker_item_value($item, 'notes', '')),
            ];
        }

        if (empty($validatedItems)) json_response(['success' => false, 'message' => 'Add at least one delivered item.'], 422);

        // Insert ingoing header
        $notes = trim((string)($data['notes'] ?? ''));
        $insHeader = $pdo->prepare("INSERT INTO ingoing (supplier_id, ingoing_status, ingoing_notes, created_by) VALUES (?, 'Pending', ?, ?)");
        $insHeader->execute([$supplierId, $notes ?: null, $userId]);
        $ingoingId = (int)$pdo->lastInsertId();

        // Insert ingoing_items. When the checker leaves the note blank, fall back to a
        // default note derived from the item's reported condition.
        $insItem = $pdo->prepare("INSERT INTO ingoing_items (item_id, ingoing_id, ingoing_quantity, ingoing_item_condition, ingoing_item_notes) VALUES (?, ?, ?, ?, ?)");
        foreach ($validatedItems as $vi) {
            $itemNote = $vi['notes'] !== '' ? $vi['notes'] : ingoing_default_item_note($vi['condition']);
            $insItem->execute([$vi['item_id'], $ingoingId, $vi['quantity'], $vi['condition'], $itemNote]);
            $saved++;
        }
    } else {
        $source = strtolower(trim((string)($data['source'] ?? 'pos')));
        if ($source === 'manual') {
            if ($items === null) {
                $items = [[
                    'inventoryItemId' => $data['inventoryItemId'] ?? $data['itemId'] ?? 0,
                    'quantity' => $data['quantity'] ?? 0,
                    'sourceLocation' => $data['sourceLocation'] ?? 'Warehouse',
                    'notes' => $data['notes'] ?? '',
                ]];
            }

            // Purpose is selected ONCE for the whole outgoing record (header-level).
            $purpose = trim((string)($data['purpose'] ?? $items[0]['purpose'] ?? 'Customer Purchase'));

            $allowedLocations = ['Warehouse', 'Store'];
            $allowedPurposes = ['Customer Purchase', 'Damaged Item Removal', 'For Replacement', 'Store Replenishment', 'IoT Shelves Replenishment', 'Emergency Release'];
            if (!in_array($purpose, $allowedPurposes, true)) json_response(['success' => false, 'message' => 'Invalid purpose.'], 422);

            // Validate all items first. Source location is per-item (chosen on each Add Item row).
            $validatedItems = [];
            $seenItemSourcePairs = [];
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $itemId = checker_item_id_from_payload($item);
                $qty = (int)checker_item_value($item, 'quantity', 0);
                $notes = trim((string)checker_item_value($item, 'notes', ''));
                $sourceLocation = trim((string)checker_item_value($item, 'sourceLocation', 'Warehouse'));

                if ($itemId <= 0) json_response(['success' => false, 'message' => 'Please choose an inventory item for every outgoing item.'], 422);
                if ($qty <= 0) json_response(['success' => false, 'message' => 'Quantity must be greater than zero for every outgoing item.'], 422);
                if ($qty > CHECKER_QTY_MAX) json_response(['success' => false, 'message' => 'Quantity cannot exceed ' . CHECKER_QTY_MAX . ' for any outgoing item.'], 422);
                if (!in_array($sourceLocation, $allowedLocations, true)) json_response(['success' => false, 'message' => 'Invalid source location for one of the outgoing items.'], 422);

                $inventoryItem = checker_inventory_item($pdo, $itemId, true);
                if (!$inventoryItem) json_response(['success' => false, 'message' => 'Selected inventory item was not found or is inactive.'], 422);

                $resolvedItemId = (int)($inventoryItem['item_id'] ?? $inventoryItem['id']);

                // ── Stock availability check ──────────────────────────────────
                // Query the actual available qty for the chosen source location and
                // reject the whole submission early if there is not enough stock.
                if ($sourceLocation === 'Warehouse') {
                    $stockStmt = $pdo->prepare("SELECT COALESCE(warehouse_item_quantity, 0) FROM warehouse_items WHERE item_id = ? LIMIT 1");
                    $stockStmt->execute([$resolvedItemId]);
                    $availableQty = (int)($stockStmt->fetchColumn() ?: 0);
                } else { // Store
                    $stockStmt = $pdo->prepare("SELECT COALESCE(store_item_quantity, 0) FROM store_items WHERE item_id = ? LIMIT 1");
                    $stockStmt->execute([$resolvedItemId]);
                    $availableQty = (int)($stockStmt->fetchColumn() ?: 0);
                }

                if ($qty > $availableQty) {
                    $itemLabel = $inventoryItem['item_name'] ?? ('Item #' . $resolvedItemId);
                    json_response([
                        'success'   => false,
                        'message'   => "Insufficient stock for \"{$itemLabel}\" in {$sourceLocation}. "
                                     . "Requested: {$qty}, Available: {$availableQty}.",
                    ], 422);
                }
                // ─────────────────────────────────────────────────────────────

                // Reject duplicate items that share the same source location.
                // The same item may repeat only if each entry uses a different source location.
                $pairKey = $resolvedItemId . '|' . $sourceLocation;
                if (isset($seenItemSourcePairs[$pairKey])) {
                    $dupName = $inventoryItem['item_name'] ?? 'This item';
                    json_response(['success' => false, 'message' => 'Duplicate item: "' . $dupName . '" is listed more than once with the same source location (' . $sourceLocation . '). Use a different source location for the duplicate.'], 422);
                }
                $seenItemSourcePairs[$pairKey] = true;

                $validatedItems[] = [
                    'item_id' => $resolvedItemId,
                    'quantity' => $qty,
                    'source_location' => $sourceLocation,
                    'notes' => $notes,
                ];
            }

            if (empty($validatedItems)) json_response(['success' => false, 'message' => 'Add at least one outgoing item.'], 422);

            // Insert outgoing header (purpose once per record; status is tracked per item)
            $insHeader = $pdo->prepare("INSERT INTO outgoing (record_source, purpose, created_by) VALUES ('Manual', ?, ?)");
            $insHeader->execute([$purpose, $userId]);
            $outgoingId = (int)$pdo->lastInsertId();

            // When the user leaves a note blank, default it based on the chosen purpose.
            $purposeNoteDefaults = [
                'Customer Purchase'          => 'Released for customer purchase.',
                'Damaged Item Removal'       => 'Removed due to damage.',
                'For Replacement'            => 'Released for replacement.',
                'Store Replenishment'        => 'Transferred to store for replenishment.',
                'IoT Shelves Replenishment'  => 'Transferred to IoT shelves for replenishment.',
                'Emergency Release'          => 'Emergency release.',
            ];
            $defaultOutgoingNote = $purposeNoteDefaults[$purpose] ?? 'Outgoing item.';

            // Insert outgoing_items with per-row source location; each item starts Pending (per-item approval)
            $insItem = $pdo->prepare("INSERT INTO outgoing_items (outgoing_id, item_id, quantity, source_location, outgoing_notes, outgoing_item_status) VALUES (?, ?, ?, ?, ?, 'Pending')");

            foreach ($validatedItems as $vi) {
                $itemNote = $vi['notes'] !== '' ? $vi['notes'] : $defaultOutgoingNote;
                $insItem->execute([$outgoingId, $vi['item_id'], $vi['quantity'], $vi['source_location'], $itemNote]);
                $saved++;
            }
        } else {
            $saleItemId = (int)($data['saleItemId'] ?? 0);
            $qty = (float)($data['quantity'] ?? 0);
            if ($qty <= 0) json_response(['success' => false, 'message' => 'Quantity must be greater than zero.'], 422);
            if ($qty > CHECKER_QTY_MAX) json_response(['success' => false, 'message' => 'Quantity cannot exceed ' . CHECKER_QTY_MAX . '.'], 422);
            $status = checker_normalize_status((string)($data['status'] ?? 'released'), 'outgoing');
            $notes = trim((string)($data['notes'] ?? ''));

            $stmt = $pdo->prepare("SELECT si.*, s.id AS sale_id, s.storage_location FROM sale_items si INNER JOIN sales s ON s.id = si.sale_id WHERE si.id = ? LIMIT 1");
            $stmt->execute([$saleItemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) json_response(['success' => false, 'message' => 'Sold item not found.'], 404);
            if (($item['storage_location'] ?? 'store') !== 'warehouse') json_response(['success' => false, 'message' => 'Only Warehouse sales can be checked for outgoing release.'], 422);

            $purpose = trim((string)($data['purpose'] ?? 'POS Warehouse Release'));
            $ins = $pdo->prepare("INSERT INTO checker_records (record_type, record_source, inventory_item_id, sale_id, sale_item_id, sku, item_name, quantity, unit, purpose, check_status, notes, checked_by)
                                  VALUES ('outgoing', 'pos', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), purpose = VALUES(purpose), check_status = VALUES(check_status), notes = VALUES(notes), checked_by = VALUES(checked_by), checked_at = NOW()");
            $ins->execute([$item['inventory_item_id'], $item['sale_id'], $item['id'], $item['sku'], $item['item_name'], $qty, $item['unit'], $purpose ?: 'POS Warehouse Release', $status, $notes, $userId]);
            $saved = 1;
        }
    }

    if ($saved <= 0) json_response(['success' => false, 'message' => 'No checker items were saved.'], 422);
    $pdo->commit();

    // ── Notify reviewers about the new checker record (post-commit, best-effort). ──
    require_once __DIR__ . '/../notifications/common.php';
    $actorId = (int)($userId ?? 0);
    if ($type === 'incoming' && !empty($ingoingId)) {
        $supplierName = '';
        try {
            $sn = $pdo->prepare('SELECT supplierName FROM supplier WHERE supplierId = ? LIMIT 1');
            $sn->execute([$supplierId]);
            $supplierName = (string)($sn->fetchColumn() ?: '');
        } catch (Throwable $e) { /* ignore */ }
        $supplierLabel = $supplierName !== '' ? $supplierName : 'a supplier';
        foreach (['admin', 'inventory', 'super-admin'] as $r) {
            notify_role(
                $pdo,
                $r,
                'incoming',
                'Incoming delivery to confirm',
                'A delivery from ' . $supplierLabel . ' with ' . $saved . ' item(s) is pending confirmation.',
                notification_link_for_role($r, 'incoming-confirmation'),
                'ingoing:' . $ingoingId . ':pending',
                $actorId
            );
        }
    } elseif ($type === 'outgoing' && !empty($outgoingId)) {
        // Notify admin/inventory/super-admin for ALL manual outgoing records —
        // including Store Replenishment and IoT Shelves Replenishment. These are
        // still per-item approval workflows: every item starts Pending and needs
        // an admin/inventory confirmation before stock moves. Skipping the
        // notification for replenishment purposes left reviewers unaware.
        $isReplenishment = in_array($purpose, ['Store Replenishment', 'IoT Shelves Replenishment'], true);
        $notifTitle = $isReplenishment
            ? 'Replenishment transfer to confirm'
            : 'Outgoing release to confirm';
        $notifMsg = $isReplenishment
            ? 'A ' . $purpose . ' transfer with ' . $saved . ' item(s) is pending your confirmation.'
            : 'An outgoing release (' . $purpose . ') with ' . $saved . ' item(s) is pending confirmation.';
        foreach (['admin', 'inventory', 'super-admin'] as $r) {
            notify_role(
                $pdo,
                $r,
                'outgoing',
                $notifTitle,
                $notifMsg,
                notification_link_for_role($r, 'outgoing-confirmation'),
                'outgoing:' . $outgoingId . ':pending',
                $actorId
            );
        }
    }

    json_response(['success' => true, 'message' => $saved . ' checker record(s) saved successfully.', 'saved' => $saved]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    json_response(['success' => false, 'message' => 'Unable to save checker record: ' . $e->getMessage()], 500);
}
