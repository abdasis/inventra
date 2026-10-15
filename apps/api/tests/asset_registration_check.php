<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Asset\CheckInAssetAction;
use Inventra\Core\Actions\Asset\CheckOutAssetAction;
use Inventra\Core\Actions\Asset\CreateAssetAction;
use Inventra\Core\Actions\Asset\DeleteAssetAction;
use Inventra\Core\Actions\Asset\UpdateAssetAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ActivityLogRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\MovementRepository;
use Inventra\Core\Services\AssetService;

echo "=== Running Inventra Asset Registration Self-Check Tests ===\n\n";

// Seluruh berkas test ini memakai assert(); kalau zend.assertions dimatikan
// pemeriksaannya hilang tanpa suara, jadi kegagalannya dibuat eksplisit.
if (ini_get('zend.assertions') !== '1') {
    echo "[-] zend.assertions tidak aktif. Jalankan dengan -d zend.assertions=1.\n";
    exit(1);
}

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Skema SQLite dipakai bersama dengan support/contract_probe.php supaya DDL
// tidak terduplikasi antar berkas test.
$sqlite->exec((string)file_get_contents(__DIR__ . '/support/schema.sqlite.sql'));
$sqlite->exec("INSERT INTO categories (code, name) VALUES ('CAT-IT', 'IT & Elektronik'), ('CAT-ATK', 'Peralatan Kantor')");
$sqlite->exec("INSERT INTO locations (code, name, building, floor) VALUES ('LOC-GUG', 'Gudang', 'A', '1'), ('LOC-DEV', 'Ruang IT', 'A', '2')");
$sqlite->exec("INSERT INTO units (code, name) VALUES ('UNIT-PCS', 'Pcs'), ('UNIT-RIM', 'Rim')");
$sqlite->exec("INSERT INTO items (code, name, category_id, unit_id, item_type) VALUES ('ITM-IT-001', 'Laptop Standar', 1, 1, 'asset'), ('ITM-ATK-001', 'Kertas A4', 2, 2, 'consumable')");

Connection::setInstance($sqlite);

$assets = new AssetRepository($sqlite);
$movements = new MovementRepository($sqlite);
$logRepository = new ActivityLogRepository($sqlite);

$assetService = new AssetService(
    $assets,
    new CreateAssetAction($assets),
    new UpdateAssetAction($assets),
    new DeleteAssetAction($assets),
    new CheckOutAssetAction($assets),
    new CheckInAssetAction($assets),
    '127.0.0.1',
);

$catIt = 1;
$catAtk = 2;
$locGug = 1;
$locDev = 2;
$laptopItem = 1;
$year = date('Y');

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

/** Payload registrasi minimal yang valid untuk kategori dan lokasi tertentu. */
$base = static function (array $override = []) use ($catIt, $locGug): array {
    return array_merge([
        'name' => 'Aset Uji',
        'category_id' => $catIt,
        'location_id' => $locGug,
    ], $override);
};

$check('1. Registrasi satuan tanpa jenis barang', static function () use ($assetService, $base, $year): void {
    $result = $assetService->create($base([
        'name' => 'Proyektor Epson EB-X06',
        'serial_number' => 'SN-PRJ-1',
        'purchase_date' => '2026-01-10',
        'purchase_cost' => 6500000,
    ]));

    assert($result['message'] === 'Aset berhasil didaftarkan');
    assert($result['count'] === 1);
    assert(count($result['data']) === 1);

    $row = $result['data'][0];
    assert($row['asset_code'] === 'AST-' . $year . '-0001');
    assert($row['status'] === 'available');
    assert($row['assigned_to'] === null);
    assert($row['item_id'] === null);
    assert($row['item_name'] === null);
    assert($row['acquisition_source'] === 'purchase');
    assert((float)$row['purchase_cost'] === 6500000.0);
});

$check('2. Registrasi massal tiga unit dengan jenis barang', static function () use ($assetService, $base, $laptopItem, $catIt, $locDev, $year): void {
    $result = $assetService->create($base([
        'name' => 'Laptop Lenovo ThinkPad E14',
        'item_id' => $laptopItem,
        'location_id' => $locDev,
        'quantity' => 3,
    ]));

    assert($result['count'] === 3);
    assert($result['message'] === '3 aset berhasil didaftarkan');
    assert(count($result['data']) === 3);

    $codes = array_column($result['data'], 'asset_code');
    assert($codes === [
        'ITM-IT-001-' . $year . '-0001',
        'ITM-IT-001-' . $year . '-0002',
        'ITM-IT-001-' . $year . '-0003',
    ]);

    foreach ($result['data'] as $row) {
        assert($row['status'] === 'available');
        assert($row['assigned_to'] === null);
        // Kategori diambil dari jenis barang karena payload tidak mengisinya.
        assert((int)$row['category_id'] === $catIt);
        assert($row['item_name'] === 'Laptop Standar');
    }
});

$check('3. Kode aset manual: diterima bila unik, ditolak bila duplikat', static function () use ($assetService, $base, $errors, $rejection, $year): void {
    $manual = $assetService->create($base(['name' => 'Lemari Arsip', 'asset_code' => 'AST-MANUAL-001']));
    assert($manual['data'][0]['asset_code'] === 'AST-MANUAL-001');
    assert($manual['count'] === 1);

    assert($errors(fn () => $assetService->create($base(['name' => 'Lemari Arsip 2', 'asset_code' => 'AST-MANUAL-001'])))['asset_code'] === 'Kode aset sudah dipakai.');
    assert($errors(fn () => $assetService->create($base(['name' => 'Lemari Arsip 3', 'asset_code' => 'AST-' . $year . '-0001'])))['asset_code'] === 'Kode aset sudah dipakai.');
    assert($errors(fn () => $assetService->create($base(['name' => 'Lemari Arsip 4', 'asset_code' => str_repeat('X', 65)])))['asset_code'] === 'Kode aset maksimal 64 karakter.');
    // Kode manual tidak dibagi ke beberapa unit sekaligus.
    assert($errors(fn () => $assetService->create($base(['name' => 'Kursi', 'asset_code' => 'AST-MANUAL-002', 'quantity' => 2])))['asset_code'] === 'Kode aset manual hanya untuk satu unit. Kosongkan bila mendaftarkan beberapa unit.');

    assert($rejection(fn () => $assetService->create($base(['name' => '', 'asset_code' => 'AST-MANUAL-001']))) === 'Nama aset wajib diisi.');
});

$check('4. Jumlah unit di luar 1-50 ditolak', static function () use ($assetService, $base, $errors): void {
    foreach ([0, 51, 'tiga'] as $quantity) {
        assert($errors(fn () => $assetService->create($base(['quantity' => $quantity])))['quantity'] === 'Jumlah unit harus antara 1 dan 50.');
    }

    assert($errors(fn () => $assetService->create($base(['quantity' => 50]))) === []);
});

$check('5. Kategori, lokasi, dan jenis barang tidak dikenal ditolak', static function () use ($assetService, $errors): void {
    assert($errors(fn () => $assetService->create(['name' => 'Aset Uji', 'category_id' => 9999, 'location_id' => 1]))['category_id'] === 'Kategori tidak ditemukan.');
    assert($errors(fn () => $assetService->create(['name' => 'Aset Uji', 'location_id' => 9999, 'category_id' => 1]))['location_id'] === 'Lokasi tidak ditemukan.');
    assert($errors(fn () => $assetService->create(['name' => 'Aset Uji', 'category_id' => 0, 'location_id' => 1]))['category_id'] === 'Kategori wajib diisi.');
    assert($errors(fn () => $assetService->create(['name' => 'Aset Uji', 'category_id' => 1, 'location_id' => 0]))['location_id'] === 'Lokasi wajib diisi.');
    assert($errors(fn () => $assetService->create(['name' => 'Aset Uji', 'category_id' => 1, 'location_id' => 1, 'item_id' => 9999]))['item_id'] === 'Barang tidak ditemukan.');
    assert($errors(fn () => $assetService->create(['name' => 'Aset Uji', 'category_id' => 1, 'location_id' => 1, 'acquisition_source' => 'warisan']))['acquisition_source'] === 'Sumber perolehan tidak valid.');
    assert($errors(fn () => $assetService->create(['name' => 'Aset Uji', 'category_id' => 1, 'location_id' => 1, 'condition_level' => 'keropos']))['condition_level'] === 'Kondisi fisik tidak valid.');
    assert($errors(fn () => $assetService->create(['name' => 'Aset Uji', 'category_id' => 1, 'location_id' => 1, 'purchase_cost' => -1]))['purchase_cost'] === 'Harga perolehan tidak boleh negatif.');
    assert($errors(fn () => $assetService->create(['name' => 'Aset Uji', 'category_id' => 1, 'location_id' => 1, 'purchase_cost' => 'mahal']))['purchase_cost'] === 'Harga perolehan harus berupa angka.');
});

$check('6. Tanggal perolehan dan masa garansi divalidasi', static function () use ($assetService, $base, $errors): void {
    assert($errors(fn () => $assetService->create($base(['purchase_date' => '31-01-2026'])))['purchase_date'] === 'Tanggal perolehan tidak valid.');
    assert($errors(fn () => $assetService->create($base(['purchase_date' => '2026-02-31'])))['purchase_date'] === 'Tanggal perolehan tidak valid.');
    assert($errors(fn () => $assetService->create($base(['name' => 'Aset Uji', 'warranty_expiry' => 'besok'])))['warranty_expiry'] === 'Masa garansi tidak valid.');
    assert($errors(fn () => $assetService->create($base(['purchase_date' => '2026-05-01', 'warranty_expiry' => '2026-04-30'])))['warranty_expiry'] === 'Masa garansi tidak boleh lebih awal dari tanggal perolehan.');
    assert($errors(fn () => $assetService->create($base(['purchase_date' => '2026-05-01', 'warranty_expiry' => '2027-05-01']))) === []);
});

$check('7. Kolom registrasi baru tersimpan dan terbaca kembali', static function () use ($assetService, $base, $catAtk, $locGug): void {
    $result = $assetService->create([
        'name' => 'Meja Rapat',
        'category_id' => $catAtk,
        'location_id' => $locGug,
        'acquisition_source' => 'grant',
        'purchase_reference' => 'BA-HIBAH-2026-011',
        'purchase_date' => '2026-02-01',
        'warranty_expiry' => '2027-02-01',
        'photo_path' => '/uploads/assets/meja.jpg',
        'document_path' => '/uploads/assets/meja.pdf',
        'notes' => 'Hibah dari yayasan',
        'condition_level' => 'fair',
    ]);

    $row = $result['data'][0];
    assert($row['acquisition_source'] === 'grant');
    assert($row['purchase_reference'] === 'BA-HIBAH-2026-011');
    assert($row['warranty_expiry'] === '2027-02-01');
    assert($row['photo_path'] === '/uploads/assets/meja.jpg');
    assert($row['document_path'] === '/uploads/assets/meja.pdf');
    assert($row['condition_level'] === 'fair');
    assert($row['notes'] === 'Hibah dari yayasan');

    $reloaded = (new AssetRepository())->find((int)$row['id']);
    assert($reloaded['acquisition_source'] === 'grant');
    assert($reloaded['document_path'] === '/uploads/assets/meja.pdf');
});

$check('8. Pembaruan mengubah status tanpa melepas pemegang', static function () use ($assetService, $assets, $base, $locGug, $locDev, $laptopItem, $logRepository): void {
    $created = $assetService->create($base([
        'name' => 'Laptop Lenovo ThinkPad E14',
        'item_id' => $laptopItem,
        'location_id' => $locGug,
    ]));
    $assetId = (int)$created['data'][0]['id'];

    $assets->checkOut($assetId, 'Budi Santoso', $locDev, 'Admin GA', AssetStatus::InUse, $locGug, 'Dipinjam untuk proyek');
    $held = $assets->find($assetId);
    assert($held['assigned_to'] === 'Budi Santoso');

    $updated = $assetService->update($assetId, [
        'name' => 'Laptop Lenovo ThinkPad E14 (Servis)',
        'category_id' => 1,
        'location_id' => $locDev,
        'item_id' => $laptopItem,
        'status' => 'maintenance',
    ]);
    assert($updated['message'] === 'Data aset berhasil diperbarui');

    $after = $assets->find($assetId);
    assert($after['status'] === 'maintenance');
    // Pemegang hanya berubah lewat serah terima/pengembalian/penempatan.
    assert($after['assigned_to'] === 'Budi Santoso');
    // Kode aset tidak dikirim, jadi tetap sama.
    assert($after['asset_code'] === $created['data'][0]['asset_code']);

    $descriptions = array_column($logRepository->recent(50), 'description');
    assert(in_array('Memperbarui data aset Laptop Lenovo ThinkPad E14 (Servis) (' . $after['asset_code'] . ') — status berubah dari Dipakai menjadi Perbaikan', $descriptions, true) === true);
});

$check('9. Aset yang tidak ada ditolak lebih dulu', static function () use ($assetService, $base, $notFound): void {
    assert($notFound(fn () => $assetService->update(9999, $base(['status' => 'available']))) === 'Aset tidak ditemukan');
});

$check('10. Riwayat pergerakan per aset', static function () use ($assets, $movements, $base, $assetService, $locGug, $locDev): void {
    $created = $assetService->create($base(['name' => 'Kursi Kerja']));
    $assetId = (int)$created['data'][0]['id'];
    assert($movements->forAsset($assetId) === []);

    $assets->checkOut($assetId, 'Siti Aminah', $locDev, 'Admin GA', AssetStatus::InUse, $locGug, 'Dipinjam');
    $history = $movements->forAsset($assetId);
    assert(count($history) === 1);
    assert($history[0]['movement_type'] === 'check_out');
    assert($history[0]['movement_label'] === 'Peminjaman / Serah Terima');
    assert($history[0]['from_location_name'] === 'Gudang');
    assert($history[0]['to_location_name'] === 'Ruang IT');
});

$check('11. Filter daftar memakai jenis barang', static function () use ($assets, $laptopItem): void {
    $filtered = $assets->all(['item_id' => $laptopItem]);
    assert($filtered !== []);
    foreach ($filtered as $row) {
        assert((int)$row['item_id'] === $laptopItem);
    }

    assert(count($assets->all(['item_id' => $laptopItem])) < count($assets->all()));
    assert($assets->all(['item_id' => 9999]) === []);
});

$check('12. Prefix kode otomatis tidak salah hitung wildcard', static function () use ($assetService, $assets, $base): void {
    $assetService->create($base(['name' => 'Aset Garis Bawah', 'asset_code' => 'A_B-2026-0001']));
    $assetService->create($base(['name' => 'Aset Wildcard', 'asset_code' => 'AXB-2026-0001']));

    assert($assets->countByCodePrefix('A_B-2026-') === 1);
    assert($assets->countByCodePrefix('AXB-2026-') === 1);
});

$check('13. Activity log naratif lengkap dengan ip_address', static function () use ($logRepository, $year): void {
    $logs = $logRepository->recent(100);
    assert($logs !== []);

    foreach ($logs as $log) {
        assert($log['ip_address'] === '127.0.0.1');
        assert($log['description'] !== '');
        // Dijalankan dari CLI tanpa sesi: pelakunya tidak boleh terbaca dari mana pun.
        assert($log['user_name'] === null);
        assert($log['user_id'] === null);
        assert($log['subject_type'] === 'asset');
    }

    $descriptions = array_column($logs, 'description');
    assert(in_array('Mendaftarkan aset Proyektor Epson EB-X06 (AST-' . $year . '-0001)', $descriptions, true) === true);
    assert(in_array('Mendaftarkan 3 unit aset Laptop Lenovo ThinkPad E14 (ITM-IT-001-' . $year . '-0001 … ITM-IT-001-' . $year . '-0003)', $descriptions, true) === true);
    $bulkFifty = array_values(array_filter($descriptions, static fn (string $d): bool => str_starts_with($d, 'Mendaftarkan 50 unit aset ')));
    assert(count($bulkFifty) === 1);

    $create = array_values(array_filter($logs, static fn (array $log): bool => $log['action'] === 'create'));
    assert(count($create) >= 8);
});

echo "\n[SUCCESS] Seluruh pemeriksaan registrasi aset lulus tanpa kegagalan.\n";
