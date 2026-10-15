<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Category\CreateCategoryAction;
use Inventra\Core\Actions\Category\DeleteCategoryAction;
use Inventra\Core\Actions\Category\UpdateCategoryAction;
use Inventra\Core\Actions\Employee\CreateEmployeeAction;
use Inventra\Core\Actions\Employee\DeleteEmployeeAction;
use Inventra\Core\Actions\Employee\UpdateEmployeeAction;
use Inventra\Core\Actions\Location\CreateLocationAction;
use Inventra\Core\Actions\Location\DeleteLocationAction;
use Inventra\Core\Actions\Location\UpdateLocationAction;
use Inventra\Core\Actions\Supplier\CreateSupplierAction;
use Inventra\Core\Actions\Supplier\DeleteSupplierAction;
use Inventra\Core\Actions\Supplier\UpdateSupplierAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ActivityLogRepository;
use Inventra\Core\Repositories\CategoryRepository;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\SupplierRepository;
use Inventra\Core\Services\CategoryService;
use Inventra\Core\Services\EmployeeService;
use Inventra\Core\Services\LocationService;
use Inventra\Core\Services\SupplierService;

echo "=== Running Inventra Master & Partner Self-Check Tests ===\n\n";

// Tanpa assert aktif seluruh pemeriksaan di bawah lulus secara hampa.
if ((int)ini_get('zend.assertions') !== 1) {
    echo "[FATAL] assert() tidak aktif (zend.assertions != 1).\n";
    exit(1);
}

$failures = 0;
$skipped = [];

/**
 * Menjalankan satu blok pemeriksaan. AssertionError dihitung sebagai kegagalan
 * supaya skrip tetap sampai ke ringkasan dan keluar dengan exit(1).
 */
$block = static function (string $title, callable $check) use (&$failures): void {
    echo $title . ' ';
    try {
        $check();
        echo "PASSED\n";
    } catch (AssertionError $e) {
        $failures++;
        echo "FAILED\n  -> " . $e->getMessage() . "\n";
    }
};

/** Pesan ValidationException, atau penanda bila aksinya tidak ditolak. */
$rejection = static function (callable $action): string {
    try {
        $action();
    } catch (ValidationException $e) {
        return $e->getMessage();
    }

    return '<tidak-ditolak>';
};

/** Pesan NotFoundException, atau penanda bila barisnya ternyata ketemu. */
$missing = static function (callable $action): string {
    try {
        $action();
    } catch (NotFoundException $e) {
        return $e->getMessage();
    }

    return '<tidak-ditolak>';
};

// --- Basis data -----------------------------------------------------------

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$sqlite->exec((string)file_get_contents(__DIR__ . '/support/schema.sqlite.sql'));

// Tabel employees dan suppliers dijaga agent migrasi di schema.sqlite.sql;
// CREATE IF NOT EXISTS di sini hanya jaring supaya self-check tetap bisa jalan
// mandiri selama mirror itu belum memuatnya.
$sqlite->exec('CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_number TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    department TEXT,
    position TEXT,
    email TEXT,
    phone TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)');
$sqlite->exec('CREATE TABLE IF NOT EXISTS suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    contact_person TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)');

$sqlite->exec("INSERT INTO categories (code, name, description) VALUES
    ('CAT-IT', 'Perangkat IT', 'Laptop dan aksesoris'),
    ('CAT-FRN', 'Furniture', 'Meja dan kursi'),
    ('CAT-OLD', 'Kategori Lama', NULL)");
$sqlite->exec("INSERT INTO locations (code, name, building, floor) VALUES
    ('LOC-GUG', 'Gudang', 'A', '1'),
    ('LOC-DEV', 'Ruang IT', 'A', '2')");
$sqlite->exec("INSERT INTO assets (asset_code, name, category_id, location_id, status, condition_level) VALUES
    ('AST-001', 'Dell Latitude 7420', 1, 1, 'available', 'good'),
    ('AST-002', 'Kursi Ergonomis', 2, 2, 'in_use', 'fair')");
$hash = password_hash('Inventra#2026', PASSWORD_DEFAULT);
$sqlite->exec("INSERT INTO users (id, name, email, password_hash, role, is_active)
    VALUES (1, 'Admin GA', 'admin@kantor.id', '$hash', 'admin', 1)");

$itemSeeded = false;
try {
    $sqlite->exec("INSERT INTO items (code, name, category_id, unit_id) VALUES ('ITM-001', 'Toner Printer', 2, 1)");
    $itemSeeded = true;
} catch (PDOException $e) {
    $skipped[] = 'Penjaga hapus kategori berbasis barang (tabel items belum bisa diisi seeder ini)';
}

Connection::setInstance($sqlite);
$_SESSION['user_id'] = 1;

$ipAddress = '127.0.0.1';

$categories = new CategoryRepository($sqlite);
$categoryService = new CategoryService(
    $categories,
    new CreateCategoryAction($categories),
    new UpdateCategoryAction($categories),
    new DeleteCategoryAction($categories),
    $ipAddress
);

$locations = new LocationRepository($sqlite);
$locationService = new LocationService(
    $locations,
    new CreateLocationAction($locations),
    new UpdateLocationAction($locations),
    new DeleteLocationAction($locations),
    $ipAddress
);

$employees = new EmployeeRepository($sqlite);
$employeeService = new EmployeeService(
    $employees,
    new CreateEmployeeAction($employees),
    new UpdateEmployeeAction($employees),
    new DeleteEmployeeAction($employees),
    $ipAddress
);

$suppliers = new SupplierRepository($sqlite);
$supplierService = new SupplierService(
    $suppliers,
    new CreateSupplierAction($suppliers),
    new UpdateSupplierAction($suppliers),
    new DeleteSupplierAction($suppliers),
    $ipAddress
);

// --- 1. Kategori ----------------------------------------------------------

$block('[TEST 1] Kategori: CRUD, induk, dan penjaga hapus', static function () use (
    $categoryService,
    $categories,
    $rejection,
    $missing,
    $itemSeeded
): void {
    $created = $categoryService->create([
        'code' => 'CAT-ELK',
        'name' => 'Elektronik',
        'description' => 'Perangkat elektronik',
    ]);
    assert($created['message'] === 'Kategori berhasil ditambahkan.');
    assert((int)$created['data']['id'] > 0);
    assert($created['data']['parent_id'] === null);
    $parentId = (int)$created['data']['id'];

    $child = $categoryService->create(['code' => 'CAT-LPT', 'name' => 'Laptop', 'parent_id' => $parentId]);
    assert((int)$child['data']['parent_id'] === $parentId);
    $childId = (int)$child['data']['id'];

    $childRow = $categories->find($childId);
    assert($childRow !== null);
    assert($childRow['parent_name'] === 'Elektronik');

    // Induk tidak dikenal dan kode duplikat ditolak sebelum baris ditulis.
    assert(
        $rejection(fn () => $categoryService->create(['code' => 'CAT-X', 'name' => 'X', 'parent_id' => 4321]))
        === 'Kategori induk tidak ditemukan.'
    );
    assert(
        $rejection(fn () => $categoryService->create(['code' => 'CAT-ELK', 'name' => 'Duplikat']))
        === 'Kode kategori sudah dipakai.'
    );
    assert(
        $rejection(fn () => $categoryService->create(['code' => '', 'name' => 'Tanpa Kode']))
        === 'Kode kategori wajib diisi.'
    );
    assert(
        $rejection(fn () => $categoryService->update($parentId, [
            'code' => 'CAT-ELK',
            'name' => 'Elektronik',
            'parent_id' => $parentId,
        ])) === 'Kategori tidak dapat menjadi induk dirinya sendiri.'
    );
    assert(
        $rejection(fn () => $categoryService->update($parentId, [
            'code' => 'CAT-ELK',
            'name' => str_repeat('x', 129),
        ])) === 'Nama kategori maksimal 128 karakter.'
    );
    assert(
        $missing(fn () => $categoryService->update(9999, ['code' => 'CAT-NA', 'name' => 'Tidak Ada']))
        === 'Kategori tidak ditemukan.'
    );
    // Baris tidak ada diperiksa lebih dulu, jadi kode duplikat pun tidak divalidasi.
    assert(
        $missing(fn () => $categoryService->update(9999, ['code' => 'CAT-ELK', 'name' => '']))
        === 'Kategori tidak ditemukan.'
    );
    assert(
        $missing(fn () => $categoryService->delete(9999)) === 'Kategori tidak ditemukan.'
    );

    $updated = $categoryService->update($parentId, [
        'code' => 'CAT-ELK',
        'name' => 'Elektronik & Gadget',
        'description' => 'Perangkat elektronik',
    ]);
    assert($updated['message'] === 'Data kategori berhasil diperbarui.');
    assert($updated['data']['name'] === 'Elektronik & Gadget');
    assert(count($categories->all()) === 5);
    assert(count($categories->all(['search' => 'Gadget'])) === 1);
    assert($categories->codeExists('CAT-ELK') === true);
    assert($categories->codeExists('CAT-ELK', $parentId) === false);

    // Kategori yang masih dipakai aset tidak boleh hilang.
    assert(
        $rejection(fn () => $categoryService->delete(1))
        === 'Kategori tidak dapat dihapus karena masih dipakai oleh 1 aset.'
    );
    assert($categories->find(1) !== null);

    if ($itemSeeded) {
        assert(
            $rejection(fn () => $categoryService->delete(2))
            === 'Kategori tidak dapat dihapus karena masih dipakai oleh 1 aset dan 1 barang.'
        );
    }

    $deleted = $categoryService->delete((int)$categories->all(['search' => 'Kategori Lama'])[0]['id']);
    assert($deleted['message'] === 'Kategori berhasil dihapus.');
    assert(count($categories->all(['search' => 'Kategori Lama'])) === 0);
});

// --- 2. Lokasi ------------------------------------------------------------

$block('[TEST 2] Lokasi: CRUD dan penjaga hapus', static function () use (
    $locationService,
    $locations,
    $rejection,
    $missing
): void {
    $created = $locationService->create([
        'code' => 'LOC-SRV',
        'name' => 'Ruang Server IT',
        'building' => 'B',
        'floor' => 'Lt 1',
    ]);
    assert($created['message'] === 'Lokasi berhasil ditambahkan.');
    assert($created['data']['floor'] === 'Lt 1');
    $locationId = (int)$created['data']['id'];

    $updated = $locationService->update($locationId, [
        'code' => 'LOC-SRV',
        'name' => 'Ruang Server IT',
        'building' => 'B',
        'floor' => 'Lt 2',
    ]);
    assert($updated['message'] === 'Data lokasi berhasil diperbarui.');
    assert($updated['data']['floor'] === 'Lt 2');
    assert($locations->find($locationId)['building'] === 'B');

    assert(
        $rejection(fn () => $locationService->create(['code' => 'LOC-SRV', 'name' => 'Duplikat']))
        === 'Kode lokasi sudah dipakai.'
    );
    assert(
        $rejection(fn () => $locationService->create(['code' => 'LOC-X', 'name' => '   ']))
        === 'Nama lokasi wajib diisi.'
    );
    assert(
        $rejection(fn () => $locationService->delete(1))
        === 'Lokasi tidak dapat dihapus karena masih dipakai oleh 1 aset.'
    );
    assert(
        $missing(fn () => $locationService->delete(9999)) === 'Lokasi tidak ditemukan.'
    );

    assert($locationService->delete($locationId)['message'] === 'Lokasi berhasil dihapus.');
    assert($locations->find($locationId) === null);
    assert(count($locations->all(['search' => 'Gudang'])) === 1);
});

// --- 3. Karyawan ----------------------------------------------------------

$block('[TEST 3] Karyawan: CRUD, nomor unik, email, status aktif', static function () use (
    $employeeService,
    $employees,
    $rejection,
    $missing
): void {
    $created = $employeeService->create([
        'employee_number' => 'EMP-001',
        'name' => 'Ahmad Farhan',
        'department' => 'IT',
        'position' => 'Programmer',
        'email' => 'ahmad@kantor.id',
        'phone' => '08123456789',
    ]);
    assert($created['message'] === 'Karyawan berhasil ditambahkan.');
    assert((int)$created['data']['is_active'] === 1);
    $employeeId = (int)$created['data']['id'];

    $inactive = $employeeService->create([
        'employee_number' => 'EMP-002',
        'name' => 'Siti Rahma',
        'is_active' => '0',
    ]);
    assert((int)$inactive['data']['is_active'] === 0);

    assert(
        $rejection(fn () => $employeeService->create(['employee_number' => 'EMP-001', 'name' => 'Duplikat']))
        === 'Nomor karyawan sudah dipakai.'
    );
    assert(
        $rejection(fn () => $employeeService->create([
            'employee_number' => 'EMP-003',
            'name' => 'Email Salah',
            'email' => 'bukan-email',
        ])) === 'Format email tidak valid.'
    );
    assert(
        $rejection(fn () => $employeeService->create(['employee_number' => 'EMP-004', 'name' => '']))
        === 'Nama karyawan wajib diisi.'
    );
    assert(
        $rejection(fn () => $employeeService->create(['employee_number' => '', 'name' => 'Tanpa Nomor']))
        === 'Nomor karyawan wajib diisi.'
    );
    assert(
        $missing(fn () => $employeeService->update(9999, ['employee_number' => 'EMP-009', 'name' => 'Tidak Ada']))
        === 'Karyawan tidak ditemukan.'
    );

    $updated = $employeeService->update($employeeId, [
        'employee_number' => 'EMP-001',
        'name' => 'Ahmad Farhan',
        'department' => 'IT',
        'position' => 'Programmer',
        'email' => 'ahmad@kantor.id',
        'phone' => '08123456789',
        'is_active' => false,
    ]);
    assert($updated['message'] === 'Data karyawan berhasil diperbarui.');
    assert((int)$updated['data']['is_active'] === 0);
    assert($employees->employeeNumberExists('EMP-001') === true);
    assert($employees->employeeNumberExists('EMP-001', $employeeId) === false);
    assert(count($employees->all(['search' => 'Ahmad'])) === 1);
    assert(count($employees->all(['search' => 'Programmer'])) === 1);

    assert($employeeService->delete($employeeId)['message'] === 'Karyawan berhasil dihapus.');
    assert($employees->find($employeeId) === null);
});

// --- 4. Supplier ----------------------------------------------------------

$block('[TEST 4] Supplier: CRUD dan kode unik', static function () use (
    $supplierService,
    $suppliers,
    $rejection,
    $missing
): void {
    $created = $supplierService->create([
        'code' => 'SUP-001',
        'name' => 'PT Sumber Komputer',
        'contact_person' => 'Rina',
        'phone' => '021-5551234',
        'email' => 'sales@sumber.id',
        'address' => 'Jl. Merdeka 1',
    ]);
    assert($created['message'] === 'Supplier berhasil ditambahkan.');
    assert((int)$created['data']['is_active'] === 1);
    $supplierId = (int)$created['data']['id'];

    assert(
        $rejection(fn () => $supplierService->create(['code' => 'SUP-001', 'name' => 'Duplikat']))
        === 'Kode supplier sudah dipakai.'
    );
    assert(
        $rejection(fn () => $supplierService->create([
            'code' => 'SUP-002',
            'name' => 'Email Salah',
            'email' => 'salah@',
        ])) === 'Format email tidak valid.'
    );
    assert(
        $rejection(fn () => $supplierService->create(['code' => 'SUP-003', 'name' => '']))
        === 'Nama supplier wajib diisi.'
    );
    assert(
        $missing(fn () => $supplierService->update(9999, ['code' => 'SUP-009', 'name' => 'Tidak Ada']))
        === 'Supplier tidak ditemukan.'
    );

    $updated = $supplierService->update($supplierId, [
        'code' => 'SUP-001',
        'name' => 'PT Sumber Komputer',
        'contact_person' => 'Rina Kartika',
        'phone' => '021-5551234',
        'email' => 'sales@sumber.id',
        'address' => 'Jl. Merdeka 1',
        'is_active' => true,
    ]);
    assert($updated['message'] === 'Data supplier berhasil diperbarui.');
    assert($updated['data']['contact_person'] === 'Rina Kartika');
    assert($suppliers->codeExists('SUP-001') === true);
    assert($suppliers->codeExists('SUP-001', $supplierId) === false);
    assert(count($suppliers->all(['search' => 'Sumber'])) === 1);

    assert($supplierService->delete($supplierId)['message'] === 'Supplier berhasil dihapus.');
    assert($suppliers->find($supplierId) === null);
});

// --- 5. Activity log ------------------------------------------------------

$block('[TEST 5] Activity log: ip_address dan deskripsi naratif', static function () use ($sqlite): void {
    $logs = (new ActivityLogRepository($sqlite))->recent(50);
    assert(count($logs) > 0);

    $descriptions = array_column($logs, 'description');

    assert(in_array(
        'Menambahkan karyawan Ahmad Farhan (EMP-001) pada departemen IT',
        $descriptions,
        true
    ));
    assert(in_array('Menambahkan kategori Laptop (CAT-LPT) dengan kategori induk Elektronik', $descriptions, true));
    assert(in_array('Menghapus supplier PT Sumber Komputer (SUP-001)', $descriptions, true));

    $locationUpdate = '';
    foreach ($descriptions as $description) {
        if (str_starts_with($description, 'Memperbarui lokasi Ruang Server IT (LOC-SRV)')) {
            $locationUpdate = $description;
        }
    }
    assert($locationUpdate !== '');
    assert(str_contains($locationUpdate, 'lantai berubah dari Lt 1 menjadi Lt 2'));

    $employeeUpdate = '';
    foreach ($descriptions as $description) {
        if (str_starts_with($description, 'Memperbarui karyawan Ahmad Farhan (EMP-001)')) {
            $employeeUpdate = $description;
        }
    }
    assert(str_contains($employeeUpdate, 'status berubah dari Aktif menjadi Nonaktif'));

    $categoryUpdate = '';
    foreach ($descriptions as $description) {
        if (str_starts_with($description, 'Memperbarui kategori Elektronik & Gadget (CAT-ELK)')) {
            $categoryUpdate = $description;
        }
    }
    assert(str_contains($categoryUpdate, 'nama berubah dari Elektronik menjadi Elektronik & Gadget'));

    // ip_address sengaja tidak dinormalkan: regresi ke null harus langsung ketahuan.
    foreach ($logs as $log) {
        assert($log['ip_address'] === '127.0.0.1');
        assert((int)$log['user_id'] === 1);
        assert($log['user_name'] === 'Admin GA');
    }

    $supplierLog = null;
    foreach ($logs as $log) {
        if ($log['action'] === 'supplier.create') {
            $supplierLog = $log;
        }
    }
    assert($supplierLog !== null);
    assert($supplierLog['subject_type'] === 'supplier');
    assert((int)$supplierLog['subject_id'] > 0);
});

// --- Ringkasan ------------------------------------------------------------

echo "\n";
foreach ($skipped as $note) {
    echo "[SKIPPED] " . $note . "\n";
}

if ($failures > 0) {
    echo "[FAILED] " . $failures . " blok pemeriksaan gagal.\n";
    exit(1);
}

echo "[SUCCESS] Seluruh pemeriksaan master data dan partner lulus tanpa kegagalan.\n";
