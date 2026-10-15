<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Assignment\CreateAssignmentAction;
use Inventra\Core\Actions\Assignment\ReturnAssignmentAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ActivityLogRepository;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\MovementRepository;
use Inventra\Core\Services\AssignmentService;

echo "=== Running Inventra Assignment Self-Check Tests ===\n\n";

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
    ('LOC-DEV', 'Ruang IT', 'Gedung Utama', 'Lt 2')");
$sqlite->exec("INSERT INTO employees (employee_number, name, department) VALUES
    ('EMP-001', 'Ahmad Farhan', 'IT'),
    ('EMP-002', 'Siti Aminah', 'Finance')");
// Aset diketik langsung supaya kombinasi status (tersedia/dihapus) bisa diuji
// tanpa melewati registrasi aset.
$sqlite->exec("INSERT INTO assets (id, asset_code, name, category_id, location_id, purchase_date, purchase_cost, status, condition_level, assigned_to) VALUES
    (1, 'AST-001', 'Laptop Lenovo ThinkPad E14', 1, 1, '2026-01-05', 12500000, 'available', 'good', NULL),
    (2, 'AST-002', 'Kursi Ergonomis', 1, 2, '2026-02-10', 2500000, 'available', 'fair', NULL),
    (3, 'AST-003', 'Printer Afkir', 1, 1, '2020-01-01', 1500000, 'disposed', 'damaged', NULL)");

Connection::setInstance($sqlite);

$assets = new AssetRepository($sqlite);
$employees = new EmployeeRepository($sqlite);
$locations = new LocationRepository($sqlite);
$assignments = new AssetAssignmentRepository($sqlite);
$loans = new AssetLoanRepository($sqlite);
$movements = new MovementRepository($sqlite);
$logRepository = new ActivityLogRepository($sqlite);

$service = new AssignmentService(
    $assignments,
    new CreateAssignmentAction($assets, $assignments, $loans, $employees, $locations),
    new ReturnAssignmentAction($assets, $assignments, $locations),
    '127.0.0.1',
);

$laptop = 1;
$kursi = 2;
$printer = 3;
$ahmad = 1;
$locGdg = 1;
$locDev = 2;

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

$assignmentId = 0;

$check('1. Penempatan memindahkan aset ke pemegang dan lokasi baru', static function () use ($service, $assets, $movements, &$assignmentId, $laptop, $ahmad, $locDev): void {
    $result = $service->create([
        'asset_id' => $laptop,
        'employee_id' => $ahmad,
        'location_id' => $locDev,
        'assigned_at' => '2026-04-01 09:30:00',
        'condition_out' => 'good',
        'notes' => 'Penempatan ruang IT',
    ], 'Admin GA');

    assert($result['message'] === 'Penempatan aset berhasil dicatat');
    $row = $result['data'];
    $assignmentId = (int)$row['id'];

    assert($row['asset_code'] === 'AST-001');
    assert($row['asset_name'] === 'Laptop Lenovo ThinkPad E14');
    assert((int)$row['employee_id'] === $ahmad);
    assert($row['employee_name'] === 'Ahmad Farhan');
    assert((int)$row['location_id'] === $locDev);
    assert($row['location_name'] === 'Ruang IT');
    assert($row['assigned_at'] === '2026-04-01 09:30:00');
    assert($row['returned_at'] === null);
    assert($row['condition_out'] === 'good');
    assert($row['condition_out_label'] === 'Baik (Normal)');
    assert($row['condition_in'] === null);
    assert($row['condition_in_label'] === null);
    assert($row['handed_by_name'] === 'Admin GA');
    assert($row['received_by_name'] === 'Ahmad Farhan');
    assert($row['status'] === 'active');
    assert($row['status_label'] === 'Aktif');

    // Aset benar-benar berpindah: status, pemegang, dan lokasinya ikut berubah.
    $asset = $assets->find($laptop);
    assert($asset['status'] === 'in_use');
    assert($asset['assigned_to'] === 'Ahmad Farhan');
    assert((int)$asset['location_id'] === $locDev);

    // Histori mutasi tetap lengkap.
    $history = $movements->forAsset($laptop);
    assert(count($history) === 1);
    assert($history[0]['movement_type'] === 'check_out');
    assert($history[0]['recipient_name'] === 'Ahmad Farhan');
    assert((int)$history[0]['to_location_id'] === $locDev);
    assert($history[0]['actor_name'] === 'Admin GA');
});

$check('2. Aset tidak boleh punya dua penempatan aktif', static function () use ($service, $assignments, $errors, $laptop, $locDev): void {
    assert($errors(fn () => $service->create([
        'asset_id' => $laptop,
        'employee_id' => 2,
        'location_id' => $locDev,
    ], 'Admin GA'))['asset_id'] === 'Aset masih berada pada pemegang atau peminjaman lain.');

    assert(count($assignments->all()) === 1);
    assert($assignments->activeForAsset($laptop) !== null);
});

$check('3. Aset terhapus, karyawan, dan lokasi tidak dikenal ditolak', static function () use ($service, $errors, $printer, $kursi, $locDev): void {
    assert($errors(fn () => $service->create(['asset_id' => $printer, 'employee_id' => 1, 'location_id' => $locDev], 'Admin GA'))['asset_id'] === 'Aset yang sudah dihapus tidak dapat dipakai transaksi baru.');
    assert($errors(fn () => $service->create(['asset_id' => 9999, 'location_id' => $locDev], 'Admin GA'))['asset_id'] === 'Aset tidak ditemukan.');
    assert($errors(fn () => $service->create(['asset_id' => $kursi, 'employee_id' => 999, 'location_id' => $locDev], 'Admin GA'))['employee_id'] === 'Karyawan tidak ditemukan.');
    assert($errors(fn () => $service->create(['asset_id' => $kursi, 'employee_id' => 1, 'location_id' => 999], 'Admin GA'))['location_id'] === 'Lokasi tidak ditemukan.');
    assert($errors(fn () => $service->create(['asset_id' => $kursi, 'location_id' => $locDev], 'Admin GA'))['received_by_name'] === 'Nama penerima wajib diisi.');
    assert($errors(fn () => $service->create(['asset_id' => $kursi, 'employee_id' => 1, 'location_id' => $locDev, 'assigned_at' => '01-04-2026'], 'Admin GA'))['assigned_at'] === 'Tanggal serah terima tidak valid.');
    assert($errors(fn () => $service->create(['asset_id' => $kursi, 'employee_id' => 1, 'location_id' => $locDev, 'condition_out' => 'rusak-berat'], 'Admin GA'))['condition_out'] === 'Kondisi aset tidak valid.');
});

$check('4. Tanpa karyawan, nama penerima menjadi pemegang', static function () use ($service, $assignments, $assets, $kursi, $locDev): void {
    $result = $service->create([
        'asset_id' => $kursi,
        'location_id' => $locDev,
        'received_by_name' => 'Budi Santoso',
    ], 'Admin GA');

    $row = $result['data'];
    assert($row['employee_id'] === null);
    assert($row['employee_name'] === null);
    assert($row['received_by_name'] === 'Budi Santoso');
    assert($row['condition_out'] === 'good');

    $asset = $assets->find($kursi);
    assert($asset['assigned_to'] === 'Budi Santoso');
    assert(count($assignments->historyForAsset($kursi)) === 1);
});

$check('5. Pengembalian menutup penempatan dan mengembalikan aset ke lokasi penyimpanan', static function () use ($service, $assignments, $assets, $movements, &$assignmentId, $laptop, $locGdg): void {
    $result = $service->return($assignmentId, [
        'return_location_id' => $locGdg,
        'condition_in' => 'fair',
        'notes' => 'Dikembalikan ke gudang',
    ], 'Admin GA');

    assert($result['message'] === 'Pengembalian aset berhasil dicatat');
    $row = $result['data'];
    assert($row['status'] === 'returned');
    assert($row['status_label'] === 'Dikembalikan');
    assert($row['returned_at'] !== null);
    assert($row['condition_in'] === 'fair');
    assert($row['condition_in_label'] === 'Cukup (Ada Aus)');
    assert($row['received_by_name'] === 'Admin GA');

    // Baris lama tidak dihapus: histori pemegang tetap ada.
    assert(count($assignments->historyForAsset($laptop)) === 1);
    assert($assignments->activeForAsset($laptop) === null);

    $asset = $assets->find($laptop);
    assert($asset['status'] === 'available');
    assert($asset['assigned_to'] === null);
    assert((int)$asset['location_id'] === $locGdg);

    $history = $movements->forAsset($laptop);
    assert(count($history) === 2);
    assert($history[0]['movement_type'] === 'check_in');
    assert($history[0]['recipient_name'] === 'Ahmad Farhan');
    assert((int)$history[0]['to_location_id'] === $locGdg);
});

$check('6. Penempatan yang sudah dikembalikan tidak dapat dikembalikan lagi', static function () use ($service, $rejection, $assignments, &$assignmentId, $locGdg): void {
    assert($rejection(fn () => $service->return($assignmentId, ['return_location_id' => $locGdg], 'Admin GA')) === 'Penempatan ini sudah dikembalikan.');
    assert($assignments->find($assignmentId)['returned_at'] !== null);
});

$check('7. Baris tidak ada dan lokasi pengembalian tidak valid ditolak lebih dulu', static function () use ($service, $notFound, $errors, $assignments, $laptop, $locDev): void {
    assert($notFound(fn () => $service->return(9999, ['return_location_id' => 1], 'Admin GA')) === 'Penempatan tidak ditemukan.');

    // Penempatan baru supaya pengembalian bisa diuji tanpa menutup baris lain.
    $created = $service->create(['asset_id' => $laptop, 'employee_id' => 1, 'location_id' => $locDev], 'Admin GA');
    $id = (int)$created['data']['id'];

    assert($errors(fn () => $service->return($id, [], 'Admin GA'))['return_location_id'] === 'Lokasi penyimpanan tidak ditemukan.');
    assert($errors(fn () => $service->return($id, ['return_location_id' => 999], 'Admin GA'))['return_location_id'] === 'Lokasi penyimpanan tidak ditemukan.');
    assert($errors(fn () => $service->return($id, ['return_location_id' => 1, 'condition_in' => 'hancur'], 'Admin GA'))['condition_in'] === 'Kondisi aset tidak valid.');

    // Penolakan tidak menyentuh barisnya.
    assert($assignments->find($id)['status'] === 'active');
});

$check('8. Activity log naratif lengkap dengan ip_address', static function () use ($logRepository): void {
    $logs = $logRepository->recent(200);
    assert($logs !== []);

    foreach ($logs as $log) {
        assert($log['ip_address'] === '127.0.0.1');
        assert($log['description'] !== '');
        assert($log['subject_type'] === 'asset_assignment');
        // Dijalankan dari CLI tanpa sesi: pelakunya tidak boleh terbaca dari mana pun.
        assert($log['user_name'] === null);
        assert($log['user_id'] === null);
    }

    $descriptions = array_column($logs, 'description');
    assert(in_array('Menempatkan aset Laptop Lenovo ThinkPad E14 (AST-001) kepada Ahmad Farhan di Ruang IT', $descriptions, true) === true);
    assert(in_array('Menempatkan aset Kursi Ergonomis (AST-002) kepada Budi Santoso di Ruang IT', $descriptions, true) === true);
    assert(in_array('Menerima kembali aset Laptop Lenovo ThinkPad E14 (AST-001) dari Ahmad Farhan (kondisi: Cukup (Ada Aus))', $descriptions, true) === true);

    $actions = array_column($logs, 'action');
    foreach (['create', 'return'] as $action) {
        assert(in_array($action, $actions, true) === true);
    }
});

echo "\n[SUCCESS] Seluruh pemeriksaan penempatan lulus tanpa kegagalan.\n";
