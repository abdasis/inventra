<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Loan\CreateLoanAction;
use Inventra\Core\Actions\Loan\ReturnLoanAction;
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
use Inventra\Core\Services\LoanService;

echo "=== Running Inventra Loan Self-Check Tests ===\n\n";

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
$sqlite->exec("INSERT INTO assets (id, asset_code, name, category_id, location_id, purchase_date, purchase_cost, status, condition_level, assigned_to) VALUES
    (1, 'AST-L01', 'Kamera DSLR Canon', 1, 1, '2026-01-05', 9000000, 'available', 'good', NULL),
    (2, 'AST-L02', 'Laptop Beku', 1, 1, '2026-01-05', 11000000, 'in_use', 'good', 'Siti Aminah'),
    (3, 'AST-L03', 'Printer Perbaikan', 1, 1, '2026-01-05', 1500000, 'maintenance', 'damaged', NULL),
    (4, 'AST-L04', 'Scanner Afkir', 1, 1, '2020-01-01', 500000, 'disposed', 'good', NULL),
    (5, 'AST-L05', 'Proyektor Aula', 1, 1, '2026-01-05', 4000000, 'available', 'good', NULL)");
// Aset 2 sudah berada pada pemegang lain: peminjaman atas aset ini harus ditolak.
$sqlite->exec("INSERT INTO asset_assignments (asset_id, employee_id, location_id, assigned_at, condition_out, handed_by_name, received_by_name, status) VALUES
    (2, 2, 1, '2026-04-01 09:00:00', 'good', 'Admin GA', 'Siti Aminah', 'active')");

Connection::setInstance($sqlite);

$assets = new AssetRepository($sqlite);
$employees = new EmployeeRepository($sqlite);
$locations = new LocationRepository($sqlite);
$assignments = new AssetAssignmentRepository($sqlite);
$loans = new AssetLoanRepository($sqlite);
$movements = new MovementRepository($sqlite);
$logRepository = new ActivityLogRepository($sqlite);

$service = new LoanService(
    $loans,
    new CreateLoanAction($assets, $loans, $assignments, $employees, $locations, $movements),
    new ReturnLoanAction($assets, $loans, $locations),
    '127.0.0.1',
);

$kamera = 1;
$beku = 2;
$printer = 3;
$afkir = 4;
$proyektor = 5;
$ahmad = 1;
$locGdg = 1;
$locDev = 2;

// Jatuh tempo relatif terhadap hari ini supaya pemeriksaan keterlambatan tidak
// bergantung pada tanggal tetap yang cepat basi.
$dueFuture = date('Y-m-d', strtotime('+30 days'));
$loanedPast = date('Y-m-d 08:00:00', strtotime('-60 days'));
$duePast = date('Y-m-d', strtotime('-30 days'));

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

$loanId = 0;

$check('1. Peminjaman menandai aset sedang dipinjam', static function () use ($service, $assets, $movements, &$loanId, $kamera, $ahmad, $locDev, $locGdg, $dueFuture): void {
    $result = $service->create([
        'asset_id' => $kamera,
        'employee_id' => $ahmad,
        'location_id' => $locDev,
        'due_at' => $dueFuture,
        'purpose' => 'Proyek audit',
        'notes' => 'Untuk dokumentasi lapangan',
    ], 'Staf GA');

    assert($result['message'] === 'Peminjaman aset berhasil dicatat');
    $row = $result['data'];
    $loanId = (int)$row['id'];

    assert($row['asset_code'] === 'AST-L01');
    assert($row['asset_name'] === 'Kamera DSLR Canon');
    assert((int)$row['employee_id'] === $ahmad);
    assert($row['employee_name'] === 'Ahmad Farhan');
    assert((int)$row['location_id'] === $locDev);
    assert($row['location_name'] === 'Ruang IT');
    assert($row['due_at'] === $dueFuture);
    assert($row['returned_at'] === null);
    assert($row['is_overdue'] === false);
    assert($row['condition_out'] === 'good');
    assert($row['condition_out_label'] === 'Baik (Normal)');
    assert($row['condition_in'] === null);
    assert($row['condition_in_label'] === null);
    assert($row['handed_by_name'] === 'Staf GA');
    assert($row['received_by_name'] === 'Ahmad Farhan');
    assert($row['purpose'] === 'Proyek audit');
    assert($row['status'] === 'active');
    assert($row['status_label'] === 'Aktif');

    // Aset berstatus on_loan dengan pemegang baru, lokasinya tidak dipindahkan.
    $asset = $assets->find($kamera);
    assert($asset['status'] === 'on_loan');
    assert($asset['assigned_to'] === 'Ahmad Farhan');
    assert((int)$asset['location_id'] === $locGdg);

    $history = $movements->forAsset($kamera);
    assert(count($history) === 1);
    assert($history[0]['movement_type'] === 'check_out');
    assert($history[0]['recipient_name'] === 'Ahmad Farhan');
    assert($history[0]['notes'] === 'Peminjaman sementara: Proyek audit');
});

$check('2. Peminjaman kedua atas aset yang sama ditolak', static function () use ($service, $errors, $loans, $kamera, $locDev, $dueFuture): void {
    assert($errors(fn () => $service->create([
        'asset_id' => $kamera,
        'employee_id' => 2,
        'location_id' => $locDev,
        'due_at' => $dueFuture,
    ], 'Staf GA'))['asset_id'] === 'Aset sedang dipinjam dan belum dikembalikan.');

    assert(count($loans->all()) === 1);
    assert($loans->activeForAsset($kamera) !== null);
});

$check('3. Aset perbaikan, terhapus, dan yang masih dipegang ditolak', static function () use ($service, $errors, $printer, $afkir, $beku, $dueFuture): void {
    assert($errors(fn () => $service->create(['asset_id' => $printer, 'employee_id' => 1, 'due_at' => $dueFuture], 'Staf GA'))['asset_id'] === 'Aset sedang dalam perbaikan.');
    assert($errors(fn () => $service->create(['asset_id' => $afkir, 'employee_id' => 1], 'Staf GA'))['asset_id'] === 'Aset yang sudah dihapus tidak dapat dipakai transaksi baru.');
    assert($errors(fn () => $service->create(['asset_id' => $beku, 'employee_id' => 1], 'Staf GA'))['asset_id'] === 'Aset sedang berada pada pemegang lain.');
    assert($errors(fn () => $service->create(['asset_id' => 9999, 'employee_id' => 1], 'Staf GA'))['asset_id'] === 'Aset tidak ditemukan.');
    assert($errors(fn () => $service->create(['asset_id' => $printer, 'employee_id' => 999], 'Staf GA'))['asset_id'] === 'Aset sedang dalam perbaikan.');
});

$check('4. Peminjam, lokasi, dan jatuh tempo tidak valid ditolak', static function () use ($service, $errors, $proyektor, $locDev, $dueFuture): void {
    assert($errors(fn () => $service->create(['asset_id' => $proyektor, 'location_id' => $locDev], 'Staf GA'))['borrower_name'] === 'Nama peminjam wajib diisi.');
    assert($errors(fn () => $service->create(['asset_id' => $proyektor, 'employee_id' => 999, 'location_id' => $locDev], 'Staf GA'))['employee_id'] === 'Karyawan tidak ditemukan.');
    assert($errors(fn () => $service->create(['asset_id' => $proyektor, 'employee_id' => 1, 'location_id' => 999], 'Staf GA'))['location_id'] === 'Lokasi tidak ditemukan.');
    assert($errors(fn () => $service->create(['asset_id' => $proyektor, 'employee_id' => 1, 'due_at' => '31-12-2026'], 'Staf GA'))['due_at'] === 'Jatuh tempo tidak valid.');
    assert($errors(fn () => $service->create(['asset_id' => $proyektor, 'employee_id' => 1, 'loaned_at' => '2026-05-10 09:00:00', 'due_at' => '2026-05-01'], 'Staf GA'))['due_at'] === 'Jatuh tempo tidak boleh lebih awal dari tanggal pinjam.');
    assert($errors(fn () => $service->create(['asset_id' => $proyektor, 'employee_id' => 1, 'due_at' => $dueFuture, 'condition_out' => 'rusak-berat'], 'Staf GA'))['condition_out'] === 'Kondisi aset tidak valid.');
});

$check('5. Peminjaman tanpa karyawan memakai nama peminjam', static function () use ($service, $assets, $proyektor, $locDev): void {
    $result = $service->create([
        'asset_id' => $proyektor,
        'borrower_name' => 'Budi Santoso',
        'purpose' => 'Rapat bulanan',
    ], 'Staf GA');

    $row = $result['data'];
    assert($row['employee_id'] === null);
    assert($row['employee_name'] === null);
    assert($row['received_by_name'] === 'Budi Santoso');
    assert($row['location_id'] === null);
    assert($row['due_at'] === null);

    $asset = $assets->find($proyektor);
    assert($asset['status'] === 'on_loan');
    assert($asset['assigned_to'] === 'Budi Santoso');
});

$check('6. Pengembalian menutup peminjaman dan aset kembali tersedia', static function () use ($service, $loans, $assets, $movements, &$loanId, $kamera, $locGdg): void {
    $result = $service->return($loanId, [
        'return_location_id' => $locGdg,
        'condition_in' => 'fair',
        'notes' => 'Dikembalikan ke gudang',
    ], 'Staf GA');

    assert($result['message'] === 'Pengembalian aset berhasil dicatat');
    $row = $result['data'];
    assert($row['status'] === 'returned');
    assert($row['status_label'] === 'Dikembalikan');
    assert($row['returned_at'] !== null);
    assert($row['condition_in'] === 'fair');
    assert($row['condition_in_label'] === 'Cukup (Ada Aus)');
    assert($row['received_by_name'] === 'Staf GA');
    assert($row['is_overdue'] === false);

    assert($loans->activeForAsset($kamera) === null);

    $asset = $assets->find($kamera);
    assert($asset['status'] === 'available');
    assert($asset['assigned_to'] === null);
    assert((int)$asset['location_id'] === $locGdg);

    $history = $movements->forAsset($kamera);
    assert(count($history) === 2);
    assert($history[0]['movement_type'] === 'check_in');
    assert((int)$history[0]['to_location_id'] === $locGdg);
});

$check('7. Peminjaman yang sudah dikembalikan tidak dapat dikembalikan lagi', static function () use ($service, $rejection, $loans, &$loanId, $locGdg): void {
    assert($rejection(fn () => $service->return($loanId, ['return_location_id' => $locGdg], 'Staf GA')) === 'Peminjaman ini sudah dikembalikan.');
    assert($loans->find($loanId)['returned_at'] !== null);
});

$check('8. is_overdue benar hanya untuk pinjaman aktif yang lewat jatuh tempo', static function () use ($service, $loans, $kamera, $ahmad, $loanedPast, $duePast, $locGdg): void {
    $created = $service->create([
        'asset_id' => $kamera,
        'employee_id' => $ahmad,
        'loaned_at' => $loanedPast,
        'due_at' => $duePast,
        'purpose' => 'Inventarisasi gudang',
    ], 'Staf GA');

    $row = $created['data'];
    $id = (int)$row['id'];
    assert($row['loaned_at'] === $loanedPast);
    assert($row['due_at'] === $duePast);
    assert($row['status'] === 'active');
    assert($row['is_overdue'] === true);

    $overdue = $loans->all(['overdue' => 1]);
    assert(count($overdue) === 1);
    assert((int)$overdue[0]['id'] === $id);
    // Pinjaman aktif tersisa dua: yang terlambat ini dan Proyektor milik Budi.
    assert(count($loans->all(['status' => 'active'])) === 2);

    $returned = $service->return($id, ['return_location_id' => $locGdg, 'condition_in' => 'good'], 'Staf GA');
    assert($returned['data']['is_overdue'] === false);
    assert($loans->all(['overdue' => 1]) === []);
});

$check('9. Baris tidak ada dan lokasi pengembalian tidak valid ditolak lebih dulu', static function () use ($service, $notFound, $errors, $loans, $kamera, $locDev): void {
    assert($notFound(fn () => $service->return(9999, ['return_location_id' => 1], 'Staf GA')) === 'Peminjaman tidak ditemukan.');

    $created = $service->create(['asset_id' => $kamera, 'employee_id' => 1, 'location_id' => $locDev], 'Staf GA');
    $id = (int)$created['data']['id'];

    assert($errors(fn () => $service->return($id, [], 'Staf GA'))['return_location_id'] === 'Lokasi penyimpanan tidak ditemukan.');
    assert($errors(fn () => $service->return($id, ['return_location_id' => 999], 'Staf GA'))['return_location_id'] === 'Lokasi penyimpanan tidak ditemukan.');
    assert($errors(fn () => $service->return($id, ['return_location_id' => 1, 'condition_in' => 'hancur'], 'Staf GA'))['condition_in'] === 'Kondisi aset tidak valid.');

    assert($loans->find($id)['status'] === 'active');
});

$check('10. Activity log naratif lengkap dengan ip_address', static function () use ($logRepository, $dueFuture): void {
    $logs = $logRepository->recent(200);
    assert($logs !== []);

    foreach ($logs as $log) {
        assert($log['ip_address'] === '127.0.0.1');
        assert($log['description'] !== '');
        assert($log['subject_type'] === 'asset_loan');
        // Dijalankan dari CLI tanpa sesi: pelakunya tidak boleh terbaca dari mana pun.
        assert($log['user_name'] === null);
        assert($log['user_id'] === null);
    }

    $descriptions = array_column($logs, 'description');
    assert(in_array('Meminjamkan aset Kamera DSLR Canon (AST-L01) kepada Ahmad Farhan, jatuh tempo ' . $dueFuture, $descriptions, true) === true);
    assert(in_array('Menerima kembali aset Kamera DSLR Canon (AST-L01) dari peminjaman Ahmad Farhan (kondisi: Cukup (Ada Aus))', $descriptions, true) === true);
    assert(in_array('Meminjamkan aset Proyektor Aula (AST-L05) kepada Budi Santoso', $descriptions, true) === true);

    $actions = array_column($logs, 'action');
    foreach (['create', 'return'] as $action) {
        assert(in_array($action, $actions, true) === true);
    }
});

echo "\n[SUCCESS] Seluruh pemeriksaan peminjaman lulus tanpa kegagalan.\n";
