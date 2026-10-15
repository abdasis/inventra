<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\User;

use Inventra\Core\Enums\UserRole;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\UserRepository;

/**
 * Aturan bersama Update, Delete, dan ToggleUserActive: administrator aktif
 * terakhir tidak boleh kehilangan perannya, dinonaktifkan, maupun dihapus.
 *
 * Pesannya diserahkan pemanggil karena tiap use case punya kalimat verbatimnya
 * sendiri, sedangkan syaratnya satu dan sama.
 */
final class GuardLastActiveAdminAction
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @param array<string, mixed> $user Baris user yang sedang diperiksa.
     */
    public function execute(array $user, string $message): void
    {
        if ($this->isLastActiveAdmin($user)) {
            throw new ValidationException($message);
        }
    }

    /**
     * @param array<string, mixed> $user Baris user yang sedang diperiksa.
     */
    public function isLastActiveAdmin(array $user): bool
    {
        if (UserRole::tryFrom(trim((string)($user['role'] ?? ''))) !== UserRole::Admin) {
            return false;
        }

        if (!(bool)($user['is_active'] ?? false)) {
            return false;
        }

        return $this->users->countActiveAdmins() <= 1;
    }
}
