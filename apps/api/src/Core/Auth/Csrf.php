<?php

declare(strict_types=1);

namespace Inventra\Core\Auth;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const TOKEN_BYTES = 32;

    public static function token(): string
    {
        $existing = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $_SESSION[self::SESSION_KEY] = $token;
        return $token;
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    public static function rotate(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        self::token();
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
            . '">';
    }
}
