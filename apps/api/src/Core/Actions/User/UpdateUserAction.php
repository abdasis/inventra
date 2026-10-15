<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\User;

use Inventra\Core\Enums\UserRole;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\UserRepository;

/**
 * Memperbarui profil pengguna, sekaligus mereset kata sandinya bila pemanggil
 * mengirim kata sandi baru. Urutan pemeriksaan dipertahankan seperti perilaku
 * sebelumnya sehingga pesan mana yang muncul lebih dulu tidak berubah.
 *
 * Aktor diserahkan pemanggil supaya larangan menonaktifkan akun sendiri bisa
 * diuji tanpa sesi.
 */
final class UpdateUserAction
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly GuardLastActiveAdminAction $guardLastActiveAdmin,
    ) {
    }

    /**
     * @param array<string, mixed> $user Baris user sebelum perubahan.
     * @param array<string, mixed> $input Payload yang sudah dinormalisasi dan divalidasi.
     * @return bool true bila kata sandi ikut direset.
     */
    public function execute(array $user, array $input, int $actorId): bool
    {
        $id = (int)$user['id'];
        $role = UserRole::from($input['role']);

        if ($role !== UserRole::Admin) {
            $this->guardLastActiveAdmin->execute(
                $user,
                'Admin aktif terakhir harus tetap berperan administrator.'
            );
        }

        if ($id === $actorId && !$input['is_active']) {
            throw new ValidationException('Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        if ((bool)($user['is_active'] ?? false) && !$input['is_active']) {
            $this->guardLastActiveAdmin->execute(
                $user,
                'Admin aktif terakhir tidak dapat dinonaktifkan.'
            );
        }

        $this->users->update($id, $input['name'], $input['email'], $role, $input['is_active']);

        if ($input['password'] === '') {
            return false;
        }

        $this->users->updatePassword($id, password_hash($input['password'], PASSWORD_DEFAULT));

        return true;
    }
}
