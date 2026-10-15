<?php
/**
 * Shell halaman master barang. Daftar dikelola master-data.js; pengisian data
 * memakai halaman form terpisah karena fieldnya lebih dari lima.
 */
$entity = 'items';
$label = 'Barang';
$subtitle = 'Daftar barang dan aset beserta kategori, satuan, dan stok minimum';
$createUrl = '/master/items/create';
$columns = [
    ['label' => 'Kode', 'width' => '140px'],
    ['label' => 'Nama Barang'],
    ['label' => 'Kategori'],
    ['label' => 'Satuan'],
    ['label' => 'Jenis'],
    ['label' => 'Stok Minimum', 'width' => '140px'],
];
require __DIR__ . '/../partials/master-list.php';
