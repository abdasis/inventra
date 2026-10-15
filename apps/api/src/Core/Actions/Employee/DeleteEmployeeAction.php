<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Employee;

use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\EmployeeRepository;

/**
 * Menghapus karyawan dan mengembalikan baris yang terhapus untuk narasi log.
 */
final class DeleteEmployeeAction
{
    public function __construct(private readonly EmployeeRepository $employees)
    {
    }

    /**
     * @return array<string, mixed> Baris karyawan sebelum dihapus.
     */
    public function execute(int $id): array
    {
        $employee = $this->employees->find($id);
        if ($employee === null) {
            throw new NotFoundException('Karyawan tidak ditemukan.');
        }

        $this->employees->delete($id);

        return $employee;
    }
}
