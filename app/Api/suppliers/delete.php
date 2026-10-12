<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $supplierId = (int)($payload['id'] ?? 0);

    if ($supplierId < 1) {
        json_response(['success' => false, 'message' => 'Invalid supplier ID.'], 422);
    }

    // Check if supplier exists
    $check = $pdo->prepare('SELECT supplierId, supplierName FROM supplier WHERE supplierId = ? LIMIT 1');
    $check->execute([$supplierId]);
    $supplier = $check->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        json_response(['success' => false, 'message' => 'Supplier not found.'], 404);
    }

    // Check if supplier is referenced by checker_records
    $refCheck = $pdo->prepare('SELECT COUNT(*) FROM checker_records WHERE supplier = ?');
    $refCheck->execute([$supplierId]);
    $refCount = (int)$refCheck->fetchColumn();

    if ($refCount > 0) {
        json_response([
            'success' => false,
            'message' => 'Cannot delete "' . $supplier['supplierName'] . '" because it is referenced by ' . $refCount . ' incoming delivery record(s). Please reassign or remove those records first.',
        ], 422);
    }

    // Safe to delete
    $del = $pdo->prepare('DELETE FROM supplier WHERE supplierId = ?');
    $del->execute([$supplierId]);

    json_response([
        'success' => true,
        'message' => 'Supplier "' . $supplier['supplierName'] . '" deleted successfully.',
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to delete supplier: ' . $e->getMessage()], 500);
}
