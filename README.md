# INVENTRA — Modern Office Asset & Inventory Management Monorepo

Inventra adalah sistem manajemen inventaris dan aset kantor modern berbasis monorepo PHP, Bootstrap 5, jQuery, dan MySQL. Dirancang untuk pengelolaan seluruh aset fisik perusahaan mulai dari pengadaan, penempatan ruangan, serah terima/peminjaman (checkout), pengembalian (checkin), perbaikan, hingga log mutasi aset terpusat.

---

## 1. Tech Stack & Versi Terbaru

- **Backend / Core**: PHP 8.4+ / PHP 8.5 (Strict types, readonly DTOs, PHP Enums, PSR-4 monorepo autoloading)
- **Frontend UI**: Bootstrap 5.3.8 (via Context7 latest jsDelivr bundle + Popper) & Bootstrap Icons 1.11.3
- **Client Scripting**: jQuery 3.7.1 (AJAX dynamic tables, modal forms, live filters)
- **Database**: MySQL 8.4 LTS (InnoDB, utf8mb4, relational integrity foreign keys)
- **Dev Tooling**: Composer script (`composer setup`, `composer serve`, `composer migrate`)

---

## 2. Struktur Monorepo

```
inventra/
├── apps/
│   ├── api/                           # RESTful API Service
│   │   ├── public/
│   │   │   └── index.php              # Entrypoint router API (/api/v1/...)
│   │   └── src/
│   │       └── Controllers/
│   │           └── AssetApiController.php
│   └── web/                           # Web Dashboard Application
│       ├── public/
│       │   ├── assets/
│       │   │   ├── css/style.css      # Custom UI theme
│       │   │   └── js/app.js          # jQuery 3.7.1 interactive logic
│       │   └── index.php              # Web front controller
│       └── views/
│           ├── layout/
│           │   ├── header.php         # Navbar & Bootstrap 5.3.8 CDN
│           │   └── footer.php         # Modals, Toasts & jQuery scripts
│           └── dashboard.php          # Metric cards, inventory table, logs
├── packages/
│   ├── core/                          # Shared Domain Logic & Models
│   │   ├── src/
│   │   │   ├── Database/Connection.php # PDO MySQL connection (w/ fallback)
│   │   │   ├── DTO/AssetDTO.php       # Readonly asset DTO
│   │   │   ├── Enums/                 # AssetStatus, AssetCondition, MovementType
│   │   │   └── Models/                # AssetModel, CategoryModel, LocationModel, MovementModel
│   │   └── tests/
│   │       └── run_tests.php          # Assert-based self-check test runner
│   └── database/                      # Schemas, Migrations & Seeds
│       ├── migrations/
│       │   └── 001_create_tables.sql
│       ├── seeders/
│       │   └── 001_demo_seed.sql
│       └── migrate.php
├── docker/
├── docker-compose.yml                 # MySQL 8.4 LTS & PHP container
├── .env.example
├── composer.json                      # Root PSR-4 autoloading
├── manage.py                          # Python CLI manager
└── README.md
```

---

## 3. Fitur Utama

1. **Dashboard Metrik Real-time**:
   - Total aset terdaftar, valuasi total aset (Rp), aset sedang dipakai, dan persediaan gudang.
2. **Katalog Aset Lengkap**:
   - Pencarian instan (nama, kode barcode, serial number, penanggung jawab).
   - Filter cepat berdasarkan kategori dan status aset.
3. **Siklus Hidup Aset (Lifecycle Management)**:
   - **Serah Terima / Pinjam (Check-out)**: Menetapkan pegawai penanggung jawab dan lokasi penempatan baru.
   - **Pengembalian (Check-in)**: Mengembalikan aset ke gudang logistik.
   - **Pencatatan Kondisi**: Sangat Baik (Baru), Baik (Normal), Cukup (Aus), Rusak.
4. **Audit Trail & Activity Log**:
   - Riwayat mutasi lokasi dan serah terima terekam otomatis di tabel `asset_movements`.
5. **REST API Siap Pakai**:
   - Endpoint JSON `/api/v1/assets`, `/api/v1/stats`, `/api/v1/categories`, `/api/v1/locations`.

---

## 4. Cara Menjalankan

Semua service dijalankan lewat Composer script (tanpa Docker).

```bash
# 1. Install dependency + buat .env + migrasi + seed demo data
composer setup

# 2. Jalankan web & API
composer serve          # http://localhost:8090
```

Perintah lain:

```bash
composer migrate                 # migrasi saja
composer migrate -- --seed       # migrasi + seed demo data
composer test                    # unit self-check test
```

Syarat: MySQL lokal aktif (service `mysql` bawaan Yerd / MySQL server biasa) dan kredensial DB di `.env` sudah sesuai.

---

## 5. API Endpoints Reference

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/api/v1/stats` | Statistik jumlah aset, valuasi total, dan log mutasi terbaru |
| `GET` | `/api/v1/assets` | Daftar aset dengan filter `search`, `category_id`, `status` |
| `POST` | `/api/v1/assets` | Mendaftarkan aset baru |
| `GET` | `/api/v1/assets/{id}` | Detail informasi spesifikasi satu aset |
| `PUT` | `/api/v1/assets/{id}` | Memperbarui data aset |
| `DELETE` | `/api/v1/assets/{id}` | Menghapus aset dari sistem |
| `POST` | `/api/v1/assets/{id}/checkout` | Serah terima aset ke pegawai tertentu |
| `POST` | `/api/v1/assets/{id}/checkin` | Pengembalian aset ke gudang |
| `GET` | `/api/v1/categories` | Daftar kategori aset |
| `GET` | `/api/v1/locations` | Daftar lokasi ruangan kantor |
