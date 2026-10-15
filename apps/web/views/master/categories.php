<?php
/**
 * Shell halaman master kategori. Daftar dan modal dikelola master-data.js.
 */
$entity = 'categories';
$label = 'Kategori';
$subtitle = 'Kelompok barang untuk mengelompokkan inventaris kantor';
$hasModal = true;
$columns = [
    ['label' => 'Kode', 'width' => '120px'],
    ['label' => 'Nama Kategori'],
    ['label' => 'Kategori Induk'],
    ['label' => 'Keterangan'],
];
$modalFields = [
    ['name' => 'code', 'label' => 'Kode Kategori', 'type' => 'text', 'required' => true, 'hint' => 'Kode unik, contoh: KAT-ELK.'],
    ['name' => 'name', 'label' => 'Nama Kategori', 'type' => 'text', 'required' => true],
    ['name' => 'parent_id', 'label' => 'Kategori Induk', 'type' => 'select', 'col' => 'col-12', 'source' => 'categories', 'emptyLabel' => 'Tanpa induk (kategori utama)', 'hint' => 'Kosongkan bila kategori ini berdiri sendiri.'],
    ['name' => 'description', 'label' => 'Keterangan', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
];
require __DIR__ . '/../partials/master-list.php';
