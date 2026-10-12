<?php
require_once __DIR__ . '/common.php';
require_login(['checker', 'admin', 'super-admin']);
ensure_ingoing_tables($pdo);

try {
    // Fetch confirmed ingoing_items where condition is NOT "Accepted / Good"
    $sql = "SELECT ii.*, inv.item_name, inv.sku, u.unit_name,
                   s.supplierName AS supplier_name,
                   ig.created_date,
                   CONCAT(confirmer.first_name, ' ', confirmer.last_name) AS confirmed_by_name
            FROM ingoing_items ii
            INNER JOIN ingoing ig ON ig.ingoing_id = ii.ingoing_id
            LEFT JOIN inventory_items inv ON inv.item_id = ii.item_id
            LEFT JOIN units u ON u.unit_id = inv.unit_id
            LEFT JOIN supplier s ON s.supplierId = ig.supplier_id
            LEFT JOIN users confirmer ON confirmer.id = ii.confirmed_by
            WHERE ii.ingoing_item_status = 'Confirmed'
              AND ii.ingoing_item_condition != 'Accepted / Good'
            ORDER BY ii.confirmed_date DESC
            LIMIT 300";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map(function ($row) {
        return [
            'ingoingItemId' => (int)($row['ingoing_item_id'] ?? 0),
            'itemId' => (int)($row['item_id'] ?? 0),
            'itemName' => $row['item_name'] ?? '',
            'sku' => $row['sku'] ?? '',
            'unit' => $row['unit_name'] ?? '',
            'quantity' => (int)($row['ingoing_quantity'] ?? 0),
            'condition' => $row['ingoing_item_condition'] ?? '',
            'notes' => $row['ingoing_item_notes'] ?? '',
            'confirmationNotes' => $row['confirmation_notes'] ?? '',
            'supplierName' => $row['supplier_name'] ?? '',
            'confirmedByName' => $row['confirmed_by_name'] ?? '',
            'confirmedDate' => $row['confirmed_date'] ?? null,
            'createdDate' => $row['created_date'] ?? null,
        ];
    }, $rows);

    json_response(['success' => true, 'items' => $items]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load re-checking items: ' . $e->getMessage()], 500);
}
