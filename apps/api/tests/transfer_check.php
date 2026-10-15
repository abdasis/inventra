<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Transfer\CancelTransferAction;
use Inventra\Core\Actions\Transfer\CompleteTransferAction;
use Inventra\Core\Actions\Transfer\CreateTransferAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ActivityLogRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\AssetTransferRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\MovementRepository;
use Inventra\Core\Services\TransferService;

echo "=== Running Inventra Asset Transfer Self-Check Tests ===\n\n";

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
// Aset diketik langsung supaya kombinasi lokasi dan status (tersedia/dihapus)
// bisa diuji tanpa melewati registrasi aset.
$sqlite->exec("INSERT INTO assets (id, asset_code, name, category_id, location_id, purchase_date, purchase_cost, status, condition_level, assigned_to) VALUES
    (1, 'AST-001', 'Proyektor Uji', 1, 2, '2026-01-05', 12500000, 'available', 'good', NULL),
    (2, 'AST-002', 'Kursi Ergonomis', 1, 1, '2026-02-10', 2500000, 'available', 'fair', NULL),
    (3, 'AST-003', 'Printer Afkir', 1, 1, '2020-01-01', 1500000, 'disposed', 'damaged', NULL)");

Connection::setInstance($sqlite);

$assets = new AssetRepository($sqlite);
$transfers = new AssetTransferRepository($sqlite);
$locations = new LocationRepository($sqlite);
$movements = new MovementRepository($sqlite);
$logRepository = new ActivityLogRepository($sqlite);

$service = new TransferService(
    $transfers,
    new CreateTransferAction($assets, $transfers, $locations),
    new CompleteTransferAction($assets, $transfers, $movements),
    new CancelTransferAction($transfers),
    '127.0.0.1',
);

$proyektor = 1;
$kursi = 2;
$printer = 3;
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

$transferId = 0;

$check('1. Mencatat mutasi tidak mengubah lokasi aktif aset', static function () use ($service, $assets, $transfers, &$transferId, $proyektor, $locGdg, $locIt): void {
    $result = $service->create([
        'asset_id' => $proyektor,
        'to_location_id' => $locGdg,
        'transfer_date' => '2026-04-05',
        'reason' => 'Penataan ulang ruang',
        'to_holder_name' => 'Budi Santoso',
        'notes' => 'Menunggu jadwal angkut',
    ], 'Admin GA');

    assert($result['message'] === 'Mutasi aset berhasil dicatat');
    $row = $result['data'];
    $transferId = (int)$row['id'];

    assert($row['asset_code'] === 'AST-001');
    assert($row['asset_name'] === 'Proyektor Uji');
    assert((int)$row['from_location_id'] === $locIt);
    assert($row['from_location_name'] === 'Ruang IT');
    assert((int)$row['to_location_id'] === $locGdg);
    assert($row['to_location_name'] === 'Gudang Logistik Pusat');
    assert($row['from_holder_name'] === null);
    assert($row['to_holder_name'] === 'Budi Santoso');
    assert($row['transfer_date'] === '2026-04-05');
    assert($row['reason'] === 'Penataan ulang ruang');
    assert($row['status'] === 'pending');
    assert($row['status_label'] === 'Menunggu');
    assert($row['executed_by_name'] === 'Admin GA');
    assert($row['completed_at'] === null);
    assert($row['cancelled_at'] === null);
    assert($row['cancel_reason'] === null);

    // Invarian utama: mutasi yang masih pending sama sekali tidak menyentuh aset.
    $asset = $assets->find($proyektor);
    assert((int)$asset['location_id'] === $locIt);
    assert($asset['status'] === 'available');
    assert($asset['assigned_to'] === null);
    assert($transfers->pendingForAsset($proyektor) !== null);
});

$check('2. Mutasi kedua ditolak selama mutasi pertama belum diselesaikan', static function () use ($service, $errors, $transfers, $proyektor, $locIt): void {
    assert($errors(fn () => $service->create([
        'asset_id' => $proyektor,
        'to_location_id' => $locIt,
        'reason' => 'Mutasi tandingan',
    ], 'Admin GA'))['asset_id'] === 'Masih ada mutasi yang belum diselesaikan untuk aset ini.');

    assert(count($transfers->all()) === 1);
});

$check('3. Tujuan sama, aset terhapus, dan tujuan tidak dikenal ditolak', static function () use ($service, $errors, $transfers, $proyektor, $kursi, $printer, $locGdg): void {
    // Kursi berada di gudang, jadi tujuan yang sama persis harus ditolak.
    assert($errors(fn () => $service->create(['asset_id' => $kursi, 'to_location_id' => $locGdg], 'Admin GA'))['to_location_id'] === 'Lokasi tujuan sama dengan lokasi aset saat ini.');
    assert($errors(fn () => $service->create(['asset_id' => $kursi, 'to_location_id' => 999], 'Admin GA'))['to_location_id'] === 'Lokasi tujuan tidak ditemukan.');
    assert($errors(fn () => $service->create(['asset_id' => 9999, 'to_location_id' => $locGdg], 'Admin GA'))['asset_id'] === 'Aset tidak ditemukan.');
    assert($errors(fn () => $service->create(['asset_id' => $printer, 'to_location_id' => $locGdg], 'Admin GA'))['asset_id'] === 'Aset yang sudah dihapus tidak dapat dimutasi.');
    assert($errors(fn () => $service->create(['asset_id' => $kursi, 'to_location_id' => $locGdg, 'transfer_date' => '05-04-2026'], 'Admin GA'))['transfer_date'] === 'Tanggal mutasi tidak valid.');

    // Penolakan tidak menyentuh aset maupun menambah baris mutasi.
    assert(count($transfers->all()) === 1);
    assert($transfers->pendingForAsset($proyektor) !== null);
});

$check('4. Penyelesaian memindahkan lokasi dan mencatat pergerakan relocate', static function () use ($service, $assets, $movements, &$transferId, $proyektor, $locGdg, $locIt): void {
    $result = $service->complete($transferId, [], 'Admin GA');

    assert($result['message'] === 'Mutasi aset berhasil diselesaikan');
    $row = $result['data'];
    assert($row['status'] === 'completed');
    assert($row['status_label'] === 'Selesai');
    assert($row['completed_at'] !== null);

    // Lokasi aktif baru berpindah di sini, lengkap dengan pemegang tujuannya.
    $asset = $assets->find($proyektor);
    assert((int)$asset['location_id'] === $locGdg);
    assert($asset['assigned_to'] === 'Budi Santoso');
    assert($asset['status'] === 'in_use');

    $history = $movements->forAsset($proyektor);
    assert(count($history) === 1);
    assert($history[0]['movement_type'] === 'relocate');
    assert((int)$history[0]['from_location_id'] === $locIt);
    assert((int)$history[0]['to_location_id'] === $locGdg);
    assert($history[0]['recipient_name'] === 'Budi Santoso');
    assert($history[0]['actor_name'] === 'Admin GA');
});

$check('5. Mutasi yang sudah selesai tidak dapat diselesaikan atau dibatalkan', static function () use ($service, $rejection, $notFound, &$transferId): void {
    assert($rejection(fn () => $service->complete($transferId, [], 'Admin GA')) === 'Mutasi ini sudah diselesaikan atau dibatalkan.');
    assert($rejection(fn () => $service->cancel($transferId, ['reason' => 'Batal'], 'Admin GA')) === 'Mutasi ini sudah diselesaikan atau dibatalkan.');
    assert($notFound(fn () => $service->complete(9999, [], 'Admin GA')) === 'Mutasi tidak ditemukan.');
    assert($notFound(fn () => $service->cancel(9999, ['reason' => 'Batal'])) === 'Mutasi tidak ditemukan.');
});

$check('6. Pembatalan menutup mutasi tanpa mengubah lokasi aset', static function () use ($service, $assets, $transfers, $movements, $errors, $proyektor, $locGdg, $locIt): void {
    $created = $service->create([
        'asset_id' => $proyektor,
        'to_location_id' => $locIt,
        'reason' => 'Rencana pengembalian ke Ruang IT',
    ], 'Admin GA');

    $id = (int)$created['data']['id'];
    assert($created['data']['transfer_date'] === date('Y-m-d'));
    assert((int)$created['data']['from_location_id'] === $locGdg);

    assert($errors(fn () => $service->cancel($id, []))['reason'] === 'Alasan pembatalan wajib diisi.');

    $result = $service->cancel($id, ['reason' => 'Ruangan belum siap'], 'Admin GA');
    assert($result['message'] === 'Mutasi aset berhasil dibatalkan');
    assert($result['data']['status'] === 'cancelled');
    assert($result['data']['status_label'] === 'Dibatalkan');
    assert($result['data']['cancel_reason'] === 'Ruangan belum siap');
    assert($result['data']['cancelled_at'] !== null);
    assert($result['data']['completed_at'] === null);

    // Aset sengaja tidak disentuh pembatalan: lokasinya tetap di gudang dan
    // tidak ada pergerakan baru yang tercatat.
    $asset = $assets->find($proyektor);
    assert((int)$asset['location_id'] === $locGdg);
    assert($asset['assigned_to'] === 'Budi Santoso');
    assert(count($movements->forAsset($proyektor)) === 1);
    assert($transfers->pendingForAsset($proyektor) === null);
});

$check('7. Activity log naratif lengkap dengan ip_address', static function () use ($logRepository): void {
    $logs = $logRepository->recent(200);
    assert($logs !== []);

    foreach ($logs as $log) {
        assert($log['ip_address'] === '127.0.0.1');
        assert($log['description'] !== '');
        assert($log['subject_type'] === 'asset_transfer');
        // Dijalankan dari CLI tanpa sesi: pelakunya tidak boleh terbaca dari mana pun.
        assert($log['user_name'] === null);
        assert($log['user_id'] === null);
    }

    $descriptions = array_column($logs, 'description');
    assert(in_array('Memindahkan aset Proyektor Uji (AST-001) dari Ruang IT ke Gudang Logistik Pusat', $descriptions, true) === true);
    assert(in_array('Menyelesaikan mutasi aset Proyektor Uji (AST-001) dari Ruang IT ke Gudang Logistik Pusat', $descriptions, true) === true);
    assert(in_array('Membatalkan mutasi aset Proyektor Uji (AST-001) (alasan: Ruangan belum siap)', $descriptions, true) === true);

    $actions = array_column($logs, 'action');
    foreach (['create', 'complete', 'cancel'] as $action) {
        assert(in_array($action, $actions, true) === true);
    }
});

echo "\n[SUCCESS] Seluruh pemeriksaan mutasi aset lulus tanpa kegagalan.\n";
