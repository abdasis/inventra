<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Delegate /api requests to API app
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/../../api/public/index.php';
    exit;
}

// Serve static assets if running via PHP built-in server
$filePath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

// Render Web UI Views
require __DIR__ . '/../views/layout/header.php';
require __DIR__ . '/../views/dashboard.php';
require __DIR__ . '/../views/layout/footer.php';
