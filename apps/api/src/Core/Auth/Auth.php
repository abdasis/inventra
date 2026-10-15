<?php

declare(strict_types=1);

namespace Inventra\Core\Auth;

use Inventra\Core\Enums\UserRole;
use Inventra\Core\Repositories\UserRepository;
use Inventra\Core\Support\ActivityLogger;

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    /**
     * Pesan sengaja generik dan sama untuk semua kegagalan, supaya tidak bisa
     * dipakai menebak email mana yang terdaftar.
     */
    private const INVALID_CREDENTIALS = 'Email atau kata sandi salah.';

    private static ?array $cachedUser = null;
    private static bool $resolved = false;

    /**
     * Idempoten: web front controller dan API front controller memanggilnya dalam request yang sama.
     */
    public static function start(?bool $secureCookie = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secureCookie ?? false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public static function attempt(string $email, string $password, ?string $ipAddress = null): array
    {
        $userRepository = new UserRepository();
        $user = $userRepository->findByEmail($email);

        if ($user !== null && self::isLocked($user)) {
            $remaining = (int)ceil((strtotime((string)$user['locked_until']) - time()) / 60);
            return [
                'success' => false,
                'message' => 'Akun terkunci sementara karena percobaan login gagal berulang. Coba lagi dalam ' . $remaining . ' menit.',
            ];
        }

        $valid = $user !== null
            && (int)$user['is_active'] === 1
            && password_verify($password, (string)$user['password_hash']);

        if (!$valid) {
            if ($user !== null) {
                $attempts = (int)$user['failed_login_attempts'] + 1;
                $lockedUntil = $attempts >= self::MAX_ATTEMPTS
                    ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60)
                    : null;
                $userRepository->recordFailedLogin((int)$user['id'], $attempts, $lockedUntil);
            }

            ActivityLogger::log(
                'login_failed',
                'Percobaan login gagal untuk akun ' . $email . '.',
                'user',
                $user !== null ? (int)$user['id'] : null,
                $ipAddress
            );

            return ['success' => false, 'message' => self::INVALID_CREDENTIALS];
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        self::$cachedUser = null;
        self::$resolved = false;

        $userRepository->markLogin((int)$user['id'], date('Y-m-d H:i:s'));
        Csrf::rotate();

        ActivityLogger::log(
            'login',
            'Pengguna ' . $user['name'] . ' (' . $user['email'] . ') berhasil login ke sistem.',
            'user',
            (int)$user['id'],
            $ipAddress
        );

        return ['success' => true, 'message' => 'Login berhasil.'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$cachedUser;
        }

        self::$resolved = true;
        self::$cachedUser = null;

        $id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if ($id <= 0) {
            return null;
        }

        $user = (new UserRepository())->find($id);
        if ($user === null || (int)$user['is_active'] !== 1) {
            return null;
        }

        self::$cachedUser = $user;
        return self::$cachedUser;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int)$user['id'] : null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        return UserRole::tryFrom((string)$user['role'])?->isAdmin() ?? false;
    }

    public static function logout(?string $ipAddress = null): void
    {
        $user = self::user();

        if ($user !== null) {
            ActivityLogger::log(
                'logout',
                'Pengguna ' . $user['name'] . ' (' . $user['email'] . ') keluar dari sistem.',
                'user',
                (int)$user['id'],
                $ipAddress
            );
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$cachedUser = null;
        self::$resolved = false;
    }

    /**
     * @param array<string, mixed> $user
     */
    private static function isLocked(array $user): bool
    {
        $lockedUntil = $user['locked_until'] ?? null;
        if (!is_string($lockedUntil) || $lockedUntil === '') {
            return false;
        }

        return strtotime($lockedUntil) > time();
    }
}
