<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Api\Http\Request;
use Inventra\Core\Auth\Auth;

$uri = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Delegate /api requests to API app. Dibungkus IIFE supaya variabel front
// controller API tidak bocor ke scope berkas ini.
if (str_starts_with($uri, '/api')) {
    (static function (): void {
        require __DIR__ . '/../../api/public/index.php';
    })();

    exit;
}

// Serve static assets if running via PHP built-in server. "return false" di level
// teratas adalah kontrak router script php -S; aset statis sengaja dilayani sebelum
// Auth::start() supaya permintaan CSS/JS tidak membuka sesi.
$filePath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

$request = Request::fromGlobals();

Auth::start($request->isSecure());

$router = require __DIR__ . '/../routes/web.php';
$router->dispatch($request);
