<?php

declare(strict_types=1);

namespace Inventra\Web\Http\Controllers;

use Inventra\Web\Support\View;

/**
 * Controller tipis halaman dashboard dan kelola pengguna. Hanya merender shell;
 * isi tabel dan form diambil skrip halaman lewat AJAX ke /api/v1.
 */
final class PageController
{
    public function dashboard(): void
    {
        View::render('dashboard', [], [
            ['label' => 'Dashboard', 'url' => null],
        ], 'Dashboard — Inventra');
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
}
