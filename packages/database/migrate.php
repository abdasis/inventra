<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Inventra\Core\Database\Connection;

$seed = in_array('--seed', $argv, true);

echo "[*] Connecting to database...\n";

try {
    $db = Connection::get();
} catch (Throwable $e) {
    echo "[-] Connection error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[+] Database connected successfully.\n";

$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    echo "[*] Running migration: " . basename($file) . "...\n";
    $sql = file_get_contents($file);
    if ($sql !== false) {
        $db->exec($sql);
    }
}

echo "[+] All migrations applied successfully.\n";

if ($seed) {
    $seedersDir = __DIR__ . '/seeders';
    $seedFiles = glob($seedersDir . '/*.sql');
    sort($seedFiles);

    foreach ($seedFiles as $sfile) {
        echo "[*] Running seeder: " . basename($sfile) . "...\n";
        $sql = file_get_contents($sfile);
        if ($sql !== false) {
            $db->exec($sql);
        }
    }
    echo "[+] Seeders executed successfully.\n";
}

echo "[DONE] Database is up to date.\n";
