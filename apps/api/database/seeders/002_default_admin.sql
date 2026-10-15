-- Inventra: Office Asset & Inventory Management System
-- Seed Default Administrator
--
-- Kata sandi awal akun ini: Inventra#2026
-- WAJIB diganti oleh administrator setelah login pertama.
--
-- ON DUPLICATE KEY UPDATE hanya menyentuh kolom `name`. Seeder ini dieksekusi ulang
-- tiap `composer migrate`, jadi password_hash, role, dan is_active sengaja tidak di-update
-- supaya kata sandi yang sudah diganti admin tidak ter-reset.

INSERT INTO users (name, email, password_hash, role, is_active) VALUES
('Administrator Inventra', 'admin@kantor.id', '$2y$12$kTX6DEqdRzGG3epF6Ulg7.h8nkemCT4hCHPMbzYcXXm7ocC1bkGvq', 'admin', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);
