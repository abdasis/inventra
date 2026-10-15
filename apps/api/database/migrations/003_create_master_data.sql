-- Inventra: Office Asset & Inventory Management System
-- Migration 003: Create Master Data (Units, Items)

CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    brand VARCHAR(128) NULL,
    model VARCHAR(128) NULL,
    specification TEXT NULL,
    category_id INT NOT NULL,
    unit_id INT NOT NULL,
    item_type ENUM('asset', 'consumable') NOT NULL DEFAULT 'asset',
    minimum_stock INT NOT NULL DEFAULT 0,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_items_category (category_id),
    INDEX idx_items_unit (unit_id),
    INDEX idx_items_type (item_type),
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories (id) ON UPDATE CASCADE,
    CONSTRAINT fk_items_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kategori induk sebagai hirarki opsional. migrate.php mengeksekusi ulang seluruh
-- berkas migrasi tiap dijalankan sementara MySQL 8 belum punya ADD COLUMN IF NOT EXISTS,
-- jadi ALTER-nya dijaga lewat information_schema. Tanpa foreign key: cukup kolomnya.
SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'parent_id');
SET @sql := IF(@ada = 0, 'ALTER TABLE categories ADD COLUMN parent_id INT NULL AFTER description', 'DO 0');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_number VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    department VARCHAR(128) NULL,
    position VARCHAR(128) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(32) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_employees_active (is_active),
    INDEX idx_employees_department (department),
    INDEX idx_employees_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    contact_person VARCHAR(128) NULL,
    phone VARCHAR(32) NULL,
    email VARCHAR(190) NULL,
    address TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suppliers_active (is_active),
    INDEX idx_suppliers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
