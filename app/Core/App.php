<?php
require_once __DIR__ . '/Session.php';

function app_base_path(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $projectPath = str_replace('\\', '/', dirname($scriptName));
    $projectPath = preg_replace('#/(api|pages)(/.*)?$#', '', $projectPath);
    $projectPath = rtrim($projectPath, '/');
    return $projectPath === '' ? '' : $projectPath;
}

function app_url(string $path = ''): string {
    $base = app_base_path();
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}
