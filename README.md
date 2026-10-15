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
│   │   │   │   ├── Actions/{Purchase,Stock}/
│   │   │   │   │                      # Pembelian, penerimaan, pembatalan, dan pencatatan stok
│   │   │   │   ├── Actions/{Assignment,Loan}/
│   │   │   │   │                      # Penempatan, pengembalian, peminjaman, dan pengembaliannya
│   │   │   │   ├── Actions/{Category,Unit,Item,Location,Employee,Supplier}/
│   │   │   │   │                      # Create/Update/DeleteAction per entitas master data
│   │   │   │   ├── Actions/User/      # Create/Update/Delete/ToggleUserActive + GuardLastActiveAdmin
│   │   │   │   ├── Auth/              # Auth session & Csrf
│   │   │   │   ├── Database/          # Connection.php (PDO MySQL, w/ fallback)
│   │   │   │   ├── DTO/               # AssetDTO, ItemDTO (readonly), UserDTO (bentuk siap tampil)
│   │   │   │   ├── Enums/             # AssetStatus, AssetCondition, MovementType, ItemType, UserRole
│   │   │   │   ├── Exceptions/        # DomainException, ValidationException, NotFound, Forbidden
│   │   │   │   ├── Repositories/      # Akses PDO prepared statement per entitas
│   │   │   │   ├── Services/          # Service per modul — orkestrasi use case + activity log
│   │   │   │   ├── Support/           # ActivityLogger, FieldChangeNarrator, BooleanInput
│   │   │   │   └── Validation/        # UserInput — normalisasi + validasi payload
│   │   │   └── Http/                  # Transport API
│   │   │       ├── Controllers/       # Controller tipis per modul (aset, pengguna, master data)
│   │   │       ├── Middleware/        # AuthGuard, CsrfGuard
│   │   │       └── Router/Request/Response/ExceptionHandler.php
│   │   ├── routes/                    # api.php — registrasi rute /api/v1/*
│   │   ├── database/
│   │   │   ├── migrations/            # 001 core, 002 users & activity logs, 003 master data
│   │   │   ├── seeders/               # 001 demo aset, 002 admin default, 003 master data
│   │   │   └── migrate.php            # Runner migrasi + seeder
│   │   └── tests/                     # run_tests.php, user_domain_check.php, master_*_check.php,
│   │                                  # contract_snapshot.php
│   └── web/                           # Frontend saja: shell halaman, view, aset statis
│       ├── public/
│       │   ├── assets/
│       │   │   ├── css/style.css      # Custom UI theme
│       │   │   └── js/                # app.js, users.js, master-*.js, assets-*.js,
│       │   │                          # purchases.js, purchases-detail.js, stock.js
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
│           ├── partials/              # Fragmen bersama halaman master data
│           ├── assets/                # form.php (registrasi), detail.php (riwayat), label.php (QR cetak)
│           ├── purchases/             # index.php, form.php, detail.php (penerimaan)
│           ├── stock/                 # index.php (saldo + riwayat pergerakan)
│           ├── transfers/             # index.php (mutasi aset)
│           ├── maintenances/          # index.php, form.php (pemeliharaan)
│           ├── disposals/             # index.php, form.php (penghapusan)
│           ├── reports/               # index.php (ringkasan + ekspor), print.php (cetak)
│           ├── activity-logs/         # index.php (audit trail)
│           ├── master/                # Daftar + form kategori, satuan, barang, lokasi, karyawan, supplier
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
3. **Registrasi Aset Individual**:
   - Setiap unit aset berdiri sendiri dengan kode unik; kode duplikat ditolak `422`, dan kode dapat dibangkitkan otomatis berurutan dari kode jenis barang (`ITM-IT-001-2026-0001`).
   - Registrasi satuan maupun massal (`quantity` 1–50) dalam satu transaksi database — setiap unit tetap mendapat kode sendiri.
   - Aset menautkan jenis barang (merek, model, spesifikasi), sumber perolehan, referensi pembelian, masa garansi, serta tautan foto/dokumen.
   - Halaman detail menampilkan identitas, data perolehan, dan riwayat mutasi aset; halaman label QR siap cetak untuk ditempel di barang.
   - Registrasi selalu dimulai sebagai aset tersedia tanpa pemegang — penempatan dan peminjaman adalah transaksi terpisah, dan penyuntingan data tidak melepas pemegang yang sedang aktif.
4. **Siklus Hidup Aset (Lifecycle Management)**:
   - **Serah Terima / Pinjam (Check-out)**: Menetapkan pegawai penanggung jawab dan lokasi penempatan baru.
   - **Pengembalian (Check-in)**: Mengembalikan aset ke gudang logistik.
   - **Pencatatan Kondisi**: Sangat Baik (Baru), Baik (Normal), Cukup (Aus), Rusak.
5. **Audit Trail & Activity Log**:
   - Riwayat mutasi lokasi dan serah terima terekam otomatis di tabel `asset_movements`.
6. **Pengadaan & Penerimaan**:
   - Transaksi pembelian mencatat supplier, nomor pembelian (otomatis berurutan bila dikosongkan), tanggal, referensi dokumen, dan baris detail barang beserta jumlah dipesan dan harga satuan.
   - Penerimaan mendukung parsial dan membedakan jumlah dipesan, diterima, dan ditolak. Barang habis pakai menambah saldo stok; barang tetap menghasilkan unit aset baru lengkap dengan kode sendiri. Barang yang sudah diterima tidak dapat diterima dua kali.
   - Pembelian yang sudah diposting tidak dihapus: status hanya dapat dibatalkan atau disunting selama masih `draft`.
7. **Stok Barang Habis Pakai**:
   - Saldo per barang dengan penanda stok rendah terhadap stok minimum, plus riwayat pergerakan yang dapat difilter per barang dan jenis pergerakan.
   - Saldo tidak dapat disunting langsung: setiap perubahan harus lewat transaksi stok masuk, keluar (per departemen atau karyawan), atau koreksi beralasan.
   - Saldo selalu dapat direkonsiliasi dari histori pergerakan — jumlah pergerakan selalu menghasilkan saldo yang sama dengan yang tersimpan.
8. **Penempatan & Peminjaman Aset**:
   - Penempatan mencatat serah terima tetap kepada karyawan dan lokasi, beserta kondisi saat keluar dan saat kembali — riwayat pemegang tersimpan, tidak pernah ditimpa.
   - Peminjaman sementara mencatat tujuan, jatuh tempo, dan kondisi keluar/kembali; pinjaman yang lewat jatuh tempo ditandai terlambat.
   - Satu aset hanya boleh punya satu pemegang aktif dan satu peminjaman aktif; aset dalam perbaikan atau sudah dihapus ditolak pada transaksi baru. Aksi serah terima cepat di dashboard ikut menulis riwayat penempatan, sehingga histori pemegang selalu lengkap.
9. **Master Data Terpusat**:
   - Enam master dengan CRUD penuh, pencarian server-side, dan kontrol akses: kategori (termasuk kategori induk), satuan, barang (jenis barang dengan merek/model/spesifikasi, jenis `asset`/`consumable`, stok minimum), lokasi (gedung, lantai, ruangan), karyawan (nomor pegawai, departemen, jabatan, status aktif), dan supplier.
   - Penjaga integritas: kode/nomor unik ditolak bila duplikat, dan master yang masih dipakai transaksi lain tidak dapat dihapus (mis. kategori yang masih dipakai aset atau barang).
   - Halaman daftar memakai modal untuk form ringkas (maksimal 5 field) dan halaman form terpisah untuk entitas berfield banyak — mengikuti aturan form proyek.
10. **Laporan, Ekspor, dan Audit Trail**:
   - Ringkasan inventaris: aset per status dan per kategori, total valuasi, stok habis pakai yang menipis, peminjaman aktif dan yang terlambat, biaya pemeliharaan serta penghapusan tahun berjalan, dan aktivitas tujuh hari terakhir.
   - Ekspor CSV (terbuka di Excel) untuk daftar aset, saldo stok, dan jejak aktivitas; laporan cetak siap PDF lewat halaman cetak peramban.
   - Audit trail menampilkan jejak setiap perubahan data — pelaku, aksi, subjek, narasi, waktu, dan alamat IP — dengan filter pencarian, jenis aksi, dan rentang tanggal. Hanya untuk administrator.
11. **REST API Siap Pakai**:
   - Endpoint JSON `/api/v1/assets`, `/api/v1/stats`, dan CRUD `/api/v1/{categories,units,items,locations,employees,suppliers}`.
12. **Autentikasi & Peran Pengguna**:
   - Login berbasis session dengan proteksi CSRF, pembatasan percobaan login gagal, dan cookie `httponly` + `SameSite=Lax`.
   - Dua peran: **Administrator** (akses penuh termasuk kelola pengguna dan hapus aset) dan **Staf** (operasional aset sehari-hari).
   - Halaman Kelola Pengguna untuk administrator: daftar, tambah, ubah, reset kata sandi, aktif/nonaktifkan, dan hapus.
   - Aktivitas penting (login, kelola pengguna, mutasi aset) tercatat sebagai log naratif di tabel `activity_logs`.

---

## 4. Halaman Aplikasi

Seluruh halaman hanya merender shell; datanya diambil lewat AJAX ke `/api/v1/*`.

| Halaman | Rute | Akses |
|---|---|---|
| Dashboard & daftar inventaris | `/` | Semua pengguna |
| Registrasi aset (tambah / ubah) | `/assets/create`, `/assets/{id}/edit` | Semua pengguna |
| Detail aset + riwayat mutasi | `/assets/{id}` | Semua pengguna |
| Label QR siap cetak | `/assets/{id}/label` | Semua pengguna |
| Pengadaan & penerimaan | `/purchases`, `/purchases/create`, `/purchases/{id}/edit`, `/purchases/{id}` | Semua pengguna (tulis: administrator) |
| Stok barang habis pakai | `/stock` | Semua pengguna |
| Penempatan aset | `/assignments` | Semua pengguna (tulis: administrator) |
| Peminjaman aset | `/loans` | Semua pengguna |
| Mutasi, pemeliharaan, penghapusan aset | `/transfers`, `/maintenances`, `/disposals` (+ `/create` untuk pemeliharaan dan penghapusan) | Semua pengguna (tulis: administrator) |
| Laporan inventaris & cetak | `/reports`, `/reports/print` | Semua pengguna |
| Audit trail | `/activity-logs` | Administrator |
| Master data | `/master/{categories,units,items,locations,employees,suppliers}` (+ `/create`, `/{id}/edit` untuk barang, karyawan, supplier) | Administrator |
| Kelola pengguna | `/users`, `/users/create`, `/users/{id}/edit` | Administrator |
| Masuk / keluar | `/login`, `/logout` | Publik |

---

## 5. Cara Menjalankan

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

## 6. API Endpoints Reference

Seluruh endpoint di bawah ini memerlukan session login. Request tanpa session yang sah akan dijawab `401`, dan request yang mengubah data (`POST`/`PUT`/`DELETE`) wajib menyertakan header `X-CSRF-Token` atau akan dijawab `403`. Endpoint `DELETE /api/v1/assets/{id}` hanya tersedia untuk peran administrator.

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/api/v1/stats` | Statistik jumlah aset, valuasi total, dan log mutasi terbaru |
| `GET` | `/api/v1/assets` | Daftar aset dengan filter `search`, `category_id`, `status`, `location_id`, `item_id` |
| `POST` | `/api/v1/assets` | Mendaftarkan aset baru (field `quantity` 1–50 untuk registrasi massal); `data` respons selalu berupa daftar aset |
| `GET` | `/api/v1/assets/{id}` | Detail aset beserta `movements` (riwayat mutasi aset tersebut) |
| `PUT` | `/api/v1/assets/{id}` | Memperbarui data aset tanpa melepas pemegang aktif |
| `DELETE` | `/api/v1/assets/{id}` | Menghapus aset dari sistem (hanya administrator) |
| `POST` | `/api/v1/assets/{id}/checkout` | Serah terima aset ke pegawai tertentu |
| `POST` | `/api/v1/assets/{id}/checkin` | Pengembalian aset ke gudang |
| `GET` | `/api/v1/purchases` | Daftar pembelian dengan filter `search` dan `status` |
| `POST` | `/api/v1/purchases` | Membuat pembelian beserta baris detailnya (hanya administrator) |
| `GET` | `/api/v1/purchases/{id}` | Detail pembelian beserta baris detail dan sisa pesanan |
| `PUT` | `/api/v1/purchases/{id}` | Mengubah pembelian selama masih `draft` (hanya administrator) |
| `POST` | `/api/v1/purchases/{id}/receive` | Menerima barang (boleh parsial) sehingga menambah stok atau mendaftarkan unit aset |
| `POST` | `/api/v1/purchases/{id}/cancel` | Membatalkan pembelian `draft` beserta alasan |
| `DELETE` | `/api/v1/purchases/{id}` | Menghapus pembelian `draft` (hanya administrator) |
| `GET` | `/api/v1/stock` | Daftar saldo barang habis pakai, filter `search` dan `low_only` |
| `GET` | `/api/v1/stock/movements` | Riwayat pergerakan stok, filter `item_id` dan `movement_type` |
| `POST` | `/api/v1/stock/movements` | Mencatat stok masuk, keluar, atau koreksi beralasan |
| `GET` | `/api/v1/assignments` | Daftar penempatan aset, filter `search`, `status`, `employee_id` |
| `POST` | `/api/v1/assignments` | Mencatat penempatan aset kepada karyawan/lokasi (hanya administrator) |
| `GET` | `/api/v1/assignments/{id}` | Detail satu penempatan |
| `POST` | `/api/v1/assignments/{id}/return` | Mengembalikan aset dari penempatan beserta kondisi masuk |
| `GET` | `/api/v1/loans` | Daftar peminjaman, filter `search`, `status`, `overdue` |
| `POST` | `/api/v1/loans` | Mencatat peminjaman sementara beserta jatuh tempo |
| `GET` | `/api/v1/loans/{id}` | Detail satu peminjaman, termasuk penanda terlambat |
| `POST` | `/api/v1/loans/{id}/return` | Mengembalikan aset dari peminjaman beserta kondisi masuk |
| `GET` | `/api/v1/transfers` | Daftar mutasi aset, filter `search`, `status`, `asset_id` |
| `POST` | `/api/v1/transfers` | Mencatat mutasi aset (lokasi aktif baru berubah saat diselesaikan) |
| `POST` | `/api/v1/transfers/{id}/complete` | Menyelesaikan mutasi sehingga aset berpindah lokasi |
| `POST` | `/api/v1/transfers/{id}/cancel` | Membatalkan mutasi tanpa mengubah lokasi aset |
| `GET` | `/api/v1/maintenances` | Daftar pemeliharaan, filter `search`, `status`, `asset_id` |
| `POST` | `/api/v1/maintenances` | Mencatat pemeliharaan sehingga aset berstatus `maintenance` |
| `POST` | `/api/v1/maintenances/{id}/complete` | Menutup pemeliharaan beserta hasil pekerjaan dan biaya |
| `GET` | `/api/v1/disposals` | Daftar penghapusan aset |
| `POST` | `/api/v1/disposals` | Menghapus aset secara final beserta alasan dan dokumen pendukung |
| `GET` | `/api/v1/reports/summary` | Ringkasan agregat inventaris untuk dashboard laporan |
| `GET` | `/api/v1/reports/{assets,stock,activity}.csv` | Unduhan CSV untuk Excel |
| `GET` | `/api/v1/activity-logs` | Jejak aktivitas, filter `search`, `action`, `subject_type`, `date_from`, `date_to` |
| `GET` | `/api/v1/activity-logs/actions` | Daftar jenis aksi yang pernah tercatat (untuk filter) |
| `GET` | `/api/v1/categories` | Daftar kategori aset (filter `search`) |
| `GET` | `/api/v1/locations` | Daftar lokasi ruangan kantor (filter `search`) |
| `GET` | `/api/v1/units` | Daftar satuan (filter `search`) |
| `GET` | `/api/v1/items` | Daftar jenis barang beserta kategori dan satuan (filter `search`) |
| `GET` | `/api/v1/employees` | Daftar karyawan (filter `search`) |
| `GET` | `/api/v1/suppliers` | Daftar supplier (filter `search`) |

Seluruh entitas master data di atas mengikuti pola yang sama, dengan `{entity}` salah satu dari `categories`, `units`, `items`, `locations`, `employees`, atau `suppliers`:

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/api/v1/{entity}` | Daftar baris, mendukung `?search=` |
| `POST` | `/api/v1/{entity}` | Menambah baris baru (hanya administrator) |
| `GET` | `/api/v1/{entity}/{id}` | Detail satu baris |
| `PUT` | `/api/v1/{entity}/{id}` | Memperbarui baris (hanya administrator) |
| `DELETE` | `/api/v1/{entity}/{id}` | Menghapus baris; ditolak `422` bila masih dipakai (hanya administrator) |
