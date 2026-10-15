<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\User;

use Inventra\Core\Enums\UserRole;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\UserRepository;

/**
 * Membalik status aktif pengguna. Peran yang tidak dikenal ditolak lebih dulu,
 * karena tanpa peran yang sah baris tidak boleh ditulis ulang.
 */
final class ToggleUserActiveAction
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly GuardLastActiveAdminAction $guardLastActiveAdmin,
    ) {
    }

    /**
     * @param array<string, mixed> $user Baris user sebelum perubahan.
     * @return bool Status aktif sebelum diubah.
     */
    public function execute(array $user, int $actorId): bool
    {
        $id = (int)$user['id'];
        $role = UserRole::tryFrom(trim((string)($user['role'] ?? '')));

        if ($role === null) {
            throw new ValidationException('Peran pengguna tidak valid, data tidak dapat diubah.');
        }

        if ($id === $actorId) {
            throw new ValidationException('Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $wasActive = (bool)($user['is_active'] ?? false);

        if ($wasActive) {
            $this->guardLastActiveAdmin->execute(
                $user,
                'Admin aktif terakhir tidak dapat dinonaktifkan.'
            );
        }

        $this->users->update(
            $id,
            trim((string)($user['name'] ?? '')),
            trim((string)($user['email'] ?? '')),
            $role,
            !$wasActive
        );

        return $wasActive;
    }
}
