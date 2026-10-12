<?php
require_once __DIR__ . '/common.php';

try {
    // Fetch all categories with their subcategories
    $catStmt = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name ASC');
    $categories = $catStmt->fetchAll();

    $subStmt = $pdo->query('SELECT sub_category_id, sub_category_name, category_id FROM sub_categories ORDER BY sub_category_name ASC');
    $subcategories = $subStmt->fetchAll();

    // Group subcategories by category name
    $result = [];
    foreach ($categories as $cat) {
        $catSubs = [];
        foreach ($subcategories as $sub) {
            if ((int)$sub['category_id'] === (int)$cat['category_id']) {
                $catSubs[] = $sub['sub_category_name'];
            }
        }
        $result[$cat['category_name']] = $catSubs;
    }

    json_response(['success' => true, 'categories' => $result]);
} catch (Throwable $e) {
    json_response([
        'success' => false,
        'message' => 'Failed to load categories: ' . $e->getMessage()
    ], 500);
}
