<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../notifications/common.php';
require_login(['admin', 'inventory', 'super-admin']);
ensure_ingoing_tables($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Method not allowed.'], 405);

try {
    $data = get_json_input();
    $ingoingItemId = (int)($data['ingoingItemId'] ?? 0);
    $action = strtolower(trim((string)($data['action'] ?? 'approve')));
    $notes = trim((string)($data['notes'] ?? ''));

    if ($ingoingItemId <= 0) json_response(['success' => false, 'message' => 'Ingoing item ID is required.'], 422);
    if (!in_array($action, ['approve', 'approved', 'confirm', 'confirmed', 'reject', 'rejected'], true)) {
        json_response(['success' => false, 'message' => 'Invalid confirmation action.'], 422);
    }

    $user = current_user();
    $userId = $user['id'] ?? null;
    $isApprove = in_array($action, ['approve', 'approved', 'confirm', 'confirmed'], true);

    // A confirmation note is mandatory when rejecting an item.
    if (!$isApprove && $notes === '') {
        json_response(['success' => false, 'message' => 'A confirmation note is required when rejecting an item.'], 422);
    }

    $pdo->beginTransaction();

    // Lock the ingoing item row
    $stmt = $pdo->prepare("SELECT ii.*, inv.item_name,
                                  COALESCE(wi.warehouse_item_quantity, 0) AS current_stock,
                                  wi.warehouse_item_id,
                                  ing.created_by AS logged_by
                           FROM ingoing_items ii
                           INNER JOIN inventory_items inv ON inv.item_id = ii.item_id
                           INNER JOIN ingoing ing ON ing.ingoing_id = ii.ingoing_id
                           LEFT JOIN warehouse_items wi ON wi.item_id = ii.item_id
                           WHERE ii.ingoing_item_id = ?
                           LIMIT 1 FOR UPDATE");
    $stmt->execute([$ingoingItemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) json_response(['success' => false, 'message' => 'Ingoing item was not found.'], 404);

    $currentStatus = $item['ingoing_item_status'] ?? 'Pending';
    if ($currentStatus !== 'Pending') {
        json_response(['success' => false, 'message' => 'This item has already been ' . strtolower($currentStatus) . '.'], 422);
    }

    $newStatus = $isApprove ? 'Confirmed' : 'Rejected';

    if (!$isApprove) {
        // ── REJECT: mark item as Rejected, no inventory changes ──
        // Default the confirmation note to a reason based on the item's condition when left blank.
        $rejectCondition = $item['ingoing_item_condition'] ?? 'Accepted / Good';
        $rejectNote = $notes !== '' ? $notes : ingoing_default_confirmation_note(false, $rejectCondition);
        $update = $pdo->prepare("UPDATE ingoing_items SET ingoing_item_status = 'Rejected', confirmed_by = ?, confirmed_date = NOW(), confirmation_notes = ? WHERE ingoing_item_id = ?");
        $update->execute([$userId, $rejectNote, $ingoingItemId]);

        // Check if all items in this delivery are now processed, update header accordingly
        sync_ingoing_header_status($pdo, (int)$item['ingoing_id'], $userId);

        $pdo->commit();

        // ── Notify the checker who logged this delivery that the item was rejected. ──
        notify_incoming_confirmation($pdo, $item, 'rejected', (int)$userId, $rejectNote);

        json_response(['success' => true, 'message' => 'Item "' . ($item['item_name'] ?? '') . '" has been rejected.']);
    }

    // ── APPROVE: only add quantity to warehouse if condition is "Accepted / Good" ──
    $itemId = (int)$item['item_id'];
    $addQty = (int)$item['ingoing_quantity'];
    $condition = $item['ingoing_item_condition'] ?? 'Accepted / Good';
    $shouldRestock = ($condition === 'Accepted / Good');

    if ($shouldRestock) {
        $before = (int)$item['current_stock'];
        $after = $before + $addQty;

        // Update or insert warehouse quantity
        if ($item['warehouse_item_id']) {
            $updateWh = $pdo->prepare("UPDATE warehouse_items SET warehouse_item_quantity = ? WHERE item_id = ?");
            $updateWh->execute([$after, $itemId]);
        } else {
            $insertWh = $pdo->prepare("INSERT INTO warehouse_items (item_id, warehouse_item_quantity) VALUES (?, ?)");
            $insertWh->execute([$itemId, $addQty]);
        }

        // Log stock movement
        checker_record_stock_movement(
            $pdo,
            $itemId,
            'restock',
            $addQty,
            $before,
            $after,
            'ingoing_item',
            $ingoingItemId,
            'Confirmed incoming item from supplier',
            $userId
        );
    }

    // Mark ingoing item as Confirmed. Default the confirmation note (acknowledging the
    // action / condition) when the reviewer left it blank.
    $confirmNote = $notes !== '' ? $notes : ingoing_default_confirmation_note(true, $condition);
    $update = $pdo->prepare("UPDATE ingoing_items SET ingoing_item_status = 'Confirmed', confirmed_by = ?, confirmed_date = NOW(), confirmation_notes = ? WHERE ingoing_item_id = ?");
    $update->execute([$userId, $confirmNote, $ingoingItemId]);

    // Check if all items in this delivery are now processed, update header accordingly
    sync_ingoing_header_status($pdo, (int)$item['ingoing_id'], $userId);

    $pdo->commit();

    // ── Notify the checker who logged this delivery that the item was confirmed. ──
    notify_incoming_confirmation($pdo, $item, 'confirmed', (int)$userId, $confirmNote, $shouldRestock ? $addQty : 0);

    if ($shouldRestock) {
        json_response([
            'success' => true,
            'message' => 'Item "' . ($item['item_name'] ?? '') . '" confirmed. ' . $addQty . ' units added to inventory.',
            'quantityAdded' => $addQty,
        ]);
    } else {
        json_response([
            'success' => true,
            'message' => 'Item "' . ($item['item_name'] ?? '') . '" confirmed (condition: ' . $condition . '). No stock added — item will return to checker for re-checking.',
            'quantityAdded' => 0,
        ]);
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    json_response(['success' => false, 'message' => 'Unable to confirm item: ' . $e->getMessage()], 500);
}

/**
 * After confirming/rejecting an individual item, check if all items in the delivery
 * are processed and update the parent ingoing header status accordingly.
 */
/**
 * Roll up the ingoing header status: 'Pending' while any item is still Pending,
 * 'Checked' once every item in the delivery has been processed (Confirmed or Rejected).
 */
function sync_ingoing_header_status(PDO $pdo, int $ingoingId, ?int $userId): void {
    $stmt = $pdo->prepare("SELECT SUM(ingoing_item_status = 'Pending') AS pending_count, COUNT(*) AS total FROM ingoing_items WHERE ingoing_id = ?");
    $stmt->execute([$ingoingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['pending_count' => 0, 'total' => 0];
    $pending = (int)($row['pending_count'] ?? 0);
    $total = (int)($row['total'] ?? 0);

    // No items or still-pending items → keep the delivery Pending; otherwise mark it Checked.
    $status = ($total > 0 && $pending === 0) ? 'Checked' : 'Pending';

    $update = $pdo->prepare("UPDATE ingoing SET ingoing_status = ?, modified_by = ?, modified_date = NOW() WHERE ingoing_id = ?");
    $update->execute([$status, $userId, $ingoingId]);
}

/**
 * Notify the checker who logged an incoming delivery about the confirmation
 * outcome of one of their items. Best-effort — never throws.
 *
 * @param array  $item      The locked ingoing_items row (must include logged_by, item_name, ingoing_id, ingoing_item_condition).
 * @param string $outcome   'confirmed' or 'rejected'.
 * @param int    $actorId   The admin/inventory user who processed the item (skipped as recipient).
 * @param string $note      The confirmation note recorded.
 * @param int    $qtyAdded  Units added to stock (confirmed + good condition), else 0.
 */
function notify_incoming_confirmation(PDO $pdo, array $item, string $outcome, int $actorId, string $note = '', int $qtyAdded = 0): void {
    $checkerId = (int)($item['logged_by'] ?? 0);
    if ($checkerId <= 0 || $checkerId === $actorId) {
        return;
    }
    $itemName = (string)($item['item_name'] ?? 'an item');
    $ingoingItemId = (int)($item['ingoing_item_id'] ?? 0);
    $condition = (string)($item['ingoing_item_condition'] ?? '');

    $isRecheck = ($outcome === 'confirmed' && $qtyAdded === 0);

    if ($outcome === 'confirmed') {
        $title = 'Delivery item confirmed';
        $msg = '"' . $itemName . '" was confirmed';
        $msg .= $qtyAdded > 0
            ? ' and ' . $qtyAdded . ' unit(s) added to warehouse stock.'
            : ' (condition: ' . ($condition ?: 'n/a') . ') — returned to you for re-checking.';
    } else {
        $title = 'Delivery item rejected';
        $msg = '"' . $itemName . '" was rejected.' . ($note !== '' ? ' Note: ' . $note : '');
    }

    // For items returning to "For Re-checking" (confirmed but no stock added), skip
    // deduplication entirely — each re-checking cycle is a distinct event the checker
    // must see regardless of whether they read the previous notification. Using a
    // timestamp-based key ensures every cycle creates a fresh notification row while
    // still satisfying the UNIQUE(user_id, dedupe_key) DB constraint.
    // For "stock added" confirmations and rejections the static key is fine because
    // those are one-time events per item.
    if ($isRecheck) {
        $dedupeKey = 'ingoing_item:' . $ingoingItemId . ':recheck:' . time();
    } else {
        $dedupeKey = 'ingoing_item:' . $ingoingItemId . ':' . $outcome;
    }

    notify_user(
        $pdo,
        $checkerId,
        'incoming',
        $title,
        $msg,
        notification_link_for_role('checker', 'checker'),
        $dedupeKey,
        'checker'
    );
}
