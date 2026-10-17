<?php
require_once __DIR__ . '/common.php';
require_login(['super-admin']);
ensure_ingoing_tables($pdo);

// Ensure soft-delete columns exist on ingoing_items (self-heal).
checker_add_column_if_missing($pdo, 'ingoing_items', 'deleted_at', "TIMESTAMP NULL DEFAULT NULL");
checker_add_column_if_missing($pdo, 'ingoing_items', 'deleted_by', "INT(10) UNSIGNED DEFAULT NULL");

$input         = get_json_input();
$action        = $input['action'] ?? 'trash';   // 'trash' | 'restore' | 'purge'
$ingoingItemId = isset($input['ingoingItemId']) ? (int)$input['ingoingItemId'] : 0;
$user          = current_user();
$userId        = $user ? (int)$user['id'] : null;

if ($ingoingItemId <= 0) {
    json_response(['success' => false, 'message' => 'Invalid item ID.'], 400);
}

$check = $pdo->prepare(
    "SELECT ingoing_item_id, ingoing_item_status, ingoing_item_condition, ingoing_id, deleted_at
     FROM ingoing_items WHERE ingoing_item_id = ? LIMIT 1"
);
$check->execute([$ingoingItemId]);
$item = $check->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    json_response(['success' => false, 'message' => 'Item not found.'], 404);
}

try {
    switch ($action) {
        case 'trash':
            if ($item['ingoing_item_status'] !== 'Confirmed') {
                json_response(['success' => false, 'message' => 'Only confirmed re-check items can be moved to Trash.'], 422);
            }
            if ($item['deleted_at'] !== null) {
                json_response(['success' => true, 'message' => 'Already in Trash.']);
            }
            $pdo->prepare("UPDATE ingoing_items SET deleted_at = NOW(), deleted_by = ? WHERE ingoing_item_id = ?")
                ->execute([$userId, $ingoingItemId]);
            json_response(['success' => true, 'message' => 'Re-check item moved to Trash.']);
            break;

        case 'restore':
            $pdo->prepare("UPDATE ingoing_items SET deleted_at = NULL, deleted_by = NULL WHERE ingoing_item_id = ?")
                ->execute([$ingoingItemId]);
            json_response(['success' => true, 'message' => 'Re-check item restored.']);
            break;

        case 'purge':
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM ingoing_items WHERE ingoing_item_id = ?")->execute([$ingoingItemId]);
            // If no items remain in the parent delivery, mark it Checked.
            $remaining = $pdo->prepare("SELECT COUNT(*) FROM ingoing_items WHERE ingoing_id = ?");
            $remaining->execute([$item['ingoing_id']]);
            if ((int)$remaining->fetchColumn() === 0) {
                $pdo->prepare("UPDATE ingoing SET ingoing_status = 'Checked' WHERE ingoing_id = ?")
                    ->execute([$item['ingoing_id']]);
            }
            $pdo->commit();
            json_response(['success' => true, 'message' => 'Re-check item permanently deleted.']);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown action. Expected: trash, restore, or purge.'], 400);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()], 500);
}
