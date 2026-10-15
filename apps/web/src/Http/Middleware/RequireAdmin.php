<?php

declare(strict_types=1);

namespace Inventra\Web\Http\Middleware;

use Inventra\Api\Http\Request;
use Inventra\Core\Auth\Auth;
use Inventra\Web\Support\View;

/**
 * Halaman kelola pengguna hanya untuk administrator. Pengunjung biasa dipulangkan
 * ke dashboard dengan pesan singkat, tanpa merender isi halaman.
 */
final class RequireAdmin
{
    public function __invoke(Request $request): void
    {
        if (Auth::isAdmin()) {
            return;
        }

        View::flash('danger', 'Halaman tersebut khusus untuk administrator.');
        View::redirect('/');
    }
}
