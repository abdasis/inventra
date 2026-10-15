<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Employee;

use Inventra\Core\Actions\Employee\Concerns\BuildsEmployeeFromPayload;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\EmployeeRepository;

/**
 * Memperbarui karyawan yang sudah ada; nomor karyawan tetap unik terhadap baris lain.
 */
final class UpdateEmployeeAction
{
    use BuildsEmployeeFromPayload;

    public function __construct(private readonly EmployeeRepository $employees)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(int $id, array $payload): void
    {
        if ($this->employees->find($id) === null) {
            throw new NotFoundException('Karyawan tidak ditemukan.');
        }

        $this->employees->update($id, $this->employeeFromPayload($payload, $id));
    }
}
