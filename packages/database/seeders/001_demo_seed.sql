-- Inventra: Office Asset & Inventory Management System
-- Seed Demo Data

INSERT INTO categories (code, name, description) VALUES
('CAT-IT', 'IT & Elektronik', 'Laptop, komputer, monitor, server, dan perangkat jaringan'),
('CAT-FRN', 'Furniture & Mebel', 'Meja kerja, kursi ergonomis, lemari berkas, dan partisi'),
('CAT-ATK', 'Peralatan Kantor', 'Printer, scanner, mesin fotokopi, proyektor, dan shredder'),
('CAT-OPS', 'Operasional & Fasilitas', 'Dispenser, AC kantor, vacuum cleaner, dan peralatan dapur')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO locations (code, name, building, floor) VALUES
('LOC-SRV', 'Ruang Server IT', 'Gedung Utama', 'Lt 2'),
('LOC-MTG', 'Ruang Rapat Utama (Boardroom)', 'Gedung Utama', 'Lt 1'),
('LOC-WRK-A', 'Ruang Kerja Terbuka Zona A', 'Gedung Utama', 'Lt 2'),
('LOC-WRK-B', 'Ruang Kerja Terbuka Zona B', 'Gedung Utama', 'Lt 2'),
('LOC-GDG', 'Gudang Logistik Pusat', 'Gedung Annex', 'Lt 1'),
('LOC-DIR', 'Ruang Direktur', 'Gedung Utama', 'Lt 3')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO assets (asset_code, name, category_id, location_id, serial_number, purchase_date, purchase_cost, status, condition_level, assigned_to, notes) VALUES
('AST-IT-2026-001', 'ThinkPad T14s Gen 5 Core Ultra 7', 1, 3, 'PF-4X8910', '2026-01-15', 24500000.00, 'in_use', 'new', 'Ahmad Farhan (Lead Developer)', 'Dilengkapi charger 65W USB-C dan tas laptop'),
('AST-IT-2026-002', 'Dell UltraSharp U2724D 27 Inch 2K 120Hz', 1, 3, 'CN-0M381K-742', '2026-01-20', 8200000.00, 'in_use', 'new', 'Ahmad Farhan (Lead Developer)', 'Kabel DP & Type-C lengkap'),
('AST-IT-2026-003', 'MacBook Pro 14 Inch M3 Pro 18GB/512GB', 1, 4, 'C02G8192MD6R', '2026-02-10', 31999000.00, 'in_use', 'new', 'Siti Rahma (UI/UX Designer)', 'Space Black edition, garansi resmi iBox'),
('AST-FRN-2026-001', 'Ergohuman Elite 2 Mesh Chair', 2, 3, 'EH2-BLK-8812', '2026-01-10', 7850000.00, 'in_use', 'good', 'Ahmad Farhan', 'Adjustable armrest & lumbar support'),
('AST-FRN-2026-002', 'Electric Standing Desk 160x80cm Oak Top', 2, 3, 'OX-DSK-1608', '2026-01-10', 5400000.00, 'in_use', 'good', 'Ahmad Farhan', 'Dual motor, memory controller'),
('AST-ATK-2026-001', 'Canon imageRUNNER 2630i Multi-function', 3, 5, 'CNR-2630-9921', '2025-11-05', 42000000.00, 'available', 'good', NULL, 'Mesin fotokopi & scanner jaringan departemen'),
('AST-IT-2026-004', 'Epson EB-FH52 Full HD Wireless Projector', 1, 2, 'EP-FH52-1102', '2025-12-01', 14250000.00, 'available', 'good', NULL, 'Terpasang di ceiling bracket Ruang Rapat'),
('AST-IT-2026-005', 'Jabra Speak 810 Professional Speakerphone', 1, 2, 'JB-SP810-773', '2025-12-01', 8900000.00, 'available', 'good', NULL, 'Conference call audio system untuk boardroom'),
('AST-OPS-2026-001', 'Daikin Inverter 2 PK Flash Inverter FTKQ50', 4, 3, 'DK-FTKQ50-441', '2025-10-15', 9600000.00, 'in_use', 'good', NULL, 'Servis berkala tiap 3 bulan'),
('AST-IT-2026-006', 'Synology DiskStation DS923+ 4-Bay NAS (16TB)', 1, 1, 'SYN-923-3381', '2025-09-12', 18500000.00, 'in_use', 'good', 'IT Infrastructure Team', 'RAID 5 konfigurasi, backup data kantor')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO asset_movements (asset_id, movement_type, from_location_id, to_location_id, actor_name, recipient_name, notes) VALUES
(1, 'check_out', 5, 3, 'Budi Santoso (Admin GA)', 'Ahmad Farhan', 'Penyerahan laptop kerja baru Q1 2026'),
(2, 'check_out', 5, 3, 'Budi Santoso (Admin GA)', 'Ahmad Farhan', 'Pemasangan monitor workstation'),
(3, 'check_out', 5, 4, 'Budi Santoso (Admin GA)', 'Siti Rahma', 'Pengadaan laptop workstation design')
ON DUPLICATE KEY UPDATE notes=VALUES(notes);
