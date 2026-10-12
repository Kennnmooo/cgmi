<?php
require_once __DIR__ . '/common.php';

try {
    $stmt = $pdo->query('SELECT supplierId, supplierName, supplierAddress, supplierNumber, isActive FROM supplier ORDER BY supplierName ASC');
    $suppliers = array_map('supplier_row_to_frontend', $stmt->fetchAll(PDO::FETCH_ASSOC));
    json_response(['success' => true, 'suppliers' => $suppliers]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load suppliers: ' . $e->getMessage()], 500);
}
