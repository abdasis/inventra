<?php

declare(strict_types=1);

/**
 * Merekam dan membandingkan hasil render halaman web.
 *
 * Setiap halaman dirender di proses terpisah lewat support/web_probe.php dengan
 * SQLite in-memory, jadi tidak butuh MySQL maupun server yang berjalan.
 * Yang dijaga: view yang dipilih, breadcrumb, judul halaman, keadaan nav aktif,
 * dan pemuatan skrip per halaman.
 *
 * Pemakaian:
 *   php apps/web/tests/page_snapshot.php --record   # tulis ulang golden
 *   php apps/web/tests/page_snapshot.php            # bandingkan dengan golden
 */

const GOLDEN_FILE = __DIR__ . '/fixtures/page-golden.json';

$admin = 1;
$staff = 2;

$scenarios = [
    'page.dashboard' => ['method' => 'GET', 'uri' => '/', 'actor' => $admin],
    'page.users.index' => ['method' => 'GET', 'uri' => '/users', 'actor' => $admin],
    'page.users.create' => ['method' => 'GET', 'uri' => '/users/create', 'actor' => $admin],
    'page.users.edit' => ['method' => 'GET', 'uri' => '/users/1/edit', 'actor' => $admin],
    'page.not-found' => ['method' => 'GET', 'uri' => '/tidak-ada', 'actor' => $admin],
    // Tanpa sesi, SEMUA path non-publik dialihkan ke login — termasuk path yang
    // tidak ada dan method yang salah. Dengan sesi, barulah 404 dirender.
    'page.not-found.unauthenticated' => ['method' => 'GET', 'uri' => '/tidak-ada'],
    'page.users.method-mismatch' => ['method' => 'POST', 'uri' => '/users', 'actor' => $admin],
    'page.users.method-mismatch.unauthenticated' => ['method' => 'POST', 'uri' => '/users'],
    // Alur login dan logout: jalur paling sensitif, sebelumnya tidak tercakup test apa pun.
    'login.post.success' => ['method' => 'POST', 'uri' => '/login', 'csrf' => true,
        'payload' => ['email' => 'admin@kantor.id', 'password' => 'Inventra#2026']],
    'login.post.wrong-password' => ['method' => 'POST', 'uri' => '/login', 'csrf' => true,
        'payload' => ['email' => 'admin@kantor.id', 'password' => 'sandi-salah']],
    'login.post.unknown-email' => ['method' => 'POST', 'uri' => '/login', 'csrf' => true,
        'payload' => ['email' => 'tidak-ada@kantor.id', 'password' => 'Inventra#2026']],
    'login.post.bad-csrf' => ['method' => 'POST', 'uri' => '/login',
        'payload' => ['email' => 'admin@kantor.id', 'password' => 'Inventra#2026']],
    'login.post.open-redirect' => ['method' => 'POST', 'uri' => '/login', 'csrf' => true,
        'payload' => ['email' => 'admin@kantor.id', 'password' => 'Inventra#2026', 'next' => '//jahat.example']],
    'logout.post' => ['method' => 'POST', 'uri' => '/logout', 'actor' => $admin, 'csrf' => true],
    // Laporan terbuka untuk semua pengguna; audit trail hanya administrator.
    'page.reports.index' => ['method' => 'GET', 'uri' => '/reports', 'actor' => $admin],
    'page.reports.index.staff' => ['method' => 'GET', 'uri' => '/reports', 'actor' => $staff],
    'page.reports.print' => ['method' => 'GET', 'uri' => '/reports/print', 'actor' => $admin],
    'page.activity-logs.index' => ['method' => 'GET', 'uri' => '/activity-logs', 'actor' => $admin],
    'page.activity-logs.staff' => ['method' => 'GET', 'uri' => '/activity-logs', 'actor' => $staff],
    'page.activity-logs.unauthenticated' => ['method' => 'GET', 'uri' => '/activity-logs'],

    // Mutasi, pemeliharaan, dan penghapusan aset.
    'page.transfers.index' => ['method' => 'GET', 'uri' => '/transfers', 'actor' => $admin],
    'page.transfers.index.staff' => ['method' => 'GET', 'uri' => '/transfers', 'actor' => $staff],
    'page.maintenances.index' => ['method' => 'GET', 'uri' => '/maintenances', 'actor' => $admin],
    'page.maintenances.create' => ['method' => 'GET', 'uri' => '/maintenances/create', 'actor' => $admin],
    'page.disposals.index' => ['method' => 'GET', 'uri' => '/disposals', 'actor' => $admin],
    'page.disposals.create' => ['method' => 'GET', 'uri' => '/disposals/create', 'actor' => $admin],
    'page.disposals.create.staff' => ['method' => 'GET', 'uri' => '/disposals/create', 'actor' => $staff],

    // Operasional aset: staf boleh melihat daftar, tulis penempatan khusus administrator.
    'page.assignments.index' => ['method' => 'GET', 'uri' => '/assignments', 'actor' => $admin],
    'page.assignments.index.staff' => ['method' => 'GET', 'uri' => '/assignments', 'actor' => $staff],
    'page.loans.index' => ['method' => 'GET', 'uri' => '/loans', 'actor' => $admin],
    'page.loans.index.staff' => ['method' => 'GET', 'uri' => '/loans', 'actor' => $staff],
    'page.loans.unauthenticated' => ['method' => 'GET', 'uri' => '/loans'],

    // Pengadaan dan stok: staf boleh membuka (read-only untuk pengadaan), sedangkan
    // pencatatan stok adalah operasi operasional.
    'page.purchases.index' => ['method' => 'GET', 'uri' => '/purchases', 'actor' => $admin],
    'page.purchases.create' => ['method' => 'GET', 'uri' => '/purchases/create', 'actor' => $admin],
    'page.purchases.edit' => ['method' => 'GET', 'uri' => '/purchases/1/edit', 'actor' => $admin],
    'page.purchases.detail' => ['method' => 'GET', 'uri' => '/purchases/1', 'actor' => $admin],
    'page.purchases.index.staff' => ['method' => 'GET', 'uri' => '/purchases', 'actor' => $staff],
    'page.purchases.unauthenticated' => ['method' => 'GET', 'uri' => '/purchases'],
    'page.stock.index' => ['method' => 'GET', 'uri' => '/stock', 'actor' => $admin],
    'page.stock.index.staff' => ['method' => 'GET', 'uri' => '/stock', 'actor' => $staff],

    // Registrasi aset: form pindah ke halaman penuh (fieldnya lebih dari lima),
    // ditambah halaman detail dengan riwayat dan halaman label QR untuk dicetak.
    'page.asset.create' => ['method' => 'GET', 'uri' => '/assets/create', 'actor' => $admin],
    'page.asset.create.staff' => ['method' => 'GET', 'uri' => '/assets/create', 'actor' => $staff],
    'page.asset.edit' => ['method' => 'GET', 'uri' => '/assets/1/edit', 'actor' => $admin],
    'page.asset.detail' => ['method' => 'GET', 'uri' => '/assets/1', 'actor' => $admin],
    'page.asset.label' => ['method' => 'GET', 'uri' => '/assets/1/label', 'actor' => $admin],
    'page.asset.create.unauthenticated' => ['method' => 'GET', 'uri' => '/assets/create'],

    // Halaman master data: shell saja, isinya lewat AJAX. Hanya administrator.
    'page.master.categories' => ['method' => 'GET', 'uri' => '/master/categories', 'actor' => $admin],
    'page.master.units' => ['method' => 'GET', 'uri' => '/master/units', 'actor' => $admin],
    'page.master.items' => ['method' => 'GET', 'uri' => '/master/items', 'actor' => $admin],
    'page.master.locations' => ['method' => 'GET', 'uri' => '/master/locations', 'actor' => $admin],
    'page.master.employees' => ['method' => 'GET', 'uri' => '/master/employees', 'actor' => $admin],
    'page.master.suppliers' => ['method' => 'GET', 'uri' => '/master/suppliers', 'actor' => $admin],
    'page.master.item.create' => ['method' => 'GET', 'uri' => '/master/items/create', 'actor' => $admin],
    'page.master.item.edit' => ['method' => 'GET', 'uri' => '/master/items/1/edit', 'actor' => $admin],
    'page.master.employee.create' => ['method' => 'GET', 'uri' => '/master/employees/create', 'actor' => $admin],
    'page.master.employee.edit' => ['method' => 'GET', 'uri' => '/master/employees/1/edit', 'actor' => $admin],
    'page.master.supplier.create' => ['method' => 'GET', 'uri' => '/master/suppliers/create', 'actor' => $admin],
    'page.master.supplier.edit' => ['method' => 'GET', 'uri' => '/master/suppliers/1/edit', 'actor' => $admin],
    'page.master.staff' => ['method' => 'GET', 'uri' => '/master/items', 'actor' => $staff],
    'page.master.unauthenticated' => ['method' => 'GET', 'uri' => '/master/items'],

    // Hanya /login dan /logout yang menghasilkan 405 untuk method salah.
    'page.logout.get' => ['method' => 'GET', 'uri' => '/logout', 'actor' => $admin],
    'page.login' => ['method' => 'GET', 'uri' => '/login'],
    'page.login.method-not-allowed' => ['method' => 'PUT', 'uri' => '/login'],
    // Tanpa sesi dan sebagai staf, halaman admin harus dialihkan tanpa merender isi.
    'page.unauthenticated' => ['method' => 'GET', 'uri' => '/users'],
    'page.users.index.staff' => ['method' => 'GET', 'uri' => '/users', 'actor' => $staff],
];

/**
 * @return array{status: int|null, html: string, stderr: string}
 */
function runScenario(array $scenario): array
{
    $command = [PHP_BINARY, __DIR__ . '/support/web_probe.php', json_encode($scenario)];
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));

    if (!is_resource($process)) {
        throw new RuntimeException('Gagal menjalankan probe.');
    }

    $stdout = (string)stream_get_contents($pipes[1]);
    $stderr = trim((string)stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $marker = strpos($stdout, '<<<STATUS:');
    if ($marker === false) {
        throw new RuntimeException("Probe tidak mencetak penanda status. stderr: $stderr");
    }

    preg_match('/<<<STATUS:([^>]*)>>>/', substr($stdout, $marker), $matches);
    $raw = $matches[1] ?? 'tidak-ada';

    $logs = null;
    if (preg_match('/<<<LOGS:(.*?)>>>/s', substr($stdout, $marker), $logMatches) === 1) {
        $decoded = json_decode($logMatches[1], true);
        $logs = $decoded === null ? $logMatches[1] : normalizeLogs($decoded);
    }

    return [
        'status' => $raw === 'tidak-ada' ? null : (int)$raw,
        'html' => normalizeHtml(substr($stdout, 0, $marker)),
        'logs' => $logs,
        'stderr' => $stderr,
    ];
}

/**
 * Samakan nilai log yang berubah tiap jalan, tanpa mengubah urutan key. ip_address
 * sengaja TIDAK dimasker karena justru itu yang ingin dijaga tetap terisi.
 *
 * @param array<int, array<string, mixed>> $logs
 *
 * @return array<int, array<string, mixed>>
 */
function normalizeLogs(array $logs): array
{
    $masked = [];

    foreach ($logs as $log) {
        if (!is_array($log)) {
            $masked[] = $log;
            continue;
        }

        foreach (['id', 'subject_id'] as $key) {
            if (array_key_exists($key, $log)) {
                $log[$key] = '<berubah>';
            }
        }

        $masked[] = $log;
    }

    return $masked;
}

/**
 * Buang bagian HTML yang memang berubah tiap jalan: token CSRF (di meta tag
 * maupun hidden field form), versi cache CSS, dan timestamp dari baris data user.
 */
function normalizeHtml(string $html): string
{
    $html = (string)preg_replace('/name="csrf-token" content="[^"]*"/', 'name="csrf-token" content="<token>"', $html);
    $html = (string)preg_replace('/name="_csrf" value="[^"]*"/', 'name="_csrf" value="<token>"', $html);
    $html = (string)preg_replace('/\?v=\d+/', '?v=<mtime>', $html);
    $html = (string)preg_replace('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', '<waktu>', $html);

    return trim($html);
}

$results = [];
$failures = 0;

foreach ($scenarios as $name => $scenario) {
    try {
        $result = runScenario($scenario);
    } catch (RuntimeException $e) {
        echo "[GAGAL] $name — {$e->getMessage()}\n";
        $results[$name] = ['status' => 'error', 'html' => null, 'logs' => null];
        $failures++;
        continue;
    }

    $results[$name] = ['status' => $result['status'], 'html' => $result['html'], 'logs' => $result['logs']];

    if ($result['stderr'] !== '') {
        echo "[PERINGATAN] $name — stderr: {$result['stderr']}\n";
    }
}

$encoded = (string)json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (in_array('--record', $_SERVER['argv'], true)) {
    if (!is_dir(dirname(GOLDEN_FILE))) {
        mkdir(dirname(GOLDEN_FILE), 0777, true);
    }

    file_put_contents(GOLDEN_FILE, $encoded . "\n");
    echo 'Golden halaman direkam: ' . count($results) . " skenario, $failures gagal.\n";
    exit($failures > 0 ? 1 : 0);
}

if (!is_file(GOLDEN_FILE)) {
    echo "Golden belum ada. Jalankan dengan --record lebih dulu.\n";
    exit(2);
}

$golden = json_decode((string)file_get_contents(GOLDEN_FILE), true);
if (!is_array($golden)) {
    echo 'Golden tidak terbaca: ' . GOLDEN_FILE . "\n";
    exit(2);
}

if ((string)json_encode($golden, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) === $encoded) {
    echo 'Hasil render halaman tidak berubah: ' . count($results) . " skenario cocok dengan golden.\n";
    exit(0);
}

echo "Hasil render halaman BERUBAH.\n";

foreach ($golden as $name => $before) {
    $after = $results[$name] ?? null;
    if ($after === $before) {
        continue;
    }

    $beforeHtml = is_array($before) ? (string)($before['html'] ?? '') : '';
    $afterHtml = is_array($after) ? (string)($after['html'] ?? '') : '';

    echo "\n--- $name ---\n";
    echo 'status: ' . json_encode($before['status'] ?? null) . ' -> ' . json_encode($after['status'] ?? null) . "\n";
    echo 'panjang html: ' . strlen($beforeHtml) . ' -> ' . strlen($afterHtml) . "\n";

    $beforeLines = explode("\n", $beforeHtml);
    $afterLines = explode("\n", $afterHtml);
    $shown = 0;
    foreach ($beforeLines as $index => $line) {
        $other = $afterLines[$index] ?? '';
        if ($line === $other) {
            continue;
        }

        echo '  baris ' . ($index + 1) . " sebelum: " . trim($line) . "\n";
        echo '  baris ' . ($index + 1) . " sesudah: " . trim($other) . "\n";

        if (++$shown >= 5) {
            echo "  (perbedaan lain tidak ditampilkan)\n";
            break;
        }
    }
}

exit(1);
