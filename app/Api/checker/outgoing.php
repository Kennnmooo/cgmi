<?php
require_once __DIR__ . '/common.php';
require_login(['checker', 'admin', 'super-admin']);
ensure_checker_tables($pdo);
ensure_outgoing_tables($pdo);

try {
    $items = [];

    // POS outgoing records (from warehouse sales) — skip if sales tables don't exist
    try {
        $posSql = "SELECT si.id AS sale_item_id, s.id AS sale_id, s.sale_number, s.created_at AS sold_at,
                          si.inventory_item_id, si.sku, si.item_name, si.quantity, si.unit, s.storage_location,
                          cr.id AS record_id, cr.check_status, cr.notes, cr.checked_at, cr.quantity AS checked_qty,
                          cr.purpose, cr.record_source, CONCAT(u.first_name, ' ', u.last_name) AS checked_by_name
                   FROM sale_items si
                   INNER JOIN sales s ON s.id = si.sale_id
                   LEFT JOIN checker_records cr ON cr.sale_item_id = si.id AND cr.record_type = 'outgoing'
                   LEFT JOIN users u ON u.id = cr.checked_by
                   WHERE s.storage_location = 'warehouse'
                   ORDER BY s.created_at DESC, si.id DESC
                   LIMIT 250";
        $posRows = $pdo->query($posSql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($posRows as $r) {
            $items[] = [
                'recordId' => isset($r['record_id']) ? (int)$r['record_id'] : null,
                'source' => 'pos',
                'saleId' => (int)$r['sale_id'],
                'saleItemId' => (int)$r['sale_item_id'],
                'saleNumber' => $r['sale_number'],
                'date' => $r['sold_at'],
                'soldAt' => $r['sold_at'],
                'storageLocation' => $r['storage_location'] ?? 'warehouse',
                'itemId' => (int)$r['inventory_item_id'],
                'sku' => $r['sku'],
                'itemName' => $r['item_name'],
                'quantity' => (float)($r['checked_qty'] ?: $r['quantity']),
                'requestedQuantity' => (float)$r['quantity'],
                'unit' => $r['unit'],
                'purpose' => $r['purpose'] ?: 'POS Warehouse Release',
                'status' => $r['check_status'] ?: 'pending',
                'notes' => $r['notes'] ?: '',
                'checkedBy' => $r['checked_by_name'] ?: '',
                'checkedAt' => $r['checked_at'] ?: null,
            ];
        }
    } catch (Throwable $e) {
        // sale_items table may not exist — skip POS records
    }

    // Ensure soft-delete columns exist (self-heal for older databases).
    try {
        checker_add_column_if_missing($pdo, 'outgoing', 'deleted_at', "TIMESTAMP NULL DEFAULT NULL");
        checker_add_column_if_missing($pdo, 'outgoing', 'deleted_by', "INT(10) UNSIGNED DEFAULT NULL");
    } catch (Throwable $e) {}

    // Manual outgoing records (from outgoing + outgoing_items tables)
    // Purpose lives on the header; source_location + status + confirmation live per line-item.
    // Only fetch non-deleted records for the main list.
    $manualSql = "SELECT o.outgoing_id, o.record_source, o.purpose, o.status AS header_status,
                         o.created_date, o.created_by,
                         oi.outgoing_item_id, oi.item_id, oi.quantity, oi.source_location, oi.outgoing_notes,
                         oi.outgoing_item_status, oi.confirmed_by, oi.confirmed_date, oi.confirmed_notes,
                         inv.item_name, inv.sku, u_name.unit_name,
                         CONCAT(creator.first_name, ' ', creator.last_name) AS created_by_name,
                         CONCAT(confirmer.first_name, ' ', confirmer.last_name) AS confirmed_by_name
                  FROM outgoing o
                  INNER JOIN outgoing_items oi ON oi.outgoing_id = o.outgoing_id
                  LEFT JOIN inventory_items inv ON inv.item_id = oi.item_id
                  LEFT JOIN units u_name ON u_name.unit_id = inv.unit_id
                  LEFT JOIN users creator ON creator.id = o.created_by
                  LEFT JOIN users confirmer ON confirmer.id = oi.confirmed_by
                  WHERE o.deleted_at IS NULL
                  ORDER BY o.created_date DESC, oi.outgoing_item_id DESC
                  LIMIT 250";
    $manualRows = $pdo->query($manualSql)->fetchAll(PDO::FETCH_ASSOC);

    // Group manual line-items under their parent transaction (one entry per outgoing_id).
    $transactions = [];
    foreach ($manualRows as $row) {
        $outgoingId = (int)$row['outgoing_id'];
        if (!isset($transactions[$outgoingId])) {
            // Derive the source from the header's record_source so POS-originated
            // outgoing transactions are labelled 'pos' (not forced to 'manual').
            $recordSource = strtolower(trim((string)($row['record_source'] ?? 'manual')));
            $source = $recordSource === 'pos' ? 'pos' : 'manual';
            $transactions[$outgoingId] = [
                'source' => $source,
                'outgoingId' => $outgoingId,
                'date' => $row['created_date'],
                'purpose' => $row['purpose'] ?? 'Manual Entry',
                'createdBy' => $row['created_by_name'] ?? '',
                'headerStatus' => $row['header_status'] ?? 'Pending',
                'items' => [],
            ];
        }
        $transactions[$outgoingId]['items'][] = [
            'recordId' => (int)$row['outgoing_item_id'],
            'outgoingId' => $outgoingId,
            'outgoingItemId' => (int)$row['outgoing_item_id'],
            'itemId' => (int)$row['item_id'],
            'sku' => $row['sku'] ?? '',
            'itemName' => $row['item_name'] ?? '',
            'quantity' => (int)$row['quantity'],
            'unit' => $row['unit_name'] ?? '',
            'sourceLocation' => $row['source_location'] ?? '',
            'status' => $row['outgoing_item_status'] ?? 'Pending',
            'notes' => $row['outgoing_notes'] ?? '',
            'confirmationNotes' => $row['confirmed_notes'] ?? '',
            'confirmedBy' => $row['confirmed_by_name'] ?? '',
            'confirmedAt' => $row['confirmed_date'] ?? null,
        ];
    }

    // Compute per-item counts and set the transaction's displayed status from the HEADER
    // (Pending until every item is processed, then Checked).
    foreach ($transactions as &$txn) {
        $statuses = array_column($txn['items'], 'status');
        $pending = count(array_filter($statuses, fn($s) => $s === 'Pending'));
        $approved = count(array_filter($statuses, fn($s) => $s === 'Approved'));
        $rejected = count(array_filter($statuses, fn($s) => $s === 'Rejected'));
        $total = count($statuses);

        // Header status is authoritative for the Status column; fall back to a derived value if missing.
        $headerStatus = $txn['headerStatus'] ?? 'Pending';
        if ($headerStatus !== 'Checked' && $total > 0 && $pending === 0) {
            $headerStatus = 'Checked';
        }
        $txn['status'] = $headerStatus;
        $txn['itemCount'] = $total;
        $txn['pendingCount'] = $pending;
        $txn['approvedCount'] = $approved;
        $txn['rejectedCount'] = $rejected;
        $txn['totalQuantity'] = array_sum(array_column($txn['items'], 'quantity'));
    }
    unset($txn);

    // Merge POS (flat) items and manual (grouped) transactions into one list, newest first.
    $items = array_merge($items, array_values($transactions));

    usort($items, function($a, $b) {
        return strtotime($b['date'] ?? $b['soldAt'] ?? '1970-01-01') <=> strtotime($a['date'] ?? $a['soldAt'] ?? '1970-01-01');
    });

    // Trashed outgoing records (soft-deleted; super-admin only; returned separately).
    $trashed = [];
    try {
        $trashedSql = "SELECT o.outgoing_id, o.record_source, o.purpose, o.status AS header_status,
                              o.created_date, o.created_by, o.deleted_at,
                              oi.outgoing_item_id, oi.item_id, oi.quantity, oi.source_location,
                              oi.outgoing_notes, oi.outgoing_item_status,
                              oi.confirmed_by, oi.confirmed_date, oi.confirmed_notes,
                              inv.item_name, inv.sku, u_name.unit_name,
                              CONCAT(creator.first_name, ' ', creator.last_name) AS created_by_name,
                              CONCAT(confirmer.first_name, ' ', confirmer.last_name) AS confirmed_by_name
                       FROM outgoing o
                       INNER JOIN outgoing_items oi ON oi.outgoing_id = o.outgoing_id
                       LEFT JOIN inventory_items inv ON inv.item_id = oi.item_id
                       LEFT JOIN units u_name ON u_name.unit_id = inv.unit_id
                       LEFT JOIN users creator ON creator.id = o.created_by
                       LEFT JOIN users confirmer ON confirmer.id = oi.confirmed_by
                       WHERE o.deleted_at IS NOT NULL
                       ORDER BY o.deleted_at DESC, oi.outgoing_item_id DESC
                       LIMIT 250";
        $trashedRows = $pdo->query($trashedSql)->fetchAll(PDO::FETCH_ASSOC);
        $trashedTxns = [];
        foreach ($trashedRows as $row) {
            $outgoingId = (int)$row['outgoing_id'];
            if (!isset($trashedTxns[$outgoingId])) {
                $recordSource = strtolower(trim((string)($row['record_source'] ?? 'manual')));
                $trashedTxns[$outgoingId] = [
                    'source'       => $recordSource === 'pos' ? 'pos' : 'manual',
                    'outgoingId'   => $outgoingId,
                    'date'         => $row['created_date'],
                    'deletedAt'    => $row['deleted_at'],
                    'purpose'      => $row['purpose'] ?? 'Manual Entry',
                    'createdBy'    => $row['created_by_name'] ?? '',
                    'headerStatus' => $row['header_status'] ?? 'Checked',
                    'status'       => $row['header_status'] ?? 'Checked',
                    'items'        => [],
                ];
            }
            $trashedTxns[$outgoingId]['items'][] = [
                'outgoingItemId'   => (int)$row['outgoing_item_id'],
                'itemId'           => (int)$row['item_id'],
                'sku'              => $row['sku'] ?? '',
                'itemName'         => $row['item_name'] ?? '',
                'quantity'         => (int)$row['quantity'],
                'unit'             => $row['unit_name'] ?? '',
                'sourceLocation'   => $row['source_location'] ?? '',
                'status'           => $row['outgoing_item_status'] ?? 'Approved',
                'notes'            => $row['outgoing_notes'] ?? '',
                'confirmationNotes'=> $row['confirmed_notes'] ?? '',
                'confirmedBy'      => $row['confirmed_by_name'] ?? '',
                'confirmedAt'      => $row['confirmed_date'] ?? null,
            ];
        }
        foreach ($trashedTxns as &$txn) {
            $txn['itemCount']     = count($txn['items']);
            $txn['totalQuantity'] = array_sum(array_column($txn['items'], 'quantity'));
        }
        unset($txn);
        $trashed = array_values($trashedTxns);
    } catch (Throwable $e) {
        // Trashed query fails gracefully (e.g. deleted_at column not yet added).
    }

    json_response(['success' => true, 'items' => $items, 'trashed' => $trashed]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load warehouse outgoing checks: ' . $e->getMessage()], 500);
}
