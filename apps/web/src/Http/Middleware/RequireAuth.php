<?php

declare(strict_types=1);

namespace Inventra\Web\Http\Middleware;

use Inventra\Api\Http\Request;
use Inventra\Core\Auth\Auth;
use Inventra\Web\Support\LoginRedirect;
use Inventra\Web\Support\View;

/**
 * Alihkan pengunjung tanpa sesi ke halaman login sambil membawa path asal pada
 * parameter next.
 */
final class RequireAuth
{
    public function __invoke(Request $request): void
    {
        if (Auth::check()) {
            return;
        }

        View::redirect(LoginRedirect::target($request));
    }
}
