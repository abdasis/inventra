<?php

declare(strict_types=1);

namespace Inventra\Core\Database;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class Connection
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function get(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::loadEnv(__DIR__ . '/../../../../../.env');
        self::applyTimezone();

        $driver = getenv('DB_CONNECTION') ?: 'mysql';
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_DATABASE') ?: 'inventra_db';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';

        // SQLite fallback for offline unit tests without an external MySQL daemon
        if ($driver === 'sqlite') {
            $sqlitePath = $database === ':memory:' ? ':memory:' : __DIR__ . '/../../../../../' . $database;
            $pdo = new PDO('sqlite:' . $sqlitePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance = $pdo;
            return self::$instance;
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$instance = $pdo;
            return self::$instance;
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Koneksi database gagal: ' . $e->getMessage() .
                '. Periksa konfigurasi koneksi di berkas .env',
                (int)$e->getCode(),
                $e
            );
        }
    }

    public static function setInstance(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Satu-satunya pemilik begin/commit/rollback. Callback yang melempar akan
     * di-rollback lalu dilempar ulang apa adanya, supaya exception domain tetap
     * sampai ke handler transport tanpa dibungkus.
     *
     * @param callable(): mixed $fn
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::get();
        $pdo->beginTransaction();

        try {
            $result = $fn();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Menyamakan zona waktu PHP dengan MySQL dan jam dinding. PHP default-nya UTC
     * sementara MySQL memakai zona waktu server, sehingga timestamp yang ditulis
     * lewat date() akan meleset beberapa jam bila tidak disetel.
     */
    private static function applyTimezone(): void
    {
        $timezone = getenv('APP_TIMEZONE') ?: 'Asia/Jakarta';

        if (in_array($timezone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($timezone);
        }
    }

    private static function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (getenv($key) === false) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}
