-- Inventra: Office Asset & Inventory Management System
-- Migration 007: Create Asset Transfers, Maintenance, and Disposals

-- Mutasi aset mencatat rencana perpindahan antarlokasi lebih dulu, lalu
-- diselesaikan sebagai langkah terpisah. Invarian: baris berstatus 'pending'
-- maupun 'cancelled' TIDAK PERNAH mengubah assets.location_id — lokasi aktif
-- aset hanya berpindah saat CompleteTransferAction menutup baris ini sebagai
-- 'completed'. Karena itu membatalkan mutasi cukup menutup barisnya.
CREATE TABLE IF NOT EXISTS asset_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    from_location_id INT NULL,
    to_location_id INT NOT NULL,
    from_holder_name VARCHAR(128) NULL,
    to_holder_name VARCHAR(128) NULL,
    transfer_date DATE NOT NULL,
    reason TEXT NULL,
    status ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    executed_by_name VARCHAR(128) NOT NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancel_reason TEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transfers_asset (asset_id),
    INDEX idx_transfers_status (status),
    INDEX idx_transfers_date (transfer_date),
    CONSTRAINT fk_transfers_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON UPDATE CASCADE,
    CONSTRAINT fk_transfers_from_location FOREIGN KEY (from_location_id) REFERENCES locations (id) ON DELETE SET NULL,
    CONSTRAINT fk_transfers_to_location FOREIGN KEY (to_location_id) REFERENCES locations (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pemeliharaan tertaut ke asetnya sebagai histori. Selama barisnya 'ongoing'
-- aset berstatus maintenance; menutupnya sebagai 'completed' mengembalikan aset
-- ke available. next_schedule diberi index karena dipakai menelusuri perawatan
-- yang akan datang.
CREATE TABLE IF NOT EXISTS asset_maintenances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    maintenance_type ENUM('preventive', 'corrective', 'inspection') NOT NULL DEFAULT 'corrective',
    maintenance_date DATE NOT NULL,
    vendor VARCHAR(128) NULL,
    cost DECIMAL(15,2) NOT NULL DEFAULT 0,
    damage_description TEXT NULL,
    work_result TEXT NULL,
    next_schedule DATE NULL,
    status ENUM('ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'ongoing',
    performed_by_name VARCHAR(128) NOT NULL,
    completed_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_maintenances_asset (asset_id),
    INDEX idx_maintenances_status (status),
    INDEX idx_maintenances_date (maintenance_date),
    INDEX idx_maintenances_next_schedule (next_schedule),
    CONSTRAINT fk_maintenances_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Penghapusan aset: barisnya tetap tersimpan sebagai histori, dan asetnya tidak
-- dibuang dari tabel assets, hanya berstatus 'disposed' supaya tidak bisa dipakai
-- transaksi baru. Invarian: satu aset hanya boleh dihapus sekali, dijaga UNIQUE
-- pada asset_id; Action memeriksa lebih dulu agar pesannya ramah, bukan error SQL.
CREATE TABLE IF NOT EXISTS asset_disposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL UNIQUE,
    disposal_date DATE NOT NULL,
    reason TEXT NOT NULL,
    method ENUM('sold', 'donated', 'destroyed', 'written_off') NOT NULL DEFAULT 'written_off',
    book_value DECIMAL(15,2) NULL,
    document_reference VARCHAR(128) NULL,
    approved_by_name VARCHAR(128) NULL,
    executed_by_name VARCHAR(128) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_disposals_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
