<?php

declare(strict_types=1);

/**
 * Menjalankan satu request API di proses terpisah lalu mencetak kontrak responsnya.
 * Dipakai apps/api/tests/contract_snapshot.php untuk merekam dan membandingkan respons.
 *
 * Skenario dikirim lewat argumen pertama dalam bentuk JSON.
 *
 * Catatan: php://input tidak tersedia di SAPI CLI, jadi body request diserahkan
 * lewat $_POST — jalur fallback yang memang dipakai controller saat body mentah
 * kosong. Jalur decode JSON diuji terpisah di run_tests.php.
 *
 * Pemakaian: php contract_probe.php '<json skenario>'
 *
 * Keluaran: body respons apa adanya, lalu penanda status di akhir.
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Inventra\Core\Auth\Csrf;
use Inventra\Core\Database\Connection;

// Warning dan notice diarahkan ke stderr supaya stdout tetap JSON murni.
ini_set('display_errors', 'stderr');

$scenario = json_decode((string)($_SERVER['argv'][1] ?? ''), true);
if (!is_array($scenario) || !isset($scenario['method'], $scenario['uri'])) {
    fwrite(STDERR, "Skenario tidak valid.\n");
    exit(2);
}

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$sqlite->exec((string)file_get_contents(__DIR__ . '/schema.sqlite.sql'));

// Seed tetap supaya kontrak respons deterministik. Password hash boleh berbeda
// tiap jalan karena hash tidak pernah ikut terkirim ke klien.
$sqlite->exec("INSERT INTO categories (code, name, description) VALUES ('CAT-IT', 'Perangkat IT', 'Laptop dan aksesoris')");
$sqlite->exec("INSERT INTO locations (code, name, building, floor) VALUES ('LOC-GUG', 'Gudang', 'A', '1'), ('LOC-DEV', 'Ruang IT', 'A', '2')");
$sqlite->exec("
    INSERT INTO assets (asset_code, name, category_id, location_id, serial_number, purchase_date, purchase_cost, status, condition_level, assigned_to, notes)
    VALUES ('AST-FIX-001', 'Dell Latitude 7420', 1, 1, 'SN-12345', '2026-03-01', 15000000, 'available', 'good', NULL, 'Aset contoh'),
           ('AST-FIX-002', 'Kursi Ergonomis', 1, 2, 'SN-99999', '2026-04-01', 2500000, 'in_use', 'fair', 'Budi', NULL)
");
$hash = password_hash('Inventra#2026', PASSWORD_DEFAULT);
$sqlite->exec("
    INSERT INTO users (id, name, email, password_hash, role, is_active)
    VALUES (1, 'Admin GA', 'admin@kantor.id', '$hash', 'admin', 1),
           (2, 'Staf GA', 'staf@kantor.id', '$hash', 'staff', 1)
");

// Master data: satuan, barang, karyawan, dan supplier tetap supaya kontrak
// respons endpoint master (termasuk penjaga hapus) deterministik.
$sqlite->exec("
    INSERT INTO units (code, name, description)
    VALUES ('PCS', 'Pcs', 'Satuan per buah'), ('RIM', 'Rim', 'Satuan per rim kertas')
");
$sqlite->exec("
    INSERT INTO items (code, name, brand, model, specification, category_id, unit_id, item_type, minimum_stock, description)
    VALUES ('ITM-IT-001', 'Laptop Standar', 'Lenovo', 'ThinkPad E14', 'Core i5 16GB', 1, 1, 'asset', 0, 'Laptop operasional'),
           ('ITM-ATK-001', 'Kertas A4 HVS 80gsm', NULL, NULL, NULL, 1, 2, 'consumable', 10, 'Stok ATK')
");
$sqlite->exec("
    INSERT INTO employees (employee_number, name, department, position, email, phone, is_active)
    VALUES ('EMP-001', 'Ahmad Farhan', 'IT', 'Developer', 'ahmad@kantor.id', '0811000001', 1)
");
$sqlite->exec("
    INSERT INTO suppliers (code, name, contact_person, phone, email, address, is_active)
    VALUES ('SUP-001', 'PT Sumber Komputer', 'Rina', '021-5550001', 'sales@sumberkomputer.id', 'Jl. Merdeka 1', 1)
");
// Riwayat mutasi tetap supaya kontrak detail aset (riwayat) dan statistik dashboard
// deterministik; aset 2 sudah berstatus in_use dengan pemegang Budi.
$sqlite->exec("
    INSERT INTO asset_movements (asset_id, movement_type, from_location_id, to_location_id, actor_name, recipient_name, notes)
    VALUES (2, 'check_out', 1, 2, 'Admin GA', 'Budi', 'Serah terima untuk proyek migrasi')
");
// Pengadaan dan stok: satu pembelian draft berisi barang habis pakai + barang tetap,
// satu pembelian untuk penerimaan aset, satu yang sudah diterima (untuk uji penolakan
// ubah/hapus/batal), dan satu draft untuk dibatalkan atau dihapus.
$sqlite->exec("
    INSERT INTO purchases (id, purchase_number, supplier_id, purchase_date, status, reference, notes, total_amount, created_by_name, received_at)
    VALUES (1, 'PO-FIX-001', 1, '2026-05-01', 'draft', 'INV-FIX-1', 'Pembelian contoh ATK', 12550000, 'Admin GA', NULL),
           (2, 'PO-FIX-002', 1, '2026-05-01', 'draft', NULL, 'Pembelian contoh aset', 24000000, 'Admin GA', NULL),
           (3, 'PO-FIX-003', 1, '2026-05-01', 'received', NULL, 'Pembelian yang sudah diterima', 100000, 'Admin GA', '2026-05-02 10:00:00'),
           (4, 'PO-FIX-004', 1, '2026-05-01', 'draft', NULL, 'Pembelian untuk dibatalkan', 100000, 'Admin GA', NULL)
");
$sqlite->exec("
    INSERT INTO purchase_details (id, purchase_id, item_id, quantity_ordered, quantity_received, quantity_rejected, unit_price)
    VALUES (1, 1, 2, 10, 0, 0, 55000),
           (2, 1, 1, 1, 0, 0, 12000000),
           (3, 2, 1, 2, 0, 0, 12000000),
           (4, 3, 2, 5, 5, 0, 20000),
           (5, 4, 2, 5, 0, 0, 20000)
");
// Saldo awal sengaja di bawah stok minimum (10) supaya daftar stok rendah terisi.
$sqlite->exec("INSERT INTO stock_balances (item_id, quantity) VALUES (2, 4)");
$sqlite->exec("
    INSERT INTO stock_movements (item_id, movement_type, quantity, balance_after, reference_type, reference_id, actor_name, reason)
    VALUES (2, 'in', 4, 4, 'purchase', 3, 'Admin GA', 'Penerimaan awal')
");
// Operasional aset: satu aset dengan penempatan aktif, satu sedang dipinjam, dan
// satu dalam perbaikan — tiga keadaan yang dipakai menguji penolakan transaksi.
$sqlite->exec("
    INSERT INTO assets (id, asset_code, name, category_id, location_id, serial_number, purchase_date, purchase_cost, status, condition_level, assigned_to, notes)
    VALUES (3, 'AST-FIX-003', 'Proyektor Uji', 1, 2, 'SN-33333', '2026-03-01', 5000000, 'in_use', 'good', 'Ahmad Farhan', 'Aset dengan penempatan aktif'),
           (4, 'AST-FIX-004', 'Kamera Uji', 1, 2, 'SN-44444', '2026-03-01', 7000000, 'on_loan', 'good', 'Ahmad Farhan', 'Aset yang sedang dipinjam'),
           (5, 'AST-FIX-005', 'Printer Perbaikan Uji', 1, 1, 'SN-55555', '2026-03-01', 3000000, 'maintenance', 'damaged', NULL, 'Aset dalam perbaikan')
");
$sqlite->exec("
    INSERT INTO asset_assignments (id, asset_id, employee_id, location_id, assigned_at, condition_out, handed_by_name, received_by_name, status, notes)
    VALUES (1, 3, 1, 2, '2026-05-01 09:00:00', 'good', 'Admin GA', 'Ahmad Farhan', 'active', 'Penempatan contoh')
");
$sqlite->exec("
    INSERT INTO asset_loans (id, asset_id, employee_id, location_id, loaned_at, due_at, condition_out, handed_by_name, received_by_name, purpose, status, notes)
    VALUES (1, 4, 1, 2, '2026-05-01 09:00:00', '2026-05-10', 'good', 'Admin GA', 'Ahmad Farhan', 'Proyek audit', 'active', 'Peminjaman contoh')
");
// Mutasi, pemeliharaan, dan penghapusan: satu aset yang sudah dihapus, satu mutasi
// tertunda, dan satu pemeliharaan berjalan — dipakai menguji penolakan transaksi.
$sqlite->exec("
    INSERT INTO assets (id, asset_code, name, category_id, location_id, serial_number, purchase_date, purchase_cost, status, condition_level, assigned_to, notes)
    VALUES (6, 'AST-FIX-006', 'Meja Rusak Uji', 1, 1, 'SN-66666', '2023-01-01', 1500000, 'disposed', 'damaged', NULL, 'Aset yang sudah dihapus')
");
$sqlite->exec("
    INSERT INTO asset_transfers (id, asset_id, from_location_id, to_location_id, transfer_date, reason, status, executed_by_name, notes)
    VALUES (1, 5, 1, 2, '2026-05-02', 'Perlu perbaikan di ruang servis', 'pending', 'Admin GA', 'Mutasi contoh')
");
$sqlite->exec("
    INSERT INTO asset_maintenances (id, asset_id, maintenance_type, maintenance_date, vendor, cost, damage_description, status, performed_by_name, notes)
    VALUES (1, 5, 'corrective', '2026-05-02', 'CV Servis Elektronik', 750000, 'Modul daya tidak menyala', 'ongoing', 'Admin GA', 'Pemeliharaan contoh'),
           (2, 1, 'preventive', '2026-03-02', 'CV Servis Elektronik', 150000, 'Pengecekan berkala', 'completed', 'Admin GA', 'Pemeliharaan yang sudah selesai')
");
$sqlite->exec("
    INSERT INTO asset_disposals (id, asset_id, disposal_date, reason, method, book_value, document_reference, approved_by_name, executed_by_name, notes)
    VALUES (1, 6, '2026-04-01', 'Rusak berat dan tidak ekonomis diperbaiki', 'destroyed', 0, 'BA-2026-004', 'Direktur Operasional', 'Admin GA', 'Penghapusan contoh')
");
// Jejak aktivitas tetap supaya daftar audit trail dan ekspornya deterministik.
$sqlite->exec("
    INSERT INTO activity_logs (id, user_id, user_name, action, subject_type, subject_id, description, ip_address, created_at)
    VALUES (1, 1, 'Admin GA', 'create', 'asset', 1, 'Menambahkan aset Dell Latitude 7420 (AST-FIX-001)', '127.0.0.1', '2026-05-01 08:00:00'),
           (2, 1, 'Admin GA', 'user.create', 'user', 2, 'Menambahkan pengguna Staf GA (staf@kantor.id) dengan peran Staf', '127.0.0.1', '2026-05-02 09:30:00')
");

Connection::setInstance($sqlite);

$method = strtoupper((string)$scenario['method']);
$uri = (string)$scenario['uri'];

$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['REQUEST_URI'] = $uri;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$_GET = [];
parse_str((string)(parse_url($uri, PHP_URL_QUERY) ?? ''), $_GET);

$_POST = is_array($scenario['payload'] ?? null) ? $scenario['payload'] : [];

// Sesi disiapkan sebelum front controller dipanggil: Auth::start() idempoten
// selama status sesi sudah aktif, jadi $_SESSION di bawah bertahan.
session_start();
$_SESSION = [];
if (!empty($scenario['actor'])) {
    $_SESSION['user_id'] = (int)$scenario['actor'];
}

$_SERVER['HTTP_X_CSRF_TOKEN'] = '';
if ($method !== 'GET') {
    // Token asli bila skenario mengharapkan jalur CSRF lolos, token salah bila tidak.
    $_SERVER['HTTP_X_CSRF_TOKEN'] = !empty($scenario['csrf'])
        ? Csrf::token()
        : 'token-tidak-valid';
}

register_shutdown_function(static function (): void {
    // http_response_code() mengembalikan false bila tidak ada kode yang pernah
    // diset — itu sendiri bagian dari kontrak yang perlu terekam.
    $code = http_response_code();

    fwrite(STDOUT, "\n<<<STATUS:" . ($code === false ? 'tidak-ada' : $code) . ">>>");

    // Log aktivitas ikut direkam karena narasinya bagian dari aturan wajib
    // proyek: causer, aksi, subject, dan ip_address harus tetap terisi.
    $logs = [];
    try {
        $statement = Connection::get()->query(
            'SELECT action, subject_type, subject_id, description, ip_address FROM activity_logs ORDER BY id'
        );
        if ($statement !== false) {
            $logs = $statement->fetchAll();
        }
    } catch (Throwable $error) {
        $logs = ['error' => $error->getMessage()];
    }

    fwrite(STDOUT, "\n<<<LOGS:" . json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ">>>");
});

require __DIR__ . '/../../public/index.php';
