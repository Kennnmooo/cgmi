<?php
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_once __DIR__ . '/../../Core/Auth.php';

date_default_timezone_set('Asia/Manila');

/**
 * Convert reportStatus DB value to frontend display string.
 */
function incident_status_to_frontend(?string $status): string {
    $status = strtolower(trim((string)$status));

    return match ($status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
}

/**
 * Resolve image URL from stored path.
 */
function incident_image_url(?string $imagePath): ?string {
    $imagePath = ltrim((string)$imagePath, '/');
    if ($imagePath === '') {
        return null;
    }

    $projectRoot = dirname(__DIR__, 3);
    $candidates = [
        $imagePath => app_url('/' . $imagePath),
        'app/' . $imagePath => app_url('/app/' . $imagePath),
        'storage/' . $imagePath => app_url('/storage/' . $imagePath),
    ];

    foreach ($candidates as $relativePath => $url) {
        if (is_file($projectRoot . '/' . $relativePath)) {
            return $url;
        }
    }

    return app_url('/' . $imagePath);
}

/**
 * Transform a DB row (incident_reports + joined user/review data) into the
 * frontend JSON shape expected by the admin JS.
 *
 * Archive is determined by incident_reviews.isActive:
 *   isActive = 1 (or NULL if no review) → not archived
 *   isActive = 0 → archived
 */
function incident_to_frontend(array $row): array {
    $reference = $row['reportNo'] ?? '';
    if ($reference === '') {
        $reference = 'INC-' . str_pad((string)($row['reportId'] ?? 0), 4, '0', STR_PAD_LEFT);
    }

    $narrative = trim((string)($row['reportNarrative'] ?? ''));

    // isActive from the latest review: NULL or 1 means active, 0 means archived
    $isArchived = isset($row['isActive']) && (int)$row['isActive'] === 0;

    return [
        'id'          => (int)($row['reportId'] ?? 0),
        'refId'       => $reference,
        'title'       => (string)($row['reportTitle'] ?? ''),
        'category'    => (string)($row['report_category'] ?? ''),
        'description' => mb_strimwidth(trim(strip_tags($narrative)), 0, 115, '…', 'UTF-8'),
        'narrative'   => $narrative,
        'image'       => incident_image_url($row['reportImg'] ?? null),
        'status'      => incident_status_to_frontend($row['reportStatus'] ?? 'pending'),
        'isArchived'  => $isArchived,
        'date'        => !empty($row['createdAt']) ? date('c', strtotime((string)$row['createdAt'])) : null,
        'reportedBy'  => $row['reported_by_name'] ?? null,
        'reviewedBy'  => $row['reviewed_by_name'] ?? null,
        'reviewNotes' => $row['review_notes'] ?? '',
        'reviewedAt'  => !empty($row['reviewed_at']) ? date('c', strtotime((string)$row['reviewed_at'])) : null,
    ];
}

/**
 * Generate next incident report number: INC-YYYYMMDD-XXXX
 */
function next_incident_number(PDO $pdo): string {
    $stmt = $pdo->query('SELECT AUTO_INCREMENT AS next_id FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "incident_reports"');
    $nextId = (int)($stmt->fetch()['next_id'] ?? 1);
    return 'INC-' . date('Ymd') . '-' . str_pad((string)$nextId, 4, '0', STR_PAD_LEFT);
}
