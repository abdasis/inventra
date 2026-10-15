<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Models\AssetModel;
use Inventra\Core\Models\CategoryModel;
use Inventra\Core\Models\LocationModel;
use Inventra\Core\Models\MovementModel;

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
echo "[TEST 2] Testing Database, AssetModel CRUD, and Checkout Workflow... ";
$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// SQLite schema compatible for testing
$sqlite->exec('
    CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        description TEXT
    );
    CREATE TABLE locations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        building TEXT,
        floor TEXT
    );
    CREATE TABLE assets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        asset_code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        category_id INTEGER NOT NULL,
        location_id INTEGER NOT NULL,
        serial_number TEXT,
        purchase_date TEXT,
        purchase_cost REAL DEFAULT 0,
        status TEXT DEFAULT "available",
        condition_level TEXT DEFAULT "good",
        assigned_to TEXT,
        notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE asset_movements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        asset_id INTEGER NOT NULL,
        movement_type TEXT NOT NULL,
        from_location_id INTEGER,
        to_location_id INTEGER,
        actor_name TEXT,
        recipient_name TEXT,
        notes TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );
');

$sqlite->exec("INSERT INTO categories (code, name) VALUES ('CAT-IT', 'IT'), ('CAT-FRN', 'Furniture')");
$sqlite->exec("INSERT INTO locations (code, name, building, floor) VALUES ('LOC-GUG', 'Gudang', 'A', '1'), ('LOC-DEV', 'Ruang IT', 'A', '2')");

Connection::setInstance($sqlite);

$assetModel = new AssetModel($sqlite);
$movementModel = new MovementModel($sqlite);

// Create
$id = $assetModel->create($dto);
assert($id > 0);

// Find
$found = $assetModel->find($id);
assert($found !== null);
assert($found['name'] === 'Dell Latitude 7420');
assert($found['status'] === 'available');

// Checkout
$checkedOut = $assetModel->checkOut($id, 'Aziz Developer', 2, 'Admin GA', 'Project Work');
assert($checkedOut === true);

$afterCheckout = $assetModel->find($id);
assert($afterCheckout['status'] === 'in_use');
assert($afterCheckout['assigned_to'] === 'Aziz Developer');
assert((int)$afterCheckout['location_id'] === 2);

// Stats
$stats = $assetModel->getStats();
assert($stats['total_assets'] === 1);
assert($stats['count_in_use'] === 1);
assert($stats['count_available'] === 0);
assert($stats['total_valuation'] == 15000000.0);

// Check-in
$checkedIn = $assetModel->checkIn($id, 1, 'Admin GA', 'Returned cleanly');
assert($checkedIn === true);

$afterCheckin = $assetModel->find($id);
assert($afterCheckin['status'] === 'available');
assert($afterCheckin['assigned_to'] === null);

// Movements log
$recent = $movementModel->getRecent(5);
assert(count($recent) === 2);
assert($recent[0]['movement_type'] === 'check_in');
assert($recent[1]['movement_type'] === 'check_out');

echo "PASSED\n";

echo "\n[SUCCESS] All 2 test suites passed cleanly with 0 failures.\n";
