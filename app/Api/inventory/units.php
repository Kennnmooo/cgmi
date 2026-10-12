<?php
require_once __DIR__ . '/common.php';

try {
    $stmt = $pdo->query('SELECT unit_id, unit_name FROM units ORDER BY unit_name ASC');
    $units = array_column($stmt->fetchAll(), 'unit_name');

    json_response(['success' => true, 'units' => $units]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Failed to load units: ' . $e->getMessage()
    ], 500);
}
