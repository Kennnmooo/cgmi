<?php
require_once __DIR__ . '/common.php';
require_login(['checker', 'admin', 'super-admin']);

try {
    $stmt = $pdo->query("SELECT supplierId, supplierName FROM supplier ORDER BY supplierName ASC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_response(['success' => true, 'suppliers' => $suppliers]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load suppliers: ' . $e->getMessage()], 500);
}
