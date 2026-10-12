<?php
require_once __DIR__ . '/common.php';
require_login(['checker', 'admin', 'super-admin']);
ensure_ingoing_tables($pdo);

try {
    $records = load_ingoing_records($pdo);
    json_response(['success' => true, 'records' => $records]);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Unable to load incoming delivery checks: ' . $e->getMessage()], 500);
}
