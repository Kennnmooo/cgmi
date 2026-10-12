<?php
require_once __DIR__ . '/common.php';
// Return requests are handled by the cashier at the POS (admin may also access).
require_login(['cashier', 'admin']);
ensure_return_request_table($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $data = get_json_input();

    $returnRequestId = (int)($data['returnRequestId'] ?? 0);
    $action = strtolower(trim((string)($data['action'] ?? '')));

    if ($returnRequestId <= 0) {
        json_response(['success' => false, 'message' => 'Return request ID is required.'], 422);
    }
    if (!in_array($action, ['complete', 'completed', 'reopen', 'pending'], true)) {
        json_response(['success' => false, 'message' => 'Invalid action.'], 422);
    }

    $user = current_user();
    $userId = $user['id'] ?? null;

    $pdo->beginTransaction();

    // Lock the row so two reviewers cannot process the same return at once.
    $stmt = $pdo->prepare("SELECT * FROM return_request WHERE return_request_id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$returnRequestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Return request not found.'], 404);
    }

    $isComplete = in_array($action, ['complete', 'completed'], true);

    if ($isComplete) {
        if (($row['status'] ?? 'Pending') === 'Completed') {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'This return is already completed.'], 422);
        }
        $update = $pdo->prepare("UPDATE return_request SET status = 'Completed', approved_by = ?, approved_at = NOW() WHERE return_request_id = ?");
        $update->execute([$userId, $returnRequestId]);
        $message = 'Return request marked as completed.';
    } else {
        // Reopen — send a completed return back to Pending and clear the approver stamp.
        $update = $pdo->prepare("UPDATE return_request SET status = 'Pending', approved_by = NULL, approved_at = NULL WHERE return_request_id = ?");
        $update->execute([$returnRequestId]);
        $message = 'Return request reopened as pending.';
    }

    $pdo->commit();
    json_response(['success' => true, 'message' => $message]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => 'Unable to update return request: ' . $e->getMessage()], 500);
}
