<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Employee;

use Inventra\Core\Actions\Employee\Concerns\BuildsEmployeeFromPayload;
use Inventra\Core\Repositories\EmployeeRepository;

/**
 * Menambahkan satu karyawan baru dan mengembalikan id hasil insert.
 */
final class CreateEmployeeAction
{
    use BuildsEmployeeFromPayload;

    public function __construct(private readonly EmployeeRepository $employees)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): int
    {
        return $this->employees->create($this->employeeFromPayload($payload, null));
    }
}
