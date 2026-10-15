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
    'received_at',
    'cancelled_at',
    'assigned_at',
    'returned_at',
    'loaned_at',
    'completed_at',
    // Jumlah aktivitas bergantung pada tanggal hari ini, jadi tidak dibandingkan.
    'activity_last_7_days',
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
    // Registrasi aset: kode unik wajib, kategori bisa diambil dari jenis barang,
    // registrasi massal, dan pemegang tidak diusik oleh penyuntingan data.
    'assets.store.with-item' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [
            'name' => 'Laptop Operasional Baru',
            'item_id' => 1,
            'location_id' => 1,
            'purchase_date' => '2026-05-01',
            'purchase_cost' => 12000000,
            'acquisition_source' => 'purchase',
            'purchase_reference' => 'PO/2026/0512',
            'warranty_expiry' => '2027-05-01',
        ],
    ],
    'assets.store.bulk' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [
            'name' => 'Kursi Ergonomis Massal',
            'category_id' => 1,
            'location_id' => 2,
            'quantity' => 3,
        ],
    ],
    'assets.store.duplicate-code' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['name' => 'Aset Kode Duplikat', 'category_id' => 1, 'location_id' => 1, 'asset_code' => 'AST-FIX-001'],
    ],
    'assets.store.unknown-item' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['name' => 'Aset Jenis Ngawur', 'item_id' => 999, 'location_id' => 1],
    ],
    'assets.store.invalid-quantity' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['name' => 'Aset Jumlah Ngawur', 'category_id' => 1, 'location_id' => 1, 'quantity' => 51],
    ],
    'assets.store.warranty-before-purchase' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [
            'name' => 'Aset Garansi Terbalik',
            'category_id' => 1,
            'location_id' => 1,
            'purchase_date' => '2026-05-01',
            'warranty_expiry' => '2026-01-01',
        ],
    ],
    'assets.store.invalid-date' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['name' => 'Aset Tanggal Ngawur', 'category_id' => 1, 'location_id' => 1, 'purchase_date' => '01-05-2026'],
    ],
    'assets.index.by-item' => ['method' => 'GET', 'uri' => '/api/v1/assets?item_id=1', 'actor' => $admin],
    'assets.show.with-history' => ['method' => 'GET', 'uri' => '/api/v1/assets/2', 'actor' => $admin],
    // Pemegang aset tidak boleh hilang hanya karena data lain disunting.
    'assets.update.keeps-holder' => [
        'method' => 'PUT',
        'uri' => '/api/v1/assets/2',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['name' => 'Kursi Ergonomis (revisi)', 'category_id' => 1, 'location_id' => 2, 'condition_level' => 'damaged'],
    ],
    'assets.store.staff' => [
        'method' => 'POST',
        'uri' => '/api/v1/assets',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['name' => 'Aset Didaftarkan Staf', 'category_id' => 1, 'location_id' => 1],
    ],

    // Master data: baca untuk semua sesi, tulis hanya administrator, dan penjaga
    // hapus menolak master yang masih dipakai transaksi lain.
    'master.units.index' => ['method' => 'GET', 'uri' => '/api/v1/units', 'actor' => $admin],
    'master.units.index.search' => ['method' => 'GET', 'uri' => '/api/v1/units?search=rim', 'actor' => $admin],
    'master.units.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/units',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'BOX', 'name' => 'Box', 'description' => 'Per dus'],
    ],
    'master.units.store.duplicate' => [
        'method' => 'POST',
        'uri' => '/api/v1/units',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'PCS', 'name' => 'Pcs (duplikat)'],
    ],
    'master.units.store.staff' => [
        'method' => 'POST',
        'uri' => '/api/v1/units',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['code' => 'SET', 'name' => 'Set'],
    ],
    'master.units.update' => [
        'method' => 'PUT',
        'uri' => '/api/v1/units/2',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'RIM', 'name' => 'Rim (500 lembar)'],
    ],
    'master.units.destroy.in-use' => ['method' => 'DELETE', 'uri' => '/api/v1/units/2', 'actor' => $admin, 'csrf' => true],
    'master.units.destroy' => ['method' => 'DELETE', 'uri' => '/api/v1/units/1', 'actor' => $admin, 'csrf' => true],
    'master.units.show.missing' => ['method' => 'GET', 'uri' => '/api/v1/units/999', 'actor' => $admin],
    'master.items.index' => ['method' => 'GET', 'uri' => '/api/v1/items', 'actor' => $admin],
    'master.items.index.search' => ['method' => 'GET', 'uri' => '/api/v1/items?search=lenovo', 'actor' => $admin],
    'master.items.show' => ['method' => 'GET', 'uri' => '/api/v1/items/1', 'actor' => $admin],
    'master.items.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/items',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [
            'code' => 'ITM-IT-002',
            'name' => 'Monitor 24 Inch',
            'brand' => 'Dell',
            'category_id' => 1,
            'unit_id' => 1,
            'item_type' => 'asset',
            'minimum_stock' => 0,
        ],
    ],
    'master.items.store.consumable' => [
        'method' => 'POST',
        'uri' => '/api/v1/items',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [
            'code' => 'ITM-ATK-002',
            'name' => 'Tinta Printer 664',
            'category_id' => 1,
            'unit_id' => 1,
            'item_type' => 'consumable',
            'minimum_stock' => 5,
        ],
    ],
    'master.items.store.unknown-category' => [
        'method' => 'POST',
        'uri' => '/api/v1/items',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'ITM-X-001', 'name' => 'Barang Tanpa Kategori', 'category_id' => 999, 'unit_id' => 1],
    ],
    'master.items.store.invalid-type' => [
        'method' => 'POST',
        'uri' => '/api/v1/items',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'ITM-X-002', 'name' => 'Barang Jenis Ngawur', 'category_id' => 1, 'unit_id' => 1, 'item_type' => 'jasa'],
    ],
    'master.items.store.negative-stock' => [
        'method' => 'POST',
        'uri' => '/api/v1/items',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'ITM-X-003', 'name' => 'Barang Stok Negatif', 'category_id' => 1, 'unit_id' => 1, 'minimum_stock' => -3],
    ],
    'master.items.update' => [
        'method' => 'PUT',
        'uri' => '/api/v1/items/1',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'ITM-IT-001', 'name' => 'Laptop Standar (revisi)', 'category_id' => 1, 'unit_id' => 1, 'item_type' => 'asset'],
    ],
    'master.items.destroy' => ['method' => 'DELETE', 'uri' => '/api/v1/items/2', 'actor' => $admin, 'csrf' => true],
    'master.categories.index' => ['method' => 'GET', 'uri' => '/api/v1/categories', 'actor' => $admin],
    'master.categories.index.search' => ['method' => 'GET', 'uri' => '/api/v1/categories?search=perangkat', 'actor' => $admin],
    'master.categories.show' => ['method' => 'GET', 'uri' => '/api/v1/categories/1', 'actor' => $admin],
    'master.categories.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/categories',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'CAT-ANAK', 'name' => 'Kategori Anak', 'description' => 'Turunan kategori IT', 'parent_id' => 1],
    ],
    'master.categories.store.duplicate' => [
        'method' => 'POST',
        'uri' => '/api/v1/categories',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'CAT-IT', 'name' => 'Kategori Duplikat'],
    ],
    'master.categories.store.unknown-parent' => [
        'method' => 'POST',
        'uri' => '/api/v1/categories',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'CAT-YATIM', 'name' => 'Kategori Tanpa Induk', 'parent_id' => 999],
    ],
    'master.categories.update.self-parent' => [
        'method' => 'PUT',
        'uri' => '/api/v1/categories/1',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'CAT-IT', 'name' => 'Perangkat IT', 'parent_id' => 1],
    ],
    'master.categories.destroy.in-use' => ['method' => 'DELETE', 'uri' => '/api/v1/categories/1', 'actor' => $admin, 'csrf' => true],
    'master.locations.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/locations',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'LOC-BARU', 'name' => 'Ruang Baru', 'building' => 'Gedung B', 'floor' => 'Lt 1'],
    ],
    'master.locations.update' => [
        'method' => 'PUT',
        'uri' => '/api/v1/locations/2',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'LOC-DEV', 'name' => 'Ruang IT (revisi)', 'building' => 'A', 'floor' => 'Lt 3'],
    ],
    'master.locations.store.duplicate' => [
        'method' => 'POST',
        'uri' => '/api/v1/locations',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'LOC-GUG', 'name' => 'Gudang Duplikat'],
    ],
    'master.locations.destroy.in-use' => ['method' => 'DELETE', 'uri' => '/api/v1/locations/1', 'actor' => $admin, 'csrf' => true],
    'master.locations.destroy' => ['method' => 'DELETE', 'uri' => '/api/v1/locations/2', 'actor' => $admin, 'csrf' => true],
    'master.employees.index' => ['method' => 'GET', 'uri' => '/api/v1/employees', 'actor' => $admin],
    'master.employees.index.search' => ['method' => 'GET', 'uri' => '/api/v1/employees?search=ahmad', 'actor' => $admin],
    'master.employees.show' => ['method' => 'GET', 'uri' => '/api/v1/employees/1', 'actor' => $admin],
    'master.employees.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/employees',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [
            'employee_number' => 'EMP-002',
            'name' => 'Siti Rahma',
            'department' => 'Finance',
            'position' => 'Staff Akuntansi',
            'email' => 'siti@kantor.id',
            'phone' => '0811000002',
        ],
    ],
    'master.employees.store.duplicate' => [
        'method' => 'POST',
        'uri' => '/api/v1/employees',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['employee_number' => 'EMP-001', 'name' => 'Nomor Duplikat'],
    ],
    'master.employees.store.invalid-email' => [
        'method' => 'POST',
        'uri' => '/api/v1/employees',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['employee_number' => 'EMP-003', 'name' => 'Email Salah', 'email' => 'bukan-email'],
    ],
    'master.employees.update' => [
        'method' => 'PUT',
        'uri' => '/api/v1/employees/1',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['employee_number' => 'EMP-001', 'name' => 'Ahmad Farhan', 'department' => 'IT', 'is_active' => false],
    ],
    'master.employees.destroy' => ['method' => 'DELETE', 'uri' => '/api/v1/employees/1', 'actor' => $admin, 'csrf' => true],
    'master.suppliers.index' => ['method' => 'GET', 'uri' => '/api/v1/suppliers', 'actor' => $admin],
    'master.suppliers.show' => ['method' => 'GET', 'uri' => '/api/v1/suppliers/1', 'actor' => $admin],
    'master.suppliers.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/suppliers',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [
            'code' => 'SUP-002',
            'name' => 'CV Mitra Kantor',
            'contact_person' => 'Budi',
            'phone' => '021-5550002',
            'email' => 'budi@mitrakantor.id',
        ],
    ],
    'master.suppliers.store.duplicate' => [
        'method' => 'POST',
        'uri' => '/api/v1/suppliers',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'SUP-001', 'name' => 'Supplier Duplikat'],
    ],
    'master.suppliers.update' => [
        'method' => 'PUT',
        'uri' => '/api/v1/suppliers/1',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['code' => 'SUP-001', 'name' => 'PT Sumber Komputer', 'contact_person' => 'Rina', 'is_active' => false],
    ],
    'master.suppliers.destroy' => ['method' => 'DELETE', 'uri' => '/api/v1/suppliers/1', 'actor' => $admin, 'csrf' => true],
    'master.destroy.staff' => ['method' => 'DELETE', 'uri' => '/api/v1/suppliers/1', 'actor' => $staff, 'csrf' => true],

    // Pengadaan: penerimaan parsial menambah stok barang habis pakai dan
    // mendaftarkan unit aset untuk barang tetap, tanpa transaksi ganda.
    'procurement.index' => ['method' => 'GET', 'uri' => '/api/v1/purchases', 'actor' => $admin],
    'procurement.index.filtered' => ['method' => 'GET', 'uri' => '/api/v1/purchases?status=draft&search=PO', 'actor' => $admin],
    'procurement.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/purchases',
        'actor' => $admin,
        'csrf' => true,
        'payload' => [
            'supplier_id' => 1,
            'purchase_date' => '2026-05-02',
            'reference' => 'INV/2026/0502',
            'notes' => 'Pengadaan ATK bulan Mei',
            'details' => [
                ['item_id' => 2, 'quantity_ordered' => 10, 'unit_price' => 55000],
                ['item_id' => 1, 'quantity_ordered' => 2, 'unit_price' => 12000000],
            ],
        ],
    ],
    'procurement.store.invalid' => [
        'method' => 'POST',
        'uri' => '/api/v1/purchases',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['purchase_date' => '02-05-2026', 'details' => []],
    ],
    'procurement.store.staff' => [
        'method' => 'POST',
        'uri' => '/api/v1/purchases',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['purchase_date' => '2026-05-02', 'details' => [['item_id' => 2, 'quantity_ordered' => 1, 'unit_price' => 1000]]],
    ],
    'procurement.show.missing' => ['method' => 'GET', 'uri' => '/api/v1/purchases/999', 'actor' => $admin],
    'procurement.receive.consumable' => [
        'method' => 'POST',
        'uri' => '/api/v1/purchases/1/receive',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['details' => [['id' => 1, 'quantity_received' => 4, 'quantity_rejected' => 1]], 'notes' => 'Sebagian tiba'],
    ],
    'procurement.receive.asset' => [
        'method' => 'POST',
        'uri' => '/api/v1/purchases/2/receive',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['details' => [['id' => 3, 'quantity_received' => 2]]],
    ],
    'procurement.receive.over' => [
        'method' => 'POST',
        'uri' => '/api/v1/purchases/1/receive',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['details' => [['id' => 1, 'quantity_received' => 99]]],
    ],
    'procurement.update.received' => [
        'method' => 'PUT',
        'uri' => '/api/v1/purchases/3',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['purchase_date' => '2026-05-03', 'details' => [['item_id' => 2, 'quantity_ordered' => 5, 'unit_price' => 1000]]],
    ],
    'procurement.cancel.draft' => [
        'method' => 'POST',
        'uri' => '/api/v1/purchases/4/cancel',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['reason' => 'Salah input supplier'],
    ],
    'procurement.cancel.received' => [
        'method' => 'POST',
        'uri' => '/api/v1/purchases/3/cancel',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['reason' => 'Batal setelah diterima'],
    ],
    'procurement.destroy.draft' => ['method' => 'DELETE', 'uri' => '/api/v1/purchases/4', 'actor' => $admin, 'csrf' => true],
    'procurement.destroy.received' => ['method' => 'DELETE', 'uri' => '/api/v1/purchases/3', 'actor' => $admin, 'csrf' => true],

    // Stok barang habis pakai: saldo hanya berubah lewat transaksi yang tercatat.
    'stock.index' => ['method' => 'GET', 'uri' => '/api/v1/stock', 'actor' => $admin],
    'stock.index.low-only' => ['method' => 'GET', 'uri' => '/api/v1/stock?low_only=1', 'actor' => $admin],
    'stock.index.staff' => ['method' => 'GET', 'uri' => '/api/v1/stock', 'actor' => $staff],
    'stock.movements.index' => ['method' => 'GET', 'uri' => '/api/v1/stock/movements', 'actor' => $admin],
    'stock.movements.filtered' => ['method' => 'GET', 'uri' => '/api/v1/stock/movements?item_id=2&movement_type=in', 'actor' => $admin],
    'stock.store.in' => [
        'method' => 'POST',
        'uri' => '/api/v1/stock/movements',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['item_id' => 2, 'movement_type' => 'in', 'quantity' => 15, 'notes' => 'Sisa stok gudang'],
    ],
    'stock.store.out' => [
        'method' => 'POST',
        'uri' => '/api/v1/stock/movements',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['item_id' => 2, 'movement_type' => 'out', 'quantity' => 3, 'department' => 'Finance'],
    ],
    'stock.store.out.insufficient' => [
        'method' => 'POST',
        'uri' => '/api/v1/stock/movements',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['item_id' => 2, 'movement_type' => 'out', 'quantity' => 99],
    ],
    'stock.store.adjustment.no-reason' => [
        'method' => 'POST',
        'uri' => '/api/v1/stock/movements',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['item_id' => 2, 'movement_type' => 'adjustment', 'quantity' => 2],
    ],
    'stock.store.asset-item' => [
        'method' => 'POST',
        'uri' => '/api/v1/stock/movements',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['item_id' => 1, 'movement_type' => 'in', 'quantity' => 1],
    ],
    'stock.store.unknown-item' => [
        'method' => 'POST',
        'uri' => '/api/v1/stock/movements',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['item_id' => 999, 'movement_type' => 'in', 'quantity' => 1],
    ],

    // Operasional aset: penempatan dan peminjaman adalah transaksi terpisah, dan
    // satu aset tidak boleh punya dua pemegang atau dua peminjaman aktif.
    'assignments.index' => ['method' => 'GET', 'uri' => '/api/v1/assignments', 'actor' => $admin],
    'assignments.index.filtered' => ['method' => 'GET', 'uri' => '/api/v1/assignments?status=active&search=proyektor', 'actor' => $admin],
    'assignments.show' => ['method' => 'GET', 'uri' => '/api/v1/assignments/1', 'actor' => $admin],
    'assignments.show.missing' => ['method' => 'GET', 'uri' => '/api/v1/assignments/999', 'actor' => $admin],
    'assignments.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/assignments',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'employee_id' => 1, 'location_id' => 2, 'condition_out' => 'good', 'notes' => 'Penempatan tetap'],
    ],
    'assignments.store.occupied' => [
        'method' => 'POST',
        'uri' => '/api/v1/assignments',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 3, 'employee_id' => 1, 'location_id' => 2],
    ],
    'assignments.store.on-loan' => [
        'method' => 'POST',
        'uri' => '/api/v1/assignments',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 4, 'employee_id' => 1, 'location_id' => 2],
    ],
    'assignments.store.missing-employee' => [
        'method' => 'POST',
        'uri' => '/api/v1/assignments',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'employee_id' => 999, 'location_id' => 2],
    ],
    'assignments.store.staff' => [
        'method' => 'POST',
        'uri' => '/api/v1/assignments',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'employee_id' => 1, 'location_id' => 2],
    ],
    'assignments.return' => [
        'method' => 'POST',
        'uri' => '/api/v1/assignments/1/return',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['return_location_id' => 1, 'condition_in' => 'fair', 'notes' => 'Kembali lengkap'],
    ],
    'assignments.return.already-returned' => [
        'method' => 'POST',
        'uri' => '/api/v1/assignments/1/return',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['return_location_id' => 1, 'condition_in' => 'good'],
    ],

    'loans.index' => ['method' => 'GET', 'uri' => '/api/v1/loans', 'actor' => $admin],
    'loans.index.overdue' => ['method' => 'GET', 'uri' => '/api/v1/loans?overdue=1', 'actor' => $admin],
    'loans.index.staff' => ['method' => 'GET', 'uri' => '/api/v1/loans', 'actor' => $staff],
    'loans.show' => ['method' => 'GET', 'uri' => '/api/v1/loans/1', 'actor' => $admin],
    'loans.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/loans',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'employee_id' => 1, 'due_at' => '2026-12-31', 'purpose' => 'Keperluan dinas luar', 'condition_out' => 'good'],
    ],
    'loans.store.already-loaned' => [
        'method' => 'POST',
        'uri' => '/api/v1/loans',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['asset_id' => 4, 'employee_id' => 1],
    ],
    'loans.store.maintenance' => [
        'method' => 'POST',
        'uri' => '/api/v1/loans',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['asset_id' => 5, 'employee_id' => 1],
    ],
    'loans.store.assigned' => [
        'method' => 'POST',
        'uri' => '/api/v1/loans',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['asset_id' => 3, 'employee_id' => 1],
    ],
    'loans.store.invalid-due' => [
        'method' => 'POST',
        'uri' => '/api/v1/loans',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'employee_id' => 1, 'due_at' => '01-06-2026'],
    ],
    'loans.return' => [
        'method' => 'POST',
        'uri' => '/api/v1/loans/1/return',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['return_location_id' => 1, 'condition_in' => 'good', 'notes' => 'Kembali tanpa kerusakan'],
    ],
    'loans.return.already-returned' => [
        'method' => 'POST',
        'uri' => '/api/v1/loans/1/return',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['return_location_id' => 1, 'condition_in' => 'good'],
    ],

    // Mutasi: lokasi aktif baru berubah setelah mutasi diselesaikan, dan mutasi
    // yang dibatalkan tidak menyentuh aset sama sekali.
    'transfers.index' => ['method' => 'GET', 'uri' => '/api/v1/transfers', 'actor' => $admin],
    'transfers.index.filtered' => ['method' => 'GET', 'uri' => '/api/v1/transfers?status=pending&search=printer', 'actor' => $admin],
    'transfers.show' => ['method' => 'GET', 'uri' => '/api/v1/transfers/1', 'actor' => $admin],
    'transfers.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/transfers',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'to_location_id' => 2, 'transfer_date' => '2026-05-03', 'reason' => 'Relokasi ruang kerja', 'to_holder_name' => 'Ahmad Farhan'],
    ],
    'transfers.store.same-location' => [
        'method' => 'POST',
        'uri' => '/api/v1/transfers',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'to_location_id' => 1],
    ],
    'transfers.store.pending-exists' => [
        'method' => 'POST',
        'uri' => '/api/v1/transfers',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 5, 'to_location_id' => 1],
    ],
    'transfers.store.disposed' => [
        'method' => 'POST',
        'uri' => '/api/v1/transfers',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 6, 'to_location_id' => 1],
    ],
    'transfers.store.staff' => [
        'method' => 'POST',
        'uri' => '/api/v1/transfers',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'to_location_id' => 2],
    ],
    'transfers.complete' => ['method' => 'POST', 'uri' => '/api/v1/transfers/1/complete', 'actor' => $admin, 'csrf' => true],
    'transfers.cancel' => [
        'method' => 'POST',
        'uri' => '/api/v1/transfers/1/cancel',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['reason' => 'Ruang tujuan masih dipakai'],
    ],

    // Pemeliharaan: aset berstatus maintenance selama perawatan berjalan.
    'maintenances.index' => ['method' => 'GET', 'uri' => '/api/v1/maintenances', 'actor' => $admin],
    'maintenances.index.filtered' => ['method' => 'GET', 'uri' => '/api/v1/maintenances?status=ongoing', 'actor' => $admin],
    'maintenances.show' => ['method' => 'GET', 'uri' => '/api/v1/maintenances/1', 'actor' => $admin],
    'maintenances.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/maintenances',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'maintenance_type' => 'preventive', 'maintenance_date' => '2026-05-03', 'vendor' => 'CV Servis Elektronik', 'cost' => 250000, 'damage_description' => 'Pembersihan dan pengecekan berkala'],
    ],
    'maintenances.store.ongoing' => [
        'method' => 'POST',
        'uri' => '/api/v1/maintenances',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 5, 'maintenance_date' => '2026-05-03'],
    ],
    'maintenances.store.on-loan' => [
        'method' => 'POST',
        'uri' => '/api/v1/maintenances',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 4, 'maintenance_date' => '2026-05-03'],
    ],
    'maintenances.store.invalid-date' => [
        'method' => 'POST',
        'uri' => '/api/v1/maintenances',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'maintenance_date' => '03-05-2026'],
    ],
    'maintenances.store.staff' => [
        'method' => 'POST',
        'uri' => '/api/v1/maintenances',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'maintenance_date' => '2026-05-03'],
    ],
    'maintenances.complete' => [
        'method' => 'POST',
        'uri' => '/api/v1/maintenances/1/complete',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['work_result' => 'Modul daya diganti dan diuji 30 menit', 'cost' => 780000, 'next_schedule' => '2026-11-02'],
    ],
    'maintenances.complete.already-done' => [
        'method' => 'POST',
        'uri' => '/api/v1/maintenances/2/complete',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['work_result' => 'Sudah selesai sebelumnya'],
    ],

    // Penghapusan: final, sekali saja, dan aset yang dihapus tidak dipakai transaksi baru.
    'disposals.index' => ['method' => 'GET', 'uri' => '/api/v1/disposals', 'actor' => $admin],
    'disposals.show' => ['method' => 'GET', 'uri' => '/api/v1/disposals/1', 'actor' => $admin],
    'disposals.store' => [
        'method' => 'POST',
        'uri' => '/api/v1/disposals',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'disposal_date' => '2026-05-04', 'reason' => 'Rusak berat dan tidak ekonomis diperbaiki', 'method' => 'destroyed', 'document_reference' => 'BA-2026-005', 'approved_by_name' => 'Direktur Operasional'],
    ],
    'disposals.store.already-disposed' => [
        'method' => 'POST',
        'uri' => '/api/v1/disposals',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 6, 'reason' => 'Penghapusan ulang'],
    ],
    'disposals.store.on-loan' => [
        'method' => 'POST',
        'uri' => '/api/v1/disposals',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 4, 'reason' => 'Masih dipinjam'],
    ],
    'disposals.store.no-reason' => [
        'method' => 'POST',
        'uri' => '/api/v1/disposals',
        'actor' => $admin,
        'csrf' => true,
        'payload' => ['asset_id' => 1],
    ],
    'disposals.store.staff' => [
        'method' => 'POST',
        'uri' => '/api/v1/disposals',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['asset_id' => 1, 'reason' => 'Coba hapus oleh staf'],
    ],
    // Aset yang sudah dihapus ditolak pada transaksi operasional berikutnya.
    'disposals.guard.on-disposed-asset' => [
        'method' => 'POST',
        'uri' => '/api/v1/loans',
        'actor' => $staff,
        'csrf' => true,
        'payload' => ['asset_id' => 6, 'employee_id' => 1],
    ],

    // Laporan, ekspor, dan audit trail: ringkasan agregat, unduhan CSV, dan jejak
    // aktivitas. Ekspor dan audit trail hanya untuk administrator.
    'reports.summary' => ['method' => 'GET', 'uri' => '/api/v1/reports/summary', 'actor' => $admin],
    'reports.summary.staff' => ['method' => 'GET', 'uri' => '/api/v1/reports/summary', 'actor' => $staff],
    'reports.assets-csv' => ['method' => 'GET', 'uri' => '/api/v1/reports/assets.csv', 'actor' => $admin],
    'reports.assets-csv.filtered' => ['method' => 'GET', 'uri' => '/api/v1/reports/assets.csv?status=available&category_id=1', 'actor' => $admin],
    'reports.stock-csv' => ['method' => 'GET', 'uri' => '/api/v1/reports/stock.csv', 'actor' => $admin],
    'reports.activity-csv' => ['method' => 'GET', 'uri' => '/api/v1/reports/activity.csv?action=create', 'actor' => $admin],
    // Ringkasan dan ekspor terbuka untuk semua pengguna; audit trail hanya administrator.
    'reports.assets-csv.staff' => ['method' => 'GET', 'uri' => '/api/v1/reports/assets.csv', 'actor' => $staff],
    'activity-logs.index' => ['method' => 'GET', 'uri' => '/api/v1/activity-logs', 'actor' => $admin],
    'activity-logs.index.filtered' => ['method' => 'GET', 'uri' => '/api/v1/activity-logs?action=create&search=aset', 'actor' => $admin],
    'activity-logs.index.staff' => ['method' => 'GET', 'uri' => '/api/v1/activity-logs', 'actor' => $staff],
    'activity-logs.actions' => ['method' => 'GET', 'uri' => '/api/v1/activity-logs/actions', 'actor' => $admin],

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
