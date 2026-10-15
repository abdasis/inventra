<?php

declare(strict_types=1);

/**
 * Registrasi seluruh endpoint API. Menerima Request sehingga IP pemanggil bisa
 * disuntikkan ke Service, bukan dibaca ulang dari scope pemanggil.
 * Mengembalikan closure yang membangun Router siap dispatch.
 * Controller diinstansiasi eksplisit karena belum ada DI container.
 */

use Inventra\Api\Http\Controllers\AssetController;
use Inventra\Api\Http\Controllers\ActivityLogController;
use Inventra\Api\Http\Controllers\AssignmentController;
use Inventra\Api\Http\Controllers\CategoryController;
use Inventra\Api\Http\Controllers\DisposalController;
use Inventra\Api\Http\Controllers\EmployeeController;
use Inventra\Api\Http\Controllers\ItemController;
use Inventra\Api\Http\Controllers\LoanController;
use Inventra\Api\Http\Controllers\LocationController;
use Inventra\Api\Http\Controllers\MaintenanceController;
use Inventra\Api\Http\Controllers\PurchaseController;
use Inventra\Api\Http\Controllers\ReportController;
use Inventra\Api\Http\Controllers\StockController;
use Inventra\Api\Http\Controllers\SupplierController;
use Inventra\Api\Http\Controllers\TransferController;
use Inventra\Api\Http\Controllers\UnitController;
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
use Inventra\Core\Actions\Assignment\CreateAssignmentAction;
use Inventra\Core\Actions\Assignment\ReturnAssignmentAction;
use Inventra\Core\Actions\Category\CreateCategoryAction;
use Inventra\Core\Actions\Category\DeleteCategoryAction;
use Inventra\Core\Actions\Category\UpdateCategoryAction;
use Inventra\Core\Actions\Disposal\CreateDisposalAction;
use Inventra\Core\Actions\Employee\CreateEmployeeAction;
use Inventra\Core\Actions\Employee\DeleteEmployeeAction;
use Inventra\Core\Actions\Employee\UpdateEmployeeAction;
use Inventra\Core\Actions\Item\CreateItemAction;
use Inventra\Core\Actions\Item\DeleteItemAction;
use Inventra\Core\Actions\Item\UpdateItemAction;
use Inventra\Core\Actions\Location\CreateLocationAction;
use Inventra\Core\Actions\Location\DeleteLocationAction;
use Inventra\Core\Actions\Location\UpdateLocationAction;
use Inventra\Core\Actions\Loan\CreateLoanAction;
use Inventra\Core\Actions\Loan\ReturnLoanAction;
use Inventra\Core\Actions\Maintenance\CompleteMaintenanceAction;
use Inventra\Core\Actions\Maintenance\CreateMaintenanceAction;
use Inventra\Core\Actions\Purchase\CancelPurchaseAction;
use Inventra\Core\Actions\Purchase\CreatePurchaseAction;
use Inventra\Core\Actions\Purchase\DeletePurchaseAction;
use Inventra\Core\Actions\Purchase\ReceivePurchaseAction;
use Inventra\Core\Actions\Purchase\UpdatePurchaseAction;
use Inventra\Core\Actions\Stock\RecordStockMovementAction;
use Inventra\Core\Actions\Supplier\CreateSupplierAction;
use Inventra\Core\Actions\Transfer\CancelTransferAction;
use Inventra\Core\Actions\Transfer\CompleteTransferAction;
use Inventra\Core\Actions\Transfer\CreateTransferAction;use Inventra\Core\Actions\Supplier\DeleteSupplierAction;
use Inventra\Core\Actions\Supplier\UpdateSupplierAction;
use Inventra\Core\Actions\Unit\CreateUnitAction;
use Inventra\Core\Actions\Unit\DeleteUnitAction;
use Inventra\Core\Actions\Unit\UpdateUnitAction;
use Inventra\Core\Actions\User\CreateUserAction;
use Inventra\Core\Actions\User\DeleteUserAction;
use Inventra\Core\Actions\User\GuardLastActiveAdminAction;
use Inventra\Core\Actions\User\ToggleUserActiveAction;
use Inventra\Core\Actions\User\UpdateUserAction;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Repositories\AssetDisposalRepository;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Repositories\AssetMaintenanceRepository;
use Inventra\Core\Repositories\AssetTransferRepository;
use Inventra\Core\Repositories\CategoryRepository;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\MovementRepository;
use Inventra\Core\Repositories\PurchaseRepository;
use Inventra\Core\Repositories\ReportRepository;
use Inventra\Core\Repositories\StockRepository;
use Inventra\Core\Repositories\SupplierRepository;
use Inventra\Core\Repositories\UnitRepository;
use Inventra\Core\Repositories\UserRepository;
use Inventra\Core\Services\AssetService;
use Inventra\Core\Services\AssignmentService;
use Inventra\Core\Services\CategoryService;
use Inventra\Core\Services\DisposalService;
use Inventra\Core\Services\EmployeeService;
use Inventra\Core\Services\ItemService;
use Inventra\Core\Services\LocationService;
use Inventra\Core\Services\LoanService;
use Inventra\Core\Services\MaintenanceService;
use Inventra\Core\Services\PurchaseService;
use Inventra\Core\Services\ReportService;
use Inventra\Core\Services\StockService;
use Inventra\Core\Services\SupplierService;
use Inventra\Core\Services\TransferService;
use Inventra\Core\Services\UnitService;
use Inventra\Core\Services\UserService;
use Inventra\Core\Validation\UserInput;

return static function (Request $request): Router {
    $router = new Router();

    // Auth diperiksa lebih dulu, baru CSRF, dan keduanya sebelum resolusi rute.
    $router->setGlobalGuards([new AuthGuard(), new CsrfGuard()]);

    $ipAddress = $request->ip();

    $assetRepository = new AssetRepository();
    $movementRepository = new MovementRepository();
    // Penempatan dan peminjaman dipakai juga oleh serah terima cepat di dashboard,
    // supaya riwayat pemegang selalu tercatat.
    $assignmentRepository = new AssetAssignmentRepository();
    $loanRepository = new AssetLoanRepository();

    $assetController = new AssetController(
        new AssetService(
            $assetRepository,
            new CreateAssetAction($assetRepository),
            new UpdateAssetAction($assetRepository),
            new DeleteAssetAction($assetRepository),
            new CheckOutAssetAction($assetRepository, $assignmentRepository),
            new CheckInAssetAction($assetRepository, $assignmentRepository),
            $ipAddress,
        ),
        $assetRepository,
        $movementRepository,
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

    // Wiring master data: tiap entitas punya service, repository, dan tiga Action
    // (create/update/delete) sendiri. Operasi tulis diperiksa peran di Controller.
    $categoryRepository = new CategoryRepository();
    $categoryController = new CategoryController(
        new CategoryService(
            $categoryRepository,
            new CreateCategoryAction($categoryRepository),
            new UpdateCategoryAction($categoryRepository),
            new DeleteCategoryAction($categoryRepository),
            $ipAddress,
        ),
        $categoryRepository,
    );

    $unitRepository = new UnitRepository();
    $unitController = new UnitController(
        new UnitService(
            $unitRepository,
            new CreateUnitAction($unitRepository),
            new UpdateUnitAction($unitRepository),
            new DeleteUnitAction($unitRepository),
            $ipAddress,
        ),
        $unitRepository,
    );

    $itemRepository = new ItemRepository();
    $itemController = new ItemController(
        new ItemService(
            $itemRepository,
            new CreateItemAction($itemRepository),
            new UpdateItemAction($itemRepository),
            new DeleteItemAction($itemRepository),
            $ipAddress,
        ),
        $itemRepository,
    );

    $locationRepository = new LocationRepository();
    $locationController = new LocationController(
        new LocationService(
            $locationRepository,
            new CreateLocationAction($locationRepository),
            new UpdateLocationAction($locationRepository),
            new DeleteLocationAction($locationRepository),
            $ipAddress,
        ),
        $locationRepository,
    );

    $employeeRepository = new EmployeeRepository();
    $employeeController = new EmployeeController(
        new EmployeeService(
            $employeeRepository,
            new CreateEmployeeAction($employeeRepository),
            new UpdateEmployeeAction($employeeRepository),
            new DeleteEmployeeAction($employeeRepository),
            $ipAddress,
        ),
        $employeeRepository,
    );

    $supplierRepository = new SupplierRepository();
    $supplierController = new SupplierController(
        new SupplierService(
            $supplierRepository,
            new CreateSupplierAction($supplierRepository),
            new UpdateSupplierAction($supplierRepository),
            new DeleteSupplierAction($supplierRepository),
            $ipAddress,
        ),
        $supplierRepository,
    );

    $router->add('GET', '/stats', static fn () => $assetController->stats());

    $router->add('GET', '/categories', static fn (Request $request) => $categoryController->index($request));
    $router->add('POST', '/categories', static fn (Request $request) => $categoryController->store($request));
    $router->add('GET', '/categories/{id}', static fn (Request $request) => $categoryController->show($request));
    $router->add('PUT', '/categories/{id}', static fn (Request $request) => $categoryController->update($request));
    $router->add('DELETE', '/categories/{id}', static fn (Request $request) => $categoryController->destroy($request));

    $router->add('GET', '/units', static fn (Request $request) => $unitController->index($request));
    $router->add('POST', '/units', static fn (Request $request) => $unitController->store($request));
    $router->add('GET', '/units/{id}', static fn (Request $request) => $unitController->show($request));
    $router->add('PUT', '/units/{id}', static fn (Request $request) => $unitController->update($request));
    $router->add('DELETE', '/units/{id}', static fn (Request $request) => $unitController->destroy($request));

    $router->add('GET', '/items', static fn (Request $request) => $itemController->index($request));
    $router->add('POST', '/items', static fn (Request $request) => $itemController->store($request));
    $router->add('GET', '/items/{id}', static fn (Request $request) => $itemController->show($request));
    $router->add('PUT', '/items/{id}', static fn (Request $request) => $itemController->update($request));
    $router->add('DELETE', '/items/{id}', static fn (Request $request) => $itemController->destroy($request));

    $router->add('GET', '/locations', static fn (Request $request) => $locationController->index($request));
    $router->add('POST', '/locations', static fn (Request $request) => $locationController->store($request));
    $router->add('GET', '/locations/{id}', static fn (Request $request) => $locationController->show($request));
    $router->add('PUT', '/locations/{id}', static fn (Request $request) => $locationController->update($request));
    $router->add('DELETE', '/locations/{id}', static fn (Request $request) => $locationController->destroy($request));

    $router->add('GET', '/employees', static fn (Request $request) => $employeeController->index($request));
    $router->add('POST', '/employees', static fn (Request $request) => $employeeController->store($request));
    $router->add('GET', '/employees/{id}', static fn (Request $request) => $employeeController->show($request));
    $router->add('PUT', '/employees/{id}', static fn (Request $request) => $employeeController->update($request));
    $router->add('DELETE', '/employees/{id}', static fn (Request $request) => $employeeController->destroy($request));

    $router->add('GET', '/suppliers', static fn (Request $request) => $supplierController->index($request));
    $router->add('POST', '/suppliers', static fn (Request $request) => $supplierController->store($request));
    $router->add('GET', '/suppliers/{id}', static fn (Request $request) => $supplierController->show($request));
    $router->add('PUT', '/suppliers/{id}', static fn (Request $request) => $supplierController->update($request));
    $router->add('DELETE', '/suppliers/{id}', static fn (Request $request) => $supplierController->destroy($request));

    // Pengadaan: rute bersegmen lebih spesifik (receive/cancel) didaftarkan sebelum
    // pola {id}; operasi tulisnya dijaga peran di Controller. Saldo stok disiapkan
    // lebih dulu karena penerimaan pembelian ikut menulis buku besar stok.
    $stockRepository = new StockRepository();
    $purchaseRepository = new PurchaseRepository();

    $purchaseController = new PurchaseController(
        new PurchaseService(
            $purchaseRepository,
            new CreatePurchaseAction($purchaseRepository, $itemRepository, $supplierRepository),
            new UpdatePurchaseAction($purchaseRepository, $itemRepository, $supplierRepository),
            new ReceivePurchaseAction(
                $purchaseRepository,
                $itemRepository,
                $locationRepository,
                $stockRepository,
                $assetRepository,
                new CreateAssetAction($assetRepository),
            ),
            new CancelPurchaseAction($purchaseRepository),
            new DeletePurchaseAction($purchaseRepository),
            $ipAddress,
        ),
        $purchaseRepository,
    );

    $router->add('GET', '/purchases', static fn (Request $request) => $purchaseController->index($request));
    $router->add('POST', '/purchases', static fn (Request $request) => $purchaseController->store($request));
    $router->add('POST', '/purchases/{id}/receive', static fn (Request $request) => $purchaseController->receive($request));
    $router->add('POST', '/purchases/{id}/cancel', static fn (Request $request) => $purchaseController->cancel($request));
    $router->add('GET', '/purchases/{id}', static fn (Request $request) => $purchaseController->show($request));
    $router->add('PUT', '/purchases/{id}', static fn (Request $request) => $purchaseController->update($request));
    $router->add('DELETE', '/purchases/{id}', static fn (Request $request) => $purchaseController->destroy($request));

    // Stok barang habis pakai: pencatatan pergerakan boleh dilakukan staf.
    $stockController = new StockController(
        new StockService(
            $stockRepository,
            new RecordStockMovementAction($stockRepository, $itemRepository, $employeeRepository),
            $ipAddress,
        ),
        $stockRepository,
    );

    $router->add('GET', '/stock', static fn (Request $request) => $stockController->index($request));
    $router->add('GET', '/stock/movements', static fn (Request $request) => $stockController->movements($request));
    $router->add('POST', '/stock/movements', static fn (Request $request) => $stockController->store($request));

    // Operasional aset: penempatan dan peminjaman adalah dua transaksi terpisah.
    // Operasi tulis penempatan dijaga peran di Controller; rute {id}/return
    // didaftarkan sebelum pola {id}.
    $assignmentController = new AssignmentController(
        new AssignmentService(
            $assignmentRepository,
            new CreateAssignmentAction($assetRepository, $assignmentRepository, $loanRepository, $employeeRepository, $locationRepository),
            new ReturnAssignmentAction($assetRepository, $assignmentRepository, $locationRepository),
            $ipAddress,
        ),
        $assignmentRepository,
    );

    $router->add('GET', '/assignments', static fn (Request $request) => $assignmentController->index($request));
    $router->add('POST', '/assignments', static fn (Request $request) => $assignmentController->store($request));
    $router->add('POST', '/assignments/{id}/return', static fn (Request $request) => $assignmentController->return($request));
    $router->add('GET', '/assignments/{id}', static fn (Request $request) => $assignmentController->show($request));

    $loanController = new LoanController(
        new LoanService(
            $loanRepository,
            new CreateLoanAction($assetRepository, $loanRepository, $assignmentRepository, $employeeRepository, $locationRepository, $movementRepository),
            new ReturnLoanAction($assetRepository, $loanRepository, $locationRepository),
            $ipAddress,
        ),
        $loanRepository,
    );

    $router->add('GET', '/loans', static fn (Request $request) => $loanController->index($request));
    $router->add('POST', '/loans', static fn (Request $request) => $loanController->store($request));
    $router->add('POST', '/loans/{id}/return', static fn (Request $request) => $loanController->return($request));
    $router->add('GET', '/loans/{id}', static fn (Request $request) => $loanController->show($request));

    // Mutasi, pemeliharaan, dan penghapusan aset. Lokasi aktif aset baru berubah
    // ketika mutasi diselesaikan; rute {id}/complete dan {id}/cancel didaftarkan
    // sebelum pola {id}.
    $transferRepository = new AssetTransferRepository();
    $maintenanceRepository = new AssetMaintenanceRepository();
    $disposalRepository = new AssetDisposalRepository();

    $transferController = new TransferController(
        new TransferService(
            $transferRepository,
            new CreateTransferAction($assetRepository, $transferRepository, $locationRepository),
            new CompleteTransferAction($assetRepository, $transferRepository, $movementRepository),
            new CancelTransferAction($transferRepository),
            $ipAddress,
        ),
        $transferRepository,
    );

    $router->add('GET', '/transfers', static fn (Request $request) => $transferController->index($request));
    $router->add('POST', '/transfers', static fn (Request $request) => $transferController->store($request));
    $router->add('POST', '/transfers/{id}/complete', static fn (Request $request) => $transferController->complete($request));
    $router->add('POST', '/transfers/{id}/cancel', static fn (Request $request) => $transferController->cancel($request));
    $router->add('GET', '/transfers/{id}', static fn (Request $request) => $transferController->show($request));

    $maintenanceController = new MaintenanceController(
        new MaintenanceService(
            $maintenanceRepository,
            new CreateMaintenanceAction($assetRepository, $maintenanceRepository, $assignmentRepository, $loanRepository, $movementRepository),
            new CompleteMaintenanceAction($maintenanceRepository, $assetRepository, $movementRepository),
            $ipAddress,
        ),
        $maintenanceRepository,
    );

    $router->add('GET', '/maintenances', static fn (Request $request) => $maintenanceController->index($request));
    $router->add('POST', '/maintenances', static fn (Request $request) => $maintenanceController->store($request));
    $router->add('POST', '/maintenances/{id}/complete', static fn (Request $request) => $maintenanceController->complete($request));
    $router->add('GET', '/maintenances/{id}', static fn (Request $request) => $maintenanceController->show($request));

    $disposalController = new DisposalController(
        new DisposalService(
            $disposalRepository,
            new CreateDisposalAction($assetRepository, $disposalRepository, $assignmentRepository, $loanRepository, $movementRepository),
            $ipAddress,
        ),
        $disposalRepository,
    );

    $router->add('GET', '/disposals', static fn (Request $request) => $disposalController->index($request));
    $router->add('POST', '/disposals', static fn (Request $request) => $disposalController->store($request));
    $router->add('GET', '/disposals/{id}', static fn (Request $request) => $disposalController->show($request));

    // Laporan, ekspor, dan audit trail. Ringkasan serta ekspor terbuka untuk semua
    // pengguna yang sudah login; audit trail dijaga peran di Controller.
    $reportRepository = new ReportRepository();

    $reportController = new ReportController(new ReportService($reportRepository, $ipAddress));

    $router->add('GET', '/reports/summary', static fn (Request $request) => $reportController->summary($request));
    $router->add('GET', '/reports/assets.csv', static fn (Request $request) => $reportController->assetsCsv($request));
    $router->add('GET', '/reports/stock.csv', static fn (Request $request) => $reportController->stockCsv($request));
    $router->add('GET', '/reports/activity.csv', static fn (Request $request) => $reportController->activityCsv($request));

    $activityLogController = new ActivityLogController($reportRepository);

    $router->add('GET', '/activity-logs/actions', static fn () => $activityLogController->actions());
    $router->add('GET', '/activity-logs', static fn (Request $request) => $activityLogController->index($request));

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
