<?php
require_once __DIR__ . '/common.php';
// Return requests are listed for the cashier at the POS (admin may also access).
require_login(['cashier', 'admin']);
ensure_return_request_table($pdo);

try {
    // Optional ?status=Pending|Completed filter.
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $statusFilter = in_array($status, ['Pending', 'Completed'], true) ? $status : null;

    $records = load_return_requests($pdo, $statusFilter);

    // Sale-item choices power the "New Return Request" picker.
    $saleItems = [];
    try { $saleItems = load_returnable_sale_items($pdo); } catch (Throwable $e) { $saleItems = []; }

    json_response(['success' => true, 'records' => $records, 'saleItems' => $saleItems]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load return requests: ' . $e->getMessage()], 500);
}
