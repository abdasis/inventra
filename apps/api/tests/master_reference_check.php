<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Item\CreateItemAction;
use Inventra\Core\Actions\Item\DeleteItemAction;
use Inventra\Core\Actions\Item\UpdateItemAction;
use Inventra\Core\Actions\Unit\CreateUnitAction;
use Inventra\Core\Actions\Unit\DeleteUnitAction;
use Inventra\Core\Actions\Unit\UpdateUnitAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ActivityLogRepository;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Repositories\UnitRepository;
use Inventra\Core\Services\ItemService;
use Inventra\Core\Services\UnitService;

echo "=== Running Inventra Master Data Self-Check Tests ===\n\n";

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

Connection::setInstance($sqlite);

$unitRepository = new UnitRepository($sqlite);
$itemRepository = new ItemRepository($sqlite);
$logRepository = new ActivityLogRepository($sqlite);

$unitService = new UnitService(
    $unitRepository,
    new CreateUnitAction($unitRepository),
    new UpdateUnitAction($unitRepository),
    new DeleteUnitAction($unitRepository),
    '127.0.0.1',
);

$itemService = new ItemService(
    $itemRepository,
    new CreateItemAction($itemRepository),
    new UpdateItemAction($itemRepository),
    new DeleteItemAction($itemRepository),
    '127.0.0.1',
);

// Dibaca langsung lewat PDO: skrip ini hanya perlu id kategori, dan tidak ingin
// terikat pada Repository modul kategori yang diurus terpisah.
$categoryIds = array_column($sqlite->query('SELECT id, code FROM categories')->fetchAll(), 'id', 'code');
$catIt = (int)$categoryIds['CAT-IT'];
$catAtk = (int)$categoryIds['CAT-ATK'];

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

$check('1. CRUD satuan', static function () use ($unitService, $unitRepository): void {
    $created = $unitService->create([
        'code' => 'RIM',
        'name' => 'Rim',
        'description' => 'Satuan per rim (500 lembar)',
    ]);
    assert($created['message'] === 'Satuan berhasil ditambahkan');
    assert($created['data']['code'] === 'RIM');
    assert($created['data']['name'] === 'Rim');
    assert($created['data']['description'] === 'Satuan per rim (500 lembar)');
    $rimId = (int)$created['data']['id'];
    assert($rimId > 0);

    $boxId = $unitRepository->create(['code' => 'BOX', 'name' => 'Box']);
    assert($boxId > 0);
    assert($unitRepository->create(['code' => 'PCS', 'name' => 'Pcs', 'description' => null]) > 0);

    $box = $unitRepository->find($boxId);
    assert($box !== null);
    assert($box['name'] === 'Box');
    assert(array_key_exists('description', $box) === true);
    assert($unitRepository->find(9999) === null);

    $updated = $unitService->update($boxId, ['code' => 'BOX', 'name' => 'Kotak', 'description' => null]);
    assert($updated['message'] === 'Data satuan berhasil diperbarui');
    assert($updated['data']['name'] === 'Kotak');
    assert($updated['data']['description'] === null);

    assert($unitRepository->codeExists('RIM') === true);
    assert($unitRepository->codeExists('RIM', $rimId) === false);
    assert($unitRepository->codeExists('TIDAK-ADA') === false);

    $all = $unitRepository->all();
    assert(count($all) === 3);
    assert($all[0]['name'] === 'Kotak');
    assert($all[2]['name'] === 'Rim');

    $searched = $unitRepository->all(['search' => 'ri']);
    assert(count($searched) === 1);
    assert($searched[0]['code'] === 'RIM');
});

$check('2. Aturan domain satuan (duplikat, kosong, dan baris yang tidak ada)', static function () use ($unitService, $rejection, $errors, $notFound): void {
    assert($rejection(fn () => $unitService->create(['code' => 'RIM', 'name' => 'Rim Baru'])) === 'Kode satuan sudah dipakai.');
    assert($rejection(fn () => $unitService->create(['code' => 'SET', 'name' => ''])) === 'Nama satuan wajib diisi.');
    assert($rejection(fn () => $unitService->create(['code' => '', 'name' => 'Set'])) === 'Kode satuan wajib diisi.');
    assert($rejection(fn () => $unitService->create(['code' => str_repeat('X', 33), 'name' => 'Set'])) === 'Kode satuan maksimal 32 karakter.');

    $codeErrors = $errors(fn () => $unitService->create(['code' => 'RIM', 'name' => '']));
    assert($codeErrors['code'] === 'Kode satuan sudah dipakai.');
    assert($codeErrors['name'] === 'Nama satuan wajib diisi.');

    // Baris yang tidak eksis ditolak sebagai 404 sebelum payload divalidasi,
    // jadi pesannya NotFoundException, bukan ValidationException.
    assert($notFound(fn () => $unitService->update(9999, ['code' => 'SET', 'name' => 'Set'])) === 'Satuan tidak ditemukan.');
    assert($notFound(fn () => $unitService->delete(9999)) === 'Satuan tidak ditemukan.');
});

$check('3. CRUD barang dengan DTO', static function () use ($itemService, $itemRepository, $catAtk, $catIt): void {
    $consumable = $itemService->create([
        'code' => 'ITM-ATK-001',
        'name' => 'Kertas A4 HVS 80gsm',
        'brand' => 'Sidu',
        'model' => 'A4 80gsm',
        'specification' => 'Gramatur 80gsm, isi 500 lembar per rim',
        'category_id' => $catAtk,
        'unit_id' => 1,
        'item_type' => 'consumable',
        'minimum_stock' => 20,
        'description' => 'Kebutuhan cetak harian',
    ]);
    assert($consumable['message'] === 'Barang berhasil didaftarkan');
    assert($consumable['data']['code'] === 'ITM-ATK-001');
    assert($consumable['data']['category_name'] === 'Peralatan Kantor');
    assert($consumable['data']['unit_name'] === 'Rim');
    assert($consumable['data']['item_type'] === 'consumable');
    assert($consumable['data']['item_type_label'] === 'Barang Habis Pakai');
    assert($consumable['data']['item_type_badge'] !== '');
    assert($consumable['data']['minimum_stock'] === 20);
    assert($consumable['data']['brand'] === 'Sidu');
    $a4Id = (int)$consumable['data']['id'];

    // minimum_stock tidak dikirim: default 0, bukan null.
    $asset = $itemService->create([
        'code' => 'ITM-IT-001',
        'name' => 'Laptop ThinkPad E14 Gen 6',
        'category_id' => $catIt,
        'unit_id' => 2,
        'item_type' => 'asset',
    ]);
    assert($asset['data']['minimum_stock'] === 0);
    assert($asset['data']['item_type_label'] === 'Aset');
    assert($asset['data']['brand'] === null);
    assert($asset['data']['specification'] === null);
    $laptopId = (int)$asset['data']['id'];

    $row = $itemRepository->find($a4Id);
    assert($row !== null);
    assert($row['category_name'] === 'Peralatan Kantor');
    assert($row['unit_name'] === 'Rim');
    assert($itemRepository->find(9999) === null);

    $updated = $itemService->update($a4Id, [
        'code' => 'ITM-ATK-001',
        'name' => 'Kertas A4 HVS 80gsm Premium',
        'brand' => 'Sidu',
        'model' => 'A4 80gsm',
        'category_id' => $catAtk,
        'unit_id' => 1,
        'item_type' => 'consumable',
        'minimum_stock' => 25,
    ]);
    assert($updated['message'] === 'Data barang berhasil diperbarui');
    assert($updated['data']['name'] === 'Kertas A4 HVS 80gsm Premium');
    assert($updated['data']['minimum_stock'] === 25);

    assert(count($itemRepository->all(['search' => 'A4'])) === 1);
    assert($itemRepository->all(['search' => 'A4'])[0]['unit_name'] === 'Rim');
    assert($itemRepository->all(['search' => 'ThinkPad'])[0]['id'] === $laptopId);
    assert(count($itemRepository->all()) === 2);
    assert($itemRepository->all()[0]['name'] === 'Kertas A4 HVS 80gsm Premium');

    assert($itemRepository->codeExists('ITM-IT-001') === true);
    assert($itemRepository->codeExists('ITM-IT-001', $laptopId) === false);
    assert($itemRepository->categoryExists($catIt) === true);
    assert($itemRepository->categoryExists(9999) === false);
    assert($itemRepository->unitExists(1) === true);
    assert($itemRepository->unitExists(9999) === false);
});

$check('4. Aturan domain barang (kategori, satuan, jenis, dan stok minimum)', static function () use ($itemService, $rejection, $errors, $notFound, $catAtk): void {
    $base = [
        'code' => 'ITM-ATK-002',
        'name' => 'Tinta Printer Epson 003 Black',
        'category_id' => $catAtk,
        'unit_id' => 1,
        'item_type' => 'consumable',
    ];

    assert($rejection(fn () => $itemService->create(['code' => 'ITM-ATK-002', 'name' => 'Tinta Epson', 'category_id' => 9999, 'unit_id' => 1, 'item_type' => 'consumable'])) === 'Kategori tidak ditemukan.');
    assert($rejection(fn () => $itemService->create(['code' => 'ITM-ATK-002', 'name' => 'Tinta Epson', 'category_id' => $catAtk, 'unit_id' => 9999, 'item_type' => 'consumable'])) === 'Satuan tidak ditemukan.');
    assert($rejection(fn () => $itemService->create(['code' => 'ITM-ATK-002', 'name' => 'Tinta Epson', 'category_id' => $catAtk, 'unit_id' => 1, 'item_type' => 'jasa'])) === 'Jenis barang tidak valid.');
    assert($rejection(fn () => $itemService->create(['code' => 'ITM-ATK-002', 'name' => 'Tinta Epson', 'category_id' => $catAtk, 'unit_id' => 1, 'item_type' => 'consumable', 'minimum_stock' => -3])) === 'Stok minimum tidak boleh negatif.');
    assert($rejection(fn () => $itemService->create(['code' => 'ITM-ATK-001', 'name' => 'Tinta Epson', 'category_id' => $catAtk, 'unit_id' => 1, 'item_type' => 'consumable'])) === 'Kode barang sudah dipakai.');
    assert($rejection(fn () => $itemService->create(['code' => 'ITM-ATK-002', 'name' => '', 'category_id' => $catAtk, 'unit_id' => 1, 'item_type' => 'consumable'])) === 'Nama barang wajib diisi.');
    assert($rejection(fn () => $itemService->create(['code' => 'ITM-ATK-002', 'name' => 'Tinta Epson', 'category_id' => 0, 'unit_id' => 1, 'item_type' => 'consumable'])) === 'Kategori wajib diisi.');

    $fieldErrors = $errors(fn () => $itemService->create(['code' => '', 'name' => '', 'category_id' => 9999, 'unit_id' => 9999, 'item_type' => 'jasa', 'minimum_stock' => -1]));
    assert($fieldErrors['code'] === 'Kode barang wajib diisi.');
    assert($fieldErrors['name'] === 'Nama barang wajib diisi.');
    assert($fieldErrors['category_id'] === 'Kategori tidak ditemukan.');
    assert($fieldErrors['unit_id'] === 'Satuan tidak ditemukan.');
    assert($fieldErrors['item_type'] === 'Jenis barang tidak valid.');
    assert($fieldErrors['minimum_stock'] === 'Stok minimum tidak boleh negatif.');

    assert($notFound(fn () => $itemService->update(9999, $base)) === 'Barang tidak ditemukan.');
    assert($notFound(fn () => $itemService->delete(9999)) === 'Barang tidak ditemukan.');

    // Tidak ada baris yang lahir dari payload yang ditolak.
    assert(count((new ItemRepository())->all()) === 2);
});

$check('5. Satuan yang masih dipakai barang tidak dapat dihapus', static function () use ($unitService, $unitRepository, $rejection, $itemService, $catAtk): void {
    assert($unitRepository->countItemReferences(1) === 1);
    assert($unitRepository->countItemReferences(2) === 1);

    $second = $itemService->create([
        'code' => 'ITM-ATK-003',
        'name' => 'Kertas F4 HVS 70gsm',
        'category_id' => $catAtk,
        'unit_id' => 1,
        'item_type' => 'consumable',
    ]);
    assert($unitRepository->countItemReferences(1) === 2);

    assert($rejection(fn () => $unitService->delete(1)) === 'Satuan tidak dapat dihapus karena masih dipakai oleh 2 barang.');
    assert($unitRepository->find(1) !== null);

    $removedItem = $itemService->delete((int)$second['data']['id']);
    assert($removedItem['message'] === 'Barang berhasil dihapus');
    assert($unitRepository->countItemReferences(1) === 1);
    assert($rejection(fn () => $unitService->delete(1)) === 'Satuan tidak dapat dihapus karena masih dipakai oleh 1 barang.');
});

$check('6. Hapus barang dan satuan yang sudah bebas', static function () use ($itemService, $itemRepository, $unitService, $unitRepository): void {
    foreach ($itemRepository->all() as $row) {
        $result = $itemService->delete((int)$row['id']);
        assert($result['message'] === 'Barang berhasil dihapus');
        assert($itemRepository->find((int)$row['id']) === null);
    }
    assert(count($itemRepository->all()) === 0);

    assert($unitRepository->countItemReferences(1) === 0);
    assert($unitService->delete(1)['message'] === 'Satuan berhasil dihapus');
    assert($unitRepository->find(1) === null);
});

$check('7. Activity log naratif lengkap dengan ip_address', static function () use ($logRepository): void {
    $logs = $logRepository->recent(50);
    assert(count($logs) === 10);

    foreach ($logs as $log) {
        assert($log['ip_address'] === '127.0.0.1');
        assert($log['description'] !== '');
        // Dijalankan dari CLI tanpa sesi: pelakunya tidak boleh terbaca dari mana pun.
        assert($log['user_name'] === null);
        assert($log['user_id'] === null);
    }

    assert($logs[0]['action'] === 'delete');
    assert($logs[0]['subject_type'] === 'unit');
    assert($logs[0]['description'] === 'Menghapus satuan Rim (RIM)');

    $descriptions = array_column($logs, 'description');
    assert(in_array('Menambahkan satuan Rim (RIM)', $descriptions, true) === true);
    assert(in_array('Memperbarui satuan Kotak (BOX)', $descriptions, true) === true);
    assert(in_array('Menambahkan barang Laptop ThinkPad E14 Gen 6 (ITM-IT-001)', $descriptions, true) === true);
    assert(in_array('Memperbarui barang Kertas A4 HVS 80gsm Premium (ITM-ATK-001) — nama berubah dari Kertas A4 HVS 80gsm menjadi Kertas A4 HVS 80gsm Premium', $descriptions, true) === true);
    assert(in_array('Menghapus barang Kertas F4 HVS 70gsm (ITM-ATK-003)', $descriptions, true) === true);

    $itemCreate = array_values(array_filter($logs, static fn (array $log): bool => $log['subject_type'] === 'item' && $log['action'] === 'create'));
    assert(count($itemCreate) === 3);
});

echo "\n[SUCCESS] Seluruh pemeriksaan master data lulus tanpa kegagalan.\n";
