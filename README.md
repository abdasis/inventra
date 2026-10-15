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
│   ├── api/                           # Seluruh backend: routing, controller, domain, database
│   │   ├── public/
│   │   │   └── index.php              # Entrypoint router API (/api/v1/...)
│   │   ├── src/
│   │   │   ├── Core/                  # Domain murni, tanpa HTTP
│   │   │   │   ├── Actions/Asset/     # Create/Update/Delete/CheckOut/CheckInAssetAction
│   │   │   │   ├── Actions/User/      # Create/Update/Delete/ToggleUserActive + GuardLastActiveAdmin
│   │   │   │   ├── Auth/              # Auth session & Csrf
│   │   │   │   ├── Database/          # Connection.php (PDO MySQL, w/ fallback)
│   │   │   │   ├── DTO/               # AssetDTO (readonly), UserDTO (bentuk siap tampil)
│   │   │   │   ├── Enums/             # AssetStatus, AssetCondition, MovementType, UserRole
│   │   │   │   ├── Exceptions/        # DomainException, ValidationException, NotFound, Forbidden
│   │   │   │   ├── Repositories/      # Akses PDO prepared statement per entitas
│   │   │   │   ├── Services/          # AssetService, UserService — orkestrasi use case + activity log
│   │   │   │   ├── Support/           # ActivityLogger
│   │   │   │   └── Validation/        # UserInput — normalisasi + validasi payload
│   │   │   └── Http/                  # Transport API
│   │   │       ├── Controllers/       # AssetController, UserController (tipis)
│   │   │       ├── Middleware/        # AuthGuard, CsrfGuard
│   │   │       └── Router/Request/Response/ExceptionHandler.php
│   │   ├── routes/                    # api.php — registrasi rute /api/v1/*
│   │   ├── database/
│   │   │   ├── migrations/            # 001_create_tables.sql, 002_create_users_and_activity_logs.sql
│   │   │   ├── seeders/               # 001_demo_seed.sql, 002_default_admin.sql
│   │   │   └── migrate.php            # Runner migrasi + seeder
│   │   └── tests/                     # run_tests.php, user_domain_check.php, contract_snapshot.php
│   └── web/                           # Frontend saja: shell halaman, view, aset statis
│       ├── public/
│       │   ├── assets/
│       │   │   ├── css/style.css      # Custom UI theme
│       │   │   └── js/                # app.js (Bootstrap 5.3.8, jQuery 3.7.1), users.js
│       │   └── index.php              # Web front controller
│       ├── routes/                    # web.php — registrasi rute halaman
│       ├── src/
│       │   ├── Http/
│       │   │   ├── Controllers/       # AuthController, PageController (tipis)
│       │   │   └── Middleware/        # RequireAuth, RequireAdmin
│       │   └── Support/               # View helper (render, escape, flash), LoginRedirect
│       ├── tests/                     # page_snapshot.php, redirect_check.php + support/web_probe.php
│       └── views/
│           ├── layout/                # header.php (navbar + CDN), footer.php (modal & skrip)
│           ├── auth/login.php
│           ├── users/                 # index.php, form.php
│           ├── errors/404.php
│           └── dashboard.php          # Metric cards, inventory table, logs
├── .env.example
├── composer.json                      # Root PSR-4 autoloading
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
6. **Autentikasi & Peran Pengguna**:
   - Login berbasis session dengan proteksi CSRF, pembatasan percobaan login gagal, dan cookie `httponly` + `SameSite=Lax`.
   - Dua peran: **Administrator** (akses penuh termasuk kelola pengguna dan hapus aset) dan **Staf** (operasional aset sehari-hari).
   - Halaman Kelola Pengguna untuk administrator: daftar, tambah, ubah, reset kata sandi, aktif/nonaktifkan, dan hapus.
   - Aktivitas penting (login, kelola pengguna, mutasi aset) tercatat sebagai log naratif di tabel `activity_logs`.

---

## 4. Cara Menjalankan

Tidak ada Docker dan tidak ada bundler. Semua service dijalankan lewat Composer script.

```bash
# 1. Install dependency + buat .env + migrasi + seed demo data + akun admin default
composer setup

# 2. Jalankan web & API
composer serve          # http://localhost:8090
```

### Akun Admin Default

Seeder membuat satu akun administrator:

| Email | Kata sandi awal |
|---|---|
| `admin@kantor.id` | `Inventra#2026` |

Ganti kata sandi ini segera setelah login pertama lewat menu **Kelola Pengguna**, karena kredensial di atas tertulis di repositori. Seeder dibuat idempoten: menjalankan `composer migrate -- --seed` berulang kali tidak akan mengembalikan kata sandi yang sudah diganti.

Perintah lain:

```bash
composer migrate                 # migrasi saja
composer migrate -- --seed       # migrasi + seed demo data
composer test                    # unit core, domain check, kontrak API, render halaman, redirect
composer test:record-contract    # rekam ulang golden kontrak API (hanya untuk perubahan yang disengaja)
composer test:record-pages       # rekam ulang golden render halaman
```

`composer test` membandingkan respons API dan hasil render halaman dengan berkas golden di
`apps/api/tests/fixtures/` dan `apps/web/tests/fixtures/`. Kalau ada perbedaan, itu diperlakukan
sebagai regresi sampai terbukti sebaliknya — jangan rekam ulang golden tanpa alasan yang jelas.

Syarat: MySQL lokal aktif (service `mysql` bawaan Yerd / MySQL server biasa) dan kredensial DB di `.env` sudah sesuai.

---

## 5. API Endpoints Reference

Seluruh endpoint di bawah ini memerlukan session login. Request tanpa session yang sah akan dijawab `401`, dan request yang mengubah data (`POST`/`PUT`/`DELETE`) wajib menyertakan header `X-CSRF-Token` atau akan dijawab `403`. Endpoint `DELETE /api/v1/assets/{id}` hanya tersedia untuk peran administrator.

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
