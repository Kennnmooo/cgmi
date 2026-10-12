<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $payload = get_json_input();
    $id = isset($payload['id']) && $payload['id'] !== '' ? (int)$payload['id'] : null;
    $name = trim((string)($payload['name'] ?? ''));
    $location = trim((string)($payload['location'] ?? ''));
    $itemId = isset($payload['itemId']) && $payload['itemId'] !== '' ? (int)$payload['itemId'] : null;
    $statusInput = ucfirst(strtolower(trim((string)($payload['status'] ?? 'Unused'))));
    $statusMap = ['Unused' => 'Unused', 'Occupied' => 'Occupied', 'Maintenance' => 'Maintenance'];
    $status = $statusMap[$statusInput] ?? 'Unused';

    // Validate required fields
    if ($name === '') {
        json_response(['success' => false, 'message' => 'Shelf name is required.'], 422);
    }

    // Validate shelf_location enum
    $validLocations = ['Top Layer', 'Middle Layer', 'Bottom Layer'];
    if ($location === '' || !in_array($location, $validLocations, true)) {
        json_response(['success' => false, 'message' => 'Shelf location must be one of: Top Layer, Middle Layer, or Bottom Layer.'], 422);
    }

    // Validate item if provided
    if ($itemId !== null) {
        $eligibleItem = $pdo->prepare("
            SELECT item_id, item_name
            FROM inventory_items
            WHERE item_id = ? AND is_active = 1
            LIMIT 1
        ");
        $eligibleItem->execute([$itemId]);
        $selectedItem = $eligibleItem->fetch(PDO::FETCH_ASSOC);

        if (!$selectedItem) {
            json_response(['success' => false, 'message' => 'Only active inventory items can be assigned to shelves.'], 422);
        }

        // Check if item is already assigned to another shelf
        $checkAssigned = $pdo->prepare('SELECT shelf_id FROM shelves WHERE item_id = ? AND shelf_id <> ?');
        $checkAssigned->execute([$itemId, $id ?? 0]);
        if ($checkAssigned->fetch()) {
            json_response(['success' => false, 'message' => 'This item is already assigned to another shelf.'], 422);
        }
    } else {
        // No item assigned — cannot be Occupied
        if ($status === 'Occupied') {
            $status = 'Unused';
        }
    }

    // The on-shelf quantity is only meaningful (and adjustable) when the shelf is
    // Occupied AND has an item assigned. In every other case it resets to 0:
    //  - no item assigned  -> quantity 0
    //  - Unused/Maintenance -> quantity 0
    $forceEmpty = ($itemId === null || $status !== 'Occupied');

    $userId = current_user()['id'] ?? null;

    $pdo->beginTransaction();

    if ($id === null) {
        // Insert new shelf
        $stmt = $pdo->prepare('
            INSERT INTO shelves (item_id, shelf_name, shelf_location, shelf_status, shelf_quantity, created_by, modified_by)
            VALUES (?, ?, ?, ?, 0, ?, ?)
        ');
        $stmt->execute([$itemId, $name, $location, $status, $userId, $userId]);
        $id = (int)$pdo->lastInsertId();
    } elseif ($forceEmpty) {
        // Update existing shelf and reset its on-shelf quantity to 0
        $stmt = $pdo->prepare('
            UPDATE shelves
            SET item_id = ?, shelf_name = ?, shelf_location = ?, shelf_status = ?, shelf_quantity = 0, modified_by = ?
            WHERE shelf_id = ?
        ');
        $stmt->execute([$itemId, $name, $location, $status, $userId, $id]);
    } else {
        // Update existing shelf
        $stmt = $pdo->prepare('
            UPDATE shelves
            SET item_id = ?, shelf_name = ?, shelf_location = ?, shelf_status = ?, modified_by = ?
            WHERE shelf_id = ?
        ');
        $stmt->execute([$itemId, $name, $location, $status, $userId, $id]);
    }

    $pdo->commit();
    json_response(['success' => true, 'message' => 'Shelf saved successfully.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => 'Unable to save shelf: ' . $e->getMessage()], 500);
}
