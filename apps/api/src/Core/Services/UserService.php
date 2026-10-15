<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\User\CreateUserAction;
use Inventra\Core\Actions\User\DeleteUserAction;
use Inventra\Core\Actions\User\ToggleUserActiveAction;
use Inventra\Core\Actions\User\UpdateUserAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\UserDTO;
use Inventra\Core\Enums\UserRole;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\UserRepository;
use Inventra\Core\Support\ActivityLogger;
use Inventra\Core\Validation\UserInput;

/**
 * Orkestrasi use case pengguna: validasi input, pemanggilan Action, transaksi
 * untuk perubahan yang menulis lebih dari sekali, dan narasi activity log.
 * Controller hanya meneruskan hasilnya ke Response.
 */
final class UserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserInput $input,
        private readonly CreateUserAction $createUser,
        private readonly UpdateUserAction $updateUser,
        private readonly DeleteUserAction $deleteUser,
        private readonly ToggleUserActiveAction $toggleUserActive,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload): array
    {
        $input = $this->input->validated($payload, null);
        $id = $this->createUser->execute($input);

        ActivityLogger::log(
            'user.create',
            sprintf(
                'Menambahkan pengguna %s (%s) dengan peran %s',
                $input['name'],
                $input['email'],
                UserRole::from($input['role'])->label()
            ),
            'user',
            $id,
            $this->ipAddress
        );

        return [
            'message' => 'Pengguna baru berhasil ditambahkan.',
            'data' => UserDTO::fromRow($this->users->find($id) ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function update(int $id, array $payload, int $actorId): array
    {
        // Urutan lama dipertahankan: 404 lebih dulu, baru 422 validasi.
        $user = $this->findOrFail($id);
        $input = $this->input->validated($payload, $id);

        $passwordChanged = Connection::transaction(
            fn (): bool => $this->updateUser->execute($user, $input, $actorId)
        );

        ActivityLogger::log(
            'user.update',
            sprintf('Memperbarui data pengguna %s (%s)', $input['name'], $input['email']),
            'user',
            $id,
            $this->ipAddress
        );

        if ($passwordChanged) {
            ActivityLogger::log(
                'user.password_reset',
                sprintf('Mereset kata sandi pengguna %s (%s)', $input['name'], $input['email']),
                'user',
                $id,
                $this->ipAddress
            );
        }

        return [
            'message' => 'Data pengguna berhasil diperbarui.',
            'data' => UserDTO::fromRow($this->users->find($id) ?? []),
        ];
    }

    /**
     * @return array{message: string}
     */
    public function delete(int $id, int $actorId): array
    {
        $user = $this->findOrFail($id);
        $this->deleteUser->execute($user, $actorId);

        ActivityLogger::log(
            'user.delete',
            sprintf('Menghapus pengguna %s (%s)', $this->name($user), $this->email($user)),
            'user',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Pengguna berhasil dihapus.'];
    }

    /**
     * @return array{message: string, data: array<string, mixed>}
     */
    public function toggleActive(int $id, int $actorId): array
    {
        $user = $this->findOrFail($id);
        $wasActive = $this->toggleUserActive->execute($user, $actorId);
        $name = $this->name($user);
        $email = $this->email($user);

        ActivityLogger::log(
            $wasActive ? 'user.deactivate' : 'user.activate',
            $wasActive
                ? sprintf('Menonaktifkan pengguna %s (%s)', $name, $email)
                : sprintf('Mengaktifkan kembali pengguna %s (%s)', $name, $email),
            'user',
            $id,
            $this->ipAddress
        );

        return [
            'message' => $wasActive
                ? 'Pengguna berhasil dinonaktifkan.'
                : 'Pengguna berhasil diaktifkan kembali.',
            'data' => UserDTO::fromRow($this->users->find($id) ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrFail(int $id): array
    {
        return $this->users->find($id) ?? throw new NotFoundException('Pengguna tidak ditemukan.');
    }

    /**
     * @param array<string, mixed> $user
     */
    private function name(array $user): string
    {
        return trim((string)($user['name'] ?? ''));
    }

    /**
     * @param array<string, mixed> $user
     */
    private function email(array $user): string
    {
        return trim((string)($user['email'] ?? ''));
    }
}
