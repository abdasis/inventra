<?php
/**
 * Shell form tambah dan ubah supplier. Data dan pengiriman ditangani master-form.js.
 *
 * @var bool $isEdit
 * @var int|null $entityId
 */
$entity = 'suppliers';
$label = 'Supplier';
$subtitle = 'Identitas rekanan pengadaan beserta narahubungnya';
$fields = [
    ['name' => 'code', 'label' => 'Kode Supplier', 'type' => 'text', 'required' => true, 'hint' => 'Kode unik, contoh: SUP-001.'],
    ['name' => 'name', 'label' => 'Nama Supplier', 'type' => 'text', 'required' => true],
    ['name' => 'contact_person', 'label' => 'Narahubung', 'type' => 'text'],
    ['name' => 'phone', 'label' => 'Telepon', 'type' => 'tel'],
    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
    ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 2],
    ['name' => 'is_active', 'label' => 'Status Supplier', 'type' => 'switch', 'col' => 'col-12', 'switchLabel' => 'Supplier aktif dan masih bekerja sama'],
];
require __DIR__ . '/../partials/master-form.php';
