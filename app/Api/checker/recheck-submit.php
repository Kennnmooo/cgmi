<?php
require_once __DIR__ . '/common.php';
require_login(['checker', 'admin', 'super-admin']);
ensure_ingoing_tables($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Method not allowed.'], 405);

try {
    $data = get_json_input();
    $ingoingItemId = (int)($data['ingoingItemId'] ?? 0);
    $condition = trim((string)($data['condition'] ?? ''));
    $notes = trim((string)($data['notes'] ?? ''));

    if ($ingoingItemId <= 0) json_response(['success' => false, 'message' => 'Item ID is required.'], 422);

    $allowedConditions = ['Accepted / Good', 'Damaged', 'Incomplete / Missing'];
    if (!in_array($condition, $allowedConditions, true)) {
        json_response(['success' => false, 'message' => 'Invalid condition. Must be one of: ' . implode(', ', $allowedConditions)], 422);
    }

    $user = current_user();
    $userId = $user['id'] ?? null;

    $pdo->beginTransaction();

    // Verify item exists and is currently Confirmed with non-good condition
    $stmt = $pdo->prepare("SELECT ii.*, inv.item_name
                           FROM ingoing_items ii
                           LEFT JOIN inventory_items inv ON inv.item_id = ii.item_id
                           WHERE ii.ingoing_item_id = ?
                           LIMIT 1 FOR UPDATE");
    $stmt->execute([$ingoingItemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) json_response(['success' => false, 'message' => 'Item not found.'], 404);

    if (($item['ingoing_item_status'] ?? '') !== 'Confirmed') {
        json_response(['success' => false, 'message' => 'Only confirmed items can be re-checked.'], 422);
    }

    if (($item['ingoing_item_condition'] ?? '') === 'Accepted / Good') {
        json_response(['success' => false, 'message' => 'This item is already in good condition and has been restocked.'], 422);
    }

    // Update condition, notes, and reset status back to Pending for re-approval
    $update = $pdo->prepare("UPDATE ingoing_items
                             SET ingoing_item_condition = ?,
                                 ingoing_item_notes = ?,
                                 ingoing_item_status = 'Pending',
                                 confirmed_by = NULL,
                                 confirmed_date = NULL,
                                 confirmation_notes = NULL
                             WHERE ingoing_item_id = ?");
    $update->execute([$condition, $notes ?: null, $ingoingItemId]);

    // This item is going back to Pending, so the whole delivery is no longer fully checked.
    // Reset the header to Pending (it will roll back to Checked once every item is processed again).
    $ingoingId = (int)$item['ingoing_id'];
    $resetHeader = $pdo->prepare("UPDATE ingoing SET ingoing_status = 'Pending', modified_by = ?, modified_date = NOW() WHERE ingoing_id = ?");
    $resetHeader->execute([$userId, $ingoingId]);

    $pdo->commit();

    $itemName = $item['item_name'] ?? 'Item';

    // ── Notify admin / super-admin / inventory that a re-checked item is back
    // on the incoming confirmation page and needs their attention.
    //
    // Every re-check cycle is a distinct review event regardless of condition,
    // so we use a timestamp-based dedupe key to bypass deduplication — without
    // it, an unread notification from a previous cycle would silently suppress
    // the new one (same bug as the confirm-incoming recheck path).
    try {
        require_once __DIR__ . '/../notifications/common.php';
        if (function_exists('notify_role') && function_exists('notification_link_for_role')) {
            if ($condition === 'Accepted / Good') {
                $notifTitle = 'Re-checked item ready for confirmation';
                $notifMsg   = '"' . $itemName . '" was re-checked and marked as Accepted / Good by the checker.'
                            . ($notes !== '' ? ' Note: ' . $notes : '')
                            . ' Please review and confirm it on the Incoming Confirmation page.';
            } else {
                // Damaged or Incomplete / Missing — still needs admin/inventory review;
                // the item is Pending again and visible on the confirmation page.
                $notifTitle = 'Re-checked item still requires review';
                $notifMsg   = '"' . $itemName . '" was re-checked by the checker with condition: ' . $condition . '.'
                            . ($notes !== '' ? ' Note: ' . $notes : '')
                            . ' The item is pending your review on the Incoming Confirmation page.';
            }

            // Timestamp suffix makes every re-check cycle unique so the
            // notification always reaches reviewers even if they haven't
            // cleared a prior one for this item.
            $dedupeBase = 'recheck-ready:' . $ingoingItemId . ':' . time();

            foreach (['admin', 'super-admin', 'inventory'] as $recipientRole) {
                $link = notification_link_for_role($recipientRole, 'incoming-confirmation');
                notify_role($pdo, $recipientRole, 'incoming', $notifTitle, $notifMsg, $link, $dedupeBase, (int)$userId);
            }
        }
    } catch (Throwable $notifErr) {
        // Notifications are auxiliary — never block the primary response.
    }

    json_response([
        'success' => true,
        'message' => '"' . $itemName . '" has been re-checked (condition: ' . $condition . '). It is now pending admin/inventory confirmation again.',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    json_response(['success' => false, 'message' => 'Unable to submit re-check: ' . $e->getMessage()], 500);
}
