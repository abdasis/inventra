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

// Halaman aset. Rute "create" dan rute bersegmen lebih spesifik didaftarkan
// sebelum pola {id}, mengikuti pencocokan first-match-wins.
$router->add('GET', '/assets/create', static fn () => $pageController->assetCreate(), [$requireAuth]);
$router->add(
    'GET',
    '/assets/{id}/edit',
    static fn (Request $request) => $pageController->assetEdit((int)$request->routeParam('id')),
    [$requireAuth]
);
$router->add(
    'GET',
    '/assets/{id}/label',
    static fn (Request $request) => $pageController->assetLabel((int)$request->routeParam('id')),
    [$requireAuth]
);
$router->add(
    'GET',
    '/assets/{id}',
    static fn (Request $request) => $pageController->assetDetail((int)$request->routeParam('id')),
    [$requireAuth]
);

$router->add('GET', '/users', static fn () => $pageController->usersIndex(), [$requireAuth, $requireAdmin]);
$router->add('GET', '/users/create', static fn () => $pageController->userCreate(), [$requireAuth, $requireAdmin]);
$router->add(
    'GET',
    '/users/{id}/edit',
    static fn (Request $request) => $pageController->userEdit((int)$request->routeParam('id')),
    [$requireAuth, $requireAdmin]
);

// Halaman master data: data acuan sistem, hanya untuk administrator. Rute form
// didaftarkan sebelum rute daftar yang segmennya lebih pendek, mengikuti
// pencocokan first-match-wins.
$router->add('GET', '/master/categories', static fn () => $pageController->masterCategories(), [$requireAuth, $requireAdmin]);
$router->add('GET', '/master/units', static fn () => $pageController->masterUnits(), [$requireAuth, $requireAdmin]);
$router->add('GET', '/master/locations', static fn () => $pageController->masterLocations(), [$requireAuth, $requireAdmin]);

$router->add('GET', '/master/items/create', static fn () => $pageController->masterItemCreate(), [$requireAuth, $requireAdmin]);
$router->add(
    'GET',
    '/master/items/{id}/edit',
    static fn (Request $request) => $pageController->masterItemEdit((int)$request->routeParam('id')),
    [$requireAuth, $requireAdmin]
);
$router->add('GET', '/master/items', static fn () => $pageController->masterItems(), [$requireAuth, $requireAdmin]);

$router->add('GET', '/master/employees/create', static fn () => $pageController->masterEmployeeCreate(), [$requireAuth, $requireAdmin]);
$router->add(
    'GET',
    '/master/employees/{id}/edit',
    static fn (Request $request) => $pageController->masterEmployeeEdit((int)$request->routeParam('id')),
    [$requireAuth, $requireAdmin]
);
$router->add('GET', '/master/employees', static fn () => $pageController->masterEmployees(), [$requireAuth, $requireAdmin]);

$router->add('GET', '/master/suppliers/create', static fn () => $pageController->masterSupplierCreate(), [$requireAuth, $requireAdmin]);
$router->add(
    'GET',
    '/master/suppliers/{id}/edit',
    static fn (Request $request) => $pageController->masterSupplierEdit((int)$request->routeParam('id')),
    [$requireAuth, $requireAdmin]
);
$router->add('GET', '/master/suppliers', static fn () => $pageController->masterSuppliers(), [$requireAuth, $requireAdmin]);

// Pengadaan dan penerimaan: staf boleh melihat daftar serta detail, sedangkan
// operasi tulisnya dijaga peran di lapisan API. Rute form/spesifik didaftarkan
// sebelum pola {id}.
$router->add('GET', '/purchases', static fn () => $pageController->purchasesIndex(), [$requireAuth]);
$router->add('GET', '/purchases/create', static fn () => $pageController->purchaseCreate(), [$requireAuth]);
$router->add(
    'GET',
    '/purchases/{id}/edit',
    static fn (Request $request) => $pageController->purchaseEdit((int)$request->routeParam('id')),
    [$requireAuth]
);
$router->add(
    'GET',
    '/purchases/{id}',
    static fn (Request $request) => $pageController->purchaseDetail((int)$request->routeParam('id')),
    [$requireAuth]
);

// Stok barang habis pakai: pencatatan pergerakan boleh dilakukan staf.
$router->add('GET', '/stock', static fn () => $pageController->stockIndex(), [$requireAuth]);

// Operasional aset: penempatan dan peminjaman. Staf boleh membuka daftarnya,
// sedangkan operasi tulis penempatan dijaga peran di lapisan API.
$router->add('GET', '/assignments', static fn () => $pageController->assignmentIndex(), [$requireAuth]);
$router->add('GET', '/loans', static fn () => $pageController->loanIndex(), [$requireAuth]);
$router->add('GET', '/transfers', static fn () => $pageController->transferIndex(), [$requireAuth]);
$router->add('GET', '/maintenances/create', static fn () => $pageController->maintenanceCreate(), [$requireAuth]);
$router->add('GET', '/maintenances', static fn () => $pageController->maintenanceIndex(), [$requireAuth]);
$router->add('GET', '/disposals/create', static fn () => $pageController->disposalCreate(), [$requireAuth]);
$router->add('GET', '/disposals', static fn () => $pageController->disposalIndex(), [$requireAuth]);

// Laporan terbuka untuk semua pengguna; audit trail memuat jejak seluruh pengguna
// sehingga hanya administrator.
$router->add('GET', '/reports/print', static fn () => $pageController->reportPrint(), [$requireAuth]);
$router->add('GET', '/reports', static fn () => $pageController->reportIndex(), [$requireAuth]);
$router->add('GET', '/activity-logs', static fn () => $pageController->activityLogIndex(), [$requireAuth, $requireAdmin]);

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
