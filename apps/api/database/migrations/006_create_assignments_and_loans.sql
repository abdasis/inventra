-- Inventra: Office Asset & Inventory Management System
-- Migration 006: Create Asset Assignments and Loans

-- Penempatan permanen dan peminjaman sementara adalah dua transaksi terpisah.
-- Keduanya menyimpan histori: baris lama tidak pernah ditimpa, hanya ditutup
-- dengan returned_at dan status returned, sehingga pemegang aset dari waktu ke
-- waktu tetap bisa ditelusuri.
CREATE TABLE IF NOT EXISTS asset_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    employee_id INT NULL,
    location_id INT NOT NULL,
    assigned_at DATETIME NOT NULL,
    returned_at DATETIME NULL,
    condition_out ENUM('new', 'good', 'fair', 'damaged') NOT NULL DEFAULT 'good',
    condition_in ENUM('new', 'good', 'fair', 'damaged') NULL,
    handed_by_name VARCHAR(128) NOT NULL,
    received_by_name VARCHAR(128) NULL,
    status ENUM('active', 'returned') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_assignments_asset (asset_id),
    INDEX idx_assignments_employee (employee_id),
    INDEX idx_assignments_status (status),
    CONSTRAINT fk_assignments_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON UPDATE CASCADE,
    CONSTRAINT fk_assignments_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE SET NULL,
    CONSTRAINT fk_assignments_location FOREIGN KEY (location_id) REFERENCES locations (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    employee_id INT NULL,
    location_id INT NULL,
    loaned_at DATETIME NOT NULL,
    due_at DATE NULL,
    returned_at DATETIME NULL,
    condition_out ENUM('new', 'good', 'fair', 'damaged') NOT NULL DEFAULT 'good',
    condition_in ENUM('new', 'good', 'fair', 'damaged') NULL,
    handed_by_name VARCHAR(128) NOT NULL,
    received_by_name VARCHAR(128) NULL,
    purpose TEXT NULL,
    status ENUM('active', 'returned') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_loans_asset (asset_id),
    INDEX idx_loans_employee (employee_id),
    INDEX idx_loans_status (status),
    INDEX idx_loans_due (due_at),
    CONSTRAINT fk_loans_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON UPDATE CASCADE,
    CONSTRAINT fk_loans_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE SET NULL,
    CONSTRAINT fk_loans_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aset yang sedang dipinjam memakai status operasional sendiri agar tidak
-- tertukar dengan "dipakai" milik penempatan permanen. migrate.php mengeksekusi
-- ulang seluruh berkas tiap dijalankan sementara MySQL 8 belum punya MODIFY ...
-- IF EXISTS, jadi ALTER-nya dijaga lewat information_schema.
SET @punya_on_loan := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'status' AND COLUMN_TYPE LIKE '%on_loan%');
SET @sql := IF(@punya_on_loan = 0, 'ALTER TABLE assets MODIFY COLUMN status ENUM(''available'', ''in_use'', ''maintenance'', ''disposed'', ''on_loan'') NOT NULL DEFAULT ''available''', 'DO 0');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
