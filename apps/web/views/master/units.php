<?php
/**
 * Shell halaman master satuan. Daftar dan modal dikelola master-data.js.
 */
$entity = 'units';
$label = 'Satuan';
$subtitle = 'Satuan ukur jumlah barang, misalnya unit, buah, atau rim';
$hasModal = true;
$columns = [
    ['label' => 'Kode', 'width' => '120px'],
    ['label' => 'Nama Satuan'],
    ['label' => 'Keterangan'],
];
$modalFields = [
    ['name' => 'code', 'label' => 'Kode Satuan', 'type' => 'text', 'required' => true, 'hint' => 'Kode unik, contoh: UNIT.'],
    ['name' => 'name', 'label' => 'Nama Satuan', 'type' => 'text', 'required' => true, 'hint' => 'Contoh: Unit, Buah, Rim.'],
    ['name' => 'description', 'label' => 'Keterangan', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
];
require __DIR__ . '/../partials/master-list.php';
