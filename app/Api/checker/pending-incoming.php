<?php
require_once __DIR__ . '/common.php';
require_login(['admin', 'inventory', 'super-admin']);
ensure_ingoing_tables($pdo);

$user   = current_user();
$role   = $user['role'] ?? '';
$isSuperAdmin = $role === 'super-admin';

try {
    // Return flat items list — each item is independently confirmable.
    // Soft-deleted records are excluded from the normal lists (filter applied inside each function).
    $pending   = load_ingoing_items_flat($pdo, 'Pending');
    $confirmed = load_ingoing_items_flat($pdo, 'Confirmed');
    $rejected  = load_ingoing_items_flat($pdo, 'Rejected');

    $response = [
        'success'   => true,
        'pending'   => $pending,
        'confirmed' => $confirmed,
        'rejected'  => $rejected,
    ];

    // Super-admin also receives the soft-deleted (trashed) items so the Trash tab can be populated.
    if ($isSuperAdmin) {
        $response['trashed'] = load_ingoing_items_flat_deleted($pdo);
    }

    json_response($response);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load incoming confirmations: ' . $e->getMessage()], 500);
}
