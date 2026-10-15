<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Stock\RecordStockMovementAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ActivityLogRepository;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Repositories\StockRepository;
use Inventra\Core\Services\StockService;

echo "=== Running Inventra Consumable Stock Self-Check Tests ===\n\n";

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
$sqlite->exec("INSERT INTO locations (code, name) VALUES ('LOC-GDG', 'Gudang')");
$sqlite->exec("INSERT INTO units (code, name) VALUES ('UNIT-PCS', 'Pcs'), ('UNIT-RIM', 'Rim')");
$sqlite->exec("INSERT INTO employees (employee_number, name, department) VALUES ('EMP-001', 'Budi Santoso', 'Finance')");
$sqlite->exec("INSERT INTO items (code, name, category_id, unit_id, item_type, minimum_stock) VALUES
    ('ITM-IT-001', 'Laptop Standar', 1, 1, 'asset', 0),
    ('ITM-ATK-001', 'Kertas A4 HVS 80gsm', 2, 2, 'consumable', 5),
    ('ITM-ATK-002', 'Tinta Printer', 2, 1, 'consumable', 2)");

Connection::setInstance($sqlite);

$items = new ItemRepository($sqlite);
$stocks = new StockRepository($sqlite);
$logRepository = new ActivityLogRepository($sqlite);

$service = new StockService(
    $stocks,
    new RecordStockMovementAction($stocks, $items, new EmployeeRepository($sqlite)),
    '127.0.0.1',
);

$laptop = 1;
$kertas = 2;
$tinta = 3;

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

/** Memastikan tidak ada saldo yang negatif di seluruh buku besar. */
$assertNoNegative = static function (PDO $sqlite): void {
    $rows = $sqlite->query('SELECT item_id, quantity FROM stock_balances')->fetchAll();
    foreach ($rows as $row) {
        assert((int)$row['quantity'] >= 0);
    }
};

$check('1. Buku besar memuat semua barang habis pakai dengan saldo awal nol', static function () use ($stocks, $assertNoNegative, $sqlite, $tinta, $laptop): void {
    $balances = $stocks->balances();
    assert(count($balances) === 2);

    foreach ($balances as $row) {
        assert($row['quantity'] === 0);
        assert($row['updated_at'] === null);
        assert($row['item_name'] !== 'Laptop Standar');
    }

    $names = array_column($balances, 'item_name');
    assert($names === ['Kertas A4 HVS 80gsm', 'Tinta Printer']);

    // Barang yang baru dibuat di master data langsung tampil dengan saldo nol
    // dan sudah ditandai menipis bila punya stok minimum.
    $tintaRow = $stocks->balances(['search' => 'Tinta Printer'])[0];
    assert((int)$tintaRow['item_id'] === $tinta);
    assert($tintaRow['item_code'] === 'ITM-ATK-002');
    assert($tintaRow['category_name'] === 'Peralatan Kantor');
    assert($tintaRow['unit_name'] === 'Pcs');
    assert((int)$tintaRow['minimum_stock'] === 2);
    assert((int)$tintaRow['quantity'] === 0);

    assert(count($stocks->balances(['low_only' => 1])) === 2);
    assert($stocks->balances(['search' => 'Laptop']) === []);
    assert($stocks->balance($tinta) === null);
    assert($stocks->balance($laptop) === null);
    assert($assertNoNegative($sqlite) === null);
});

$check('2. Stok masuk menaikkan saldo dan mencatat balance_after', static function () use ($service, $stocks, $sqlite, $kertas, $assertNoNegative): void {
    $result = $service->recordMovement([
        'item_id' => $kertas,
        'movement_type' => 'in',
        'quantity' => 10,
        'notes' => 'Sisa pakai gudang',
    ], 'Admin GA');

    assert($result['message'] === 'Stok masuk berhasil dicatat');
    assert((int)$result['data']['balance']['quantity'] === 10);
    assert((int)$result['data']['movement']['quantity'] === 10);
    assert((int)$result['data']['movement']['balance_after'] === 10);
    assert($result['data']['movement']['movement_type_label'] === 'Stok Masuk');
    assert($result['data']['movement']['reference_type'] === null);
    assert($result['data']['movement']['actor_name'] === 'Admin GA');
    assert((int)$stocks->balance($kertas)['quantity'] === 10);
    assert($stocks->balance($kertas)['updated_at'] !== null);

    // Saldo menipis hanya bila sudah di bawah atau sama dengan stok minimum.
    $row = $stocks->balances(['search' => 'Kertas A4 HVS 80gsm'])[0];
    assert((int)$row['quantity'] === 10);
    assert((int)$row['minimum_stock'] === 5);
    $low = $stocks->balances(['low_only' => 1]);
    assert(count($low) === 1);
    assert($low[0]['item_code'] === 'ITM-ATK-002');
    assert($assertNoNegative($sqlite) === null);
});

$check('3. Stok keluar menurunkan saldo dengan balance_after yang benar', static function () use ($service, $stocks, $sqlite, $kertas, $assertNoNegative): void {
    $result = $service->recordMovement([
        'item_id' => $kertas,
        'movement_type' => 'out',
        'quantity' => 4,
        'department' => 'Finance',
        'employee_id' => 1,
    ], 'Admin GA');

    assert((int)$result['data']['movement']['balance_after'] === 6);
    assert($result['data']['movement']['department'] === 'Finance');
    assert($result['data']['movement']['employee_name'] === 'Budi Santoso');
    assert((int)$stocks->balance($kertas)['quantity'] === 6);

    // Departemen ikut terbaca dari karyawan bila payload tidak mengisinya.
    $next = $service->recordMovement([
        'item_id' => $kertas,
        'movement_type' => 'out',
        'quantity' => 1,
        'employee_id' => 1,
    ], 'Admin GA');
    assert($next['data']['movement']['department'] === 'Finance');
    assert((int)$next['data']['movement']['balance_after'] === 5);
    assert($assertNoNegative($sqlite) === null);

    // Daftar riwayat bisa disaring per jenis pergerakan.
    assert(count($stocks->movements(['item_id' => $kertas, 'movement_type' => 'out'])) === 2);
    assert(count($stocks->movements(['item_id' => $kertas, 'movement_type' => 'in'])) === 1);
    assert(count($stocks->movements(['item_id' => $kertas])) === 3);
});

$check('4. Stok keluar melebihi saldo ditolak dengan pesan menyebut sisa', static function () use ($service, $stocks, $rejection, $kertas): void {
    assert($rejection(fn () => $service->recordMovement(['item_id' => $kertas, 'movement_type' => 'out', 'quantity' => 6], 'Admin GA')) === 'Stok tidak mencukupi. Sisa 5 unit.');
    // Saldo tidak boleh berubah setelah penolakan.
    assert((int)$stocks->balance($kertas)['quantity'] === 5);

    // Pengeluaran tepat sebesar saldo tetap boleh, dan saldo boleh nol.
    $result = $service->recordMovement(['item_id' => $kertas, 'movement_type' => 'out', 'quantity' => 5], 'Admin GA');
    assert((int)$result['data']['movement']['balance_after'] === 0);
    assert((int)$stocks->balance($kertas)['quantity'] === 0);
});

$check('5. Validasi payload pergerakan', static function () use ($service, $errors, $laptop, $tinta): void {
    assert($errors(fn () => $service->recordMovement(['item_id' => 9999, 'movement_type' => 'in', 'quantity' => 1], 'Admin GA'))['item_id'] === 'Barang tidak ditemukan.');
    assert($errors(fn () => $service->recordMovement(['item_id' => $laptop, 'movement_type' => 'in', 'quantity' => 1], 'Admin GA'))['item_id'] === 'Hanya barang habis pakai yang memiliki saldo stok.');
    assert($errors(fn () => $service->recordMovement(['item_id' => $tinta, 'movement_type' => 'transfer', 'quantity' => 1], 'Admin GA'))['movement_type'] === 'Jenis pergerakan tidak valid.');
    assert($errors(fn () => $service->recordMovement(['item_id' => $tinta, 'movement_type' => 'in', 'quantity' => 0], 'Admin GA'))['quantity'] === 'Jumlah harus lebih dari 0.');
    assert($errors(fn () => $service->recordMovement(['item_id' => $tinta, 'movement_type' => 'in', 'quantity' => -2], 'Admin GA'))['quantity'] === 'Jumlah harus lebih dari 0.');
    assert($errors(fn () => $service->recordMovement(['item_id' => $tinta, 'movement_type' => 'in', 'quantity' => 'dua'], 'Admin GA'))['quantity'] === 'Jumlah harus lebih dari 0.');
    assert($errors(fn () => $service->recordMovement(['item_id' => $tinta, 'movement_type' => 'in', 'quantity' => 1, 'employee_id' => 9999], 'Admin GA'))['employee_id'] === 'Karyawan tidak ditemukan.');
    // Arah koreksi diabaikan untuk pergerakan yang bukan koreksi.
    assert($service->recordMovement(['item_id' => $tinta, 'movement_type' => 'in', 'quantity' => 1, 'direction' => 'turun'], 'Admin GA')['data']['movement']['quantity'] === 1);
    assert($service->recordMovement(['item_id' => $tinta, 'movement_type' => 'out', 'quantity' => 1], 'Admin GA')['data']['movement']['balance_after'] === 0);
});

$check('6. Koreksi wajib beralasan dan arahnya harus dikenal', static function () use ($service, $errors, $rejection, $tinta): void {
    assert($errors(fn () => $service->recordMovement(['item_id' => $tinta, 'movement_type' => 'adjustment', 'quantity' => 3], 'Admin GA'))['reason'] === 'Alasan koreksi wajib diisi.');
    assert($errors(fn () => $service->recordMovement(['item_id' => $tinta, 'movement_type' => 'adjustment', 'quantity' => 3, 'reason' => 'salah input', 'direction' => 'naik'], 'Admin GA'))['direction'] === 'Arah koreksi tidak valid.');
    assert($rejection(fn () => $service->recordMovement(['item_id' => $tinta, 'movement_type' => 'adjustment', 'quantity' => 3], 'Admin GA')) === 'Alasan koreksi wajib diisi.');
});

$check('7. Koreksi naik dan turun bekerja, serta tidak boleh melewati saldo', static function () use ($service, $stocks, $errors, $tinta, $sqlite, $assertNoNegative): void {
    $increase = $service->recordMovement([
        'item_id' => $tinta,
        'movement_type' => 'adjustment',
        'quantity' => 3,
        'reason' => 'salah input',
    ], 'Admin GA');

    assert($increase['message'] === 'Koreksi stok berhasil dicatat');
    assert((int)$increase['data']['movement']['balance_after'] === 3);
    assert($increase['data']['movement']['reason'] === 'salah input');
    assert((int)$stocks->balance($tinta)['quantity'] === 3);

    $decrease = $service->recordMovement([
        'item_id' => $tinta,
        'movement_type' => 'adjustment',
        'quantity' => 2,
        'direction' => 'decrease',
        'reason' => 'stok rusak',
    ], 'Admin GA');
    assert((int)$decrease['data']['movement']['balance_after'] === 1);
    assert((int)$decrease['data']['movement']['quantity'] === 2);

    assert($errors(fn () => $service->recordMovement(['item_id' => $tinta, 'movement_type' => 'adjustment', 'quantity' => 4, 'direction' => 'decrease', 'reason' => 'stok rusak'], 'Admin GA'))['quantity'] === 'Stok tidak mencukupi. Sisa 1 unit.');
    assert((int)$stocks->balance($tinta)['quantity'] === 1);
    assert($assertNoNegative($sqlite) === null);
});

$check('8. Saldo barang berjenis aset tidak dikelola di sini', static function () use ($service, $stocks, $rejection, $laptop): void {
    assert($rejection(fn () => $service->recordMovement(['item_id' => $laptop, 'movement_type' => 'adjustment', 'quantity' => 1, 'reason' => 'percobaan'], 'Admin GA')) === 'Hanya barang habis pakai yang memiliki saldo stok.');
    assert($stocks->balance($laptop) === null);
    assert($stocks->movements(['item_id' => $laptop]) === []);
});

$check('9. Rekonsiliasi: saldo sama dengan jumlah seluruh pergerakan', static function () use ($service, $stocks, $kertas, $tinta, $sqlite, $assertNoNegative): void {
    // Seluruh barang digerakkan sekali lagi supaya riwayatnya bercampur arah.
    $service->recordMovement(['item_id' => $kertas, 'movement_type' => 'in', 'quantity' => 7], 'Admin GA');
    $service->recordMovement(['item_id' => $kertas, 'movement_type' => 'adjustment', 'quantity' => 1, 'reason' => 'temuan opname'], 'Admin GA');
    $service->recordMovement(['item_id' => $kertas, 'movement_type' => 'out', 'quantity' => 3], 'Admin GA');

    foreach ([$kertas, $tinta] as $itemId) {
        $quantity = (int)$stocks->balance($itemId)['quantity'];

        // Riwayat dibaca dari daftar terbaru dulu, jadi dibalik untuk menelusuri
        // saldonya dari awal.
        $movements = array_reverse($stocks->movements(['item_id' => $itemId]));
        $running = 0;

        foreach ($movements as $movement) {
            $step = (int)$movement['quantity'];
            $balanceAfter = (int)$movement['balance_after'];

            if ($movement['movement_type'] === 'adjustment') {
                // Koreksi: arahnya tersirat dari selisih terhadap saldo berjalan,
                // tetapi besar pergeserannya wajib sama dengan kuantitasnya.
                $delta = $balanceAfter - $running;
                assert(abs($delta) === $step);
                $running = $balanceAfter;
            } else {
                $running += $movement['movement_type'] === 'in' ? $step : -$step;
                assert($running === $balanceAfter);
            }

            assert($running >= 0);
        }

        // Kriteria PRD 6.3.10: saldo tersimpan = hasil penelusuran seluruh mutasi.
        assert($running === $quantity);
        assert($quantity >= 0);
    }

    assert((int)$stocks->balance($kertas)['quantity'] === 5);
    assert((int)$stocks->balance($tinta)['quantity'] === 1);
    assert($assertNoNegative($sqlite) === null);
});

$check('10. Activity log naratif lengkap dengan ip_address', static function () use ($logRepository): void {
    $logs = $logRepository->recent(100);
    assert($logs !== []);

    foreach ($logs as $log) {
        assert($log['ip_address'] === '127.0.0.1');
        assert($log['description'] !== '');
        assert($log['subject_type'] === 'stock_movement');
        // Dijalankan dari CLI tanpa sesi: pelakunya tidak boleh terbaca dari mana pun.
        assert($log['user_name'] === null);
        assert($log['user_id'] === null);
    }

    $descriptions = array_column($logs, 'description');
    assert(in_array('Mencatat penerimaan 10 Rim Kertas A4 HVS 80gsm', $descriptions, true) === true);
    assert(in_array('Mencatat pengeluaran 4 Rim Kertas A4 HVS 80gsm untuk departemen Finance', $descriptions, true) === true);
    assert(in_array('Mencatat pengeluaran 5 Rim Kertas A4 HVS 80gsm', $descriptions, true) === true);
    assert(in_array('Mencatat koreksi stok Tinta Printer (sebesar 3 Pcs, alasan: salah input)', $descriptions, true) === true);
    assert(in_array('Mencatat koreksi stok Tinta Printer (sebesar 2 Pcs, alasan: stok rusak)', $descriptions, true) === true);

    $actions = array_column($logs, 'action');
    foreach (['stock_in', 'stock_out', 'stock_adjustment'] as $action) {
        assert(in_array($action, $actions, true) === true);
    }
});

echo "\n[SUCCESS] Seluruh pemeriksaan stok barang habis pakai lulus tanpa kegagalan.\n";
