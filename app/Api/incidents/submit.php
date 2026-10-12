<?php
require_once __DIR__ . '/common.php';
require_login(['inventory', 'admin', 'super-admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $category = trim($_POST['category'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $narrative = trim($_POST['narrative'] ?? '');

    $allowedCategories = [
        'Inventory Discrepancy',
        'Damaged Item',
        'Missing Item',
        'Incorrect Item',
        'Stock Issue',
        'IoT Sensor Issue',
        'Shelf Issue',
        'Hardware Issue',
        'System Issue',
        'Safety Incident',
        'Unauthorized Activity',
        'Other',
    ];

    if (!in_array($category, $allowedCategories, true)) {
        json_response(['success' => false, 'message' => 'Please select a valid report category.'], 422);
    }
    if ($title === '' || mb_strlen($narrative) < 20) {
        json_response(['success' => false, 'message' => 'Please provide a title and a detailed narrative.'], 422);
    }

    // Handle image upload
    $imagePath = '';
    if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            json_response(['success' => false, 'message' => 'Image upload failed.'], 422);
        }
        $tmp = $_FILES['image']['tmp_name'];
        $mime = mime_content_type($tmp);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            json_response(['success' => false, 'message' => 'Only JPG, PNG, and WEBP images are allowed.'], 422);
        }
        if (($_FILES['image']['size'] ?? 0) > 5 * 1024 * 1024) {
            json_response(['success' => false, 'message' => 'Image must be 5MB or smaller.'], 422);
        }
        $uploadDir = dirname(__DIR__, 2) . '/uploads/incidents';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        $baseName = sanitize_filename(pathinfo($_FILES['image']['name'], PATHINFO_FILENAME));
        $filename = strtolower('incident-' . $baseName . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime]);
        $dest = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            json_response(['success' => false, 'message' => 'Unable to save uploaded image.'], 500);
        }
        $imagePath = 'uploads/incidents/' . $filename;
    }

    // Generate report number and insert
    $reportNo = next_incident_number($pdo);
    $userId = (int)(current_user()['id'] ?? 0);

    $stmt = $pdo->prepare('INSERT INTO incident_reports (reportNo, reportedBy, reportTitle, reportNarrative, reportImg, report_category) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$reportNo, $userId, $title, $narrative, $imagePath, $category]);

    $id = (int)$pdo->lastInsertId();

    // Fetch the newly created record for response
    $fetch = $pdo->prepare("SELECT ir.*, CONCAT(u.first_name, ' ', u.last_name) AS reported_by_name, NULL AS reviewed_by_name, NULL AS review_notes, NULL AS reviewed_at
        FROM incident_reports ir
        LEFT JOIN users u ON u.id = ir.reportedBy
        WHERE ir.reportId = ? LIMIT 1");
    $fetch->execute([$id]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    // ── Notify: alert admins/super-admins that a new incident needs review. ──
    require_once __DIR__ . '/../notifications/common.php';
    $reporterName = trim((string)($row['reported_by_name'] ?? '')) ?: 'A staff member';
    foreach (['admin', 'super-admin'] as $r) {
        notify_role(
            $pdo,
            $r,
            'incident',
            'New incident report: ' . $title,
            $reporterName . ' filed a "' . $category . '" report (' . $reportNo . ').',
            notification_link_for_role($r, 'incidents'),
            'incident:' . $id . ':submitted',
            $userId // don't notify the reporter if they are also admin/super-admin
        );
    }

    json_response(['success' => true, 'message' => 'Incident report submitted successfully.', 'item' => incident_to_frontend($row ?: [])]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to submit incident report: ' . $e->getMessage()], 500);
}
