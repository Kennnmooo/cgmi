<?php
require_once __DIR__ . '/common.php';
// Return requests are raised by the cashier at the POS (admin may also access).
require_login(['cashier', 'admin']);
ensure_return_request_table($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $data = get_json_input();

    $saleItemId   = (int)($data['saleItemId'] ?? 0);
    $returnType   = trim((string)($data['returnType'] ?? ''));
    $returnQty    = (int)($data['returnQuantity'] ?? 0);
    $returnMoney  = (float)($data['returnMoney'] ?? 0);
    $reason       = trim((string)($data['reason'] ?? ''));

    // Field-level validation (structured errors, like the suppliers module).
    $errors = [];
    if ($saleItemId <= 0)                                   $errors['saleItemId']     = 'Choose the sold item being returned.';
    if (!in_array($returnType, ['Refund', 'Replacement'], true)) $errors['returnType'] = 'Select a valid return type.';
    if ($returnQty < 1)                                     $errors['returnQuantity'] = 'Return quantity must be at least 1.';
    if ($returnQty > 100000)                                $errors['returnQuantity'] = 'Return quantity is too large.';
    if ($returnMoney < 0)                                   $errors['returnMoney']    = 'Return amount cannot be negative.';

    if ($errors) json_response(['success' => false, 'errors' => $errors], 422);

    // Validate the sale item exists and that the return quantity does not exceed what was sold.
    $stmt = $pdo->prepare("SELECT sale_item_id, quantity, unit_price FROM sale_item WHERE sale_item_id = ? LIMIT 1");
    $stmt->execute([$saleItemId]);
    $saleItem = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$saleItem) {
        json_response(['success' => false, 'errors' => ['saleItemId' => 'That sold item no longer exists.']], 422);
    }
    if ($returnQty > (int)$saleItem['quantity']) {
        json_response(['success' => false, 'errors' => ['returnQuantity' => 'Cannot return more than the ' . (int)$saleItem['quantity'] . ' originally sold.']], 422);
    }

    // A Replacement returns goods, not money — force money to 0 to keep records consistent.
    if ($returnType === 'Replacement') {
        $returnMoney = 0.00;
    } elseif ($returnMoney <= 0) {
        // Default a Refund amount to sold quantity * unit price when the checker leaves it blank.
        $returnMoney = round($returnQty * (float)$saleItem['unit_price'], 2);
    }

    $user = current_user();
    $userId = $user['id'] ?? null;

    $insert = $pdo->prepare("INSERT INTO return_request
        (sale_item_id, return_type, return_quantity, return_money, reason, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())");
    $insert->execute([
        $saleItemId,
        $returnType,
        $returnQty,
        $returnMoney,
        $reason !== '' ? $reason : null,
        $userId,
    ]);

    json_response(['success' => true, 'message' => 'Return request created and marked Pending.', 'returnRequestId' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to save return request: ' . $e->getMessage()], 500);
}
