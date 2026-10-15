<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Actions\Asset\CheckInAssetAction;
use Inventra\Core\Actions\Asset\CheckOutAssetAction;
use Inventra\Core\Actions\User\DeleteUserAction;
use Inventra\Core\Actions\User\GuardLastActiveAdminAction;
use Inventra\Core\Actions\User\ToggleUserActiveAction;
use Inventra\Core\Actions\User\UpdateUserAction;
use Inventra\Core\Auth\Csrf;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Enums\UserRole;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ActivityLogRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\CategoryRepository;
use Inventra\Core\Repositories\LocationRepository;
use Inventra\Core\Repositories\MovementRepository;
use Inventra\Core\Repositories\UserRepository;

echo "=== Running Inventra Core Self-Check Tests ===\n\n";

// 1. Test DTO & Enums
echo "[TEST 1] Testing AssetDTO and Enums... ";
$dto = AssetDTO::fromArray([
    'asset_code' => 'TEST-001',
    'name' => 'Dell Latitude 7420',
    'category_id' => 1,
    'location_id' => 2,
    'serial_number' => 'SN-12345',
    'purchase_date' => '2026-03-01',
    'purchase_cost' => 15000000,
    'status' => 'available',
    'condition_level' => 'good',
]);

assert($dto->assetCode === 'TEST-001');
assert($dto->name === 'Dell Latitude 7420');
assert($dto->status === AssetStatus::Available);
assert($dto->conditionLevel === AssetCondition::Good);
$arr = $dto->toArray();
assert($arr['status_label'] === 'Tersedia');
assert($arr['purchase_cost_formatted'] === 'Rp 15.000.000');
echo "PASSED\n";

// 2. Test in-memory SQLite schema and model workflow
echo "[TEST 2] Testing Database, AssetRepository CRUD, and Checkout Workflow... ";
$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Skema SQLite dipakai bersama dengan support/contract_probe.php supaya DDL
// tidak terduplikasi antar berkas test.
$sqlite->exec((string)file_get_contents(__DIR__ . '/support/schema.sqlite.sql'));

$sqlite->exec("INSERT INTO categories (code, name) VALUES ('CAT-IT', 'IT'), ('CAT-FRN', 'Furniture')");
$sqlite->exec("INSERT INTO locations (code, name, building, floor) VALUES ('LOC-GUG', 'Gudang', 'A', '1'), ('LOC-DEV', 'Ruang IT', 'A', '2')");

Connection::setInstance($sqlite);

$assetRepository = new AssetRepository($sqlite);
$movementRepository = new MovementRepository($sqlite);

// Create
$id = $assetRepository->create($dto);
assert($id > 0);

// Find
$found = $assetRepository->find($id);
assert($found !== null);
assert($found['name'] === 'Dell Latitude 7420');
assert($found['status'] === 'available');

// Checkout. Status, aktor, dan lokasi asal dikirim eksplisit oleh pemanggil.
$checkedOut = $assetRepository->checkOut($id, 'Aziz Developer', 2, 'Admin GA', AssetStatus::InUse, 2, 'Project Work');
assert($checkedOut === true);

$afterCheckout = $assetRepository->find($id);
assert($afterCheckout['status'] === 'in_use');
assert($afterCheckout['assigned_to'] === 'Aziz Developer');
assert((int)$afterCheckout['location_id'] === 2);

// Stats
$stats = $assetRepository->getStats();
assert($stats['total_assets'] === 1);
assert($stats['count_in_use'] === 1);
assert($stats['count_available'] === 0);
assert($stats['total_valuation'] == 15000000.0);

// Check-in
$checkedIn = $assetRepository->checkIn($id, 1, 'Admin GA', AssetStatus::Available, 2, 'Aziz Developer', 'Returned cleanly');
assert($checkedIn === true);

$afterCheckin = $assetRepository->find($id);
assert($afterCheckin['status'] === 'available');
assert($afterCheckin['assigned_to'] === null);
assert((int)$afterCheckin['location_id'] === 1);

// Movements log: penerima saat checkin adalah pemegang sebelumnya.
$recent = $movementRepository->getRecent(5);
assert(count($recent) === 2);
assert($recent[0]['movement_type'] === 'check_in');
assert($recent[0]['recipient_name'] === 'Aziz Developer');
assert((int)$recent[0]['from_location_id'] === 2);
assert((int)$recent[0]['to_location_id'] === 1);
assert($recent[1]['movement_type'] === 'check_out');
assert((int)$recent[1]['from_location_id'] === 2);
assert((int)$recent[1]['to_location_id'] === 2);

echo "PASSED\n";

// 3. Auth foundation: password hashing, CSRF, UserRole, UserRepository, ActivityLogRepository
echo "[TEST 3] Testing Auth foundation (hashing, CSRF, users, activity log)... ";

// Tabel users dan activity_logs sudah ikut terbuat dari skema bersama di atas.

// Password hashing roundtrip
$hash = password_hash('Inventra#2026', PASSWORD_DEFAULT);
assert(password_verify('Inventra#2026', $hash) === true);
assert(password_verify('kata-sandi-salah', $hash) === false);

// CSRF token lifecycle
$_SESSION = [];
$token = Csrf::token();
assert($token !== '');
assert(Csrf::validate($token) === true);
assert(Csrf::validate('token-palsu') === false);
assert(Csrf::validate(null) === false);
assert(Csrf::validate('') === false);
assert(str_contains(Csrf::field(), $token) === true);
Csrf::rotate();
assert(Csrf::validate($token) === false);
assert(Csrf::validate(Csrf::token()) === true);

// UserRole
assert(UserRole::Admin->isAdmin() === true);
assert(UserRole::Staff->isAdmin() === false);
assert(UserRole::Admin->label() !== '');
assert(UserRole::Staff->label() !== '');
assert(UserRole::Admin->badgeClass() !== '');

// UserRepository
$userRepository = new UserRepository($sqlite);
$adminId = $userRepository->create('Administrator Inventra', 'admin@kantor.id', $hash, UserRole::Admin);
assert($adminId > 0);

$foundUser = $userRepository->find($adminId);
assert($foundUser !== null);
assert(array_key_exists('password_hash', $foundUser) === false);
assert($foundUser['email'] === 'admin@kantor.id');
assert($foundUser['role'] === 'admin');
assert((int)$foundUser['is_active'] === 1);

$byEmail = $userRepository->findByEmail('admin@kantor.id');
assert($byEmail !== null);
assert(isset($byEmail['password_hash']) === true);
assert(password_verify('Inventra#2026', $byEmail['password_hash']) === true);
assert(array_key_exists('locked_until', $byEmail) === true);

$staffId = $userRepository->create(
    'Staf Inventra',
    'staf@kantor.id',
    password_hash('Staf#2026', PASSWORD_DEFAULT),
    UserRole::Staff
);
assert($staffId > 0);

assert($userRepository->emailExists('admin@kantor.id') === true);
assert($userRepository->emailExists('admin@kantor.id', $adminId) === false);
assert($userRepository->emailExists('admin@kantor.id', $staffId) === true);
assert($userRepository->emailExists('tidak-ada@kantor.id') === false);

assert($userRepository->countActiveAdmins() === 1);
assert($userRepository->update($staffId, 'Staf Operasional', 'staf@kantor.id', UserRole::Admin, true) === true);
assert($userRepository->countActiveAdmins() === 2);
$updatedStaff = $userRepository->find($staffId);
assert($updatedStaff['name'] === 'Staf Operasional');
assert($updatedStaff['role'] === 'admin');

$newHash = password_hash('Staf#2027', PASSWORD_DEFAULT);
assert($userRepository->updatePassword($staffId, $newHash) === true);
$staffByEmail = $userRepository->findByEmail('staf@kantor.id');
assert(password_verify('Staf#2027', $staffByEmail['password_hash']) === true);
assert(password_verify('Staf#2026', $staffByEmail['password_hash']) === false);

// markLogin mengisi last_login_at sekaligus mereset penghitung dan kunci
assert($userRepository->recordFailedLogin($adminId, 2, null) === true);
assert($userRepository->markLogin($adminId, '2026-04-01 08:30:00') === true);
$loggedIn = $userRepository->find($adminId);
assert($loggedIn['last_login_at'] === '2026-04-01 08:30:00');
$loggedInFull = $userRepository->findByEmail('admin@kantor.id');
assert((int)$loggedInFull['failed_login_attempts'] === 0);
assert($loggedInFull['locked_until'] === null);

assert($userRepository->recordFailedLogin($adminId, 3, '2026-04-01 08:45:00') === true);
$throttled = $userRepository->findByEmail('admin@kantor.id');
assert((int)$throttled['failed_login_attempts'] === 3);
assert($throttled['locked_until'] === '2026-04-01 08:45:00');

assert($userRepository->markLogin($adminId, '2026-04-01 09:00:00') === true);
$reset = $userRepository->findByEmail('admin@kantor.id');
assert((int)$reset['failed_login_attempts'] === 0);
assert($reset['locked_until'] === null);

assert(count($userRepository->all()) === 2);

// ActivityLogRepository
$logRepository = new ActivityLogRepository($sqlite);
$logId = $logRepository->record([
    'user_id' => $adminId,
    'user_name' => 'Administrator Inventra',
    'action' => 'create_user',
    'subject_type' => 'user',
    'subject_id' => $staffId,
    'description' => 'Menambahkan pengguna Staf Operasional (staf@kantor.id) dengan peran staf',
    'created_at' => '2026-04-01 09:15:00',
]);
assert($logId > 0);

$secondLogId = $logRepository->record([
    'action' => 'logout',
    'description' => 'Administrator Inventra keluar dari sistem',
    'created_at' => '2026-04-02 10:00:00',
]);
assert($secondLogId > $logId);

$recentLogs = $logRepository->recent(5);
assert(count($recentLogs) === 2);
assert((int)$recentLogs[0]['id'] === $secondLogId);
assert($recentLogs[0]['description'] === 'Administrator Inventra keluar dari sistem');
assert($recentLogs[0]['user_name'] === null);
assert($recentLogs[1]['description'] === 'Menambahkan pengguna Staf Operasional (staf@kantor.id) dengan peran staf');

assert($userRepository->delete($staffId) === true);
assert($userRepository->find($staffId) === null);

echo "PASSED\n";

// 4. Aturan domain pengguna (admin aktif terakhir, akun sendiri) dan transisi status aset
echo "[TEST 4] Testing user domain rules and asset status transitions... ";

$lastActiveAdmin = new GuardLastActiveAdminAction($userRepository);
$updateUser = new UpdateUserAction($userRepository, $lastActiveAdmin);
$deleteUser = new DeleteUserAction($userRepository, $lastActiveAdmin);
$toggleUser = new ToggleUserActiveAction($userRepository, $lastActiveAdmin);

/** Pesan ValidationException, atau penanda bila aksinya ternyata tidak ditolak. */
$rejection = static function (callable $action): string {
    try {
        $action();
    } catch (ValidationException $e) {
        return $e->getMessage();
    }

    return '<tidak-ditolak>';
};

// Hanya adminId yang tersisa setelah TEST 3, jadi dia administrator aktif terakhir.
$adminRow = $userRepository->find($adminId);
assert($adminRow !== null);
assert($userRepository->countActiveAdmins() === 1);
assert($lastActiveAdmin->isLastActiveAdmin($adminRow) === true);

$adminInput = [
    'name' => 'Administrator Inventra',
    'email' => 'admin@kantor.id',
    'role' => 'staff',
    'is_active' => true,
    'password' => '',
    'password_confirmation' => '',
];

// Turun peran, dinonaktifkan, atau dihapus: semuanya ditolak selama dia satu-satunya admin aktif.
assert($rejection(fn () => $updateUser->execute($adminRow, $adminInput, 999)) === 'Admin aktif terakhir harus tetap berperan administrator.');
assert($rejection(fn () => $toggleUser->execute($adminRow, 999)) === 'Admin aktif terakhir tidak dapat dinonaktifkan.');
assert($rejection(fn () => $deleteUser->execute($adminRow, 999)) === 'Admin aktif terakhir tidak dapat dihapus. Ubah peran pengguna lain terlebih dahulu.');

// Akun sendiri juga tidak boleh dinonaktifkan maupun dihapus, walaupun dia bukan admin terakhir.
$selfDeactivate = $adminInput;
$selfDeactivate['role'] = 'admin';
$selfDeactivate['is_active'] = false;
assert($rejection(fn () => $updateUser->execute($adminRow, $selfDeactivate, $adminId)) === 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
assert($rejection(fn () => $deleteUser->execute($adminRow, $adminId)) === 'Anda tidak dapat menghapus akun Anda sendiri.');
assert($rejection(fn () => $toggleUser->execute($adminRow, $adminId)) === 'Anda tidak dapat menonaktifkan akun Anda sendiri.');

// Semua penolakan di atas tidak menyentuh barisnya.
$untouchedAdmin = $userRepository->find($adminId);
assert($untouchedAdmin['role'] === 'admin');
assert((int)$untouchedAdmin['is_active'] === 1);
assert($userRepository->countActiveAdmins() === 1);

// Begitu ada admin aktif lain, aturan admin-terakhir berpindah ke admin yang tersisa.
$secondAdminId = $userRepository->create('Admin Kedua', 'admin2@kantor.id', password_hash('Rahasia#2026', PASSWORD_DEFAULT), UserRole::Admin);
$userRepository->update($adminId, 'Administrator Inventra', 'admin@kantor.id', UserRole::Staff, true);
assert($lastActiveAdmin->isLastActiveAdmin($userRepository->find($secondAdminId)) === true);
assert($lastActiveAdmin->isLastActiveAdmin($userRepository->find($adminId)) === false);

// Menonaktifkan dan mengaktifkan kembali pengguna lain berjalan normal.
$activeStaffRow = $userRepository->find($adminId);
assert($toggleUser->execute($activeStaffRow, $secondAdminId) === true);
assert((int)$userRepository->find($adminId)['is_active'] === 0);
assert($toggleUser->execute($userRepository->find($adminId), $secondAdminId) === false);
assert((int)$userRepository->find($adminId)['is_active'] === 1);

// Update dengan kata sandi baru melaporkan bahwa kata sandi ikut direset.
$resetInput = $adminInput;
$resetInput['password'] = 'Rahasia#2027';
$resetInput['password_confirmation'] = 'Rahasia#2027';
assert($updateUser->execute($userRepository->find($adminId), $resetInput, $secondAdminId) === true);
assert(password_verify('Rahasia#2027', $userRepository->findByEmail('admin@kantor.id')['password_hash']) === true);

// Hapus pengguna biasa oleh admin lain tetap berjalan.
$throwawayId = $userRepository->create('Pengguna Sementara', 'sementara@kantor.id', password_hash('Rahasia#2026', PASSWORD_DEFAULT), UserRole::Staff);
$deleteUser->execute($userRepository->find($throwawayId), $secondAdminId);
assert($userRepository->find($throwawayId) === null);

// Transisi status aset lewat Action: available -> in_use -> available lagi.
$checkOutAction = new CheckOutAssetAction($assetRepository);
$checkInAction = new CheckInAssetAction($assetRepository);

$beforeCheckOut = $checkOutAction->execute($id, ['recipient_name' => 'Budi Santoso', 'target_location_id' => 2, 'notes' => 'Untuk proyek migrasi'], 'Admin GA');
assert((int)$beforeCheckOut['location_id'] === 1);
$inUseRow = $assetRepository->find($id);
assert($inUseRow['status'] === 'in_use');
assert($inUseRow['assigned_to'] === 'Budi Santoso');
assert((int)$inUseRow['location_id'] === 2);

$beforeCheckIn = $checkInAction->execute($id, ['return_location_id' => 1, 'notes' => 'Kembali lengkap'], 'Admin GA');
assert((int)$beforeCheckIn['location_id'] === 2);
$availableRow = $assetRepository->find($id);
assert($availableRow['status'] === 'available');
assert($availableRow['assigned_to'] === null);
assert((int)$availableRow['location_id'] === 1);

$actionMovements = $movementRepository->getRecent(2);
assert($actionMovements[0]['movement_type'] === 'check_in');
assert($actionMovements[0]['recipient_name'] === 'Budi Santoso');
assert((int)$actionMovements[0]['from_location_id'] === 2);
assert((int)$actionMovements[0]['to_location_id'] === 1);
assert($actionMovements[1]['movement_type'] === 'check_out');
assert((int)$actionMovements[1]['from_location_id'] === 1);
assert((int)$actionMovements[1]['to_location_id'] === 2);
assert($actionMovements[1]['actor_name'] === 'Admin GA');

// Payload tidak lengkap ditolak Action sebelum ada baris yang berubah.
assert($rejection(fn () => $checkOutAction->execute($id, ['target_location_id' => 2], 'Admin GA')) === 'Nama penerima dan lokasi target wajib diisi');
assert($rejection(fn () => $checkInAction->execute($id, [], 'Admin GA')) === 'Lokasi gudang/ruang penyimpanan wajib diisi');
assert($assetRepository->find($id)['status'] === 'available');

echo "PASSED\n";

echo "\n[SUCCESS] All 4 test suites passed cleanly with 0 failures.\n";
