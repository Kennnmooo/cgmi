<?php
require_once __DIR__ . '/common.php';
require_login(['admin', 'super-admin']);

try {
    $includeArchived = isset($_GET['archived']) && $_GET['archived'] === '1';

    // Archive is determined by the latest incident_reviews.isActive for each report.
    // isActive = 0 means archived. No review or isActive = 1 means active.
    if ($includeArchived) {
        // Show only archived: reports whose latest review has isActive = 0
        $whereSql = 'WHERE rv.isActive = 0';
    } else {
        // Show active: reports with no review OR latest review has isActive = 1
        $whereSql = 'WHERE (rv.isActive IS NULL OR rv.isActive = 1)';
    }

    $stmt = $pdo->query("SELECT ir.*,
            CONCAT(u.first_name, ' ', u.last_name) AS reported_by_name,
            rv.review_notes,
            rv.reviewed_at,
            rv.isActive,
            CONCAT(ru.first_name, ' ', ru.last_name) AS reviewed_by_name
        FROM incident_reports ir
        LEFT JOIN users u ON u.id = ir.reportedBy
        LEFT JOIN incident_reviews rv ON rv.reportId = ir.reportId
            AND rv.reviewId = (
                SELECT MAX(rv2.reviewId) FROM incident_reviews rv2 WHERE rv2.reportId = ir.reportId
            )
        LEFT JOIN users ru ON ru.id = rv.reviewedBy
        $whereSql
        ORDER BY ir.createdAt DESC, ir.reportId DESC");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $items = array_map('incident_to_frontend', $rows);

    $summary = [
        'total'    => count($items),
        'approved' => count(array_filter($items, fn($r) => $r['status'] === 'Approved')),
        'pending'  => count(array_filter($items, fn($r) => $r['status'] === 'Pending')),
        'rejected' => count(array_filter($items, fn($r) => $r['status'] === 'Rejected')),
    ];

    json_response(['success' => true, 'items' => $items, 'summary' => $summary]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load incident reports: ' . $e->getMessage()], 500);
}
