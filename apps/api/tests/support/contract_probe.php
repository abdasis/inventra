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
