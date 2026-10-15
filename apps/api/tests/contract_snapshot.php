<?php

declare(strict_types=1);

/**
 * Merekam dan membandingkan kontrak respons seluruh endpoint API.
 *
 * Setiap skenario dijalankan di proses terpisah lewat support/contract_probe.php
 * dengan SQLite in-memory, jadi tidak butuh MySQL maupun server yang berjalan.
 *
 * Pemakaian:
 *   php apps/api/tests/contract_snapshot.php --record   # tulis ulang golden
 *   php apps/api/tests/contract_snapshot.php            # bandingkan dengan golden
 *
 * Nilai yang memang berubah tiap jalan (id, timestamp, kode aset hasil generate)
 * dinormalkan sebelum dibandingkan, sedangkan urutan key dipertahankan karena
 * urutan itu bagian dari kontrak.
 */

const GOLDEN_FILE = __DIR__ . '/fixtures/contract-golden.json';

/** Key yang isinya berubah tiap jalan sehingga tidak dibandingkan. */
const VOLATILE_KEYS = [
    'id',
    'asset_id',
    'user_id',
    'subject_id',
    'created_at',
    'updated_at',
    'last_login_at',
    'last_login_formatted',
    'locked_until',
];

$admin = 1;
$staff = 2;

$scenarios = [
    'assets.index' => ['method' => 'GET', 'uri' => '/api/v1/assets', 'actor' => $admin],
    'assets.index.filtered' => ['method' => 'GET', 'uri' => '/api/v1/assets?status=available&search=Dell', 'actor' => $admin],
    'assets.show' => ['method' => 'GET', 'uri' => '/api/v1/assets/1', 'actor' => $admin],
    'assets.show.missing' => ['method' => 'GET', 'uri' => '/api/v1/assets/999', 'actor' => $admin],
    'assets.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [
            'name' => 'Laptop Uji',
            'category_id' => 1,
            'location_id' => 1,
            'serial_number' => 'SN-BARU-1',
            'purchase_date' => '2026-05-01',
            'purchase_cost' => 9000000,
            'status' => 'available',
            'condition_level' => 'new',
        ],
    ],
    'assets.store.invalid' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['name' => '   '],
    ],
    'assets.update' => [
        'method' => 'PUT',
        'uri' => '/api/v1/assets/1',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['name' => 'Dell Latitude 7420 (revisi)', 'category_id' => 1, 'location_id' => 1, 'status' => 'maintenance'],
    ],
    'assets.destroy.staff' => ['method' => 'DELETE', 'uri' => '/api/v1/assets/1', 'actor' => $staff, 'csrf' => true],
    'assets.destroy.missing.staff' => ['method' => 'DELETE', 'uri' => '/api/v1/assets/999', 'actor' => $staff, 'csrf' => true],
    'assets.destroy.admin' => ['method' => 'DELETE', 'uri' => '/api/v1/assets/1', 'actor' => $admin, 'csrf' => true],
    'assets.checkout' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets/1/checkout',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['recipient_name' => 'Budi Santoso', 'target_location_id' => 2, 'notes' => 'Untuk proyek migrasi'],
    ],
    'assets.checkout.invalid' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets/1/checkout',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [],
    ],
    'assets.checkin' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets/2/checkin',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['return_location_id' => 1, 'notes' => 'Kembali lengkap'],
    ],
    'assets.stats' => ['method' => 'GET', 'uri' => '/api/v1/stats', 'actor' => $admin],
    'assets.categories' => ['method' => 'GET', 'uri' => '/api/v1/categories', 'actor' => $admin],
    'assets.locations' => ['method' => 'GET', 'uri' => '/api/v1/locations', 'actor' => $admin],
    'users.index' => ['method' => 'GET', 'uri' => '/api/v1/users', 'actor' => $admin],
    'users.index.staff' => ['method' => 'GET', 'uri' => '/api/v1/users', 'actor' => $staff],
    'users.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/users',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [
            'name' => 'Rina Operator',
            'email' => 'rina@kantor.id',
            'password' => 'Rahasia#2026',
            'password_confirmation' => 'Rahasia#2026',
            'role' => 'staff',
            'is_active' => true,
        ],
    ],
    'users.store.invalid' => [
        'method' => 'POST',
        'uri' => '/api/v1/users',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['name' => '', 'email' => 'bukan-email', 'password' => '123', 'password_confirmation' => '456', 'role' => 'superuser'],
    ],
    'users.update' => [
        'method' => 'PUT',
        'uri' => '/api/v1/users/2',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['name' => 'Staf GA Utama', 'email' => 'staf@kantor.id', 'role' => 'staff', 'is_active' => true],
    ],
    'users.update.self-deactivate' => [
        'method' => 'PUT',
        'uri' => '/api/v1/users/1',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['name' => 'Admin GA', 'email' => 'admin@kantor.id', 'role' => 'admin', 'is_active' => false],
    ],
    'users.toggle-active' => ['method' => 'POST', 'uri' => '/api/v1/users/2/toggle-active', 'actor' => $admin, 'csrf' => true],
    'users.destroy' => ['method' => 'DELETE', 'uri' => '/api/v1/users/2', 'actor' => $admin, 'csrf' => true],
    'auth.unauthenticated' => ['method' => 'GET', 'uri' => '/api/v1/assets'],
    // Tanpa sesi DAN tanpa token: 401 harus menang atas 403.
    'auth.unauthenticated.post' => ['method' => 'POST', 'uri' => '/api/v1/assets', 'payload' => ['name' => 'Tanpa sesi']],
    'csrf.invalid' => ['method' => 'POST', 'uri' => '/api/v1/assets', 'actor' => $admin, 'payload' => ['name' => 'Tanpa token']],
    'route.not-found' => ['method' => 'GET', 'uri' => '/api/v1/tidak-ada', 'actor' => $admin],
    // Token valid supaya request benar-benar sampai ke resolusi rute.
    'route.method-mismatch' => ['method' => 'PATCH', 'uri' => '/api/v1/assets/1', 'actor' => $admin, 'csrf' => true],
];

/**
 * @return array{status: int, body: mixed, stderr: string}
 */
function runScenario(array $scenario): array
{
    $command = [PHP_BINARY, __DIR__ . '/support/contract_probe.php', json_encode($scenario)];
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

    $status = null;
    $logs = null;
    $body = $stdout;
    $marker = strpos($stdout, '<<<STATUS:');
    if ($marker !== false) {
        $tail = substr($stdout, $marker);
        preg_match('/<<<STATUS:([^>]*)>>>/', $tail, $matches);
        $raw = $matches[1] ?? 'tidak-ada';
        $status = $raw === 'tidak-ada' ? null : (int)$raw;
        $body = substr($stdout, 0, $marker);

        if (preg_match('/<<<LOGS:(.*?)>>>/s', $tail, $logMatches) === 1) {
            $decodedLogs = json_decode($logMatches[1], true);
            $logs = $decodedLogs === null ? $logMatches[1] : normalize($decodedLogs);
        }
    } else {
        throw new RuntimeException("Probe tidak mencetak penanda status. stderr: $stderr");
    }

    $decoded = json_decode(trim($body), true);

    return [
        'status' => $status,
        'body' => $decoded === null ? trim($body) : normalize($decoded),
        'logs' => $logs,
        'stderr' => $stderr,
    ];
}

/**
 * Ganti nilai yang berubah tiap jalan, tanpa mengubah urutan key.
 */
function normalize(mixed $value): mixed
{
    if (!is_array($value)) {
        return is_string($value) ? maskGeneratedCode($value) : $value;
    }

    $normalized = [];
    foreach ($value as $key => $item) {
        $normalized[$key] = is_string($key) && in_array($key, VOLATILE_KEYS, true)
            ? '<berubah>'
            : normalize($item);
    }

    return $normalized;
}

/** Kode aset yang di-generate otomatis mengikuti pola AST-<tahun>-<acak>. */
function maskGeneratedCode(string $value): string
{
    return (string)preg_replace('/AST-\d{4}-[A-Z0-9]{5}/', '<kode-otomatis>', $value);
}

$results = [];
$failures = 0;

foreach ($scenarios as $name => $scenario) {
    try {
        $result = runScenario($scenario);
    } catch (RuntimeException $e) {
        echo "[GAGAL] $name — {$e->getMessage()}\n";
        $results[$name] = ['status' => 'error', 'body' => null];
        $failures++;
        continue;
    }

    $results[$name] = ['status' => $result['status'], 'body' => $result['body'], 'logs' => $result['logs']];

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
    echo 'Golden direkam: ' . count($results) . " skenario, $failures gagal.\n";
    exit($failures > 0 ? 1 : 0);
}

if (!is_file(GOLDEN_FILE)) {
    echo "Golden belum ada. Jalankan dengan --record lebih dulu.\n";
    exit(2);
}

$golden = json_decode((string)file_get_contents(GOLDEN_FILE), true);
if (!is_array($golden)) {
    echo "Golden tidak terbaca: " . GOLDEN_FILE . "\n";
    exit(2);
}

$expected = (string)json_encode($golden, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($expected === $encoded) {
    echo 'Kontrak respons tidak berubah: ' . count($results) . " skenario cocok dengan golden.\n";
    exit(0);
}

echo "Kontrak respons BERUBAH.\n";

foreach ($golden as $name => $before) {
    $after = $results[$name] ?? null;
    if ($after === $before) {
        continue;
    }

    echo "\n--- $name ---\n";
    echo 'sebelum: ' . json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    echo 'sesudah: ' . json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

$missing = array_diff(array_keys($results), array_keys($golden));
foreach ($missing as $name) {
    echo "\n--- $name (baru, belum ada di golden) ---\n";
}

exit(1);
