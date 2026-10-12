<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $supplierId = (int)($payload['id'] ?? 0);
    $action = strtolower(trim((string)($payload['action'] ?? '')));

    if ($supplierId < 1 || !in_array($action, ['activate', 'deactivate'], true)) {
        json_response(['success' => false, 'message' => 'Invalid request.'], 422);
    }

    $check = $pdo->prepare('SELECT * FROM supplier WHERE supplierId = ? LIMIT 1');
    $check->execute([$supplierId]);
    $supplier = $check->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        json_response(['success' => false, 'message' => 'Supplier not found.'], 404);
    }

    $isActive = $action === 'activate' ? 1 : 0;
    $update = $pdo->prepare('UPDATE supplier SET isActive = ? WHERE supplierId = ?');
    $update->execute([$isActive, $supplierId]);

    $fetch = $pdo->prepare('SELECT supplierId, supplierName, supplierAddress, supplierNumber, isActive FROM supplier WHERE supplierId = ? LIMIT 1');
    $fetch->execute([$supplierId]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    json_response([
        'success'  => true,
        'message'  => $action === 'activate' ? 'Supplier activated successfully.' : 'Supplier deactivated successfully.',
        'supplier' => supplier_row_to_frontend($row),
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to update supplier status: ' . $e->getMessage()], 500);
}
