<?php
require_once __DIR__ . '/common.php';
require_login(['super-admin']);
ensure_ingoing_tables($pdo);

$input = get_json_input();
$action    = $input['action']    ?? '';   // 'trash' | 'restore' | 'purge'
$ingoingId = isset($input['ingoingId']) ? (int)$input['ingoingId'] : 0;
$user      = current_user();
$userId    = $user ? (int)$user['id'] : null;

if (!$ingoingId || $ingoingId <= 0) {
    json_response(['success' => false, 'message' => 'Invalid delivery ID.'], 400);
}

// Verify the record actually exists.
$check = $pdo->prepare("SELECT ingoing_id, ingoing_status, deleted_at FROM ingoing WHERE ingoing_id = ?");
$check->execute([$ingoingId]);
$record = $check->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    json_response(['success' => false, 'message' => 'Delivery not found.'], 404);
}

try {
    switch ($action) {
        case 'trash':
            // Soft-delete: mark deleted_at and deleted_by; hidden from all roles.
            // Only Checked deliveries may be trashed — Pending ones are still being processed.
            if ($record['ingoing_status'] !== 'Checked') {
                json_response(['success' => false, 'message' => 'Only fully-checked deliveries can be moved to Trash.'], 422);
            }
            if ($record['deleted_at'] !== null) {
                json_response(['success' => true, 'message' => 'Already in Trash.']);
            }
            $stmt = $pdo->prepare("UPDATE ingoing SET deleted_at = NOW(), deleted_by = ? WHERE ingoing_id = ?");
            $stmt->execute([$userId, $ingoingId]);
            json_response(['success' => true, 'message' => 'Delivery moved to Trash.']);
            break;

        case 'restore':
            // Un-delete: clear deleted_at and deleted_by so the record reappears for all roles.
            $stmt = $pdo->prepare("UPDATE ingoing SET deleted_at = NULL, deleted_by = NULL WHERE ingoing_id = ?");
            $stmt->execute([$ingoingId]);
            json_response(['success' => true, 'message' => 'Delivery restored.']);
            break;

        case 'purge':
            // Hard-delete: permanently remove the delivery and all its line-items.
            // Stock was already adjusted at confirmation time, so this is safe for audit-only records.
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM ingoing_items WHERE ingoing_id = ?")->execute([$ingoingId]);
            $pdo->prepare("DELETE FROM ingoing WHERE ingoing_id = ?")->execute([$ingoingId]);
            $pdo->commit();
            json_response(['success' => true, 'message' => 'Delivery permanently deleted.']);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown action. Expected: trash, restore, or purge.'], 400);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()], 500);
}
