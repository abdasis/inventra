<?php

declare(strict_types=1);

namespace Inventra\Web\Http\Controllers;

use Inventra\Web\Support\View;

/**
 * Controller tipis halaman dashboard, aset, dan kelola pengguna. Hanya merender
 * shell; isi tabel dan form diambil skrip halaman lewat AJAX ke /api/v1.
 */
final class PageController
{
    public function dashboard(): void
    {
        View::render('dashboard', [], [
            ['label' => 'Dashboard', 'url' => null],
        ], 'Dashboard — Inventra');
    }

    public function assetCreate(): void
    {
        View::render('assets/form', ['isEdit' => false, 'assetId' => null], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Daftar Aset', 'url' => '/'],
            ['label' => 'Tambah Aset', 'url' => null],
        ], 'Tambah Aset — Inventra');
    }

    public function assetEdit(int $id): void
    {
        View::render('assets/form', ['isEdit' => true, 'assetId' => $id], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Daftar Aset', 'url' => '/'],
            ['label' => 'Ubah Aset', 'url' => null],
        ], 'Ubah Aset — Inventra');
    }

    public function assetDetail(int $id): void
    {
        View::render('assets/detail', ['assetId' => $id], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Daftar Aset', 'url' => '/'],
            ['label' => 'Detail Aset', 'url' => null],
        ], 'Detail Aset — Inventra');
    }

    /**
     * Label QR adalah artefak cetak, jadi dirender mandiri tanpa layout dashboard.
     */
    public function assetLabel(int $id): void
    {
        View::renderStandalone('assets/label', ['assetId' => $id]);
    }

    public function usersIndex(): void
    {
        View::render('users/index', [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Kelola Pengguna', 'url' => null],
        ], 'Kelola Pengguna — Inventra');
    }

    public function userCreate(): void
    {
        View::render('users/form', ['isEdit' => false, 'userId' => null], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Kelola Pengguna', 'url' => '/users'],
            ['label' => 'Tambah Pengguna', 'url' => null],
        ], 'Tambah Pengguna — Inventra');
    }

    public function userEdit(int $id): void
    {
        View::render('users/form', ['isEdit' => true, 'userId' => $id], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Kelola Pengguna', 'url' => '/users'],
            ['label' => 'Ubah Pengguna', 'url' => null],
        ], 'Ubah Pengguna — Inventra');
    }

    public function masterCategories(): void
    {
        $this->masterList('categories', 'Kategori');
    }

    public function masterUnits(): void
    {
        $this->masterList('units', 'Satuan');
    }

    public function masterItems(): void
    {
        $this->masterList('items', 'Barang');
    }

    public function masterLocations(): void
    {
        $this->masterList('locations', 'Lokasi');
    }

    public function masterEmployees(): void
    {
        $this->masterList('employees', 'Karyawan');
    }

    public function masterSuppliers(): void
    {
        $this->masterList('suppliers', 'Supplier');
    }

    public function masterItemCreate(): void
    {
        $this->masterForm('items', 'master/item-form', 'Barang', false, null);
    }

    public function masterItemEdit(int $id): void
    {
        $this->masterForm('items', 'master/item-form', 'Barang', true, $id);
    }

    public function masterEmployeeCreate(): void
    {
        $this->masterForm('employees', 'master/employee-form', 'Karyawan', false, null);
    }

    public function masterEmployeeEdit(int $id): void
    {
        $this->masterForm('employees', 'master/employee-form', 'Karyawan', true, $id);
    }

    public function masterSupplierCreate(): void
    {
        $this->masterForm('suppliers', 'master/supplier-form', 'Supplier', false, null);
    }

    public function masterSupplierEdit(int $id): void
    {
        $this->masterForm('suppliers', 'master/supplier-form', 'Supplier', true, $id);
    }

    public function purchasesIndex(): void
    {
        View::render('purchases/index', [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Pengadaan & Penerimaan', 'url' => null],
        ], 'Pengadaan & Penerimaan — Inventra');
    }

    public function purchaseCreate(): void
    {
        $this->purchaseForm(false, null);
    }

    public function purchaseEdit(int $id): void
    {
        $this->purchaseForm(true, $id);
    }

    public function purchaseDetail(int $id): void
    {
        View::render('purchases/detail', ['purchaseId' => $id], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Pengadaan & Penerimaan', 'url' => '/purchases'],
            ['label' => 'Detail Pembelian', 'url' => null],
        ], 'Detail Pembelian — Inventra');
    }

    public function stockIndex(): void
    {
        View::render('stock/index', [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Stok Barang Habis Pakai', 'url' => null],
        ], 'Stok Barang Habis Pakai — Inventra');
    }

    public function assignmentIndex(): void
    {
        View::render('assignments/index', [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Penempatan Aset', 'url' => null],
        ], 'Penempatan Aset — Inventra');
    }

    public function loanIndex(): void
    {
        View::render('loans/index', [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Peminjaman Aset', 'url' => null],
        ], 'Peminjaman Aset — Inventra');
    }

    public function transferIndex(): void
    {
        View::render('transfers/index', [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Mutasi Aset', 'url' => null],
        ], 'Mutasi Aset — Inventra');
    }

    public function maintenanceIndex(): void
    {
        View::render('maintenances/index', [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Pemeliharaan Aset', 'url' => null],
        ], 'Pemeliharaan Aset — Inventra');
    }

    public function maintenanceCreate(): void
    {
        View::render('maintenances/form', ['isEdit' => false, 'maintenanceId' => null], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Pemeliharaan Aset', 'url' => '/maintenances'],
            ['label' => 'Catat Pemeliharaan', 'url' => null],
        ], 'Catat Pemeliharaan — Inventra');
    }

    public function disposalIndex(): void
    {
        View::render('disposals/index', [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Penghapusan Aset', 'url' => null],
        ], 'Penghapusan Aset — Inventra');
    }

    public function disposalCreate(): void
    {
        View::render('disposals/form', ['isEdit' => false, 'disposalId' => null], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Penghapusan Aset', 'url' => '/disposals'],
            ['label' => 'Catat Penghapusan', 'url' => null],
        ], 'Catat Penghapusan — Inventra');
    }

    public function reportIndex(): void
    {
        View::render('reports/index', [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Laporan Inventaris', 'url' => null],
        ], 'Laporan Inventaris — Inventra');
    }

    /**
     * Laporan cetak adalah artefak cetak, jadi dirender mandiri tanpa layout dashboard.
     */
    public function reportPrint(): void
    {
        View::renderStandalone('reports/print', []);
    }

    public function activityLogIndex(): void
    {
        View::render('activity-logs/index', [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Audit Trail', 'url' => null],
        ], 'Audit Trail — Inventra');
    }

    /**
     * Halaman form pembelian melayani tambah dan ubah dengan view yang sama.
     */
    private function purchaseForm(bool $isEdit, ?int $id): void
    {
        $action = $isEdit ? 'Ubah Pembelian' : 'Tambah Pembelian';

        View::render('purchases/form', ['isEdit' => $isEdit, 'purchaseId' => $id], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => 'Pengadaan & Penerimaan', 'url' => '/purchases'],
            ['label' => $action, 'url' => null],
        ], $action . ' — Inventra');
    }

    /**
     * Halaman daftar master: nama view mengikuti nama entitas.
     */
    private function masterList(string $entity, string $label): void
    {
        View::render('master/' . $entity, [], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => $label, 'url' => null],
        ], 'Master ' . $label . ' — Inventra');
    }

    /**
     * Halaman form master; item daftar pada breadcrumb menautkan ke daftar entitas.
     */
    private function masterForm(string $entity, string $view, string $label, bool $isEdit, ?int $id): void
    {
        $action = ($isEdit ? 'Ubah ' : 'Tambah ') . $label;

        View::render($view, ['isEdit' => $isEdit, 'entityId' => $id], [
            ['label' => 'Dashboard', 'url' => '/'],
            ['label' => $label, 'url' => '/master/' . $entity],
            ['label' => $action, 'url' => null],
        ], $action . ' — Inventra');
    }
}
