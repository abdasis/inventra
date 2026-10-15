<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\User\GuardLastActiveAdminAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\UserDTO;
use Inventra\Core\Enums\UserRole;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\UserRepository;
use Inventra\Core\Validation\UserInput;

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$sqlite->exec('CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT "staff", is_active INTEGER NOT NULL DEFAULT 1,
    failed_login_attempts INTEGER NOT NULL DEFAULT 0, locked_until TEXT, last_login_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
)');
Connection::setInstance($sqlite);

$userRepository = new UserRepository($sqlite);
$userInput = new UserInput($userRepository);
$lastActiveAdmin = new GuardLastActiveAdminAction($userRepository);

$adminId = $userRepository->create('Budi', 'budi@kantor.id', password_hash('rahasia123', PASSWORD_DEFAULT), UserRole::Admin);
$userRepository->markLogin($adminId, '2026-10-15 09:20:00');
$staffId = $userRepository->create('Siti', 'siti@kantor.id', password_hash('rahasia123', PASSWORD_DEFAULT), UserRole::Staff, false);

// --- normalisasi payload
$norm = $userInput->normalize(['name' => '  Budi  ', 'email' => 'b@k.id', 'role' => 'admin', 'is_active' => 'false', 'password' => ' aa ']);
assert($norm['name'] === 'Budi');
assert($norm['is_active'] === false);
assert($norm['password'] === ' aa ');
assert($userInput->normalize([])['is_active'] === true);
assert($userInput->normalize(['is_active' => '0'])['is_active'] === false);
assert($userInput->normalize(['is_active' => false])['is_active'] === false);

// --- validasi
assert(isset($userInput->validate(['name' => '', 'email' => '', 'role' => 'x', 'password' => '', 'password_confirmation' => ''], null)['name']));
$bad = $userInput->validate(['name' => 'A', 'email' => 'bukan-email', 'role' => 'staff', 'password' => '12345678', 'password_confirmation' => '12345678'], null);
assert($bad['email'] === 'Format email tidak valid.');
$dup = $userInput->validate(['name' => 'A', 'email' => 'budi@kantor.id', 'role' => 'staff', 'password' => '12345678', 'password_confirmation' => '12345678'], null);
assert($dup['email'] === 'Email tersebut sudah dipakai pengguna lain.');
$same = $userInput->validate(['name' => 'A', 'email' => 'budi@kantor.id', 'role' => 'staff', 'password' => '12345678', 'password_confirmation' => '12345678'], $adminId);
assert(!isset($same['email']));
$short = $userInput->validate(['name' => 'A', 'email' => 'a@k.id', 'role' => 'staff', 'password' => '1234567', 'password_confirmation' => '1234567'], null);
assert($short['password'] === 'Kata sandi minimal 8 karakter.');
$mismatch = $userInput->validate(['name' => 'A', 'email' => 'a@k.id', 'role' => 'staff', 'password' => '12345678', 'password_confirmation' => '87654321'], null);
assert($mismatch['password'] === 'Konfirmasi kata sandi tidak cocok.');
// update tanpa kata sandi: lolos
$editNoPass = $userInput->validate(['name' => 'A', 'email' => 'a@k.id', 'role' => 'staff', 'password' => '', 'password_confirmation' => ''], $adminId);
assert($editNoPass === [], 'update tanpa kata sandi harus lolos');
// create tanpa kata sandi: gagal
$createNoPass = $userInput->validate(['name' => 'A', 'email' => 'a@k.id', 'role' => 'staff', 'password' => '', 'password_confirmation' => ''], null);
assert(isset($createNoPass['password']));
// role tak dikenal
$badRole = $userInput->validate(['name' => 'A', 'email' => 'a@k.id', 'role' => 'owner', 'password' => '12345678', 'password_confirmation' => '12345678'], null);
assert($badRole['role'] === 'Peran pengguna tidak valid.');

// --- validated() mengubah peta error menjadi ValidationException 422
$thrown = null;
try {
    $userInput->validated(['name' => '', 'email' => '', 'role' => 'x', 'password' => '', 'password_confirmation' => ''], null);
} catch (ValidationException $e) {
    $thrown = $e;
}

assert($thrown instanceof ValidationException, 'validated() harus melempar ValidationException');
assert($thrown->status() === 422);
assert($thrown->getMessage() === 'Nama pengguna wajib diisi.', 'message diambil dari error field pertama');
assert($thrown->errors()['email'] === 'Email wajib diisi.');

// --- presentasi
$p = UserDTO::fromRow($userRepository->find($adminId));
$expected = ['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at', 'updated_at', 'role_label', 'role_badge_class', 'status_label', 'last_login_formatted'];
assert(array_keys($p) === $expected, 'key response tidak sesuai kontrak');
assert($p['is_active'] === true);
assert($p['role'] === 'admin');
assert($p['role_label'] === 'Administrator');
assert($p['status_label'] === 'Aktif');
assert($p['last_login_formatted'] === '15 Okt 2026 09:20');
assert(!array_key_exists('password_hash', $p));

$p2 = UserDTO::fromRow($userRepository->find($staffId));
assert($p2['is_active'] === false);
assert($p2['status_label'] === 'Nonaktif');
assert($p2['role_label'] === 'Staf');
assert($p2['last_login_formatted'] === null);

// --- aturan bisnis
assert($lastActiveAdmin->isLastActiveAdmin($userRepository->find($adminId)) === true, 'satu-satunya admin aktif = admin terakhir');
$userRepository->update($staffId, 'Siti', 'siti@kantor.id', UserRole::Admin, true);
assert($userRepository->countActiveAdmins() === 2);
assert($lastActiveAdmin->isLastActiveAdmin($userRepository->find($adminId)) === false, 'dua admin aktif = bukan terakhir');
$userRepository->update($staffId, 'Siti', 'siti@kantor.id', UserRole::Staff, false);
assert($lastActiveAdmin->isLastActiveAdmin($userRepository->find($staffId)) === false, 'staf nonaktif bukan admin terakhir');

echo '[OK] User domain contract self-check passed.' . PHP_EOL;
