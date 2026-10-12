<?php
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/App.php';

function json_response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function get_json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

function h(?string $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function sanitize_filename(string $filename): string {
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename);
    $filename = trim((string)$filename, '-.');
    return $filename !== '' ? $filename : 'file';
}
