-- Inventra: Office Asset & Inventory Management System
-- Migration 004: Extend Assets for Individual Registration

-- Registrasi aset batch 2: setiap unit punya identitas sendiri dan menautkan
-- "jenis barang" (items) di samping kategori. migrate.php mengeksekusi ulang
-- seluruh berkas tiap dijalankan sementara MySQL 8 belum punya ADD COLUMN IF NOT
-- EXISTS, jadi ALTER-nya dijaga lewat information_schema. Tanpa foreign key untuk
-- item_id: data lama bisa saja tidak konsisten, dan registrasi tidak boleh gagal
-- hanya karena itu.
SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'item_id');
SET @sql := IF(@ada = 0, 'ALTER TABLE assets ADD COLUMN item_id INT NULL AFTER category_id, ADD COLUMN acquisition_source VARCHAR(64) NULL AFTER serial_number, ADD COLUMN purchase_reference VARCHAR(128) NULL AFTER acquisition_source, ADD COLUMN warranty_expiry DATE NULL AFTER purchase_reference, ADD COLUMN photo_path VARCHAR(255) NULL AFTER notes, ADD COLUMN document_path VARCHAR(255) NULL AFTER photo_path, ADD INDEX idx_assets_item (item_id)', 'DO 0');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
