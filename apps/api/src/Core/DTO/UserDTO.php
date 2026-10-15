<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

use Inventra\Core\Enums\UserRole;

/**
 * Bentuk objek user siap tampil. password_hash tidak pernah ikut. Urutan key
 * hasil fromRow() bagian dari kontrak respons, jangan diubah.
 */
final class UserDTO
{
    /** @var array<int, string> */
    private const MONTHS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /**
     * @param array<string, mixed> $user Baris tabel users.
     * @return array<string, mixed>
     */
    public static function fromRow(array $user): array
    {
        $role = UserRole::tryFrom(self::stringValue($user['role'] ?? ''));
        $isActive = (bool)($user['is_active'] ?? false);

        return [
            'id' => (int)($user['id'] ?? 0),
            'name' => self::stringValue($user['name'] ?? ''),
            'email' => self::stringValue($user['email'] ?? ''),
            'role' => self::stringValue($user['role'] ?? ''),
            'is_active' => $isActive,
            'last_login_at' => $user['last_login_at'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null,
            'role_label' => $role?->label(),
            'role_badge_class' => $role?->badgeClass(),
            'status_label' => $isActive ? 'Aktif' : 'Nonaktif',
            'last_login_formatted' => self::formatLastLogin($user['last_login_at'] ?? null),
        ];
    }

    /**
     * Contoh keluaran: "15 Okt 2026 09:20".
     */
    private static function formatLastLogin(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('d', $timestamp)
            . ' ' . self::MONTHS[(int)date('n', $timestamp)]
            . ' ' . date('Y H:i', $timestamp);
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
