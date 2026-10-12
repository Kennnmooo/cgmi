<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_once __DIR__ . '/../../Core/Database.php';
require_login(['inventory', 'admin', 'super-admin']);

/**
 * Inventory-role dashboard summary endpoint.
 *
 * Returns every KPI and analytics dataset the inventory dashboard needs in a
 * single response, scoped to the four areas the inventory role cares about:
 *   1. Inventory   — item counts, stock units, stock value, low/out of stock
 *   2. Shelves     — status breakdown, low-stock shelves, capacity/weight
 *   3. Incoming    — ingoing / ingoing_items confirmation workflow
 *   4. Incidents   — incident_reports status + category breakdown
 *
 * Stock has no stored column on inventory_items; overall quantity per item is
 * derived as warehouse_items + store_items + shelves.shelf_quantity.
 */
try {
    // ── Reusable derived-quantity subquery ─────────────────────────────────
    // overall_qty = warehouse + store + shelf, per active item.
    $itemQtyFrom = "
        FROM inventory_items i
        LEFT JOIN warehouse_items w ON w.item_id = i.item_id
        LEFT JOIN store_items st ON st.item_id = i.item_id
        LEFT JOIN (
            SELECT item_id, COALESCE(SUM(shelf_quantity), 0) AS shelf_qty
            FROM shelves WHERE item_id IS NOT NULL GROUP BY item_id
        ) sh ON sh.item_id = i.item_id
    ";

    // ── 1. INVENTORY KPIs ───────────────────────────────────────────────────
    $inv = $pdo->query("
        SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_items,
            COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS inactive_items,
            COALESCE(SUM(CASE WHEN is_active = 1 THEN overall_qty ELSE 0 END), 0) AS total_stock_units,
            COALESCE(SUM(CASE WHEN is_active = 1 THEN overall_qty * unit_price ELSE 0 END), 0) AS stock_value,
            COALESCE(SUM(CASE WHEN is_active = 1 AND overall_qty <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
            COALESCE(SUM(CASE WHEN is_active = 1 AND overall_qty > 0 AND overall_qty <= reorder_level THEN 1 ELSE 0 END), 0) AS low_stock
        FROM (
            SELECT
                i.item_id,
                i.is_active,
                i.unit_price,
                i.reorder_level,
                COALESCE(w.warehouse_item_quantity, 0)
                    + COALESCE(st.store_item_quantity, 0)
                    + COALESCE(sh.shelf_qty, 0) AS overall_qty
            $itemQtyFrom
        ) t
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $inventory = [
        'totalItems'     => (int)  ($inv['total_items'] ?? 0),
        'activeItems'    => (int)  ($inv['active_items'] ?? 0),
        'inactiveItems'  => (int)  ($inv['inactive_items'] ?? 0),
        'totalStockUnits'=> (int)  ($inv['total_stock_units'] ?? 0),
        'stockValue'     => (float)($inv['stock_value'] ?? 0),
        'outOfStock'     => (int)  ($inv['out_of_stock'] ?? 0),
        'lowStock'       => (int)  ($inv['low_stock'] ?? 0),
    ];

    // Stock units by category (top 8 categories) — column chart.
    $byCategory = $pdo->query("
        SELECT
            COALESCE(c.category_name, 'Uncategorized') AS category_name,
            COALESCE(SUM(
                COALESCE(w.warehouse_item_quantity, 0)
                + COALESCE(st.store_item_quantity, 0)
                + COALESCE(sh.shelf_qty, 0)
            ), 0) AS total_units,
            COUNT(*) AS item_count
        FROM inventory_items i
        LEFT JOIN sub_categories scat ON scat.sub_category_id = i.sub_category_id
        LEFT JOIN categories c ON c.category_id = scat.category_id
        LEFT JOIN warehouse_items w ON w.item_id = i.item_id
        LEFT JOIN store_items st ON st.item_id = i.item_id
        LEFT JOIN (
            SELECT item_id, COALESCE(SUM(shelf_quantity), 0) AS shelf_qty
            FROM shelves WHERE item_id IS NOT NULL GROUP BY item_id
        ) sh ON sh.item_id = i.item_id
        WHERE i.is_active = 1
        GROUP BY category_name
        ORDER BY total_units DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Low-stock item list (active, overall qty at/under reorder level).
    $lowStockItems = $pdo->query("
        SELECT
            i.sku,
            i.item_name,
            i.reorder_level,
            u.unit_name AS unit,
            COALESCE(w.warehouse_item_quantity, 0)
                + COALESCE(st.store_item_quantity, 0)
                + COALESCE(sh.shelf_qty, 0) AS quantity
        FROM inventory_items i
        LEFT JOIN units u ON u.unit_id = i.unit_id
        LEFT JOIN warehouse_items w ON w.item_id = i.item_id
        LEFT JOIN store_items st ON st.item_id = i.item_id
        LEFT JOIN (
            SELECT item_id, COALESCE(SUM(shelf_quantity), 0) AS shelf_qty
            FROM shelves WHERE item_id IS NOT NULL GROUP BY item_id
        ) sh ON sh.item_id = i.item_id
        WHERE i.is_active = 1
        HAVING quantity <= i.reorder_level
        ORDER BY quantity ASC, i.item_name ASC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── 2. SHELF KPIs ────────────────────────────────────────────────────────
    $shelfAgg = $pdo->query("
        SELECT
            COUNT(*) AS total_shelves,
            COALESCE(SUM(CASE WHEN shelf_status = 'Occupied' THEN 1 ELSE 0 END), 0) AS occupied,
            COALESCE(SUM(CASE WHEN shelf_status = 'Unused' THEN 1 ELSE 0 END), 0) AS unused,
            COALESCE(SUM(CASE WHEN shelf_status = 'Maintenance' THEN 1 ELSE 0 END), 0) AS maintenance,
            COALESCE(SUM(CASE WHEN low_stock = 1 THEN 1 ELSE 0 END), 0) AS low_stock_shelves,
            COALESCE(SUM(shelf_quantity), 0) AS shelf_units
        FROM shelves
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $shelves = [
        'totalShelves'    => (int)($shelfAgg['total_shelves'] ?? 0),
        'occupied'        => (int)($shelfAgg['occupied'] ?? 0),
        'unused'          => (int)($shelfAgg['unused'] ?? 0),
        'maintenance'     => (int)($shelfAgg['maintenance'] ?? 0),
        'lowStockShelves' => (int)($shelfAgg['low_stock_shelves'] ?? 0),
        'shelfUnits'      => (int)($shelfAgg['shelf_units'] ?? 0),
    ];

    // Shelves by location layer — for a bar/column chart.
    $shelvesByLocation = $pdo->query("
        SELECT shelf_location, COUNT(*) AS total
        FROM shelves
        GROUP BY shelf_location
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Low-stock shelf list (shelves flagged low_stock, with the assigned item).
    $lowStockShelves = $pdo->query("
        SELECT
            s.shelf_name,
            s.shelf_location,
            s.shelf_quantity,
            s.min_weight,
            s.max_weight,
            i.item_name
        FROM shelves s
        LEFT JOIN inventory_items i ON i.item_id = s.item_id
        WHERE s.low_stock = 1
        ORDER BY s.shelf_quantity ASC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── 3. INCOMING CONFIRMATION KPIs ─────────────────────────────────────────
    // Header roll-up status.
    $ingoingAgg = $pdo->query("
        SELECT
            COUNT(*) AS total_deliveries,
            COALESCE(SUM(CASE WHEN ingoing_status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN ingoing_status = 'Checked' THEN 1 ELSE 0 END), 0) AS checked
        FROM ingoing
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    // Line-item level status + condition breakdown.
    $itemsAgg = $pdo->query("
        SELECT
            COUNT(*) AS total_items,
            COALESCE(SUM(CASE WHEN ingoing_item_status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_items,
            COALESCE(SUM(CASE WHEN ingoing_item_status = 'Confirmed' THEN 1 ELSE 0 END), 0) AS confirmed_items,
            COALESCE(SUM(CASE WHEN ingoing_item_status = 'Rejected' THEN 1 ELSE 0 END), 0) AS rejected_items,
            COALESCE(SUM(CASE WHEN ingoing_item_condition = 'Accepted / Good' THEN 1 ELSE 0 END), 0) AS good_items,
            COALESCE(SUM(CASE WHEN ingoing_item_condition = 'Damaged' THEN 1 ELSE 0 END), 0) AS damaged_items,
            COALESCE(SUM(CASE WHEN ingoing_item_condition = 'Incomplete / Missing' THEN 1 ELSE 0 END), 0) AS incomplete_items
        FROM ingoing_items
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $incoming = [
        'totalDeliveries' => (int)($ingoingAgg['total_deliveries'] ?? 0),
        'pending'         => (int)($ingoingAgg['pending'] ?? 0),
        'checked'         => (int)($ingoingAgg['checked'] ?? 0),
        'totalItems'      => (int)($itemsAgg['total_items'] ?? 0),
        'pendingItems'    => (int)($itemsAgg['pending_items'] ?? 0),
        'confirmedItems'  => (int)($itemsAgg['confirmed_items'] ?? 0),
        'rejectedItems'   => (int)($itemsAgg['rejected_items'] ?? 0),
        'goodItems'       => (int)($itemsAgg['good_items'] ?? 0),
        'damagedItems'    => (int)($itemsAgg['damaged_items'] ?? 0),
        'incompleteItems' => (int)($itemsAgg['incomplete_items'] ?? 0),
    ];

    // Recent pending incoming items awaiting confirmation.
    $pendingIncoming = $pdo->query("
        SELECT
            i.item_name,
            ii.ingoing_quantity,
            ii.ingoing_item_condition,
            g.ingoing_id,
            sup.supplierName AS supplier_name,
            g.created_date
        FROM ingoing_items ii
        JOIN ingoing g ON g.ingoing_id = ii.ingoing_id
        LEFT JOIN inventory_items i ON i.item_id = ii.item_id
        LEFT JOIN supplier sup ON sup.supplierId = g.supplier_id
        WHERE ii.ingoing_item_status = 'Pending'
        ORDER BY g.created_date DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── 4. INCIDENT REPORT KPIs ───────────────────────────────────────────────
    $incidentAgg = $pdo->query("
        SELECT
            COUNT(*) AS total_reports,
            COALESCE(SUM(CASE WHEN reportStatus = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN reportStatus = 'approved' THEN 1 ELSE 0 END), 0) AS approved,
            COALESCE(SUM(CASE WHEN reportStatus = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected
        FROM incident_reports
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $incidents = [
        'totalReports' => (int)($incidentAgg['total_reports'] ?? 0),
        'pending'      => (int)($incidentAgg['pending'] ?? 0),
        'approved'     => (int)($incidentAgg['approved'] ?? 0),
        'rejected'     => (int)($incidentAgg['rejected'] ?? 0),
    ];

    // Incidents grouped by category — pie chart.
    $incidentsByCategory = $pdo->query("
        SELECT report_category AS category, COUNT(*) AS total
        FROM incident_reports
        GROUP BY report_category
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent incident reports.
    $recentIncidents = $pdo->query("
        SELECT
            r.reportNo,
            r.reportTitle,
            r.report_category,
            r.reportStatus,
            r.createdAt,
            CONCAT(u.first_name, ' ', u.last_name) AS reported_by
        FROM incident_reports r
        LEFT JOIN users u ON u.id = r.reportedBy
        ORDER BY r.createdAt DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── IoT / DISTRIBUTION / SUPPLIER ADDITIONS ────────────────────────────────
    // Stock distribution across warehouse / store / IoT shelf (active items).
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

    // IoT shelf weight-threshold breaches (over max / under min). est_weight =
    // shelf quantity × assigned item unit weight.
    $shelfWeightAgg = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN max_weight IS NOT NULL AND est_weight > max_weight THEN 1 ELSE 0 END), 0) AS over_capacity,
            COALESCE(SUM(CASE WHEN min_weight IS NOT NULL AND shelf_quantity > 0 AND est_weight < min_weight THEN 1 ELSE 0 END), 0) AS under_min
        FROM (
            SELECT s.shelf_quantity, s.max_weight, s.min_weight,
                   COALESCE(s.shelf_quantity, 0) * COALESCE(inv.unit_weight_kg, 0) AS est_weight
            FROM shelves s
            LEFT JOIN inventory_items inv ON inv.item_id = s.item_id
        ) t
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $shelfTotal = (int)($shelves['totalShelves'] ?? 0);
    $shelves['overCapacity']  = (int)($shelfWeightAgg['over_capacity'] ?? 0);
    $shelves['underMin']      = (int)($shelfWeightAgg['under_min'] ?? 0);
    $shelves['occupancyRate'] = $shelfTotal > 0
        ? round(((int)($shelves['occupied'] ?? 0) / $shelfTotal) * 100, 1)
        : 0.0;

    // Per-shelf estimated weight vs its min/max band — weight-utilization chart.
    $shelfWeights = $pdo->query("
        SELECT
            s.shelf_name, s.shelf_location, s.shelf_quantity,
            s.min_weight, s.max_weight, inv.item_name,
            ROUND(COALESCE(s.shelf_quantity, 0) * COALESCE(inv.unit_weight_kg, 0), 2) AS est_weight
        FROM shelves s
        LEFT JOIN inventory_items inv ON inv.item_id = s.item_id
        WHERE s.item_id IS NOT NULL
        ORDER BY est_weight DESC
        LIMIT 12
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Supplier delivery quality — damaged / incomplete rate per supplier.
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
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Outgoing by purpose — why stock leaves the warehouse (approved lines).
    $outgoingByPurpose = $pdo->query("
        SELECT og.purpose AS purpose, COALESCE(SUM(oi.quantity), 0) AS total_qty, COUNT(*) AS line_count
        FROM outgoing_items oi
        INNER JOIN outgoing og ON og.outgoing_id = oi.outgoing_id
        WHERE oi.outgoing_item_status = 'Approved'
        GROUP BY og.purpose
        ORDER BY total_qty DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    json_response([
        'success'  => true,
        'inventory'=> $inventory + [
            'byCategory'    => $byCategory,
            'lowStockItems' => $lowStockItems,
            'distribution'  => [
                'warehouse' => (int)($distribution['warehouse_units'] ?? 0),
                'store'     => (int)($distribution['store_units'] ?? 0),
                'shelf'     => (int)($distribution['shelf_units'] ?? 0),
            ],
        ],
        'shelves'  => $shelves + [
            'byLocation'      => $shelvesByLocation,
            'lowStockShelves' => $lowStockShelves,
            'weights'         => $shelfWeights,
        ],
        'supplierQuality'   => $supplierQuality,
        'outgoingByPurpose' => $outgoingByPurpose,
        'incoming' => $incoming + [
            'pendingList' => $pendingIncoming,
        ],
        'incidents'=> $incidents + [
            'byCategory' => $incidentsByCategory,
            'recent'     => $recentIncidents,
        ],
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Inventory dashboard load failed: ' . $e->getMessage(),
    ], 500);
}
