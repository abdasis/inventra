<?php
/**
 * Shell form tambah dan ubah barang. Data dan pengiriman ditangani master-form.js.
 *
 * @var bool $isEdit
 * @var int|null $entityId
 */
$entity = 'items';
$label = 'Barang';
$subtitle = 'Lengkapi identitas barang agar mudah ditelusuri dan dihitung stoknya';
$fields = [
    ['name' => 'code', 'label' => 'Kode Barang', 'type' => 'text', 'required' => true, 'hint' => 'Kode unik, contoh: BRG-ELK-001.'],
    ['name' => 'name', 'label' => 'Nama Barang', 'type' => 'text', 'required' => true],
    ['name' => 'brand', 'label' => 'Merek', 'type' => 'text'],
    ['name' => 'model', 'label' => 'Model / Tipe', 'type' => 'text'],
    ['name' => 'category_id', 'label' => 'Kategori', 'type' => 'select', 'required' => true, 'source' => 'categories', 'emptyLabel' => 'Pilih kategori'],
    ['name' => 'unit_id', 'label' => 'Satuan', 'type' => 'select', 'required' => true, 'source' => 'units', 'emptyLabel' => 'Pilih satuan'],
    // Label opsi sengaja sama persis dengan item_type_label dari API supaya
    // istilahnya tidak berbeda antara dropdown dan badge di daftar.
    ['name' => 'item_type', 'label' => 'Jenis Barang', 'type' => 'select', 'required' => true, 'default' => 'asset', 'hint' => 'Aset dilacak per unit, barang habis pakai dihitung dari stok.', 'options' => [
        ['value' => 'asset', 'label' => 'Aset'],
        ['value' => 'consumable', 'label' => 'Barang Habis Pakai'],
    ]],
    ['name' => 'minimum_stock', 'label' => 'Stok Minimum', 'type' => 'number', 'min' => '0', 'step' => '1', 'hint' => 'Batas peringatan stok menipis. Isi 0 bila tidak dipantau.'],
    ['name' => 'specification', 'label' => 'Spesifikasi', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
    ['name' => 'description', 'label' => 'Keterangan', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
];
require __DIR__ . '/../partials/master-form.php';
