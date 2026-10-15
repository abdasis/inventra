<?php
/**
 * Shell form tambah dan ubah karyawan. Data dan pengiriman ditangani master-form.js.
 *
 * @var bool $isEdit
 * @var int|null $entityId
 */
$entity = 'employees';
$label = 'Karyawan';
$subtitle = 'Identitas pegawai yang dapat menerima serah terima aset';
$fields = [
    ['name' => 'employee_number', 'label' => 'Nomor Induk Karyawan', 'type' => 'text', 'required' => true, 'hint' => 'Nomor unik, contoh: EMP-001.'],
    ['name' => 'name', 'label' => 'Nama Lengkap', 'type' => 'text', 'required' => true],
    ['name' => 'department', 'label' => 'Departemen', 'type' => 'text'],
    ['name' => 'position', 'label' => 'Jabatan', 'type' => 'text'],
    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
    ['name' => 'phone', 'label' => 'Telepon', 'type' => 'tel'],
    ['name' => 'is_active', 'label' => 'Status Karyawan', 'type' => 'switch', 'col' => 'col-12', 'switchLabel' => 'Karyawan aktif dan dapat menerima aset'],
];
require __DIR__ . '/../partials/master-form.php';
