<?php

declare(strict_types=1);

/**
 * Tabel rute halaman web. Mengembalikan Router siap dispatch.
 * Controller diinstansiasi eksplisit karena belum ada DI container.
 */

use Inventra\Api\Http\Request;
use Inventra\Api\Http\Router;
use Inventra\Core\Auth\Auth;
use Inventra\Web\Http\Controllers\AuthController;
use Inventra\Web\Http\Controllers\PageController;
use Inventra\Web\Http\Middleware\RequireAdmin;
use Inventra\Web\Http\Middleware\RequireAuth;
use Inventra\Web\Support\LoginRedirect;
use Inventra\Web\Support\View;

$router = new Router();

$authController = new AuthController();
$pageController = new PageController();
$requireAuth = new RequireAuth();
$requireAdmin = new RequireAdmin();

// Halaman publik, tanpa guard. Logout hanya menerima POST; method lain jatuh ke 405.
$router->add('GET', '/login', static fn () => $authController->showLogin());
$router->add('POST', '/login', static fn (Request $request) => $authController->login($request->ip()));
$router->add('POST', '/logout', static fn (Request $request) => $authController->logout($request->ip()));

$router->add('GET', '/', static fn () => $pageController->dashboard(), [$requireAuth]);

$router->add('GET', '/users', static fn () => $pageController->usersIndex(), [$requireAuth, $requireAdmin]);
$router->add('GET', '/users/create', static fn () => $pageController->userCreate(), [$requireAuth, $requireAdmin]);
$router->add(
    'GET',
    '/users/{id}/edit',
    static fn (Request $request) => $pageController->userEdit((int)$request->routeParam('id')),
    [$requireAuth, $requireAdmin]
);

// Rantai routing lama memeriksa "belum login" SEBELUM fallback, jadi tanpa sesi
// semua path yang tidak publik dialihkan ke login; dengan sesi barulah 404 dirender.
$notFound = static function (Request $request): void {
    if (!Auth::check()) {
        View::redirect(LoginRedirect::target($request));
        return;
    }

    http_response_code(404);
    View::render('errors/404', [], [
        ['label' => 'Halaman Tidak Ditemukan', 'url' => null],
    ], 'Halaman Tidak Ditemukan — Inventra');
};

// Rantai lama hanya menghasilkan 405 untuk /login dan /logout — dua rute yang
// method-nya dinyatakan eksplisit. Path lain dengan method salah jatuh ke 404,
// sama seperti dulu.
$router->setMethodNotAllowed(static function (Request $request) use ($notFound): void {
    if (in_array($request->rawPath(), ['/login', '/logout'], true)) {
        View::methodNotAllowed();
    }

    $notFound($request);
});

$router->setNotFound($notFound);

return $router;
