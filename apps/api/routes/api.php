<?php

declare(strict_types=1);

/**
 * Registrasi seluruh endpoint API. Menerima Request sehingga IP pemanggil bisa
 * disuntikkan ke Service, bukan dibaca ulang dari scope pemanggil.
 * Mengembalikan closure yang membangun Router siap dispatch.
 * Controller diinstansiasi eksplisit karena belum ada DI container.
 */

use Inventra\Api\Http\Controllers\AssetController;
use Inventra\Api\Http\Controllers\UserController;
use Inventra\Api\Http\Middleware\AuthGuard;
use Inventra\Api\Http\Middleware\CsrfGuard;
use Inventra\Api\Http\Request;
use Inventra\Api\Http\Router;
use Inventra\Core\Actions\Asset\CheckInAssetAction;
use Inventra\Core\Actions\Asset\CheckOutAssetAction;
use Inventra\Core\Actions\Asset\CreateAssetAction;
use Inventra\Core\Actions\Asset\DeleteAssetAction;
use Inventra\Core\Actions\Asset\UpdateAssetAction;
use Inventra\Core\Actions\User\CreateUserAction;
use Inventra\Core\Actions\User\DeleteUserAction;
use Inventra\Core\Actions\User\GuardLastActiveAdminAction;
use Inventra\Core\Actions\User\ToggleUserActiveAction;
use Inventra\Core\Actions\User\UpdateUserAction;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\CategoryRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\MovementRepository;
use Inventra\Core\Repositories\UserRepository;
use Inventra\Core\Services\AssetService;
use Inventra\Core\Services\UserService;
use Inventra\Core\Validation\UserInput;

return static function (Request $request): Router {
    $router = new Router();

    // Auth diperiksa lebih dulu, baru CSRF, dan keduanya sebelum resolusi rute.
    $router->setGlobalGuards([new AuthGuard(), new CsrfGuard()]);

    $ipAddress = $request->ip();

    $assetRepository = new AssetRepository();

    $assetController = new AssetController(
        new AssetService(
            $assetRepository,
            new CreateAssetAction($assetRepository),
            new UpdateAssetAction($assetRepository),
            new DeleteAssetAction($assetRepository),
            new CheckOutAssetAction($assetRepository),
            new CheckInAssetAction($assetRepository),
            $ipAddress,
        ),
        $assetRepository,
        new CategoryRepository(),
        new LocationRepository(),
        new MovementRepository(),
    );

    $userRepository = new UserRepository();
    $lastActiveAdmin = new GuardLastActiveAdminAction($userRepository);

    $userController = new UserController(
        new UserService(
            $userRepository,
            new UserInput($userRepository),
            new CreateUserAction($userRepository),
            new UpdateUserAction($userRepository, $lastActiveAdmin),
            new DeleteUserAction($userRepository, $lastActiveAdmin),
            new ToggleUserActiveAction($userRepository, $lastActiveAdmin),
            $ipAddress,
        ),
        $userRepository,
    );

    $router->add('GET', '/stats', static fn () => $assetController->stats());
    $router->add('GET', '/categories', static fn () => $assetController->categories());
    $router->add('GET', '/locations', static fn () => $assetController->locations());

    $router->add('GET', '/assets', static fn (Request $request) => $assetController->index($request));
    $router->add('POST', '/assets', static fn (Request $request) => $assetController->store($request));

    // Rute bersegmen lebih spesifik didaftarkan sebelum pola {id}.
    $router->add('POST', '/assets/{id}/checkout', static fn (Request $request) => $assetController->checkOut($request));
    $router->add('POST', '/assets/{id}/checkin', static fn (Request $request) => $assetController->checkIn($request));

    $router->add('GET', '/assets/{id}', static fn (Request $request) => $assetController->show($request));
    $router->add('PUT', '/assets/{id}', static fn (Request $request) => $assetController->update($request));
    $router->add('POST', '/assets/{id}', static fn (Request $request) => $assetController->update($request));
    $router->add('DELETE', '/assets/{id}', static fn (Request $request) => $assetController->destroy($request));

    $router->add('GET', '/users', static fn (Request $request) => $userController->index($request));
    $router->add('POST', '/users', static fn (Request $request) => $userController->store($request));

    $router->add('POST', '/users/{id}/toggle-active', static fn (Request $request) => $userController->toggleActive($request));

    $router->add('PUT', '/users/{id}', static fn (Request $request) => $userController->update($request));
    $router->add('POST', '/users/{id}', static fn (Request $request) => $userController->update($request));
    $router->add('DELETE', '/users/{id}', static fn (Request $request) => $userController->destroy($request));

    return $router;
};
