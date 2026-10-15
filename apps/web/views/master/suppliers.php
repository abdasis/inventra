<?php
/**
 * Shell halaman master supplier. Daftar dikelola master-data.js; pengisian
 * data memakai halaman form terpisah karena fieldnya lebih dari lima.
 */
$entity = 'suppliers';
$label = 'Supplier';
$subtitle = 'Rekanan pengadaan barang beserta narahubungnya';
$createUrl = '/master/suppliers/create';
$columns = [
    ['label' => 'Kode', 'width' => '140px'],
    ['label' => 'Nama Supplier'],
    ['label' => 'Narahubung'],
    ['label' => 'Telepon'],
    ['label' => 'Status', 'width' => '120px'],
];
require __DIR__ . '/../partials/master-list.php';
