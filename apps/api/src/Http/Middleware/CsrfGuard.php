<?php

declare(strict_types=1);

namespace Inventra\Api\Http\Middleware;

use Inventra\Api\Http\Request;
use Inventra\Core\Auth\Csrf;
use Inventra\Core\Exceptions\ForbiddenException;

final class CsrfGuard
{
    /** @var list<string> */
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __invoke(Request $request): void
    {
        if (!in_array($request->method(), self::UNSAFE_METHODS, true)) {
            return;
        }

        if (!Csrf::validate($request->header('X-CSRF-Token'))) {
            throw new ForbiddenException(
                'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.'
            );
        }
    }
}
