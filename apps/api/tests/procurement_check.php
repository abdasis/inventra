<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Asset\CreateAssetAction;
use Inventra\Core\Actions\Purchase\CancelPurchaseAction;
use Inventra\Core\Actions\Purchase\CreatePurchaseAction;
use Inventra\Core\Actions\Purchase\DeletePurchaseAction;
use Inventra\Core\Actions\Purchase\ReceivePurchaseAction;
use Inventra\Core\Actions\Purchase\UpdatePurchaseAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ActivityLogRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\PurchaseRepository;
use Inventra\Core\Repositories\StockRepository;
use Inventra\Core\Repositories\SupplierRepository;
use Inventra\Core\Services\PurchaseService;

echo "=== Running Inventra Procurement Self-Check Tests ===\n\n";

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
$sqlite->exec("INSERT INTO locations (code, name, building, floor) VALUES
    ('LOC-GDG', 'Gudang Logistik Pusat', 'Gedung Annex', 'Lt 1'),
    ('LOC-DEV', 'Ruang IT', 'Gedung Utama', 'Lt 2'),
    ('LOC-AWAL', 'Ruang Awal', 'Gedung Abadi', 'Lt 1')");
$sqlite->exec("INSERT INTO units (code, name) VALUES ('UNIT-PCS', 'Pcs'), ('UNIT-RIM', 'Rim')");
$sqlite->exec("INSERT INTO employees (employee_number, name, department) VALUES ('EMP-001', 'Budi Santoso', 'Finance')");
$sqlite->exec("INSERT INTO suppliers (code, name) VALUES ('SUP-001', 'PT Sumber Rejeki')");
$sqlite->exec("INSERT INTO items (code, name, category_id, unit_id, item_type, minimum_stock) VALUES
    ('ITM-IT-001', 'Laptop Standar', 1, 1, 'asset', 0),
    ('ITM-ATK-001', 'Kertas A4 HVS 80gsm', 2, 2, 'consumable', 5),
    ('ITM-ATK-002', 'Tinta Printer', 2, 1, 'consumable', 2)");

Connection::setInstance($sqlite);

$assets = new AssetRepository($sqlite);
$items = new ItemRepository($sqlite);
$suppliers = new SupplierRepository($sqlite);
$locations = new LocationRepository($sqlite);
$stocks = new StockRepository($sqlite);
$purchases = new PurchaseRepository($sqlite);
$logRepository = new ActivityLogRepository($sqlite);

$service = new PurchaseService(
    $purchases,
    new CreatePurchaseAction($purchases, $items, $suppliers),
    new UpdatePurchaseAction($purchases, $items, $suppliers),
    new ReceivePurchaseAction($purchases, $items, $locations, $stocks, $assets, new CreateAssetAction($assets)),
    new CancelPurchaseAction($purchases),
    new DeletePurchaseAction($purchases),
    '127.0.0.1',
);

$laptop = 1;
$kertas = 2;
$tinta = 3;
$supplier = 1;
$locGdg = 1;
$locAwal = 3;
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

/** Satu baris pesanan yang valid untuk barang habis pakai. */
$line = static function (array $override = []) use ($kertas): array {
    return array_merge(['item_id' => $kertas, 'quantity_ordered' => 1, 'unit_price' => 1000], $override);
};

$purchaseId = 0;
$laptopDetailId = 0;
$deletedDraftId = 0;
$numberA = '';
$numberB = '';
$numberC = '';

$check('1. Pembelian baru: nomor otomatis, total, dan agregat baris', static function () use ($service, $purchases, $year, $kertas, $laptop, $supplier, &$purchaseId, &$laptopDetailId, &$numberA): void {
    $result = $service->create([
        'purchase_date' => '2026-03-01',
        'supplier_id' => $supplier,
        'reference' => 'SP-2026-0042',
        'notes' => 'Pengadaan triwulan pertama',
        'details' => [
            ['item_id' => $kertas, 'quantity_ordered' => 20, 'unit_price' => 45000],
            ['item_id' => $laptop, 'quantity_ordered' => 2, 'unit_price' => 12500000],
        ],
    ], 'Admin GA');

    assert($result['message'] === 'Pembelian berhasil dicatat');
    $row = $result['data'];
    $purchaseId = (int)$row['id'];
    $numberA = (string)$row['purchase_number'];

    assert($numberA === 'PO-' . $year . '-0001');
    assert($row['status'] === 'draft');
    assert($row['status_label'] === 'Draf');
    assert($row['supplier_name'] === 'PT Sumber Rejeki');
    assert((float)$row['total_amount'] === 25900000.0);
    assert($row['total_amount_formatted'] === 'Rp 25.900.000');
    assert($row['item_count'] === 2);
    assert($row['quantity_ordered'] === 22);
    assert($row['quantity_received'] === 0);
    assert($row['created_by_name'] === 'Admin GA');
    assert($row['received_at'] === null);
    assert(count($row['details']) === 2);

    $first = $row['details'][0];
    assert($first['item_code'] === 'ITM-ATK-001');
    assert($first['item_type'] === 'consumable');
    assert($first['unit_name'] === 'Rim');
    assert($first['quantity_outstanding'] === 20);
    assert($first['unit_price_formatted'] === 'Rp 45.000');
    assert($first['subtotal'] === 900000.0);
    assert($row['details'][1]['item_type'] === 'asset');
    assert($row['details'][1]['subtotal'] === 25000000.0);
    $laptopDetailId = (int)$row['details'][1]['id'];

    // Daftar memakai agregat baris dan filter status.
    $listed = $purchases->all(['search' => $numberA]);
    assert(count($listed) === 1);
    assert((int)$listed[0]['item_count'] === 2);
    assert((int)$listed[0]['quantity_ordered'] === 22);
    assert((int)$listed[0]['quantity_received'] === 0);
    assert((int)$purchases->all(['search' => 'Sumber Rejeki'])[0]['id'] === $purchaseId);
    assert(count($purchases->all(['status' => 'draft'])) === 1);
    assert($purchases->all(['status' => 'received']) === []);

    // Detail satu pembelian memuat baris lengkap dan menolak id yang tidak ada.
    assert(count($service->detail($purchaseId)['data']['details']) === 2);
});

$check('2. Header dan baris tidak valid ditolak dengan peta error per field', static function () use ($service, $errors, $line, $year, $laptop): void {
    $base = ['purchase_date' => '2026-03-02', 'details' => [$line()]];

    assert($errors(fn () => $service->create(array_merge($base, ['purchase_number' => 'PO-' . $year . '-0001']), 'Admin'))['purchase_number'] === 'Nomor pembelian sudah dipakai.');
    assert($errors(fn () => $service->create(array_merge($base, ['purchase_number' => str_repeat('X', 65)]), 'Admin'))['purchase_number'] === 'Nomor pembelian maksimal 64 karakter.');
    assert($errors(fn () => $service->create(array_merge($base, ['purchase_date' => '31-03-2026']), 'Admin'))['purchase_date'] === 'Tanggal pembelian tidak valid.');
    assert($errors(fn () => $service->create(array_merge($base, ['purchase_date' => '2026-02-31']), 'Admin'))['purchase_date'] === 'Tanggal pembelian tidak valid.');
    assert($errors(fn () => $service->create(['details' => [$line()]], 'Admin'))['purchase_date'] === 'Tanggal pembelian tidak valid.');
    assert($errors(fn () => $service->create(array_merge($base, ['supplier_id' => 9999]), 'Admin'))['supplier_id'] === 'Supplier tidak ditemukan.');
    assert($errors(fn () => $service->create(array_merge($base, ['reference' => str_repeat('R', 129)]), 'Admin'))['reference'] === 'Referensi maksimal 128 karakter.');

    assert($errors(fn () => $service->create(['purchase_date' => '2026-03-02', 'details' => [['item_id' => 9999, 'quantity_ordered' => 1, 'unit_price' => 0]]], 'Admin'))['details.0.item_id'] === 'Barang tidak ditemukan.');
    assert($errors(fn () => $service->create(['purchase_date' => '2026-03-02', 'details' => [$line(['quantity_ordered' => 0])]], 'Admin'))['details.0.quantity_ordered'] === 'Jumlah dipesan harus lebih dari 0.');
    assert($errors(fn () => $service->create(['purchase_date' => '2026-03-02', 'details' => [$line(['quantity_ordered' => 'dua'])]], 'Admin'))['details.0.quantity_ordered'] === 'Jumlah dipesan harus lebih dari 0.');
    assert($errors(fn () => $service->create(['purchase_date' => '2026-03-02', 'details' => [$line(['unit_price' => -1])]], 'Admin'))['details.0.unit_price'] === 'Harga satuan tidak boleh negatif.');
    assert($errors(fn () => $service->create(['purchase_date' => '2026-03-02', 'details' => [$line(['unit_price' => 'mahal'])]], 'Admin'))['details.0.unit_price'] === 'Harga satuan harus berupa angka.');

    // Baris kedua yang salah tetap dilaporkan pada indeksnya sendiri.
    $twoLines = $errors(fn () => $service->create([
        'purchase_date' => '2026-03-02',
        'details' => [['item_id' => $laptop, 'quantity_ordered' => 1, 'unit_price' => 0], $line(['quantity_ordered' => -3])],
    ], 'Admin'));
    assert(isset($twoLines['details.1.quantity_ordered']) === true);
    assert(isset($twoLines['details.0.item_id']) === false);
});

$check('3. Pembelian tanpa baris detail ditolak', static function () use ($service, $rejection, $errors): void {
    assert($errors(fn () => $service->create(['purchase_date' => '2026-03-02', 'details' => []], 'Admin'))['details'] === 'Pembelian harus memiliki minimal satu baris barang.');
    assert($rejection(fn () => $service->create(['purchase_date' => '2026-03-02'], 'Admin')) === 'Pembelian harus memiliki minimal satu baris barang.');
});

$check('4. Pembaruan hanya saat draf dan mengganti seluruh baris', static function () use ($service, $purchases, $line, $tinta, $kertas, $supplier, $year, &$numberB, &$deletedDraftId): void {
    $created = $service->create([
        'purchase_date' => '2026-03-02',
        'details' => [['item_id' => $tinta, 'quantity_ordered' => 5, 'unit_price' => 20000]],
    ], 'Admin GA');
    $id = (int)$created['data']['id'];
    $numberB = (string)$created['data']['purchase_number'];
    assert($numberB === 'PO-' . $year . '-0002');

    $updated = $service->update($id, [
        'purchase_date' => '2026-03-09',
        'supplier_id' => $supplier,
        'reference' => 'SP-2026-0050',
        'details' => [['item_id' => $kertas, 'quantity_ordered' => 3, 'unit_price' => 5000]],
    ]);

    assert($updated['message'] === 'Data pembelian berhasil diperbarui');
    $row = $updated['data'];
    assert($row['purchase_number'] === $numberB);
    assert($row['purchase_date'] === '2026-03-09');
    assert($row['reference'] === 'SP-2026-0050');
    assert($row['supplier_name'] === 'PT Sumber Rejeki');
    assert((float)$row['total_amount'] === 15000.0);
    assert(count($row['details']) === 1);
    assert((int)$row['details'][0]['item_id'] === $kertas);
    assert((int)$row['details'][0]['quantity_ordered'] === 3);
    // Baris lama benar-benar diganti, bukan ditambahkan.
    assert(count($purchases->details($id)) === 1);

    // Nomor milik pembelian lain tetap ditolak saat memperbarui.
    $other = $service->create(['purchase_date' => '2026-03-10', 'details' => [$line()]], 'Admin GA');
    $duplicate = [];

    try {
        $service->update((int)$other['data']['id'], [
            'purchase_number' => $numberB,
            'purchase_date' => '2026-03-10',
            'details' => [$line()],
        ]);
    } catch (ValidationException $e) {
        $duplicate = $e->errors();
    }

    assert($duplicate['purchase_number'] === 'Nomor pembelian sudah dipakai.');
    $service->delete((int)$other['data']['id']);

    $deletedDraftId = $id;
});

$check('5. Penerimaan sebagian menambah stok dan mendaftarkan unit aset', static function () use ($service, $purchases, $stocks, $assets, $sqlite, $purchaseId, $laptopDetailId, $numberA, $kertas, $laptop, $locGdg): void {
    $details = $purchases->details($purchaseId);
    $result = $service->receive($purchaseId, [
        'details' => [
            ['id' => (int)$details[0]['id'], 'quantity_received' => 8],
            ['id' => (int)$details[1]['id'], 'quantity_received' => 1],
        ],
    ], 'Admin GA');

    assert($result['message'] === 'Penerimaan barang berhasil dicatat');
    $row = $result['data'];
    assert($row['status'] === 'partial');
    assert($row['status_label'] === 'Diterima Sebagian');
    assert($row['quantity_received'] === 9);
    assert($row['received_at'] === null);

    assert((int)$row['details'][0]['quantity_received'] === 8);
    assert((int)$row['details'][0]['quantity_outstanding'] === 12);
    assert((int)$row['details'][1]['quantity_received'] === 1);
    assert((int)$row['details'][1]['quantity_outstanding'] === 1);

    // Barang habis pakai: saldo bertambah dan tercatat sebagai stok masuk.
    $balance = $stocks->balance($kertas);
    assert((int)$balance['quantity'] === 8);
    $movements = $stocks->movements(['item_id' => $kertas]);
    assert(count($movements) === 1);
    assert($movements[0]['movement_type'] === 'in');
    assert((int)$movements[0]['quantity'] === 8);
    assert((int)$movements[0]['balance_after'] === 8);
    assert($movements[0]['reference_type'] === 'purchase');
    assert((int)$movements[0]['reference_id'] === $purchaseId);
    assert($movements[0]['actor_name'] === 'Admin GA');
    assert($movements[0]['item_name'] === 'Kertas A4 HVS 80gsm');

    // Barang berjenis aset: satu unit lahir dengan identitas sendiri.
    $registered = $assets->all(['item_id' => $laptop]);
    assert(count($registered) === 1);
    assert($registered[0]['status'] === 'available');
    assert($registered[0]['acquisition_source'] === 'purchase');
    assert($registered[0]['purchase_reference'] === $numberA);
    assert($registered[0]['purchase_date'] === '2026-03-01');
    assert((float)$registered[0]['purchase_cost'] === 12500000.0);
    assert((int)$registered[0]['location_id'] === $locGdg);

    $linked = $sqlite->query('SELECT purchase_id, purchase_detail_id FROM assets WHERE item_id = ' . $laptop . ' ORDER BY id ASC')->fetchAll();
    assert((int)$linked[0]['purchase_id'] === $purchaseId);
    assert((int)$linked[0]['purchase_detail_id'] === $laptopDetailId);
});

$check('6. Penerimaan kedua melunasi seluruh baris dan menutup pembelian', static function () use ($service, $purchases, $stocks, $assets, $purchaseId, $laptopDetailId, $kertas, $laptop): void {
    $details = $purchases->details($purchaseId);
    assert((int)$details[1]['id'] === $laptopDetailId);

    $result = $service->receive($purchaseId, [
        'details' => [
            ['id' => (int)$details[0]['id'], 'quantity_received' => 12],
            ['id' => (int)$details[1]['id'], 'quantity_received' => 1],
        ],
    ], 'Admin GA');

    assert($result['data']['status'] === 'received');
    assert($result['data']['status_label'] === 'Diterima');
    assert($result['data']['received_at'] !== null);
    assert($result['data']['quantity_received'] === 22);
    assert((int)$result['data']['details'][0]['quantity_outstanding'] === 0);

    $movements = $stocks->movements(['item_id' => $kertas]);
    assert(count($movements) === 2);
    assert((int)$movements[0]['quantity'] === 12);
    assert((int)$movements[0]['balance_after'] === 20);
    assert((int)$stocks->balance($kertas)['quantity'] === 20);
    assert(count($assets->all(['item_id' => $laptop])) === 2);
});

$check('7. Penerimaan melebihi sisa pesanan ditolak', static function () use ($service, $purchases, $errors, $rejection, $kertas): void {
    $created = $service->create([
        'purchase_date' => '2026-04-01',
        'details' => [['item_id' => $kertas, 'quantity_ordered' => 10, 'unit_price' => 45000]],
    ], 'Admin GA');
    $id = (int)$created['data']['id'];
    $detailId = (int)$purchases->details($id)[0]['id'];

    assert($errors(fn () => $service->receive($id, ['details' => [['id' => $detailId, 'quantity_received' => 12]]], 'Admin GA'))['details.0.quantity_received'] === 'Jumlah diterima melebihi sisa pesanan.');
    assert($errors(fn () => $service->receive($id, ['details' => [['id' => $detailId, 'quantity_received' => 7, 'quantity_rejected' => 4]]], 'Admin GA'))['details.0.quantity_received'] === 'Jumlah diterima melebihi sisa pesanan.');
    assert($errors(fn () => $service->receive($id, ['details' => [['id' => 9999, 'quantity_received' => 1]]], 'Admin GA'))['details.0.id'] === 'Baris pembelian tidak ditemukan.');
    assert($errors(fn () => $service->receive($id, ['details' => [['id' => $detailId, 'quantity_received' => -1]]], 'Admin GA'))['details.0.quantity_received'] === 'Jumlah diterima tidak valid.');
    assert($errors(fn () => $service->receive($id, ['details' => [['id' => $detailId, 'quantity_rejected' => 'banyak']]], 'Admin GA'))['details.0.quantity_rejected'] === 'Jumlah ditolak tidak valid.');
    assert($rejection(fn () => $service->receive($id, ['details' => [['id' => $detailId, 'quantity_received' => 0, 'quantity_rejected' => 0]]], 'Admin GA')) === 'Tidak ada baris penerimaan yang diproses.');

    // Penolakan tidak dikenakan apa pun ke saldo dan status belum berubah.
    assert($service->detail($id)['data']['status'] === 'draft');
    assert($service->detail($id)['data']['quantity_received'] === 0);

    // Penolakan ikut melunasi baris: 6 diterima + 4 ditolak = 10 dipesan.
    $result = $service->receive($id, ['details' => [['id' => $detailId, 'quantity_received' => 6, 'quantity_rejected' => 4]]], 'Admin GA');
    assert($result['data']['status'] === 'received');
    assert($result['data']['details'][0]['quantity_rejected'] === 4);
    assert($result['data']['details'][0]['quantity_outstanding'] === 0);

    // Pengiriman ulang atas baris yang sudah lunas ditolak, jadi tidak menggandakan stok.
    assert($rejection(fn () => $service->receive($id, ['details' => [['id' => $detailId, 'quantity_received' => 1]]], 'Admin GA')) === 'Pembelian sudah diterima seluruhnya.');
});

$check('8. Pembelian yang sudah diterima tidak dapat diubah, dihapus, dibatalkan, atau diterima lagi', static function () use ($service, $rejection, $purchases, $line, $purchaseId): void {
    assert($rejection(fn () => $service->update($purchaseId, ['purchase_date' => '2026-05-01', 'details' => [$line()]])) === 'Pembelian yang sudah diproses tidak dapat diubah.');
    assert($rejection(fn () => $service->delete($purchaseId)) === 'Pembelian yang sudah diproses tidak dapat dihapus.');
    assert($rejection(fn () => $service->cancel($purchaseId, ['reason' => 'Salah input'])) === 'Pembelian yang sudah menerima barang tidak dapat dibatalkan. Koreksi lewat transaksi stok atau penghapusan aset.');
    assert($rejection(fn () => $service->receive($purchaseId, ['details' => [['id' => (int)$purchases->details($purchaseId)[0]['id'], 'quantity_received' => 1]]], 'Admin GA')) === 'Pembelian sudah diterima seluruhnya.');

    $row = $purchases->find($purchaseId);
    assert($row['status'] === 'received');
    assert($row['cancel_reason'] === null);
});

$check('9. Pembatalan draf menyimpan alasan dan menutup jalur penerimaan', static function () use ($service, $purchases, $line, $rejection, $errors, &$numberC): void {
    $created = $service->create(['purchase_date' => '2026-05-02', 'supplier_id' => 1, 'details' => [$line()]], 'Admin GA');
    $id = (int)$created['data']['id'];
    $numberC = (string)$created['data']['purchase_number'];

    assert($errors(fn () => $service->cancel($id, ['reason' => '   ']))['reason'] === 'Alasan pembatalan wajib diisi.');

    $result = $service->cancel($id, ['reason' => 'Stok masih tersedia']);
    assert($result['message'] === 'Pembelian berhasil dibatalkan');

    $row = $purchases->find($id);
    assert($row['status'] === 'cancelled');
    assert($row['cancel_reason'] === 'Stok masih tersedia');
    assert($row['cancelled_at'] !== null);

    $presented = $service->detail($id)['data'];
    assert($presented['status_label'] === 'Dibatalkan');
    assert($presented['status_badge'] === 'bg-danger-subtle text-danger border border-danger-subtle');

    assert($rejection(fn () => $service->receive($id, ['details' => [['id' => (int)$purchases->details($id)[0]['id'], 'quantity_received' => 1]]], 'Admin GA')) === 'Pembelian yang dibatalkan tidak dapat diterima.');
    assert($rejection(fn () => $service->delete($id)) === 'Pembelian yang sudah diproses tidak dapat dihapus.');
});

$check('10. Pembelian draf dapat dihapus beserta barisnya', static function () use ($service, $purchases, &$deletedDraftId): void {
    $result = $service->delete($deletedDraftId);
    assert($result['message'] === 'Pembelian berhasil dihapus');
    assert($purchases->find($deletedDraftId) === null);
    assert($purchases->details($deletedDraftId) === []);
});

$check('11. Penerimaan aset jatuh ke lokasi pertama bila gudang baku tidak ada', static function () use ($service, $assets, $sqlite, $laptop, $locAwal): void {
    $sqlite->exec("UPDATE locations SET code = 'LOC-PUSAT' WHERE code = 'LOC-GDG'");

    $created = $service->create(['purchase_date' => '2026-06-01', 'details' => [['item_id' => $laptop, 'quantity_ordered' => 1, 'unit_price' => 9000000]]], 'Admin GA');
    $id = (int)$created['data']['id'];
    $detailId = (int)$sqlite->query('SELECT id FROM purchase_details WHERE purchase_id = ' . $id)->fetchColumn();

    $before = count($assets->all(['item_id' => $laptop]));
    $service->receive($id, ['details' => [['id' => $detailId, 'quantity_received' => 1]]], 'Admin GA');

    $registered = $assets->all(['item_id' => $laptop]);
    assert(count($registered) === $before + 1);
    assert((int)$registered[0]['location_id'] === $locAwal);
    assert($registered[0]['purchase_reference'] === (string)$created['data']['purchase_number']);
});

$check('12. Baris tidak ada ditolak lebih dulu', static function () use ($service, $notFound): void {
    assert($notFound(fn () => $service->detail(9999)) === 'Pembelian tidak ditemukan.');
    assert($notFound(fn () => $service->update(9999, ['purchase_date' => '2026-06-01'])) === 'Pembelian tidak ditemukan.');
    assert($notFound(fn () => $service->receive(9999, ['details' => []], 'Admin GA')) === 'Pembelian tidak ditemukan.');
    assert($notFound(fn () => $service->cancel(9999, ['reason' => 'x'])) === 'Pembelian tidak ditemukan.');
    assert($notFound(fn () => $service->delete(9999)) === 'Pembelian tidak ditemukan.');
});

$check('13. Activity log naratif lengkap dengan ip_address', static function () use ($logRepository, $numberA, $numberC, $year): void {
    $logs = $logRepository->recent(200);
    assert($logs !== []);

    foreach ($logs as $log) {
        assert($log['ip_address'] === '127.0.0.1');
        assert($log['description'] !== '');
        assert($log['subject_type'] === 'purchase');
        // Dijalankan dari CLI tanpa sesi: pelakunya tidak boleh terbaca dari mana pun.
        assert($log['user_name'] === null);
        assert($log['user_id'] === null);
    }

    $descriptions = array_column($logs, 'description');
    assert(in_array('Mencatat pembelian ' . $numberA . ' dari PT Sumber Rejeki (2 baris barang, total Rp 25.900.000)', $descriptions, true) === true);
    assert(in_array('Memperbarui pembelian PO-' . $year . '-0002 — supplier berubah dari (kosong) menjadi PT Sumber Rejeki; tanggal pembelian berubah dari 2026-03-02 menjadi 2026-03-09; referensi berubah dari (kosong) menjadi SP-2026-0050', $descriptions, true) === true);
    assert(in_array('Menerima 8 unit Kertas A4 HVS 80gsm dari pembelian ' . $numberA, $descriptions, true) === true);
    assert(in_array('Menerima 1 unit aset Laptop Standar dari pembelian ' . $numberA, $descriptions, true) === true);
    assert(in_array('Menerima 12 unit Kertas A4 HVS 80gsm dari pembelian ' . $numberA, $descriptions, true) === true);
    assert(in_array('Membatalkan pembelian ' . $numberC . ' (alasan: Stok masih tersedia)', $descriptions, true) === true);
    assert(in_array('Menghapus pembelian PO-' . $year . '-0002', $descriptions, true) === true);

    $actions = array_column($logs, 'action');
    foreach (['create', 'update', 'receive', 'cancel', 'delete'] as $action) {
        assert(in_array($action, $actions, true) === true);
    }
});

echo "\n[SUCCESS] Seluruh pemeriksaan pengadaan lulus tanpa kegagalan.\n";
