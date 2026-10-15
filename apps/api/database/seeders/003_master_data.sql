-- Inventra: Office Asset & Inventory Management System
-- Seed Master Data: Units & Items
--
-- ON DUPLICATE KEY UPDATE hanya menyentuh kolom `code` (tidak mengubah nilainya).
-- Seeder dieksekusi ulang tiap `composer migrate`, jadi kolom yang bisa diubah
-- pengguna (name, brand, model, kategori, satuan, dst) sengaja tidak di-update.

INSERT INTO units (code, name, description) VALUES
('PCS', 'Pcs', 'Satuan per buah'),
('UNIT', 'Unit', 'Satuan per unit perangkat'),
('SET', 'Set', 'Satuan per set atau paket'),
('BOX', 'Box', 'Satuan per kotak'),
('RIM', 'Rim', 'Satuan per rim (500 lembar)')
ON DUPLICATE KEY UPDATE code = VALUES(code);

INSERT INTO items (code, name, brand, model, specification, category_id, unit_id, item_type, minimum_stock, description) VALUES
(
    'ITM-IT-001', 'Laptop Lenovo ThinkPad E14 Gen 6', 'Lenovo', 'ThinkPad E14 Gen 6',
    'Core Ultra 5 125H, RAM 16GB, SSD 512GB, layar 14 inch WUXGA',
    (SELECT id FROM categories WHERE code = 'CAT-IT'),
    (SELECT id FROM units WHERE code = 'PCS'),
    'asset', 0, 'Laptop kerja standar untuk staf'
),
(
    'ITM-IT-002', 'Monitor LG 24MP400 24 Inch IPS', 'LG', '24MP400',
    'Resolusi Full HD 1920x1080, panel IPS, HDMI + D-Sub',
    (SELECT id FROM categories WHERE code = 'CAT-IT'),
    (SELECT id FROM units WHERE code = 'UNIT'),
    'asset', 0, 'Monitor pendamping workstation'
),
(
    'ITM-FRN-001', 'Kursi Ergonomis Ergohuman Elite 2', 'Ergohuman', 'Elite 2',
    'Mesh back, adjustable armrest, lumbar support, tilt lock',
    (SELECT id FROM categories WHERE code = 'CAT-FRN'),
    (SELECT id FROM units WHERE code = 'UNIT'),
    'asset', 0, 'Kursi kerja dengan penopang pinggang'
),
(
    'ITM-OPS-001', 'AC Daikin Inverter 2 PK FTKQ50', 'Daikin', 'FTKQ50',
    'Inverter 2 PK, refrigerant R32, kapasitas 5.000 Watt',
    (SELECT id FROM categories WHERE code = 'CAT-OPS'),
    (SELECT id FROM units WHERE code = 'UNIT'),
    'asset', 0, 'Pendingin ruang kerja, servis berkala tiap 3 bulan'
),
(
    'ITM-ATK-001', 'Kertas A4 HVS 80gsm', 'Sidu', 'A4 80gsm',
    'Ukuran A4, gramatur 80gsm, isi 500 lembar per rim',
    (SELECT id FROM categories WHERE code = 'CAT-ATK'),
    (SELECT id FROM units WHERE code = 'RIM'),
    'consumable', 20, 'Kebutuhan cetak harian seluruh departemen'
),
(
    'ITM-ATK-002', 'Tinta Printer Epson 003 Black', 'Epson', '003 Black',
    'Botol tinta hitam 65 ml untuk seri Epson EcoTank',
    (SELECT id FROM categories WHERE code = 'CAT-ATK'),
    (SELECT id FROM units WHERE code = 'BOX'),
    'consumable', 10, 'Stok tinta printer departemen'
),
(
    'ITM-ATK-003', 'Alat Tulis Kantor Paket Lengkap', NULL, NULL,
    'Paket berisi pulpen, pensil, penghapus, penggaris, dan sticky notes',
    (SELECT id FROM categories WHERE code = 'CAT-ATK'),
    (SELECT id FROM units WHERE code = 'SET'),
    'consumable', 5, 'Paket alat tulis untuk staf baru'
)
ON DUPLICATE KEY UPDATE code = VALUES(code);

INSERT INTO employees (employee_number, name, department, position, email, phone, is_active) VALUES
('EMP-2026-001', 'Ahmad Farhan', 'IT', 'Lead Developer', 'ahmad.farhan@kantor.id', '0812-1000-0001', 1),
('EMP-2026-002', 'Siti Rahma', 'Design', 'UI/UX Designer', 'siti.rahma@kantor.id', '0812-1000-0002', 1),
('EMP-2026-003', 'Budi Santoso', 'General Affairs', 'Staf Umum', 'budi.santoso@kantor.id', '0812-1000-0003', 1),
('EMP-2026-004', 'Rina Kartika', 'Finance', 'Staf Akuntansi', 'rina.kartika@kantor.id', '0812-1000-0004', 0)
ON DUPLICATE KEY UPDATE employee_number = VALUES(employee_number);

INSERT INTO suppliers (code, name, contact_person, phone, email, address, is_active) VALUES
('SUP-2026-001', 'PT Sumber Komputer Nusantara', 'Hendra Wijaya', '021-5550-1001', 'sales@sumberkomputer.id', 'Jl. Mangga Dua Raya No. 12, Jakarta Utara', 1),
('SUP-2026-002', 'CV Mitra Furnitur Kantor', 'Dewi Lestari', '021-5550-1002', 'order@mitrafurnitur.id', 'Jl. Industri Raya No. 45, Tangerang', 1),
('SUP-2026-003', 'Toko ATK Berkah Jaya', 'Slamet Riyadi', '021-5550-1003', 'berkahjaya@mail.id', 'Jl. Pasar Baru No. 8, Jakarta Pusat', 1)
ON DUPLICATE KEY UPDATE code = VALUES(code);
