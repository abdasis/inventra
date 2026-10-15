<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Loan\CreateLoanAction;
use Inventra\Core\Actions\Maintenance\CompleteMaintenanceAction;
use Inventra\Core\Actions\Maintenance\CreateMaintenanceAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ActivityLogRepository;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Repositories\AssetMaintenanceRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\MovementRepository;
use Inventra\Core\Services\MaintenanceService;

echo "=== Running Inventra Asset Maintenance Self-Check Tests ===\n\n";

// Seluruh berkas test ini memakai assert(); kalau zend.assertions dimatikan
// pemeriksaannya hilang tanpa suara, jadi kegagalannya dibuat eksplisit.
if (ini_get('zend.assertions') !== '1') {
    echo "[-] zend.assertions tidak aktif. Jalankan dengan -d zend.assertions=1.\n";
    exit(1);
}

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$sqlite->exec((string)file_get_contents(__DIR__ . '/support/schema.sqlite.sql'));
$sqlite->exec("INSERT INTO categories (code, name) VALUES ('CAT-IT', 'IT & Elektronik')");
$sqlite->exec("INSERT INTO locations (code, name, building, floor) VALUES
    ('LOC-GDG', 'Gudang Logistik Pusat', 'Gedung Annex', 'Lt 1'),
    ('LOC-IT', 'Ruang IT', 'Gedung Utama', 'Lt 2')");
$sqlite->exec("INSERT INTO employees (employee_number, name, department) VALUES ('EMP-001', 'Ahmad Farhan', 'IT')");
// Aset diketik langsung supaya kombinasi lokasi dan status (tersedia/dihapus)
// bisa diuji tanpa melewati registrasi aset.
$sqlite->exec("INSERT INTO assets (id, asset_code, name, category_id, location_id, purchase_date, purchase_cost, status, condition_level, assigned_to) VALUES
    (1, 'AST-001', 'Proyektor Uji', 1, 2, '2026-01-05', 12500000, 'available', 'good', NULL),
    (2, 'AST-002', 'Kursi Ergonomis', 1, 1, '2026-02-10', 2500000, 'available', 'fair', NULL),
    (3, 'AST-003', 'Printer Afkir', 1, 1, '2020-01-01', 1500000, 'disposed', 'damaged', NULL),
    (4, 'AST-004', 'Laptop Bekas', 1, 1, '2021-03-01', 8000000, 'available', 'fair', NULL)");

Connection::setInstance($sqlite);

$assets = new AssetRepository($sqlite);
$maintenances = new AssetMaintenanceRepository($sqlite);
$assignments = new AssetAssignmentRepository($sqlite);
$loans = new AssetLoanRepository($sqlite);
$employees = new EmployeeRepository($sqlite);
$locations = new LocationRepository($sqlite);
$movements = new MovementRepository($sqlite);
$logRepository = new ActivityLogRepository($sqlite);

$service = new MaintenanceService(
    $maintenances,
    new CreateMaintenanceAction($assets, $maintenances, $assignments, $loans, $movements),
    new CompleteMaintenanceAction($maintenances, $assets, $movements),
    '127.0.0.1',
);

// Peminjaman disiapkan lewat Action aslinya supaya penolakan "aset sedang
// dipinjam" diuji atas keadaan yang benar-benar bisa terjadi.
$createLoan = new CreateLoanAction($assets, $loans, $assignments, $employees, $locations, $movements);

$proyektor = 1;
$kursi = 2;
$printer = 3;
$laptop = 4;
$ahmad = 1;
$locGdg = 1;
$locIt = 2;

/** Menjalankan satu blok pemeriksaan; berhenti dengan exit(1) pada kegagalan pertama. */
$check = static function (string $label, callable $body): void {
    echo $label . '... ';
    try {
        $body();
    } catch (Throwable $e) {
        echo "FAILED\n";
        echo '  ' . $e::class . ': ' . $e->getMessage() . "\n";
        exit(1);
    }
    echo "PASSED\n";
};

/** Pesan ValidationException, atau penanda bila ternyata tidak ditolak. */
$rejection = static function (callable $action): string {
    try {
        $action();
    } catch (ValidationException $e) {
        return $e->getMessage();
    }

    return '<tidak-ditolak>';
};

/** Peta error per field dari ValidationException, atau array kosong bila lolos. */
$errors = static function (callable $action): array {
    try {
        $action();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
};

/** Pesan NotFoundException, atau penanda bila barisnya ternyata masih ada. */
$notFound = static function (callable $action): string {
    try {
        $action();
    } catch (NotFoundException $e) {
        return $e->getMessage();
    }

    return '<ditemukan>';
};

$maintenanceId = 0;

$check('1. Mencatat pemeliharaan menandai aset berstatus perbaikan', static function () use ($service, $assets, $movements, &$maintenanceId, $proyektor, $locIt): void {
    $result = $service->create([
        'asset_id' => $proyektor,
        'maintenance_type' => 'preventive',
        'maintenance_date' => '2026-04-10',
        'vendor' => 'CV Servis Elektronik',
        'cost' => 350000,
        'damage_description' => 'Lampu proyektor mulai redup',
        'next_schedule' => '2026-10-10',
        'notes' => 'Dikerjakan di tempat',
    ], 'Admin GA');

    assert($result['message'] === 'Pemeliharaan aset berhasil dicatat');
    $row = $result['data'];
    $maintenanceId = (int)$row['id'];

    assert($row['asset_code'] === 'AST-001');
    assert($row['asset_name'] === 'Proyektor Uji');
    assert($row['maintenance_type'] === 'preventive');
    assert($row['maintenance_type_label'] === 'Preventif');
    assert($row['maintenance_date'] === '2026-04-10');
    assert($row['vendor'] === 'CV Servis Elektronik');
    assert((float)$row['cost'] === 350000.0);
    assert($row['cost_formatted'] === 'Rp 350.000');
    assert($row['damage_description'] === 'Lampu proyektor mulai redup');
    assert($row['work_result'] === null);
    assert($row['next_schedule'] === '2026-10-10');
    assert($row['status'] === 'ongoing');
    assert($row['status_label'] === 'Sedang Dikerjakan');
    assert($row['performed_by_name'] === 'Admin GA');
    assert($row['completed_at'] === null);

    // Asetnya ditandai perbaikan tanpa berpindah lokasi maupun pemegang.
    $asset = $assets->find($proyektor);
    assert($asset['status'] === 'maintenance');
    assert((int)$asset['location_id'] === $locIt);
    assert($asset['assigned_to'] === null);

    $history = $movements->forAsset($proyektor);
    assert(count($history) === 1);
    assert($history[0]['movement_type'] === 'repair');
    assert($history[0]['recipient_name'] === 'CV Servis Elektronik');
    assert($history[0]['actor_name'] === 'Admin GA');
});

$check('2. Pemeliharaan kedua ditolak selama yang pertama belum selesai', static function () use ($service, $errors, $maintenances, $proyektor): void {
    assert($errors(fn () => $service->create([
        'asset_id' => $proyektor,
        'maintenance_date' => '2026-04-11',
    ], 'Admin GA'))['asset_id'] === 'Masih ada pemeliharaan yang belum selesai untuk aset ini.');

    assert(count($maintenances->all()) === 1);
});

$check('3. Aset terhapus dan aset yang sedang dipinjam ditolak', static function () use ($service, $errors, $createLoan, $printer, $kursi, $ahmad): void {
    assert($errors(fn () => $service->create(['asset_id' => $printer, 'maintenance_date' => '2026-04-11'], 'Admin GA'))['asset_id'] === 'Aset yang sudah dihapus tidak dapat dipelihara.');
    assert($errors(fn () => $service->create(['asset_id' => 9999, 'maintenance_date' => '2026-04-11'], 'Admin GA'))['asset_id'] === 'Aset tidak ditemukan.');

    $createLoan->execute([
        'asset_id' => $kursi,
        'employee_id' => $ahmad,
        'loaned_at' => '2026-04-02 08:00:00',
    ], 'Admin GA');

    assert($errors(fn () => $service->create(['asset_id' => $kursi, 'maintenance_date' => '2026-04-11'], 'Admin GA'))['asset_id'] === 'Tarik kembali aset dari pemegang sebelum memeliharanya.');
});

$check('4. Jenis, tanggal, biaya, dan jadwal tidak valid ditolak', static function () use ($service, $errors, $maintenances, $laptop): void {
    assert($errors(fn () => $service->create(['asset_id' => $laptop, 'maintenance_date' => '2026-04-11', 'maintenance_type' => 'rutin'], 'Admin GA'))['maintenance_type'] === 'Jenis pemeliharaan tidak valid.');
    assert($errors(fn () => $service->create(['asset_id' => $laptop], 'Admin GA'))['maintenance_date'] === 'Tanggal pemeliharaan wajib diisi.');
    assert($errors(fn () => $service->create(['asset_id' => $laptop, 'maintenance_date' => '11-04-2026'], 'Admin GA'))['maintenance_date'] === 'Tanggal pemeliharaan tidak valid.');
    assert($errors(fn () => $service->create(['asset_id' => $laptop, 'maintenance_date' => '2026-04-11', 'cost' => -1], 'Admin GA'))['cost'] === 'Biaya pemeliharaan tidak boleh negatif.');
    assert($errors(fn () => $service->create(['asset_id' => $laptop, 'maintenance_date' => '2026-04-11', 'cost' => 'mahal'], 'Admin GA'))['cost'] === 'Biaya pemeliharaan tidak valid.');
    assert($errors(fn () => $service->create(['asset_id' => $laptop, 'maintenance_date' => '2026-04-11', 'next_schedule' => 'besok'], 'Admin GA'))['next_schedule'] === 'Jadwal berikutnya tidak valid.');

    // Penolakan tidak menambah baris maupun mengubah status aset.
    assert(count($maintenances->all()) === 1);
});

$check('5. Penyelesaian mengembalikan aset ke tersedia dan menyimpan hasilnya', static function () use ($service, $assets, $maintenances, $movements, $errors, &$maintenanceId, $proyektor, $locIt): void {
    assert($errors(fn () => $service->complete($maintenanceId, [], 'Admin GA'))['work_result'] === 'Hasil pekerjaan wajib diisi.');
    assert($errors(fn () => $service->complete($maintenanceId, ['work_result' => 'Selesai', 'cost' => -5], 'Admin GA'))['cost'] === 'Biaya pemeliharaan tidak boleh negatif.');
    assert($errors(fn () => $service->complete($maintenanceId, ['work_result' => 'Selesai', 'next_schedule' => 'lusa'], 'Admin GA'))['next_schedule'] === 'Jadwal berikutnya tidak valid.');
    assert($maintenances->find($maintenanceId)['status'] === 'ongoing');

    $result = $service->complete($maintenanceId, [
        'work_result' => 'Modul daya diganti',
        'cost' => 425000,
        'next_schedule' => '2027-04-10',
    ], 'Admin GA');

    assert($result['message'] === 'Pemeliharaan aset berhasil diselesaikan');
    $row = $result['data'];
    assert($row['status'] === 'completed');
    assert($row['status_label'] === 'Selesai');
    assert($row['work_result'] === 'Modul daya diganti');
    assert((float)$row['cost'] === 425000.0);
    assert($row['cost_formatted'] === 'Rp 425.000');
    assert($row['next_schedule'] === '2027-04-10');
    assert($row['completed_at'] !== null);

    $asset = $assets->find($proyektor);
    assert($asset['status'] === 'available');
    assert($asset['assigned_to'] === null);
    assert((int)$asset['location_id'] === $locIt);
    assert($maintenances->ongoingForAsset($proyektor) === null);

    $history = $movements->forAsset($proyektor);
    assert(count($history) === 2);
    assert($history[0]['movement_type'] === 'check_in');
    assert($history[0]['notes'] === 'Selesai pemeliharaan: Modul daya diganti');
    assert($history[0]['actor_name'] === 'Admin GA');
});

$check('6. Pemeliharaan yang sudah ditutup tidak dapat ditutup lagi', static function () use ($service, $rejection, $notFound, &$maintenanceId, $proyektor): void {
    assert($rejection(fn () => $service->complete($maintenanceId, ['work_result' => 'Ulang'], 'Admin GA')) === 'Pemeliharaan ini sudah selesai atau dibatalkan.');
    assert($notFound(fn () => $service->complete(9999, ['work_result' => 'Ulang'], 'Admin GA')) === 'Pemeliharaan tidak ditemukan.');

    // Yang tertutup tidak menghalangi pemeliharaan berikutnya atas aset yang sama,
    // dan data minimumnya jatuh ke nilai bawaan.
    $created = $service->create([
        'asset_id' => $proyektor,
        'maintenance_date' => '2026-05-01',
    ], 'Admin GA');

    assert($created['data']['maintenance_type'] === 'corrective');
    assert($created['data']['maintenance_type_label'] === 'Korektif');
    assert((float)$created['data']['cost'] === 0.0);
    assert($created['data']['cost_formatted'] === 'Rp 0');
    assert($created['data']['status'] === 'ongoing');
});

$check('7. Activity log naratif lengkap dengan ip_address', static function () use ($logRepository): void {
    $logs = $logRepository->recent(200);
    assert($logs !== []);

    foreach ($logs as $log) {
        assert($log['ip_address'] === '127.0.0.1');
        assert($log['description'] !== '');
        assert($log['subject_type'] === 'asset_maintenance');
        // Dijalankan dari CLI tanpa sesi: pelakunya tidak boleh terbaca dari mana pun.
        assert($log['user_name'] === null);
        assert($log['user_id'] === null);
    }

    $descriptions = array_column($logs, 'description');
    assert(in_array('Memulai pemeliharaan aset Proyektor Uji (AST-001) (Preventif)', $descriptions, true) === true);
    assert(in_array('Menutup pemeliharaan aset Proyektor Uji (AST-001) (hasil: Modul daya diganti)', $descriptions, true) === true);

    $actions = array_column($logs, 'action');
    foreach (['create', 'complete'] as $action) {
        assert(in_array($action, $actions, true) === true);
    }
});

echo "\n[SUCCESS] Seluruh pemeriksaan pemeliharaan aset lulus tanpa kegagalan.\n";
