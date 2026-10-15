<?php
/**
 * Shell halaman master karyawan. Daftar dikelola master-data.js; pengisian data
 * memakai halaman form terpisah karena fieldnya lebih dari lima.
 */
$entity = 'employees';
$label = 'Karyawan';
$subtitle = 'Data pegawai yang dapat menerima serah terima aset';
$createUrl = '/master/employees/create';
$columns = [
    ['label' => 'Nomor Induk', 'width' => '160px'],
    ['label' => 'Nama & Jabatan'],
    ['label' => 'Departemen'],
    ['label' => 'Kontak'],
    ['label' => 'Status', 'width' => '120px'],
];
require __DIR__ . '/../partials/master-list.php';
