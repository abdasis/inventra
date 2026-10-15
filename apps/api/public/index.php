<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Api\Http\ExceptionHandler;
use Inventra\Api\Http\Request;
use Inventra\Core\Auth\Auth;

$request = Request::fromGlobals();

// Dashboard dan API dilayani dari origin yang sama, jadi CORS tidak dibutuhkan.
// Wildcard "Access-Control-Allow-Origin: *" juga tidak boleh berdampingan dengan cookie session.
// Bila kelak dipakai lintas origin: refleksikan origin dari APP_URL, kirim
// "Access-Control-Allow-Credentials: true" beserta "Vary: Origin".
Auth::start($request->isSecure());

$router = (require __DIR__ . '/../routes/api.php')($request);

try {
    $router->dispatch($request);
} catch (\Throwable $throwable) {
    (new ExceptionHandler())->handle($throwable);
}
