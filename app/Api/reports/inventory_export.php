<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_once __DIR__ . '/../../Core/Database.php';
require_login(['admin', 'super-admin']);
try {
    $start = trim((string)($_GET['start_date'] ?? ''));
    $end = trim((string)($_GET['end_date'] ?? ''));
    if ($start !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) throw new RuntimeException('Invalid start date.');
    if ($end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) throw new RuntimeException('Invalid end date.');
    if ($start !== '' && $end !== '' && $start > $end) throw new RuntimeException('Start date cannot be later than end date.');
    $where=['1=1']; $params=[];
    if ($start !== '') { $where[]='DATE(sm.created_at) >= ?'; $params[]=$start; }
    if ($end !== '') { $where[]='DATE(sm.created_at) <= ?'; $params[]=$end; }
    $stmt = $pdo->prepare("SELECT sm.created_at, i.sku, i.item_name, sm.movement_type, sm.quantity_change, sm.quantity_before, sm.quantity_after, sm.reference_type, sm.reference_id, sm.notes
        FROM stock_movements sm
        LEFT JOIN inventory_items i ON i.id = sm.inventory_item_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY sm.created_at DESC, sm.id DESC");
    $stmt->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory-report.csv"');
    $out=fopen('php://output','w');
    fputcsv($out,['Created At','SKU','Item','Movement Type','Quantity Change','Quantity Before','Quantity After','Reference Type','Reference ID','Notes']);
    foreach($stmt->fetchAll() as $row){ fputcsv($out, [$row['created_at'],$row['sku'],$row['item_name'],$row['movement_type'],$row['quantity_change'],$row['quantity_before'],$row['quantity_after'],$row['reference_type'],$row['reference_id'],$row['notes']]); }
    fclose($out); exit;
} catch (Throwable $e) {
    http_response_code($e instanceof RuntimeException ? 422 : 500);
    echo 'Unable to export inventory report: ' . $e->getMessage();
}
