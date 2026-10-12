<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $editingId = isset($payload['id']) && $payload['id'] !== '' ? (int)$payload['id'] : null;

    $validation = validate_supplier_payload($pdo, $payload, $editingId);
    if ($validation['errors']) {
        json_response(['success' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $validation['errors']], 422);
    }

    $data = $validation['data'];

    if ($editingId === null) {
        // Create new supplier
        $stmt = $pdo->prepare('INSERT INTO supplier (supplierName, supplierAddress, supplierNumber, isActive) VALUES (?, ?, ?, 1)');
        $stmt->execute([
            $data['supplierName'],
            $data['supplierAddress'] ?: null,
            $data['supplierNumber'] ?: null,
        ]);
        $supplierId = (int)$pdo->lastInsertId();
    } else {
        // Update existing supplier
        $check = $pdo->prepare('SELECT * FROM supplier WHERE supplierId = ? LIMIT 1');
        $check->execute([$editingId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            json_response(['success' => false, 'message' => 'Supplier not found.'], 404);
        }

        $stmt = $pdo->prepare('UPDATE supplier SET supplierName = ?, supplierAddress = ?, supplierNumber = ? WHERE supplierId = ?');
        $stmt->execute([
            $data['supplierName'],
            $data['supplierAddress'] ?: null,
            $data['supplierNumber'] ?: null,
            $editingId,
        ]);
        $supplierId = $editingId;
    }

    // Fetch the saved record
    $fetch = $pdo->prepare('SELECT supplierId, supplierName, supplierAddress, supplierNumber, isActive FROM supplier WHERE supplierId = ? LIMIT 1');
    $fetch->execute([$supplierId]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    json_response([
        'success'  => true,
        'message'  => $editingId === null ? 'Supplier created successfully.' : 'Supplier updated successfully.',
        'supplier' => supplier_row_to_frontend($row),
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to save supplier: ' . $e->getMessage()], 500);
}
