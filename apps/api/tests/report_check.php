<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Api\Http\Controllers\ActivityLogController;
use Inventra\Api\Http\Request;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\ActivityLogDTO;
use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Repositories\ReportRepository;
use Inventra\Core\Services\ReportService;
use Inventra\Core\Support\CsvWriter;

echo "=== Running Inventra Reporting And Audit Trail Self-Check Tests ===\n\n";

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

$today = date('Y-m-d');
$thisYear = date('Y');
$lastYear = ((int)$thisYear - 1) . '-06-01';

// Kategori, lokasi, dan satuan.
$sqlite->exec("INSERT INTO categories (code, name) VALUES ('CAT-IT', 'Perangkat IT'), ('CAT-ATK', 'Peralatan Kantor')");
$sqlite->exec("INSERT INTO locations (code, name) VALUES ('LOC-GDG', 'Gudang'), ('LOC-IT', 'Ruang IT')");
$sqlite->exec("INSERT INTO units (code, name) VALUES ('UNIT-PCS', 'Pcs')");

// Barang: satu aset (jenis barang untuk ekspor) dan dua barang habis pakai.
$sqlite->exec("INSERT INTO items (code, name, category_id, unit_id, item_type, minimum_stock) VALUES
    ('ITM-IT-001', 'Laptop Standar, 14 inci', 1, 1, 'asset', 0),
    ('ITM-ATK-001', 'Kertas A4 \"HVS\"', 2, 1, 'consumable', 5),
    ('ITM-ATK-002', 'Tinta Printer', 2, 1, 'consumable', 2)");

// Aset: 2 tersedia, 1 dipakai, 1 dipinjam, 1 perbaikan, 1 dihapus.
$sqlite->exec("INSERT INTO assets (asset_code, name, category_id, location_id, item_id, serial_number, purchase_date, purchase_cost, status, condition_level, assigned_to) VALUES
    ('AST-001', 'Dell Latitude 7420', 1, 2, 1, 'SN-001', '{$thisYear}-02-01', 15000000, 'available', 'good', NULL),
    ('AST-002', 'Lenovo ThinkPad T14', 1, 2, 1, 'SN-002', '{$thisYear}-02-05', 12000000, 'available', 'new', NULL),
    ('AST-003', 'Kursi Ergonomis', 2, 1, NULL, 'SN-003', '{$thisYear}-03-01', 2500000, 'in_use', 'fair', 'Budi'),
    ('AST-004', 'Proyektor Epson', 1, 1, NULL, 'SN-004', '{$thisYear}-04-01', 8000000, 'on_loan', 'good', 'Sari'),
    ('AST-005', 'Printer HP LaserJet', 2, 1, NULL, NULL, '{$thisYear}-05-01', 4500000, 'maintenance', 'damaged', NULL),
    ('AST-006', 'Monitor LG 24 inci', 1, 1, NULL, NULL, '{$thisYear}-06-01', 3000000, 'disposed', 'damaged', NULL)");

// Saldo: kertas menipis (3 <= 5), tinta aman (10 > 2).
$sqlite->exec("INSERT INTO stock_balances (item_id, quantity) VALUES (2, 3), (3, 10)");

// Peminjaman: satu aktif dan sudah lewat jatuh tempo, satu aktif belum jatuh
// tempo, satu sudah kembali.
$sqlite->exec("INSERT INTO asset_loans (asset_id, employee_id, loaned_at, due_at, returned_at, handed_by_name, received_by_name, status) VALUES
    (4, NULL, '{$today}', '" . date('Y-m-d', strtotime('-3 days')) . "', NULL, 'Admin GA', 'Sari', 'active'),
    (2, NULL, '{$today}', '" . date('Y-m-d', strtotime('+3 days')) . "', NULL, 'Admin GA', 'Budi', 'active'),
    (3, NULL, '{$today}', '" . date('Y-m-d', strtotime('+7 days')) . "', '{$today}', 'Admin GA', 'Budi', 'returned')");

// Pemeliharaan: satu tahun berjalan, satu tahun lalu (tidak boleh ikut dihitung).
$sqlite->exec("INSERT INTO asset_maintenances (asset_id, maintenance_type, maintenance_date, cost, status, performed_by_name) VALUES
    (5, 'corrective', '{$thisYear}-07-10', 1250000, 'ongoing', 'Admin GA'),
    (5, 'preventive', '{$lastYear}', 900000, 'completed', 'Admin GA')");

// Penghapusan: satu tahun berjalan, satu tahun lalu.
$sqlite->exec("INSERT INTO asset_disposals (asset_id, disposal_date, reason, method, executed_by_name) VALUES
    (6, '{$thisYear}-08-01', 'Rusak berat', 'written_off', 'Admin GA'),
    (5, '{$lastYear}', 'Rusak', 'written_off', 'Admin GA')");

// Pengguna: dua aktif, satu nonaktif.
$hash = password_hash('Inventra#2026', PASSWORD_DEFAULT);
$sqlite->exec("INSERT INTO users (name, email, password_hash, role, is_active) VALUES
    ('Admin GA', 'admin@kantor.id', '{$hash}', 'admin', 1),
    ('Staf GA', 'staf@kantor.id', '{$hash}', 'staff', 1),
    ('Mantan Staf', 'mantan@kantor.id', '{$hash}', 'staff', 0)");

// Jejak audit: campuran aksi, pelaku, dan waktu. Baris ke-7 hari terakhir,
// sisanya lebih lama; urutan id sengaja dibalik dari urutan waktu supaya
// pengurutan terbaru dulu benar-benar teruji.
$sqlite->exec("INSERT INTO activity_logs (user_id, user_name, action, subject_type, subject_id, description, ip_address, created_at) VALUES
    (1, 'Admin GA', 'create', 'asset', 1, 'Menambah aset Dell Latitude 7420', '127.0.0.1', '" . date('Y-m-d H:i:s', strtotime('-30 days')) . "'),
    (2, 'Staf GA', 'stock_in', 'stock_movement', 1, 'Mencatat penerimaan 10 Pcs Kertas A4 HVS', '10.0.0.2', '" . date('Y-m-d H:i:s', strtotime('-10 days')) . "'),
    (1, 'Admin GA', 'check_out', 'asset', 4, 'Menyerahkan Proyektor Epson kepada Sari', '127.0.0.1', '" . date('Y-m-d H:i:s', strtotime('-3 days')) . "'),
    (2, 'Staf GA', 'export', 'report', NULL, 'Mengekspor daftar aset ke berkas CSV', '10.0.0.2', '" . date('Y-m-d H:i:s', strtotime('-2 days')) . "'),
    (1, 'Admin GA', 'login', NULL, NULL, 'Admin GA masuk ke sistem', '127.0.0.1', '" . date('Y-m-d H:i:s') . "')");

Connection::setInstance($sqlite);

$reports = new ReportRepository($sqlite);
$service = new ReportService($reports, '127.0.0.1');

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

/** Baris CSV tanpa BOM, dipecah per baris. */
$csvLines = static function (string $content): array {
    assert(substr($content, 0, 3) === "\xEF\xBB\xBF");

    return explode("\r\n", substr($content, 3));
};

$check('1. Ringkasan menghitung aset per status dan valuasi perolehan', static function () use ($service): void {
    $summary = $service->summary();

    assert($summary['assets_total'] === 6);
    assert($summary['total_valuation'] === 45000000.0);
    assert($summary['total_valuation_formatted'] === 'Rp 45.000.000');

    $counts = [];
    foreach ($summary['assets_by_status'] as $row) {
        $counts[$row['status']] = $row['count'];
    }

    assert($counts === [
        'available' => 2,
        'in_use' => 1,
        'maintenance' => 1,
        'disposed' => 1,
        'on_loan' => 1,
    ]);

    // Persentase dihitung dari total aset, dan setiap baris membawa labelnya.
    foreach ($summary['assets_by_status'] as $row) {
        if ($row['status'] === 'available') {
            assert($row['status_label'] === 'Tersedia');
            assert($row['percentage'] === 33.3);
        }
        if ($row['status'] === 'on_loan') {
            assert($row['status_label'] === 'Sedang Dipinjam');
            assert($row['percentage'] === 16.7);
        }
    }

    // Kategori teratas: IT 4 aset, Peralatan Kantor 2 aset.
    assert(count($summary['top_categories']) === 2);
    assert($summary['top_categories'][0]['category_name'] === 'Perangkat IT');
    assert($summary['top_categories'][0]['count'] === 4);
    assert($summary['top_categories'][0]['percentage'] === 66.7);
    assert($summary['top_categories'][1]['category_name'] === 'Peralatan Kantor');
    assert($summary['top_categories'][1]['count'] === 2);
});

$check('2. Ringkasan menghitung peminjaman, stok menipis, dan pengguna aktif', static function () use ($service): void {
    $summary = $service->summary();

    // Dua pinjaman aktif, satu di antaranya sudah lewat jatuh tempo.
    assert($summary['active_loans'] === 2);
    assert($summary['overdue_loans'] === 1);

    // Hanya kertas yang saldonya di bawah atau sama dengan stok minimum.
    assert($summary['low_stock_items'] === 1);

    // "Mantan Staf" nonaktif, jadi tidak ikut dihitung.
    assert($summary['active_users'] === 2);
});

$check('3. Biaya pemeliharaan dan penghapusan hanya tahun berjalan', static function () use ($service): void {
    $summary = $service->summary();

    // 1.250.000 tahun ini; 900.000 tahun lalu tidak ikut.
    assert($summary['maintenance_cost_this_year'] === 1250000.0);
    assert($summary['maintenance_cost_this_year_formatted'] === 'Rp 1.250.000');

    // Satu penghapusan tahun ini, satu tahun lalu tidak ikut.
    assert($summary['disposals_this_year'] === 1);
});

$check('4. Aktivitas tujuh hari terakhir hanya menghitung baris yang masuk rentang', static function () use ($service): void {
    // Baris pada -3, -2, dan 0 hari masuk; -10 dan -30 hari tidak.
    assert($service->summary()['activity_last_7_days'] === 3);
});

$check('5. Baris ekspor aset membawa label status dan kondisi, serta ikut filter', static function () use ($reports): void {
    $rows = $reports->assetRows();
    assert(count($rows) === 6);
    assert($rows[0]['name'] === 'Dell Latitude 7420');
    assert($rows[0]['asset_code'] === 'AST-001');
    assert($rows[0]['category_name'] === 'Perangkat IT');
    assert($rows[0]['location_name'] === 'Ruang IT');
    assert($rows[0]['item_name'] === 'Laptop Standar, 14 inci');
    assert($rows[0]['serial_number'] === 'SN-001');
    assert($rows[0]['purchase_date'] === date('Y') . '-02-01');
    assert((float)$rows[0]['purchase_cost'] === 15000000.0);
    assert($rows[0]['status'] === 'available');
    assert($rows[0]['condition_level'] === 'good');

    assert(count($reports->assetRows(['status' => 'available'])) === 2);
    assert(count($reports->assetRows(['category_id' => 2])) === 2);
    assert(count($reports->assetRows(['location_id' => 2])) === 2);
    assert(count($reports->assetRows(['status' => 'available', 'category_id' => 1])) === 2);
    assert($reports->assetRows(['status' => 'tidak-ada']) === []);
});

$check('6. Baris saldo stok menandai stok rendah sesuai stok minimum', static function () use ($reports): void {
    $rows = $reports->stockRows();
    assert(count($rows) === 2);

    $byCode = [];
    foreach ($rows as $row) {
        $byCode[$row['item_code']] = $row;
    }

    assert($byCode['ITM-ATK-001']['item_name'] === 'Kertas A4 "HVS"');
    assert($byCode['ITM-ATK-001']['category_name'] === 'Peralatan Kantor');
    assert($byCode['ITM-ATK-001']['unit_name'] === 'Pcs');
    assert((int)$byCode['ITM-ATK-001']['quantity'] === 3);
    assert((int)$byCode['ITM-ATK-001']['minimum_stock'] === 5);
    assert($byCode['ITM-ATK-001']['is_low'] === true);

    assert((int)$byCode['ITM-ATK-002']['quantity'] === 10);
    assert($byCode['ITM-ATK-002']['is_low'] === false);

    $low = $reports->stockRows(['low_only' => 1]);
    assert(count($low) === 1);
    assert($low[0]['item_code'] === 'ITM-ATK-001');

    // Barang berjenis aset tidak punya saldo dan tidak ikut terdaftar.
    assert(array_search('ITM-IT-001', array_column($rows, 'item_code'), true) === false);
});

$check('7. CSV benar-benar menulis BOM, judul kolom, dan mengutip nilai khusus', static function () use ($csvLines): void {
    $content = CsvWriter::toString(
        [
            ['kode' => 'A,1', 'nama' => 'Kertas "HVS"', 'jumlah' => 3],
            ['kode' => 'B', 'nama' => 'Biasa', 'jumlah' => 0],
        ],
        ['Kode' => 'kode', 'Nama' => 'nama', 'Jumlah' => 'jumlah']
    );

    $lines = $csvLines($content);

    // Baris terakhir selalu kosong karena setiap baris diakhiri CRLF.
    assert($lines === [
        'Kode,Nama,Jumlah',
        '"A,1","Kertas ""HVS""",3',
        'B,Biasa,0',
        '',
    ]);

    // Nilai yang belum ada di baris sumber tetap ditulis sebagai kolom kosong.
    $empty = $csvLines(CsvWriter::toString([['a' => 1]], ['A' => 'a', 'B' => 'b', 'C' => 'c']));
    assert($empty[0] === 'A,B,C');
    assert($empty[1] === '1,,');
});

$check('8. Jejak audit: filter aksi, rentang tanggal, pencarian, dan urutan terbaru dulu', static function () use ($reports): void {
    $all = $reports->activityRows();
    assert(count($all) === 5);

    // Terbaru dulu: login (hari ini) paling atas, entri -30 hari paling bawah.
    assert($all[0]['action'] === 'login');
    assert($all[4]['action'] === 'create');

    assert(count($reports->activityRows(['action' => 'export'])) === 1);
    assert(count($reports->activityRows(['subject_type' => 'asset'])) === 2);

    $from = date('Y-m-d', strtotime('-3 days'));
    assert(count($reports->activityRows(['date_from' => $from])) === 3);

    $to = date('Y-m-d', strtotime('-10 days'));
    assert(count($reports->activityRows(['date_to' => $to])) === 2);

    assert(count($reports->activityRows(['date_from' => $from, 'date_to' => $from])) === 1);
    assert($reports->activityRows(['date_from' => $from, 'date_to' => $from])[0]['action'] === 'check_out');

    // search menelusuri deskripsi maupun nama pelaku.
    assert(count($reports->activityRows(['search' => 'Proyektor'])) === 1);
    assert(count($reports->activityRows(['search' => 'Staf GA'])) === 2);
    assert($reports->activityRows(['search' => 'tidak-ada']) === []);

    // Batas baris: default 200, maksimum 1000, nilai ngawur kembali ke default.
    assert(count($reports->activityRows(['limit' => 2])) === 2);
    assert(count($reports->activityRows(['limit' => 9999])) === 5);
    assert(count($reports->activityRows(['limit' => 'dua'])) === 5);

    // Daftar aksi untuk dropdown urut alfabetis dan unik.
    assert($reports->activityActions() === ['check_out', 'create', 'export', 'login', 'stock_in']);
});

$check('9. Ekspor CSV jejak audit memakai label aksi dan waktu yang terbaca', static function () use ($service, $csvLines): void {
    $result = $service->activityCsv(['action' => 'check_out']);

    assert($result['filename'] === 'inventra-aktivitas-' . date('Y-m-d') . '.csv');

    $lines = $csvLines($result['content']);
    assert($lines[0] === 'Waktu,Pengguna,Aksi,Objek,ID Objek,Keterangan,Alamat IP');
    assert(count($lines) === 1 + 2);
    assert(str_contains($lines[1], 'Serah terima keluar') === true);
    assert(str_contains($lines[1], 'Admin GA') === true);
    assert(str_contains($lines[1], '127.0.0.1') === true);
});

$check('10. Ekspor CSV aset memakai label, jumlah baris sama dengan sumber, dan tercatat', static function () use ($service, $csvLines, $sqlite): void {
    $before = (int)$sqlite->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();

    $result = $service->assetsCsv([]);

    assert($result['filename'] === 'inventra-aset-' . date('Y-m-d') . '.csv');

    $lines = $csvLines($result['content']);
    assert($lines[0] === 'Kode Aset,Nama,Kategori,Lokasi,Jenis Barang,Nomor Seri,Tanggal Perolehan,Harga Perolehan,Status,Kondisi,Pemegang');
    assert(count($lines) === 6 + 2);

    // Status dan kondisi dikirim sebagai label, bukan enum mentah.
    assert(str_contains($result['content'], 'Dell Latitude 7420') === true);
    assert(str_contains($result['content'], 'Tersedia') === true);
    assert(str_contains($result['content'], 'Sangat Baik (Baru)') === true);
    assert(str_contains($result['content'], ',available,') === false);

    // Nilai berkoma di dalam nama barang ikut dikutip.
    assert(str_contains($result['content'], '"Laptop Standar, 14 inci"') === true);

    // Ekspor adalah tindakan yang diaudit, dan tidak ada baris lain yang ikut tertulis.
    $after = (int)$sqlite->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
    assert($after === $before + 1);

    $log = $sqlite->query('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 1')->fetch();
    assert($log['action'] === 'export');
    assert($log['description'] === 'Mengekspor daftar aset ke berkas CSV');
    assert($log['subject_type'] === 'report');
    assert($log['ip_address'] === '127.0.0.1');
});

$check('11. Ekspor CSV aset menyebut filter di narasi log', static function () use ($service, $csvLines, $sqlite): void {
    $result = $service->assetsCsv(['status' => 'available', 'category_id' => 1]);

    // Filter ikut menyempitkan baris yang diekspor: dua aset tersedia kategori IT.
    assert(count($csvLines($result['content'])) === 2 + 2);

    $log = $sqlite->query('SELECT description FROM activity_logs ORDER BY id DESC LIMIT 1')->fetch();
    assert($log['description'] === 'Mengekspor daftar aset ke berkas CSV (filter: status=available, category_id=1)');
});

$check('12. Ekspor CSV stok memuat penanda stok menipis', static function () use ($service, $csvLines): void {
    $result = $service->stockCsv(['low_only' => 1]);

    assert($result['filename'] === 'inventra-stok-' . date('Y-m-d') . '.csv');

    $lines = $csvLines($result['content']);
    assert($lines[0] === 'Kode Barang,Nama,Kategori,Satuan,Saldo,Stok Minimum,Stok Menipis');
    assert(count($lines) === 1 + 2);

    // Nama barang memuat tanda kutip, jadi wajib digandakan di dalam CSV.
    assert($lines[1] === 'ITM-ATK-001,"Kertas A4 ""HVS""",Peralatan Kantor,Pcs,3,5,Ya');

    $all = $csvLines($service->stockCsv([])['content']);
    assert(count($all) === 2 + 2);
    assert(str_contains($all[2], ',Tidak') === true);
});

$check('13. ActivityLogDTO memetakan label aksi dan waktu, termasuk aksi tak dikenal', static function (): void {
    $row = [
        'id' => 7,
        'user_id' => 3,
        'user_name' => 'Admin GA',
        'action' => 'user.deactivate',
        'subject_type' => 'user',
        'subject_id' => 9,
        'description' => 'Menonaktifkan pengguna Mantan Staf',
        'ip_address' => '127.0.0.1',
        'created_at' => '2026-10-15 12:30:00',
    ];
    $dto = ActivityLogDTO::fromArray($row)->toArray();

    assert($dto['action'] === 'user.deactivate');
    assert($dto['action_label'] === 'Menonaktifkan pengguna');
    assert($dto['created_at_formatted'] === '15 Okt 2026 12:30');
    assert($dto['created_at'] === '2026-10-15 12:30:00');
    assert($dto['user_id'] === 3);
    assert($dto['subject_type'] === 'user');
    assert($dto['subject_id'] === 9);
    assert($dto['ip_address'] === '127.0.0.1');

    // Aksi yang belum terpetakan tetap tampil apa adanya, bukan kosong.
    $unknown = ActivityLogDTO::fromArray(['action' => 'aksi.baru', 'created_at' => '2026-01-02 03:04:05'])->toArray();
    assert($unknown['action_label'] === 'aksi.baru');
    assert($unknown['user_id'] === null);
    assert($unknown['user_name'] === null);
    assert($unknown['ip_address'] === null);
    assert($unknown['created_at_formatted'] === '02 Jan 2026 03:04');

    // Label modul lain yang memang ditulis sistem ikut terbaca manusia.
    $labels = [
        'create' => 'Menambah data',
        'update' => 'Memperbarui data',
        'delete' => 'Menghapus data',
        'check_in' => 'Serah terima masuk',
        'stock_in' => 'Stok masuk',
        'stock_out' => 'Stok keluar',
        'stock_adjustment' => 'Koreksi stok',
        'receive' => 'Menerima pengadaan',
        'cancel' => 'Membatalkan data',
        'complete' => 'Menyelesaikan data',
        'return' => 'Pengembalian',
        'login' => 'Masuk sistem',
        'login_failed' => 'Gagal masuk',
        'logout' => 'Keluar sistem',
        'user.create' => 'Menambah pengguna',
        'category.delete' => 'Menghapus kategori',
        'location.update' => 'Memperbarui lokasi',
        'supplier.create' => 'Menambah supplier',
        'employee.delete' => 'Menghapus karyawan',
        'export' => 'Ekspor laporan',
    ];

    foreach ($labels as $action => $label) {
        $mapped = ActivityLogDTO::fromArray(['action' => $action, 'created_at' => ''])->toArray();
        assert($mapped['action_label'] === $label);
    }

    // Waktu kosong tidak boleh meledak, cukup dikembalikan apa adanya.
    assert(ActivityLogDTO::fromArray([])->toArray()['created_at_formatted'] === '');
});

$check('14. Ringkasan aman saat tabel masih kosong', static function (): void {
    $empty = new PDO('sqlite::memory:');
    $empty->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $empty->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $empty->exec((string)file_get_contents(__DIR__ . '/support/schema.sqlite.sql'));

    $summary = (new ReportService(new ReportRepository($empty), '127.0.0.1'))->summary();

    assert($summary['assets_total'] === 0);
    assert($summary['total_valuation'] === 0.0);
    assert($summary['total_valuation_formatted'] === 'Rp 0');
    assert($summary['top_categories'] === []);
    assert($summary['active_loans'] === 0);
    assert($summary['overdue_loans'] === 0);
    assert($summary['activity_last_7_days'] === 0);
    assert($summary['assets_by_status'][0]['percentage'] === 0.0);
});

$check('15. Jejak audit menolak pembaca tanpa peran administrator', static function () use ($reports): void {
    $controller = new ActivityLogController($reports);
    $request = new Request('GET', '/activity-logs');

    // Dijalankan dari CLI tanpa sesi, jadi Auth::isAdmin() bernilai false.
    $rejection = static function (callable $call): string {
        try {
            $call();
        } catch (ForbiddenException $e) {
            return $e->getMessage();
        }

        return '<tidak-ditolak>';
    };

    assert($rejection(fn () => $controller->index($request)) === 'Anda tidak memiliki izin untuk melihat audit trail.');
    assert($rejection(fn () => $controller->actions()) === 'Anda tidak memiliki izin untuk melihat audit trail.');
});

echo "\n[SUCCESS] Seluruh pemeriksaan laporan dan jejak audit lulus tanpa kegagalan.\n";
