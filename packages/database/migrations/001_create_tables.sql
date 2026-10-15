-- Inventra: Office Asset & Inventory Management System
-- Migration 001: Create Core Tables

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    building VARCHAR(128) NULL DEFAULT 'Main Office',
    floor VARCHAR(32) NULL DEFAULT '1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    location_id INT NOT NULL,
    serial_number VARCHAR(128) NULL,
    purchase_date DATE NULL,
    purchase_cost DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    status ENUM('available', 'in_use', 'maintenance', 'disposed') NOT NULL DEFAULT 'available',
    condition_level ENUM('new', 'good', 'fair', 'damaged') NOT NULL DEFAULT 'good',
    assigned_to VARCHAR(128) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_location (location_id),
    INDEX idx_status (status),
    INDEX idx_asset_code (asset_code),
    CONSTRAINT fk_assets_category FOREIGN KEY (category_id) REFERENCES categories (id) ON UPDATE CASCADE,
    CONSTRAINT fk_assets_location FOREIGN KEY (location_id) REFERENCES locations (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    movement_type ENUM('check_out', 'check_in', 'relocate', 'repair', 'dispose') NOT NULL,
    from_location_id INT NULL,
    to_location_id INT NULL,
    actor_name VARCHAR(128) NOT NULL,
    recipient_name VARCHAR(128) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_movement_asset (asset_id),
    INDEX idx_movement_type (movement_type),
    CONSTRAINT fk_movement_asset FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE,
    CONSTRAINT fk_movement_from_loc FOREIGN KEY (from_location_id) REFERENCES locations (id) ON DELETE SET NULL,
    CONSTRAINT fk_movement_to_loc FOREIGN KEY (to_location_id) REFERENCES locations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
