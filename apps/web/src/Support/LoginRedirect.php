<?php

declare(strict_types=1);

namespace Inventra\Web\Support;

use Inventra\Api\Http\Request;

/**
 * Tujuan pengalihan ke halaman login. Dipakai bersama oleh middleware, fallback
 * 404, dan controller login supaya aturan sanitasi tujuan tidak terduplikasi.
 */
final class LoginRedirect
{
    /**
     * URL halaman login yang membawa path asal pada parameter next.
     */
    public static function target(Request $request): string
    {
        return '/login?next=' . urlencode($request->rawPath());
    }

    /**
     * Hanya izinkan tujuan internal (diawali "/" tunggal) untuk mencegah open redirect.
     */
    public static function sanitizeNext(?string $next): string
    {
        if ($next === null) {
            return '/';
        }

        $next = trim($next);
        if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
            return '/';
        }

        return $next;
    }
}
