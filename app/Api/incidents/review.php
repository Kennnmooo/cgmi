<?php
require_once __DIR__ . '/common.php';
require_login(['admin', 'super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (int)($payload['id'] ?? 0);
    $action = strtolower(trim((string)($payload['action'] ?? '')));
    $reviewNotes = trim((string)($payload['reviewNotes'] ?? ''));

    if ($id < 1 || !in_array($action, ['approve', 'reject', 'archive'], true)) {
        json_response(['success' => false, 'message' => 'Invalid request.'], 422);
    }

    // Verify the report exists
    $check = $pdo->prepare('SELECT reportId, reportStatus FROM incident_reports WHERE reportId = ? LIMIT 1');
    $check->execute([$id]);
    $report = $check->fetch(PDO::FETCH_ASSOC);
    if (!$report) {
        json_response(['success' => false, 'message' => 'Incident report not found.'], 404);
    }

    $userId = (int)(current_user()['id'] ?? 0);
    $message = 'Incident report updated successfully.';

    if ($action === 'archive') {
        // Only approved or rejected reports can be archived
        if (!in_array($report['reportStatus'], ['approved', 'rejected'], true)) {
            json_response(['success' => false, 'message' => 'Only approved or rejected reports can be archived.'], 422);
        }

        // Set isActive = 0 on the latest review for this report
        $archiveStmt = $pdo->prepare('UPDATE incident_reviews SET isActive = 0
            WHERE reportId = ? AND reviewId = (
                SELECT maxId FROM (SELECT MAX(reviewId) AS maxId FROM incident_reviews WHERE reportId = ?) AS tmp
            )');
        $archiveStmt->execute([$id, $id]);

        // If no review exists yet, create one with isActive = 0
        if ($archiveStmt->rowCount() < 1) {
            $insertReview = $pdo->prepare('INSERT INTO incident_reviews (reportId, reviewedBy, review_notes, isActive) VALUES (?, ?, ?, 0)');
            $insertReview->execute([$id, $userId, 'Archived by admin']);
        }

        $message = 'Incident report archived successfully.';
    } else {
        // Determine new status
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        // Update reportStatus on incident_reports
        $update = $pdo->prepare('UPDATE incident_reports SET reportStatus = ? WHERE reportId = ?');
        $update->execute([$newStatus, $id]);

        // Insert a review record into incident_reviews (isActive = 1 by default)
        $insertReview = $pdo->prepare('INSERT INTO incident_reviews (reportId, reviewedBy, review_notes) VALUES (?, ?, ?)');
        $insertReview->execute([$id, $userId, $reviewNotes]);

        $message = $action === 'approve'
            ? 'Incident report approved successfully.'
            : 'Incident report rejected successfully.';
    }

    // Fetch updated record for response
    $fetch = $pdo->prepare("SELECT ir.*,
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
        WHERE ir.reportId = ? LIMIT 1");
    $fetch->execute([$id]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    // ── Notify: tell the original reporter the outcome of their report. ──
    // (Archive is an internal admin action, so it does not notify the reporter.)
    if ($action !== 'archive' && !empty($row)) {
        require_once __DIR__ . '/../notifications/common.php';
        $reporterId = (int)($row['reportedBy'] ?? 0);
        if ($reporterId > 0 && $reporterId !== $userId) {
            $reporterRole = '';
            try {
                $rs = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
                $rs->execute([$reporterId]);
                $reporterRole = (string)($rs->fetchColumn() ?: '');
            } catch (Throwable $e) { /* ignore */ }

            // Inventory users only submit reports; they do not receive
            // approval/rejection notifications.
            if ($reporterRole !== 'inventory') {
                $refNo = (string)($row['reportNo'] ?? ('INC-' . $id));
                $title = (string)($row['reportTitle'] ?? 'your incident report');
                $outcome = $action === 'approve' ? 'approved' : 'rejected';
                notify_user(
                    $pdo,
                    $reporterId,
                    'incident',
                    'Incident report ' . $outcome,
                    'Your report "' . $title . '" (' . $refNo . ') was ' . $outcome . '.',
                    notification_link_for_role($reporterRole, 'incidents'),
                    'incident:' . $id . ':' . $outcome,
                    $reporterRole ?: null
                );
            }
        }
    }

    json_response([
        'success' => true,
        'message' => $message,
        'item'    => incident_to_frontend($row ?: []),
    ]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to update incident report: ' . $e->getMessage()], 500);
}
