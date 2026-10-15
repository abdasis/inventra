<?php
/**
 * Shell halaman master lokasi. Daftar dan modal dikelola master-data.js.
 */
$entity = 'locations';
$label = 'Lokasi';
$subtitle = 'Tempat penyimpanan dan penempatan barang';
$hasModal = true;
$columns = [
    ['label' => 'Kode', 'width' => '120px'],
    ['label' => 'Nama Lokasi'],
    ['label' => 'Gedung'],
    ['label' => 'Lantai', 'width' => '100px'],
];
$modalFields = [
    ['name' => 'code', 'label' => 'Kode Lokasi', 'type' => 'text', 'required' => true, 'hint' => 'Kode unik, contoh: GDG-A1.'],
    ['name' => 'name', 'label' => 'Nama Lokasi', 'type' => 'text', 'required' => true],
    ['name' => 'building', 'label' => 'Gedung', 'type' => 'text', 'hint' => 'Contoh: Gedung A.'],
    ['name' => 'floor', 'label' => 'Lantai', 'type' => 'text', 'hint' => 'Contoh: Lantai 2.'],
];
require __DIR__ . '/../partials/master-list.php';
