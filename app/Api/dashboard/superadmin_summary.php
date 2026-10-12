<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_once __DIR__ . '/../../Core/Database.php';
require_login(['super-admin']);

/**
 * Super-admin governance dashboard endpoint.
 *
 * Focuses on the oversight layer that only super-admin owns, on top of the
 * shared inventory/IoT analytics served by the reports endpoints:
 *   1. Users     — role distribution, active/inactive, locked, failed logins,
 *                  dormant accounts (no login in 30+ days).
 *   2. Suppliers — active/inactive master-data health.
 *   3. Audit     — recent stock-change actions (who confirmed incoming /
 *                  approved outgoing), across ingoing_items + outgoing_items.
 *   4. Data      — data-quality flags (inactive items still holding stock,
 *                  Occupied shelves with no assigned item, items missing
 *                  unit/subcategory).
 */
try {
    // ── 1. USERS ───────────────────────────────────────────────────────────────
    $userAgg = $pdo->query("
        SELECT
            COUNT(*) AS total_users,
            COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_users,
            COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS inactive_users,
            COALESCE(SUM(CASE WHEN locked_until IS NOT NULL AND locked_until > NOW() THEN 1 ELSE 0 END), 0) AS locked_users,
            COALESCE(SUM(failed_attempts), 0) AS total_failed_attempts,
            COALESCE(SUM(CASE WHEN last_login_at IS NULL OR last_login_at < (NOW() - INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) AS dormant_users
        FROM users
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $users = [
        'totalUsers'          => (int)($userAgg['total_users'] ?? 0),
        'activeUsers'         => (int)($userAgg['active_users'] ?? 0),
        'inactiveUsers'       => (int)($userAgg['inactive_users'] ?? 0),
        'lockedUsers'         => (int)($userAgg['locked_users'] ?? 0),
        'totalFailedAttempts' => (int)($userAgg['total_failed_attempts'] ?? 0),
        'dormantUsers'        => (int)($userAgg['dormant_users'] ?? 0),
    ];

    // Role distribution — for a bar/pie of users per role.
    $byRole = $pdo->query("
        SELECT role, COUNT(*) AS total
        FROM users
        GROUP BY role
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Security watch list — locked or high-failed-attempt accounts.
    $securityAccounts = $pdo->query("
        SELECT
            CONCAT(first_name, ' ', last_name) AS full_name,
            username,
            role,
            is_active,
            failed_attempts,
            locked_until,
            last_login_at
        FROM users
        WHERE failed_attempts > 0
           OR (locked_until IS NOT NULL AND locked_until > NOW())
        ORDER BY failed_attempts DESC, locked_until DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── 2. SUPPLIERS ────────────────────────────────────────────────────────────
    $supplierAgg = $pdo->query("
        SELECT
            COUNT(*) AS total_suppliers,
            COALESCE(SUM(CASE WHEN isActive = 1 THEN 1 ELSE 0 END), 0) AS active_suppliers,
            COALESCE(SUM(CASE WHEN isActive = 0 THEN 1 ELSE 0 END), 0) AS inactive_suppliers
        FROM supplier
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $suppliers = [
        'totalSuppliers'    => (int)($supplierAgg['total_suppliers'] ?? 0),
        'activeSuppliers'   => (int)($supplierAgg['active_suppliers'] ?? 0),
        'inactiveSuppliers' => (int)($supplierAgg['inactive_suppliers'] ?? 0),
    ];

    // ── 3. STOCK-CHANGE AUDIT ─────────────────────────────────────────────────
    // Recent confirmed incoming and approved outgoing actions, unioned, with the
    // acting user. This is the accountability trail for every stock change.
    $auditTrail = $pdo->query("
        (
            SELECT
                ii.confirmed_date AS action_date,
                'Incoming Confirmed' AS action_type,
                inv.item_name,
                ii.ingoing_quantity AS quantity,
                CONCAT(u.first_name, ' ', u.last_name) AS actor,
                u.role AS actor_role
            FROM ingoing_items ii
            LEFT JOIN inventory_items inv ON inv.item_id = ii.item_id
            LEFT JOIN users u ON u.id = ii.confirmed_by
            WHERE ii.ingoing_item_status = 'Confirmed' AND ii.confirmed_date IS NOT NULL
        )
        UNION ALL
        (
            SELECT
                oi.confirmed_date AS action_date,
                'Outgoing Approved' AS action_type,
                inv.item_name,
                oi.quantity AS quantity,
                CONCAT(u.first_name, ' ', u.last_name) AS actor,
                u.role AS actor_role
            FROM outgoing_items oi
            LEFT JOIN inventory_items inv ON inv.item_id = oi.item_id
            LEFT JOIN users u ON u.id = oi.confirmed_by
            WHERE oi.outgoing_item_status = 'Approved' AND oi.confirmed_date IS NOT NULL
        )
        ORDER BY action_date DESC
        LIMIT 15
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── 4. DATA-QUALITY FLAGS ─────────────────────────────────────────────────
    // Inactive items that still hold stock somewhere.
    $inactiveWithStock = (int) $pdo->query("
        SELECT COUNT(*) FROM (
            SELECT i.item_id,
                   COALESCE(w.warehouse_item_quantity, 0)
                   + COALESCE(st.store_item_quantity, 0)
                   + COALESCE(sh.shelf_qty, 0) AS overall_qty
            FROM inventory_items i
            LEFT JOIN warehouse_items w ON w.item_id = i.item_id
            LEFT JOIN store_items st ON st.item_id = i.item_id
            LEFT JOIN (
                SELECT item_id, COALESCE(SUM(shelf_quantity), 0) AS shelf_qty
                FROM shelves WHERE item_id IS NOT NULL GROUP BY item_id
            ) sh ON sh.item_id = i.item_id
            WHERE i.is_active = 0
            HAVING overall_qty > 0
        ) t
    ")->fetchColumn();

    // Occupied shelves with no assigned item (sensor/config mismatch).
    $occupiedNoItem = (int) $pdo->query("
        SELECT COUNT(*) FROM shelves
        WHERE shelf_status = 'Occupied' AND item_id IS NULL
    ")->fetchColumn();

    // Items missing a unit or subcategory (incomplete master data).
    $missingRefs = (int) $pdo->query("
        SELECT COUNT(*) FROM inventory_items
        WHERE unit_id IS NULL OR sub_category_id IS NULL
    ")->fetchColumn();

    $dataQuality = [
        'inactiveWithStock' => $inactiveWithStock,
        'occupiedNoItem'    => $occupiedNoItem,
        'missingRefs'       => $missingRefs,
    ];

    json_response([
        'success'     => true,
        'users'       => $users + ['byRole' => $byRole, 'securityAccounts' => $securityAccounts],
        'suppliers'   => $suppliers,
        'auditTrail'  => $auditTrail,
        'dataQuality' => $dataQuality,
    ]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Super-admin dashboard load failed: ' . $e->getMessage(),
    ], 500);
}
