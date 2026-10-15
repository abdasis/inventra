-- Skema SQLite untuk test dan perekaman kontrak.
-- Cerminan apps/api/database/migrations/*.sql dengan penyesuaian SQLite:
-- ENUM menjadi TEXT, TINYINT menjadi INTEGER, tanpa index dan foreign key.
-- Setiap tabel baru di migrasi wajib ditambahkan juga di sini.

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    building TEXT,
    floor TEXT
);

CREATE TABLE IF NOT EXISTS assets (
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

CREATE TABLE IF NOT EXISTS asset_movements (
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

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT "staff",
    is_active INTEGER NOT NULL DEFAULT 1,
    failed_login_attempts INTEGER NOT NULL DEFAULT 0,
    locked_until TEXT,
    last_login_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    user_name TEXT,
    action TEXT NOT NULL,
    subject_type TEXT,
    subject_id INTEGER,
    description TEXT NOT NULL,
    ip_address TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
