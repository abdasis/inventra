<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Assignment\CreateAssignmentAction;
use Inventra\Core\Actions\Disposal\CreateDisposalAction;
use Inventra\Core\Actions\Loan\CreateLoanAction;
use Inventra\Core\Actions\Maintenance\CreateMaintenanceAction;
use Inventra\Core\Actions\Transfer\CreateTransferAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ActivityLogRepository;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Repositories\AssetDisposalRepository;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Repositories\AssetMaintenanceRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\AssetTransferRepository;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\MovementRepository;
use Inventra\Core\Services\DisposalService;

echo "=== Running Inventra Asset Disposal Self-Check Tests ===\n\n";

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
    (4, 'AST-004', 'Laptop Bekas', 1, 1, '2021-03-01', 8000000, 'available', 'fair', NULL),
    (5, 'AST-005', 'Meja Lipat', 1, 1, '2022-01-15', 750000, 'available', 'good', NULL),
    (6, 'AST-006', 'Whiteboard', 1, 1, '2023-02-20', 900000, 'available', 'good', NULL)");

Connection::setInstance($sqlite);

$assets = new AssetRepository($sqlite);
$disposals = new AssetDisposalRepository($sqlite);
$assignments = new AssetAssignmentRepository($sqlite);
$loans = new AssetLoanRepository($sqlite);
$maintenances = new AssetMaintenanceRepository($sqlite);
$transfers = new AssetTransferRepository($sqlite);
$employees = new EmployeeRepository($sqlite);
$locations = new LocationRepository($sqlite);
$movements = new MovementRepository($sqlite);
$logRepository = new ActivityLogRepository($sqlite);

$service = new DisposalService(
    $disposals,
    new CreateDisposalAction($assets, $disposals, $assignments, $loans, $movements),
    '127.0.0.1',
);

// Transaksi lain disiapkan lewat Action aslinya supaya penolakan atas aset yang
// sudah dihapus diuji atas keadaan yang benar-benar bisa terjadi.
$createAssignment = new CreateAssignmentAction($assets, $assignments, $loans, $employees, $locations);
$createLoan = new CreateLoanAction($assets, $loans, $assignments, $employees, $locations, $movements);
$createMaintenance = new CreateMaintenanceAction($assets, $maintenances, $assignments, $loans, $movements);
$createTransfer = new CreateTransferAction($assets, $transfers, $locations);

$proyektor = 1;
$kursi = 2;
$printer = 3;
$laptop = 4;
$meja = 5;
$whiteboard = 6;
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

$disposalId = 0;

$check('1. Penghapusan menandai aset disposed dan mencatat pergerakan', static function () use ($service, $assets, $movements, &$disposalId, $proyektor, $locIt): void {
    $result = $service->create([
        'asset_id' => $proyektor,
        'disposal_date' => '2026-05-01',
        'reason' => 'Rusak berat dan tidak ekonomis diperbaiki',
        'method' => 'destroyed',
        'book_value' => 0,
        'document_reference' => 'BAP-2026-011',
        'approved_by_name' => 'Kepala Bagian Umum',
        'notes' => 'Dimusnahkan di gudang',
    ], 'Admin GA');

    assert($result['message'] === 'Penghapusan aset berhasil dicatat');
    $row = $result['data'];
    $disposalId = (int)$row['id'];

    assert($row['asset_code'] === 'AST-001');
    assert($row['asset_name'] === 'Proyektor Uji');
    assert($row['category_name'] === 'IT & Elektronik');
    assert($row['disposal_date'] === '2026-05-01');
    assert($row['reason'] === 'Rusak berat dan tidak ekonomis diperbaiki');
    assert($row['method'] === 'destroyed');
    assert($row['method_label'] === 'Dimusnahkan');
    assert((float)$row['book_value'] === 0.0);
    assert($row['book_value_formatted'] === 'Rp 0');
    assert($row['document_reference'] === 'BAP-2026-011');
    assert($row['approved_by_name'] === 'Kepala Bagian Umum');
    assert($row['executed_by_name'] === 'Admin GA');

    // Asetnya tetap ada sebagai histori, hanya statusnya berpindah ke disposed
    // dan pemegangnya dilepas.
    $asset = $assets->find($proyektor);
    assert($asset['status'] === 'disposed');
    assert($asset['assigned_to'] === null);
    assert((int)$asset['location_id'] === $locIt);

    $history = $movements->forAsset($proyektor);
    assert(count($history) === 1);
    assert($history[0]['movement_type'] === 'dispose');
    assert($history[0]['actor_name'] === 'Admin GA');
    assert($history[0]['notes'] === 'Alasan: Rusak berat dan tidak ekonomis diperbaiki');
});

$check('2. Aset yang sudah dihapus ditolak pada transaksi baru', static function () use ($errors, $createAssignment, $createLoan, $createMaintenance, $createTransfer, $proyektor, $locGdg): void {
    assert($errors(fn () => $createAssignment->execute([
        'asset_id' => $proyektor,
        'location_id' => $locGdg,
        'received_by_name' => 'Budi Santoso',
    ], 'Admin GA'))['asset_id'] === 'Aset yang sudah dihapus tidak dapat dipakai transaksi baru.');

    assert($errors(fn () => $createLoan->execute([
        'asset_id' => $proyektor,
        'borrower_name' => 'Budi Santoso',
    ], 'Admin GA'))['asset_id'] === 'Aset yang sudah dihapus tidak dapat dipakai transaksi baru.');

    assert($errors(fn () => $createMaintenance->execute([
        'asset_id' => $proyektor,
        'maintenance_date' => '2026-05-02',
    ], 'Admin GA'))['asset_id'] === 'Aset yang sudah dihapus tidak dapat dipelihara.');

    assert($errors(fn () => $createTransfer->execute([
        'asset_id' => $proyektor,
        'to_location_id' => $locGdg,
    ], 'Admin GA'))['asset_id'] === 'Aset yang sudah dihapus tidak dapat dimutasi.');
});

$check('3. Penghapusan kedua atas aset yang sama ditolak sebelum menyentuh tabel', static function () use ($service, $errors, $disposals, $proyektor, $printer): void {
    // Proyektor sudah berstatus disposed, printer sudah begitu sejak awal.
    assert($errors(fn () => $service->create(['asset_id' => $proyektor, 'reason' => 'Dihapus lagi'], 'Admin GA'))['asset_id'] === 'Aset ini sudah dihapus sebelumnya.');
    assert($errors(fn () => $service->create(['asset_id' => $printer, 'reason' => 'Dihapus lagi'], 'Admin GA'))['asset_id'] === 'Aset ini sudah dihapus sebelumnya.');

    assert(count($disposals->all()) === 1);
});

$check('4. Baris penghapusan yang sudah ada tetap menolak walau statusnya belum disposed', static function () use ($service, $errors, $disposals, $meja): void {
    // Pemeriksaan baris dilakukan lebih dulu supaya UNIQUE asset_id tidak pernah
    // muncul sebagai error SQL yang membingungkan.
    $disposals->create([
        'asset_id' => $meja,
        'disposal_date' => '2026-04-01',
        'reason' => 'Tercatat ganda',
        'executed_by_name' => 'Admin GA',
    ]);

    assert($errors(fn () => $service->create(['asset_id' => $meja, 'reason' => 'Dihapus lagi'], 'Admin GA'))['asset_id'] === 'Aset ini sudah dihapus sebelumnya.');
    assert(count($disposals->all()) === 2);
});

$check('5. Aset yang masih dipinjam atau dipelihara ditolak', static function () use ($service, $errors, $createLoan, $createMaintenance, $assets, $disposals, $kursi, $laptop, $ahmad): void {
    $createLoan->execute([
        'asset_id' => $kursi,
        'employee_id' => $ahmad,
        'loaned_at' => '2026-04-02 08:00:00',
    ], 'Admin GA');

    assert($errors(fn () => $service->create(['asset_id' => $kursi, 'reason' => 'Dihapus'], 'Admin GA'))['asset_id'] === 'Tarik kembali aset dan selesaikan pemeliharaan sebelum menghapusnya.');

    $createMaintenance->execute([
        'asset_id' => $laptop,
        'maintenance_date' => '2026-04-03',
    ], 'Admin GA');

    assert($errors(fn () => $service->create(['asset_id' => $laptop, 'reason' => 'Dihapus'], 'Admin GA'))['asset_id'] === 'Tarik kembali aset dan selesaikan pemeliharaan sebelum menghapusnya.');

    // Penolakan tidak menyentuh aset maupun menambah baris penghapusan.
    assert(count($disposals->all()) === 2);
    assert($assets->find($kursi)['status'] === 'on_loan');
    assert($assets->find($laptop)['status'] === 'maintenance');
});

$check('6. Alasan, tanggal, metode, dan nilai buku tidak valid ditolak', static function () use ($service, $errors, $disposals, $whiteboard): void {
    assert($errors(fn () => $service->create(['asset_id' => $whiteboard, 'method' => 'destroyed'], 'Admin GA'))['reason'] === 'Alasan penghapusan wajib diisi.');
    assert($errors(fn () => $service->create(['asset_id' => $whiteboard, 'reason' => 'Rusak', 'method' => 'dibakar'], 'Admin GA'))['method'] === 'Metode penghapusan tidak valid.');
    assert($errors(fn () => $service->create(['asset_id' => $whiteboard, 'reason' => 'Rusak', 'disposal_date' => '01-05-2026'], 'Admin GA'))['disposal_date'] === 'Tanggal penghapusan tidak valid.');
    assert($errors(fn () => $service->create(['asset_id' => $whiteboard, 'reason' => 'Rusak', 'book_value' => -1], 'Admin GA'))['book_value'] === 'Nilai buku tidak boleh negatif.');
    assert($errors(fn () => $service->create(['asset_id' => $whiteboard, 'reason' => 'Rusak', 'book_value' => 'gratis'], 'Admin GA'))['book_value'] === 'Nilai buku tidak valid.');
    assert($errors(fn () => $service->create(['asset_id' => 9999, 'reason' => 'Rusak'], 'Admin GA'))['asset_id'] === 'Aset tidak ditemukan.');

    assert(count($disposals->all()) === 2);
});

$check('7. Penghapusan dengan data minimum memakai nilai bawaan', static function () use ($service, $assets, $disposals, $whiteboard): void {
    $result = $service->create([
        'asset_id' => $whiteboard,
        'reason' => 'Tidak terpakai lagi',
    ], 'Admin GA');

    $row = $result['data'];
    assert($row['disposal_date'] === date('Y-m-d'));
    assert($row['method'] === 'written_off');
    assert($row['method_label'] === 'Dihapusbukukan');
    assert($row['book_value'] === null);
    assert($row['book_value_formatted'] === null);
    assert($row['approved_by_name'] === null);

    assert($assets->find($whiteboard)['status'] === 'disposed');
    assert(count($disposals->all()) === 3);
});

$check('8. Detail dan pencarian baris penghapusan per aset', static function () use ($service, $disposals, $notFound, &$disposalId, $proyektor, $kursi): void {
    assert((int)$service->detail($disposalId)['data']['id'] === $disposalId);
    assert($notFound(fn () => $service->detail(9999)) === 'Penghapusan tidak ditemukan.');

    assert($disposals->findByAsset($proyektor) !== null);
    assert($disposals->findByAsset($kursi) === null);
    assert($disposals->find($disposals->findByAsset($proyektor)['id'])['asset_code'] === 'AST-001');
});

$check('9. Activity log naratif lengkap dengan ip_address', static function () use ($logRepository): void {
    $logs = $logRepository->recent(200);
    assert($logs !== []);

    foreach ($logs as $log) {
        assert($log['ip_address'] === '127.0.0.1');
        assert($log['description'] !== '');
        assert($log['subject_type'] === 'asset_disposal');
        // Dijalankan dari CLI tanpa sesi: pelakunya tidak boleh terbaca dari mana pun.
        assert($log['user_name'] === null);
        assert($log['user_id'] === null);
    }

    $descriptions = array_column($logs, 'description');
    assert(in_array('Menghapus aset Proyektor Uji (AST-001) dengan metode Dimusnahkan (alasan: Rusak berat dan tidak ekonomis diperbaiki)', $descriptions, true) === true);
    assert(in_array('Menghapus aset Whiteboard (AST-006) dengan metode Dihapusbukukan (alasan: Tidak terpakai lagi)', $descriptions, true) === true);

    $actions = array_column($logs, 'action');
    assert(in_array('delete', $actions, true) === true);
});

echo "\n[SUCCESS] Seluruh pemeriksaan penghapusan aset lulus tanpa kegagalan.\n";
