<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../notifications/common.php';
require_login(['checker', 'admin', 'super-admin']);
ensure_outgoing_tables($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Method not allowed.'], 405);

try {
    $data = get_json_input();
    // Per-item confirmation: each outgoing_item is approved/rejected independently.
    $outgoingItemId = (int)($data['outgoingItemId'] ?? $data['recordId'] ?? 0);
    $action = strtolower(trim((string)($data['action'] ?? '')));
    $notes = trim((string)($data['notes'] ?? ''));

    if ($outgoingItemId <= 0) json_response(['success' => false, 'message' => 'Outgoing item ID is required.'], 422);
    if (!in_array($action, ['approve', 'approved', 'confirm', 'confirmed', 'reject', 'rejected'], true)) {
        json_response(['success' => false, 'message' => 'Invalid action.'], 422);
    }

    $user = current_user();
    $userId = $user['id'] ?? null;
    $isApprove = in_array($action, ['approve', 'approved', 'confirm', 'confirmed'], true);

    $pdo->beginTransaction();

    // Lock the single line-item together with its header purpose.
    $stmt = $pdo->prepare("SELECT oi.*, o.purpose, o.outgoing_id AS parent_outgoing_id, o.created_by AS logged_by, o.record_source, inv.item_name
                           FROM outgoing_items oi
                           INNER JOIN outgoing o ON o.outgoing_id = oi.outgoing_id
                           LEFT JOIN inventory_items inv ON inv.item_id = oi.item_id
                           WHERE oi.outgoing_item_id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$outgoingItemId]);
    $line = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$line) json_response(['success' => false, 'message' => 'Outgoing item not found.'], 404);
    if (($line['outgoing_item_status'] ?? 'Pending') !== 'Pending') {
        json_response(['success' => false, 'message' => 'This item has already been ' . strtolower($line['outgoing_item_status']) . '.'], 422);
    }

    $outgoingId = (int)$line['parent_outgoing_id'];
    $purpose = $line['purpose'];
    $itemId = (int)$line['item_id'];
    $qty = (int)$line['quantity'];
    $sourceLocation = $line['source_location'] ?? 'Warehouse';

    if (!$isApprove) {
        // REJECT — no stock changes, just mark the single item rejected.
        $update = $pdo->prepare("UPDATE outgoing_items SET outgoing_item_status = 'Rejected', confirmed_by = ?, confirmed_date = NOW(), confirmed_notes = ? WHERE outgoing_item_id = ?");
        $update->execute([$userId, $notes ?: null, $outgoingItemId]);

        // Roll up the transaction status to 'Checked' once no items remain Pending.
        outgoing_rollup_status($pdo, $outgoingId, $userId);

        $pdo->commit();

        // Notify the manual-outgoing creator that their item was rejected.
        notify_outgoing_confirmation($pdo, $line, 'rejected', (int)$userId, $notes);

        json_response(['success' => true, 'message' => 'Item rejected. No stock changes made.']);
    }

    // APPROVE — deduct from the item's own source location and, for replenishment purposes, add to the destination.
    if ($qty > 0) {
        $before = 0;
        if ($sourceLocation === 'Warehouse') {
            $stk = $pdo->prepare("SELECT warehouse_item_quantity FROM warehouse_items WHERE item_id = ? LIMIT 1");
            $stk->execute([$itemId]);
            $before = (int)($stk->fetchColumn() ?: 0);
            $after = max(0, $before - $qty);
            $pdo->prepare("UPDATE warehouse_items SET warehouse_item_quantity = ? WHERE item_id = ?")->execute([$after, $itemId]);
        } elseif ($sourceLocation === 'Store') {
            $stk = $pdo->prepare("SELECT store_item_quantity FROM store_items WHERE item_id = ? LIMIT 1");
            $stk->execute([$itemId]);
            $before = (int)($stk->fetchColumn() ?: 0);
            $after = max(0, $before - $qty);
            $pdo->prepare("UPDATE store_items SET store_item_quantity = ? WHERE item_id = ?")->execute([$after, $itemId]);
        }

        // Add to destination based on purpose.
        if ($purpose === 'Store Replenishment') {
            $existing = $pdo->prepare("SELECT store_item_id FROM store_items WHERE item_id = ? LIMIT 1");
            $existing->execute([$itemId]);
            if ($existing->fetchColumn()) {
                $pdo->prepare("UPDATE store_items SET store_item_quantity = store_item_quantity + ? WHERE item_id = ?")->execute([$qty, $itemId]);
            } else {
                $pdo->prepare("INSERT INTO store_items (item_id, store_item_quantity) VALUES (?, ?)")->execute([$itemId, $qty]);
            }
        } elseif ($purpose === 'IoT Shelves Replenishment') {
            $pdo->prepare("UPDATE shelves SET shelf_quantity = shelf_quantity + ? WHERE item_id = ?")->execute([$qty, $itemId]);
        }

        // Log stock movement for the deduction from source.
        checker_record_stock_movement(
            $pdo,
            $itemId,
            'outgoing',
            -$qty,
            $before,
            max(0, $before - $qty),
            'outgoing',
            $outgoingId,
            $purpose . ' from ' . $sourceLocation,
            $userId
        );
    }

    // Mark the single item approved. Default the confirmation note when none is provided.
    $confirmNote = $notes !== '' ? $notes : 'Quantity verified and approved';
    $update = $pdo->prepare("UPDATE outgoing_items SET outgoing_item_status = 'Approved', confirmed_by = ?, confirmed_date = NOW(), confirmed_notes = ? WHERE outgoing_item_id = ?");
    $update->execute([$userId, $confirmNote, $outgoingItemId]);

    // Roll up the transaction status to 'Checked' once no items remain Pending.
    outgoing_rollup_status($pdo, $outgoingId, $userId);

    $pdo->commit();

    // Notify the manual-outgoing creator that their item was approved.
    // Flag whether this approval is a real stock deduction (i.e. stock leaves
    // the inventory system entirely) vs. an internal transfer that just moves
    // stock between locations without reducing total inventory.
    $isDeduction = !in_array($purpose, ['Store Replenishment', 'IoT Shelves Replenishment'], true);
    notify_outgoing_confirmation($pdo, $line, 'approved', (int)$userId, $confirmNote, $qty, $sourceLocation, $isDeduction);

    json_response([
        'success' => true,
        'message' => 'Item approved. ' . $qty . ' unit(s) deducted from ' . $sourceLocation . '.',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    json_response(['success' => false, 'message' => 'Unable to confirm outgoing item: ' . $e->getMessage()], 500);
}

/**
 * Notify the creator of a manual outgoing release about the confirmation
 * outcome of one of their items. Best-effort — never throws. POS-originated
 * releases are skipped (the cashier who created them has no notification bell).
 *
 * @param array  $line           Locked outgoing_items row joined with header (logged_by, record_source, item_name).
 * @param string $outcome        'approved' or 'rejected'.
 * @param int    $actorId        The user who processed the item (skipped as recipient).
 * @param string $note           Confirmation note.
 * @param int    $qty            Units affected (approve only).
 * @param string $sourceLocation Source location (approve only).
 * @param bool   $isDeduction    True when stock actually leaves the inventory system (not an internal transfer).
 */
function notify_outgoing_confirmation(PDO $pdo, array $line, string $outcome, int $actorId, string $note = '', int $qty = 0, string $sourceLocation = '', bool $isDeduction = true): void {
    if (strtolower((string)($line['record_source'] ?? '')) === 'pos') {
        return; // POS release creator (cashier) has no bell
    }
    $creatorId = (int)($line['logged_by'] ?? 0);
    $itemName = (string)($line['item_name'] ?? 'an item');
    $outgoingItemId = (int)($line['outgoing_item_id'] ?? 0);
    $outgoingId = (int)($line['parent_outgoing_id'] ?? $line['outgoing_id'] ?? 0);
    $purpose = (string)($line['purpose'] ?? '');

    if ($outcome === 'approved') {
        $title = 'Outgoing item approved';
        if ($purpose === 'Store Replenishment') {
            $msg = '"' . $itemName . '" was approved — ' . $qty . ' unit(s) deducted from ' . ($sourceLocation ?: 'stock') . ' and added to Store stock.';
        } elseif ($purpose === 'IoT Shelves Replenishment') {
            $msg = '"' . $itemName . '" was approved — ' . $qty . ' unit(s) deducted from ' . ($sourceLocation ?: 'stock') . ' and added to IoT Shelves.';
        } else {
            $msg = '"' . $itemName . '" was approved — ' . $qty . ' unit(s) released from ' . ($sourceLocation ?: 'stock') . '.';
            if ($purpose !== '') {
                $msg .= ' Purpose: ' . $purpose . '.';
            }
        }
    } else {
        $title = 'Outgoing item rejected';
        $msg = '"' . $itemName . '" was rejected.' . ($note !== '' ? ' Note: ' . $note : '');
    }

    // Notify the checker who created the outgoing record (skip self-notification).
    if ($creatorId > 0 && $creatorId !== $actorId) {
        notify_user(
            $pdo,
            $creatorId,
            'outgoing',
            $title,
            $msg,
            notification_link_for_role('checker', 'checker'),
            'outgoing_item:' . $outgoingItemId . ':' . $outcome,
            'checker'
        );
    }

    // On approval, broadcast to admin, super-admin, and inventory for all
    // outgoing types — including replenishment transfers. These roles confirmed
    // the item themselves but other users on the same roles need visibility.
    if ($outcome === 'approved') {
        if ($isDeduction) {
            $broadcastTitle = 'Outgoing transaction approved';
            $broadcastMsg = '"' . $itemName . '" — ' . $qty . ' unit(s) deducted from ' . ($sourceLocation ?: 'stock') . '.';
            if ($purpose !== '') {
                $broadcastMsg .= ' Purpose: ' . $purpose . '.';
            }
        } elseif ($purpose === 'Store Replenishment') {
            $broadcastTitle = 'Store replenishment approved';
            $broadcastMsg = '"' . $itemName . '" — ' . $qty . ' unit(s) transferred from ' . ($sourceLocation ?: 'stock') . ' to Store stock.';
        } else {
            // IoT Shelves Replenishment
            $broadcastTitle = 'IoT Shelves replenishment approved';
            $broadcastMsg = '"' . $itemName . '" — ' . $qty . ' unit(s) transferred from ' . ($sourceLocation ?: 'stock') . ' to IoT Shelves.';
        }

        foreach (['admin', 'super-admin', 'inventory'] as $r) {
            $link = notification_link_for_role($r, 'outgoing-confirmation');
            // Per-user dedupe suffix is applied inside notify_roles; use a per-item
            // key so the same approval event does not fire twice for the same user.
            notify_role(
                $pdo,
                $r,
                'outgoing',
                $broadcastTitle,
                $broadcastMsg,
                $link,
                'outgoing_item:' . $outgoingItemId . ':approved:broadcast',
                $actorId  // exclude the confirmer from receiving their own broadcast
            );
        }
    }
}
