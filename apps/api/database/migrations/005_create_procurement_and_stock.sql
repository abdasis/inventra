-- Inventra: Office Asset & Inventory Management System
-- Migration 005: Create Procurement and Consumable Stock

-- Pengadaan memisahkan jumlah dipesan, diterima, dan ditolak supaya penerimaan
-- parsial bisa dicatat; barang habis pakai tidak menyimpan satu baris aset per
-- unit, jadi saldonya hidup di stock_balances dan hanya berubah lewat
-- stock_movements yang bisa diaudit.
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_number VARCHAR(64) NOT NULL UNIQUE,
    supplier_id INT NULL,
    purchase_date DATE NOT NULL,
    status ENUM('draft', 'partial', 'received', 'cancelled') NOT NULL DEFAULT 'draft',
    reference VARCHAR(128) NULL,
    notes TEXT NULL,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_by_name VARCHAR(128) NULL,
    received_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    cancel_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_purchases_supplier (supplier_id),
    INDEX idx_purchases_status (status),
    INDEX idx_purchases_date (purchase_date),
    CONSTRAINT fk_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity_ordered INT NOT NULL DEFAULT 0,
    quantity_received INT NOT NULL DEFAULT 0,
    quantity_rejected INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_purchase_details_purchase (purchase_id),
    INDEX idx_purchase_details_item (item_id),
    CONSTRAINT fk_purchase_details_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id) ON DELETE CASCADE,
    CONSTRAINT fk_purchase_details_item FOREIGN KEY (item_id) REFERENCES items (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL UNIQUE,
    quantity INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_balances_item FOREIGN KEY (item_id) REFERENCES items (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    balance_after INT NOT NULL,
    reference_type VARCHAR(64) NULL,
    reference_id INT NULL,
    department VARCHAR(128) NULL,
    employee_id INT NULL,
    reason TEXT NULL,
    notes TEXT NULL,
    actor_name VARCHAR(128) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stock_movements_item (item_id),
    INDEX idx_stock_movements_type (movement_type),
    INDEX idx_stock_movements_created (created_at),
    CONSTRAINT fk_stock_movements_item FOREIGN KEY (item_id) REFERENCES items (id) ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Penerimaan barang berjenis aset menautkan unit yang lahir dari pembelian ke
-- baris pesanannya, sehingga asal-usul tiap unit bisa ditelusuri. migrate.php
-- mengeksekusi ulang seluruh berkas tiap dijalankan sementara MySQL 8 belum punya
-- ADD COLUMN IF NOT EXISTS, jadi ALTER-nya dijaga lewat information_schema.
SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assets' AND COLUMN_NAME = 'purchase_id');
SET @sql := IF(@ada = 0, 'ALTER TABLE assets ADD COLUMN purchase_id INT NULL AFTER item_id, ADD COLUMN purchase_detail_id INT NULL AFTER purchase_id, ADD INDEX idx_assets_purchase (purchase_id)', 'DO 0');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
