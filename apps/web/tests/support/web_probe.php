<?php

declare(strict_types=1);

/**
 * Merender satu halaman web di proses terpisah lalu mencetak HTML-nya.
 * Dipakai apps/web/tests/page_snapshot.php untuk merekam dan membandingkan
 * hasil render halaman.
 *
 * Skenario dikirim lewat argumen pertama dalam bentuk JSON.
 * Pemakaian: php web_probe.php '<json skenario>'
 */

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Inventra\Core\Auth\Csrf;
use Inventra\Core\Database\Connection;

ini_set('display_errors', 'stderr');

$scenario = json_decode((string)($_SERVER['argv'][1] ?? ''), true);
if (!is_array($scenario) || !isset($scenario['method'], $scenario['uri'])) {
    fwrite(STDERR, "Skenario tidak valid.\n");
    exit(2);
}

// Skema SQLite dipakai bersama dengan harness API supaya DDL tidak terduplikasi.
$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$sqlite->exec((string)file_get_contents(__DIR__ . '/../../../api/tests/support/schema.sqlite.sql'));

$hash = password_hash('Inventra#2026', PASSWORD_DEFAULT);
$sqlite->exec("
    INSERT INTO users (id, name, email, password_hash, role, is_active)
    VALUES (1, 'Admin GA', 'admin@kantor.id', '$hash', 'admin', 1),
           (2, 'Staf GA', 'staf@kantor.id', '$hash', 'staff', 1)
");

Connection::setInstance($sqlite);

$_SERVER['REQUEST_METHOD'] = strtoupper((string)$scenario['method']);
$_SERVER['REQUEST_URI'] = (string)$scenario['uri'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost:8090';

$_GET = [];
parse_str((string)(parse_url((string)$scenario['uri'], PHP_URL_QUERY) ?? ''), $_GET);
$_POST = is_array($scenario['payload'] ?? null) ? $scenario['payload'] : [];

// Sesi disiapkan lebih dulu: Auth::start() idempoten selama sesi sudah aktif.
session_start();
$_SESSION = [];
if (!empty($scenario['actor'])) {
    $_SESSION['user_id'] = (int)$scenario['actor'];
}

// Token asli supaya jalur CSRF lolos tanpa memalsukan apa pun.
if (!empty($scenario['csrf'])) {
    $_POST['_csrf'] = Csrf::token();
}

register_shutdown_function(static function (): void {
    $code = http_response_code();

    fwrite(STDOUT, "\n<<<STATUS:" . ($code === false ? 'tidak-ada' : $code) . ">>>");

    // Log aktivitas ikut direkam: alur login dan logout juga wajib meninggalkan
    // jejak audit dengan narasi dan ip_address yang terisi.
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
