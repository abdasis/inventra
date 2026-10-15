<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\User;

use Inventra\Core\Enums\UserRole;
use Inventra\Core\Repositories\UserRepository;

/**
 * Menambahkan satu pengguna dan mengembalikan id hasil insert. Payload dianggap
 * sudah dinormalisasi dan divalidasi pemanggil (UserInput).
 */
final class CreateUserAction
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input): int
    {
        return $this->users->create(
            $input['name'],
            $input['email'],
            password_hash($input['password'], PASSWORD_DEFAULT),
            UserRole::from($input['role']),
            $input['is_active']
        );
    }
}
