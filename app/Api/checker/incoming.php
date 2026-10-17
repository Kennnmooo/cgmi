<?php
require_once __DIR__ . '/common.php';
require_login(['checker', 'admin', 'super-admin']);
ensure_ingoing_tables($pdo);

try {
    $user   = current_user();
    $role   = $user['role'] ?? '';
    $isSuperAdmin = $role === 'super-admin';

    $records  = load_ingoing_records($pdo);
    $response = ['success' => true, 'records' => $records];

    // Super-admin also receives soft-deleted (trashed) records so the Trash tab can be populated.
    if ($isSuperAdmin) {
        $response['trashed'] = load_ingoing_records_deleted($pdo);
    }

    json_response($response);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load incoming delivery checks: ' . $e->getMessage()], 500);
}
