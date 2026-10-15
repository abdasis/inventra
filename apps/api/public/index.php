<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Api\Controllers\AssetApiController;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Normalize path: strip /api/v1 prefix or /api prefix
$path = preg_replace('#^/api(/v1)?#', '', $uri);
$path = rtrim($path, '/') ?: '/';

try {
    $controller = new AssetApiController();

    // Routing map
    if ($path === '/stats' && $method === 'GET') {
        $controller->stats();
    } elseif ($path === '/categories' && $method === 'GET') {
        $controller->categories();
    } elseif ($path === '/locations' && $method === 'GET') {
        $controller->locations();
    } elseif ($path === '/assets' && $method === 'GET') {
        $controller->index();
    } elseif ($path === '/assets' && $method === 'POST') {
        $controller->store();
    } elseif (preg_match('#^/assets/(\d+)$#', $path, $matches)) {
        $id = (int)$matches[1];
        if ($method === 'GET') {
            $controller->show($id);
        } elseif ($method === 'PUT' || $method === 'POST') {
            $controller->update($id);
        } elseif ($method === 'DELETE') {
            $controller->destroy($id);
        }
    } elseif (preg_match('#^/assets/(\d+)/checkout$#', $path, $matches) && $method === 'POST') {
        $controller->checkOut((int)$matches[1]);
    } elseif (preg_match('#^/assets/(\d+)/checkin$#', $path, $matches) && $method === 'POST') {
        $controller->checkIn((int)$matches[1]);
    } else {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint not found: ' . $method . ' ' . $path,
        ]);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'hint' => 'Start MySQL container with `python3 manage.py docker up` or configure .env',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
