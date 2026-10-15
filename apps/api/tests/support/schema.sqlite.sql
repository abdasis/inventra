-- Skema SQLite untuk test dan perekaman kontrak.
-- Cerminan apps/api/database/migrations/*.sql dengan penyesuaian SQLite:
-- ENUM menjadi TEXT, TINYINT menjadi INTEGER, tanpa index dan foreign key.
-- Setiap tabel baru di migrasi wajib ditambahkan juga di sini.

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    parent_id INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    building TEXT,
    floor TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    category_id INTEGER NOT NULL,
    item_id INTEGER,
    purchase_id INTEGER,
    purchase_detail_id INTEGER,
    location_id INTEGER NOT NULL,
    serial_number TEXT,
    acquisition_source TEXT,
    purchase_reference TEXT,
    warranty_expiry TEXT,
    purchase_date TEXT,
    purchase_cost REAL DEFAULT 0,
    status TEXT DEFAULT "available",
    condition_level TEXT DEFAULT "good",
    assigned_to TEXT,
    notes TEXT,
    photo_path TEXT,
    document_path TEXT,
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

CREATE TABLE IF NOT EXISTS units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    brand TEXT,
    model TEXT,
    specification TEXT,
    category_id INTEGER NOT NULL,
    unit_id INTEGER NOT NULL,
    item_type TEXT DEFAULT "asset",
    minimum_stock INTEGER DEFAULT 0,
    description TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_number TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    department TEXT,
    position TEXT,
    email TEXT,
    phone TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    contact_person TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
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

CREATE TABLE IF NOT EXISTS purchases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_number TEXT NOT NULL UNIQUE,
    supplier_id INTEGER,
    purchase_date TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT "draft",
    reference TEXT,
    notes TEXT,
    total_amount REAL NOT NULL DEFAULT 0,
    created_by_name TEXT,
    received_at TEXT,
    cancelled_at TEXT,
    cancel_reason TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS purchase_details (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purchase_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    quantity_ordered INTEGER NOT NULL DEFAULT 0,
    quantity_received INTEGER NOT NULL DEFAULT 0,
    quantity_rejected INTEGER NOT NULL DEFAULT 0,
    unit_price REAL NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_balances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL UNIQUE,
    quantity INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER NOT NULL,
    movement_type TEXT NOT NULL,
    quantity INTEGER NOT NULL,
    balance_after INTEGER NOT NULL,
    reference_type TEXT,
    reference_id INTEGER,
    department TEXT,
    employee_id INTEGER,
    reason TEXT,
    notes TEXT,
    actor_name TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Penempatan permanen dan peminjaman sementara. Di SQLite kolom ENUM menjadi
-- TEXT, jadi nilai status apa pun diterima; khususnya assets.status juga TEXT
-- sehingga status operasional "on_loan" dari migrasi 006 tidak butuh ALTER.
CREATE TABLE IF NOT EXISTS asset_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL,
    employee_id INTEGER,
    location_id INTEGER NOT NULL,
    assigned_at TEXT NOT NULL,
    returned_at TEXT,
    condition_out TEXT NOT NULL DEFAULT "good",
    condition_in TEXT,
    handed_by_name TEXT NOT NULL,
    received_by_name TEXT,
    status TEXT NOT NULL DEFAULT "active",
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS asset_loans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL,
    employee_id INTEGER,
    location_id INTEGER,
    loaned_at TEXT NOT NULL,
    due_at TEXT,
    returned_at TEXT,
    condition_out TEXT NOT NULL DEFAULT "good",
    condition_in TEXT,
    handed_by_name TEXT NOT NULL,
    received_by_name TEXT,
    purpose TEXT,
    status TEXT NOT NULL DEFAULT "active",
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Mutasi, pemeliharaan, dan penghapusan aset. DECIMAL menjadi REAL, dan hanya
-- UNIQUE asset_id pada penghapusan yang dipertahankan karena invarian "satu aset
-- hanya dihapus sekali" memang perlu ditegakkan meski tanpa index dan foreign key.
CREATE TABLE IF NOT EXISTS asset_transfers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL,
    from_location_id INTEGER,
    to_location_id INTEGER NOT NULL,
    from_holder_name TEXT,
    to_holder_name TEXT,
    transfer_date TEXT NOT NULL,
    reason TEXT,
    status TEXT NOT NULL DEFAULT "pending",
    executed_by_name TEXT NOT NULL,
    completed_at TEXT,
    cancelled_at TEXT,
    cancel_reason TEXT,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS asset_maintenances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL,
    maintenance_type TEXT NOT NULL DEFAULT "corrective",
    maintenance_date TEXT NOT NULL,
    vendor TEXT,
    cost REAL NOT NULL DEFAULT 0,
    damage_description TEXT,
    work_result TEXT,
    next_schedule TEXT,
    status TEXT NOT NULL DEFAULT "ongoing",
    performed_by_name TEXT NOT NULL,
    completed_at TEXT,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS asset_disposals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL UNIQUE,
    disposal_date TEXT NOT NULL,
    reason TEXT NOT NULL,
    method TEXT NOT NULL DEFAULT "written_off",
    book_value REAL,
    document_reference TEXT,
    approved_by_name TEXT,
    executed_by_name TEXT NOT NULL,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);
