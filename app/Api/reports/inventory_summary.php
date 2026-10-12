<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_once __DIR__ . '/../../Core/Database.php';
require_login(['admin', 'super-admin']);

/**
 * Inventory report / analytics endpoint.
 *
 * Live schema notes:
 *   - inventory_items has no stored quantity/price/status/category columns.
 *   - Overall quantity = warehouse_items + store_items + shelves.shelf_quantity.
 *   - Value        = overall_qty * inventory_items.unit_price.
 *   - Active flag  = inventory_items.is_active.
 *   - Category     = inventory_items -> sub_categories -> categories.category_name.
 *   - There is no stock_movements table; movement analytics are derived from
 *     ingoing_items (incoming/confirmed) and outgoing_items (outgoing/approved).
 */

function inventory_report_date_range(): array {
    $start = trim((string)($_GET['start_date'] ?? ''));
    $end = trim((string)($_GET['end_date'] ?? ''));
    if ($start !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) throw new RuntimeException('Invalid start date.');
    if ($end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) throw new RuntimeException('Invalid end date.');
    if ($start !== '' && $end !== '' && $start > $end) throw new RuntimeException('Start date cannot be later than end date.');
    return [$start, $end];
}

try {
    [$startDate, $endDate] = inventory_report_date_range();

    // A reusable per-item overall-quantity subquery.
    $overallQtyExpr = "
        COALESCE(w.warehouse_item_quantity, 0)
        + COALESCE(st.store_item_quantity, 0)
        + COALESCE(sh.shelf_qty, 0)
    ";
    $stockJoins = "
        LEFT JOIN warehouse_items w ON w.item_id = i.item_id
        LEFT JOIN store_items st ON st.item_id = i.item_id
        LEFT JOIN (
            SELECT item_id, COALESCE(SUM(shelf_quantity), 0) AS shelf_qty
            FROM shelves WHERE item_id IS NOT NULL GROUP BY item_id
        ) sh ON sh.item_id = i.item_id
    ";

    // Overall inventory summary.
    $summary = $pdo->query("
        SELECT
            COUNT(*) AS item_count,
            COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_count,
            COALESCE(SUM(overall_qty), 0) AS total_units,
            COALESCE(SUM(overall_qty * unit_price), 0) AS stock_value,
            COALESCE(SUM(CASE WHEN overall_qty <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock_count,
            COALESCE(SUM(CASE WHEN overall_qty = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count
        FROM (
            SELECT i.item_id, i.is_active, i.reorder_level, i.unit_price,
                   $overallQtyExpr AS overall_qty
            FROM inventory_items i
            $stockJoins
        ) t
    ")->fetch() ?: [];

    // Stock by category — feeds the 'Current Stock Levels by Category' column chart.
    $byCategory = $pdo->query("
        SELECT
            COALESCE(c.category_name, 'Uncategorized') AS category_name,
            COUNT(i.item_id) AS item_count,
            COALESCE(SUM($overallQtyExpr), 0) AS total_units,
            COALESCE(SUM(($overallQtyExpr) * i.unit_price), 0) AS stock_value
        FROM inventory_items i
        LEFT JOIN sub_categories sc ON sc.sub_category_id = i.sub_category_id
        LEFT JOIN categories c ON c.category_id = sc.category_id
        $stockJoins
        GROUP BY COALESCE(c.category_name, 'Uncategorized')
        ORDER BY stock_value DESC, category_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Low-stock items (at/under reorder level) — feeds refill alerts + tables.
    $lowStockItems = $pdo->query("
        SELECT
            i.sku,
            i.item_name,
            i.reorder_level,
            i.unit_price AS price,
            u.unit_name AS unit,
            COALESCE(c.category_name, 'Uncategorized') AS category_name,
            $overallQtyExpr AS quantity
        FROM inventory_items i
        LEFT JOIN units u ON u.unit_id = i.unit_id
        LEFT JOIN sub_categories sc ON sc.sub_category_id = i.sub_category_id
        LEFT JOIN categories c ON c.category_id = sc.category_id
        $stockJoins
        WHERE i.is_active = 1
        HAVING quantity <= i.reorder_level
        ORDER BY quantity ASC, i.item_name ASC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Movement analytics derived from confirmed incoming + approved outgoing lines.
    $inWhere = ['ii.ingoing_item_status = \'Confirmed\''];
    $inParams = [];
    if ($startDate !== '') { $inWhere[] = 'DATE(ig.created_date) >= ?'; $inParams[] = $startDate; }
    if ($endDate !== '')   { $inWhere[] = 'DATE(ig.created_date) <= ?'; $inParams[] = $endDate; }
    $inWhereSql = implode(' AND ', $inWhere);

    $incomingStmt = $pdo->prepare("
        SELECT ig.created_date AS created_at,
               'incoming' AS movement_type,
               ii.ingoing_quantity AS quantity_change,
               inv.sku, inv.item_name,
               u.unit_name AS unit,
               COALESCE(c.category_name, 'Uncategorized') AS category_name
        FROM ingoing_items ii
        INNER JOIN ingoing ig ON ig.ingoing_id = ii.ingoing_id
        LEFT JOIN inventory_items inv ON inv.item_id = ii.item_id
        LEFT JOIN units u ON u.unit_id = inv.unit_id
        LEFT JOIN sub_categories sc ON sc.sub_category_id = inv.sub_category_id
        LEFT JOIN categories c ON c.category_id = sc.category_id
        WHERE $inWhereSql
        ORDER BY ig.created_date DESC
        LIMIT 60
    ");
    $incomingStmt->execute($inParams);
    $incoming = $incomingStmt->fetchAll(PDO::FETCH_ASSOC);

    $outWhere = ['oi.outgoing_item_status = \'Approved\''];
    $outParams = [];
    if ($startDate !== '') { $outWhere[] = 'DATE(og.created_date) >= ?'; $outParams[] = $startDate; }
    if ($endDate !== '')   { $outWhere[] = 'DATE(og.created_date) <= ?'; $outParams[] = $endDate; }
    $outWhereSql = implode(' AND ', $outWhere);

    $outgoingStmt = $pdo->prepare("
        SELECT og.created_date AS created_at,
               'outgoing' AS movement_type,
               -oi.quantity AS quantity_change,
               inv.sku, inv.item_name,
               COALESCE(oi.unit, u.unit_name) AS unit,
               COALESCE(c.category_name, 'Uncategorized') AS category_name
        FROM outgoing_items oi
        INNER JOIN outgoing og ON og.outgoing_id = oi.outgoing_id
        LEFT JOIN inventory_items inv ON inv.item_id = oi.item_id
        LEFT JOIN units u ON u.unit_id = inv.unit_id
        LEFT JOIN sub_categories sc ON sc.sub_category_id = inv.sub_category_id
        LEFT JOIN categories c ON c.category_id = sc.category_id
        WHERE $outWhereSql
        ORDER BY og.created_date DESC
        LIMIT 60
    ");
    $outgoingStmt->execute($outParams);
    $outgoing = $outgoingStmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge and sort movements by date desc, cap at 100.
    $movements = array_merge($incoming, $outgoing);
    usort($movements, static function ($a, $b) {
        return strcmp((string)$b['created_at'], (string)$a['created_at']);
    });
    $movements = array_slice($movements, 0, 100);

    $movementSummary = [
        [
            'movement_type' => 'incoming',
            'entry_count'   => count($incoming),
            'total_change'  => array_sum(array_map(static fn($r) => (float)$r['quantity_change'], $incoming)),
        ],
        [
            'movement_type' => 'outgoing',
            'entry_count'   => count($outgoing),
            'total_change'  => array_sum(array_map(static fn($r) => (float)$r['quantity_change'], $outgoing)),
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // INVENTORY & IoT FOCUS ADDITIONS
    // The system's core is inventory control + IoT shelf monitoring, so the
    // following datasets give stock-location distribution, IoT shelf health,
    // supplier delivery quality, and outflow reasons.
    // ─────────────────────────────────────────────────────────────────────────

    // Stock distribution across the three physical locations (warehouse / store
    // / IoT shelf). Powers a stacked location-distribution chart.
    $distribution = $pdo->query("
        SELECT
            COALESCE(SUM(w.warehouse_item_quantity), 0) AS warehouse_units,
            COALESCE(SUM(st.store_item_quantity), 0)     AS store_units,
            COALESCE(SUM(sh.shelf_qty), 0)               AS shelf_units
        FROM inventory_items i
        LEFT JOIN warehouse_items w ON w.item_id = i.item_id
        LEFT JOIN store_items st ON st.item_id = i.item_id
        LEFT JOIN (
            SELECT item_id, COALESCE(SUM(shelf_quantity), 0) AS shelf_qty
            FROM shelves WHERE item_id IS NOT NULL GROUP BY item_id
        ) sh ON sh.item_id = i.item_id
        WHERE i.is_active = 1
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    // IoT shelf health KPIs — status mix, low-stock flag, and weight-threshold
    // breaches. `est_weight` estimates the current load from shelf quantity ×
    // the assigned item's unit weight; over/under compares it to the shelf's
    // configured max/min weight band.
    $shelfHealth = $pdo->query("
        SELECT
            COUNT(*) AS total_shelves,
            COALESCE(SUM(CASE WHEN shelf_status = 'Occupied' THEN 1 ELSE 0 END), 0)    AS occupied,
            COALESCE(SUM(CASE WHEN shelf_status = 'Unused' THEN 1 ELSE 0 END), 0)      AS unused,
            COALESCE(SUM(CASE WHEN shelf_status = 'Maintenance' THEN 1 ELSE 0 END), 0) AS maintenance,
            COALESCE(SUM(CASE WHEN low_stock = 1 THEN 1 ELSE 0 END), 0)                AS low_stock_shelves,
            COALESCE(SUM(CASE WHEN max_weight IS NOT NULL AND est_weight > max_weight THEN 1 ELSE 0 END), 0) AS over_capacity,
            COALESCE(SUM(CASE WHEN min_weight IS NOT NULL AND shelf_quantity > 0 AND est_weight < min_weight THEN 1 ELSE 0 END), 0) AS under_min
        FROM (
            SELECT
                s.shelf_status, s.low_stock, s.shelf_quantity, s.max_weight, s.min_weight,
                COALESCE(s.shelf_quantity, 0) * COALESCE(inv.unit_weight_kg, 0) AS est_weight
            FROM shelves s
            LEFT JOIN inventory_items inv ON inv.item_id = s.item_id
        ) sh
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    // Occupancy % = occupied / total shelves.
    $totalShelves = (int)($shelfHealth['total_shelves'] ?? 0);
    $occupancyRate = $totalShelves > 0
        ? round(((int)($shelfHealth['occupied'] ?? 0) / $totalShelves) * 100, 1)
        : 0.0;

    // Per-shelf weight utilization — current estimated load vs the min/max band.
    // Feeds a weight-utilization chart (the IoT differentiator).
    $shelfWeights = $pdo->query("
        SELECT
            s.shelf_name,
            s.shelf_location,
            s.shelf_quantity,
            s.min_weight,
            s.max_weight,
            inv.item_name,
            ROUND(COALESCE(s.shelf_quantity, 0) * COALESCE(inv.unit_weight_kg, 0), 2) AS est_weight
        FROM shelves s
        LEFT JOIN inventory_items inv ON inv.item_id = s.item_id
        WHERE s.item_id IS NOT NULL
        ORDER BY est_weight DESC
        LIMIT 12
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Supplier delivery quality — % of incoming line items that arrived Damaged
    // or Incomplete per supplier. Actionable for procurement decisions.
    $supplierQuality = $pdo->query("
        SELECT
            sup.supplierName AS supplier_name,
            COUNT(*) AS total_items,
            COALESCE(SUM(CASE WHEN ii.ingoing_item_condition = 'Accepted / Good' THEN 1 ELSE 0 END), 0)      AS good_items,
            COALESCE(SUM(CASE WHEN ii.ingoing_item_condition = 'Damaged' THEN 1 ELSE 0 END), 0)             AS damaged_items,
            COALESCE(SUM(CASE WHEN ii.ingoing_item_condition = 'Incomplete / Missing' THEN 1 ELSE 0 END), 0) AS incomplete_items
        FROM ingoing_items ii
        INNER JOIN ingoing ig ON ig.ingoing_id = ii.ingoing_id
        INNER JOIN supplier sup ON sup.supplierId = ig.supplier_id
        GROUP BY sup.supplierId, sup.supplierName
        HAVING total_items > 0
        ORDER BY (
            SUM(CASE WHEN ii.ingoing_item_condition = 'Damaged' THEN 1 ELSE 0 END)
            + SUM(CASE WHEN ii.ingoing_item_condition = 'Incomplete / Missing' THEN 1 ELSE 0 END)
        ) DESC, total_items DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Outgoing by purpose — why stock leaves the warehouse (Customer Purchase,
    // Store Replenishment, IoT Shelves Replenishment, Damaged Removal, ...).
    $outByPurposeWhere = ['oi.outgoing_item_status = \'Approved\''];
    $outByPurposeParams = [];
    if ($startDate !== '') { $outByPurposeWhere[] = 'DATE(og.created_date) >= ?'; $outByPurposeParams[] = $startDate; }
    if ($endDate !== '')   { $outByPurposeWhere[] = 'DATE(og.created_date) <= ?'; $outByPurposeParams[] = $endDate; }
    $outByPurposeSql = implode(' AND ', $outByPurposeWhere);
    $outByPurposeStmt = $pdo->prepare("
        SELECT og.purpose AS purpose, COALESCE(SUM(oi.quantity), 0) AS total_qty, COUNT(*) AS line_count
        FROM outgoing_items oi
        INNER JOIN outgoing og ON og.outgoing_id = oi.outgoing_id
        WHERE $outByPurposeSql
        GROUP BY og.purpose
        ORDER BY total_qty DESC
    ");
    $outByPurposeStmt->execute($outByPurposeParams);
    $outgoingByPurpose = $outByPurposeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Net stock change over the range (confirmed inflow − approved outflow).
    $incomingTotal = array_sum(array_map(static fn($r) => (float)$r['quantity_change'], $incoming));
    $outgoingTotal = array_sum(array_map(static fn($r) => (float)$r['quantity_change'], $outgoing));

    $shelfSummary = [
        'totalShelves'    => $totalShelves,
        'occupied'        => (int)($shelfHealth['occupied'] ?? 0),
        'unused'          => (int)($shelfHealth['unused'] ?? 0),
        'maintenance'     => (int)($shelfHealth['maintenance'] ?? 0),
        'lowStockShelves' => (int)($shelfHealth['low_stock_shelves'] ?? 0),
        'overCapacity'    => (int)($shelfHealth['over_capacity'] ?? 0),
        'underMin'        => (int)($shelfHealth['under_min'] ?? 0),
        'occupancyRate'   => $occupancyRate,
    ];

    $rangeLabel = ($startDate || $endDate)
        ? trim(($startDate ?: 'Beginning') . ' to ' . ($endDate ?: 'Today'))
        : 'All dates';

    json_response([
        'success' => true,
        'rangeLabel' => $rangeLabel,
        'summary' => [
            'itemCount'        => (int)($summary['item_count'] ?? 0),
            'activeCount'      => (int)($summary['active_count'] ?? 0),
            'totalUnits'       => (int)($summary['total_units'] ?? 0),
            'stockValue'       => (float)($summary['stock_value'] ?? 0),
            'lowStockCount'    => (int)($summary['low_stock_count'] ?? 0),
            'outOfStockCount'  => (int)($summary['out_of_stock_count'] ?? 0),
            'netStockChange'   => $incomingTotal + $outgoingTotal, // outgoing is negative
            'incomingUnits'    => $incomingTotal,
            'outgoingUnits'    => abs($outgoingTotal),
        ],
        'byCategory' => $byCategory,
        'lowStockItems' => $lowStockItems,
        'movements' => $movements,
        'filteredItems' => $movements,
        'movementSummary' => $movementSummary,
        'distribution' => [
            'warehouse' => (int)($distribution['warehouse_units'] ?? 0),
            'store'     => (int)($distribution['store_units'] ?? 0),
            'shelf'     => (int)($distribution['shelf_units'] ?? 0),
        ],
        'shelfSummary'    => $shelfSummary,
        'shelfWeights'    => $shelfWeights,
        'supplierQuality' => $supplierQuality,
        'outgoingByPurpose' => $outgoingByPurpose,
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Inventory report load failed: ' . $e->getMessage(),
    ], $e instanceof RuntimeException ? 422 : 500);
}
