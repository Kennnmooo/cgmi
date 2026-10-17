<?php
require_once __DIR__ . '/common.php';
require_login(['super-admin']);
ensure_outgoing_tables($pdo);

// Ensure soft-delete columns exist on the outgoing header table.
checker_add_column_if_missing($pdo, 'outgoing', 'deleted_at', "TIMESTAMP NULL DEFAULT NULL");
checker_add_column_if_missing($pdo, 'outgoing', 'deleted_by', "INT(10) UNSIGNED DEFAULT NULL");

$input     = get_json_input();
$action    = $input['action']     ?? '';   // 'trash' | 'restore' | 'purge'
$outgoingId = isset($input['outgoingId']) ? (int)$input['outgoingId'] : 0;
$user      = current_user();
$userId    = $user ? (int)$user['id'] : null;

if (!$outgoingId || $outgoingId <= 0) {
    json_response(['success' => false, 'message' => 'Invalid outgoing ID.'], 400);
}

// Verify the record exists.
$check = $pdo->prepare("SELECT outgoing_id, status, deleted_at FROM outgoing WHERE outgoing_id = ?");
$check->execute([$outgoingId]);
$record = $check->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    json_response(['success' => false, 'message' => 'Outgoing record not found.'], 404);
}

try {
    switch ($action) {
        case 'trash':
            // Soft-delete: only fully-checked records may be trashed.
            if ($record['status'] !== 'Checked') {
                json_response(['success' => false, 'message' => 'Only fully-checked outgoing records can be moved to Trash.'], 422);
            }
            if ($record['deleted_at'] !== null) {
                json_response(['success' => true, 'message' => 'Already in Trash.']);
            }
            $stmt = $pdo->prepare("UPDATE outgoing SET deleted_at = NOW(), deleted_by = ? WHERE outgoing_id = ?");
            $stmt->execute([$userId, $outgoingId]);
            json_response(['success' => true, 'message' => 'Outgoing record moved to Trash.']);
            break;

        case 'restore':
            $stmt = $pdo->prepare("UPDATE outgoing SET deleted_at = NULL, deleted_by = NULL WHERE outgoing_id = ?");
            $stmt->execute([$outgoingId]);
            json_response(['success' => true, 'message' => 'Outgoing record restored.']);
            break;

        case 'purge':
            // Hard-delete: stock was already adjusted at confirmation time.
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM outgoing_items WHERE outgoing_id = ?")->execute([$outgoingId]);
            $pdo->prepare("DELETE FROM outgoing WHERE outgoing_id = ?")->execute([$outgoingId]);
            $pdo->commit();
            json_response(['success' => true, 'message' => 'Outgoing record permanently deleted.']);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown action. Expected: trash, restore, or purge.'], 400);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()], 500);
}
