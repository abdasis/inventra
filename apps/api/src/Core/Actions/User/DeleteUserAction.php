<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\User;

use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\UserRepository;

/**
 * Menghapus satu pengguna. Akun sendiri dan administrator aktif terakhir
 * ditolak sebelum baris apa pun terhapus.
 */
final class DeleteUserAction
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly GuardLastActiveAdminAction $guardLastActiveAdmin,
    ) {
    }

    /**
     * @param array<string, mixed> $user Baris user yang akan dihapus.
     */
    public function execute(array $user, int $actorId): void
    {
        $id = (int)$user['id'];

        if ($id === $actorId) {
            throw new ValidationException('Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $this->guardLastActiveAdmin->execute(
            $user,
            'Admin aktif terakhir tidak dapat dihapus. Ubah peran pengguna lain terlebih dahulu.'
        );

        $this->users->delete($id);
    }
}
