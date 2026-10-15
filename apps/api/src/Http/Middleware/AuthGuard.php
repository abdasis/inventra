<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Middleware;

use Inventra\Api\Http\Request;
use Inventra\Core\Auth\Auth;
use Inventra\Core\Exceptions\UnauthenticatedException;

final class AuthGuard
{
    public function __invoke(Request $request): void
    {
        if (!Auth::check()) {
            throw new UnauthenticatedException(
                'Sesi tidak ditemukan atau telah berakhir. Silakan login kembali.'
            );
        }
    }
}
