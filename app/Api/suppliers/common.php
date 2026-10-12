<?php
require_once __DIR__ . '/../../Core/Auth.php';
require_once __DIR__ . '/../../Core/Database.php';
require_once __DIR__ . '/../../Core/Helpers.php';
require_login(['admin', 'super-admin']);

/**
 * Transform a supplier DB row into a frontend-friendly array.
 */
function supplier_row_to_frontend(array $row): array {
    return [
        'id'        => (int)$row['supplierId'],
        'name'      => (string)($row['supplierName'] ?? ''),
        'address'   => (string)($row['supplierAddress'] ?? ''),
        'phone'     => (string)($row['supplierNumber'] ?? ''),
        'isActive'  => !empty($row['isActive']),
    ];
}

/**
 * Validate supplier payload for create/update.
 */
function validate_supplier_payload(PDO $pdo, array $payload, ?int $editingId = null): array {
    $name    = trim((string)($payload['name'] ?? ''));
    $address = trim((string)($payload['address'] ?? ''));
    $phone   = trim((string)($payload['phone'] ?? ''));

    $errors = [];

    if ($name === '') {
        $errors['name'] = 'Supplier name is required.';
    } elseif (mb_strlen($name) > 50) {
        $errors['name'] = 'Supplier name must not exceed 50 characters.';
    } else {
        // Check uniqueness
        $stmt = $pdo->prepare('SELECT supplierId FROM supplier WHERE LOWER(supplierName) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        $existing = $stmt->fetchColumn();
        if ($existing && (int)$existing !== (int)$editingId) {
            $errors['name'] = 'A supplier with this name already exists.';
        }
    }

    if ($address === '') {
        $errors['address'] = 'Address is required.';
    } elseif (mb_strlen($address) > 255) {
        $errors['address'] = 'Address must not exceed 255 characters.';
    }

    if ($phone === '') {
        $errors['phone'] = 'Phone number is required.';
    } elseif (strlen($phone) > 11) {
        $errors['phone'] = 'Phone number must not exceed 11 digits.';
    } elseif (!preg_match('/^[0-9]{1,11}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number (digits only, max 11).';
    }

    return [
        'errors' => $errors,
        'data'   => [
            'supplierName'    => $name,
            'supplierAddress' => $address,
            'supplierNumber'  => $phone,
        ],
    ];
}
