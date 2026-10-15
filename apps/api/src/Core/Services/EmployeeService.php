<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Employee\CreateEmployeeAction;
use Inventra\Core\Actions\Employee\DeleteEmployeeAction;
use Inventra\Core\Actions\Employee\UpdateEmployeeAction;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Support\ActivityLogger;
use Inventra\Core\Support\FieldChangeNarrator;

/**
 * Orkestrasi use case karyawan: memanggil Action lalu menarasikan activity log.
 * Penulisan hanya menyentuh satu tabel, jadi tidak ada transaksi di sini.
 */
final class EmployeeService
{
    /** @var array<string, string> */
    private const CHANGE_LABELS = [
        'name' => 'nama',
        'department' => 'departemen',
        'position' => 'jabatan',
        'email' => 'email',
        'phone' => 'telepon',
        'status' => 'status',
    ];

    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly CreateEmployeeAction $createEmployee,
        private readonly UpdateEmployeeAction $updateEmployee,
        private readonly DeleteEmployeeAction $deleteEmployee,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload): array
    {
        $id = $this->createEmployee->execute($payload);
        $employee = $this->employees->find($id) ?? [];

        ActivityLogger::log(
            'employee.create',
            'Menambahkan karyawan ' . $this->label($employee) . $this->departmentNote($employee),
            'employee',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Karyawan berhasil ditambahkan.', 'data' => $employee];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function update(int $id, array $payload): array
    {
        // Urutan lama dipertahankan: 404 lebih dulu, baru 422 validasi.
        $before = $this->findOrFail($id);
        $this->updateEmployee->execute($id, $payload);
        $after = $this->employees->find($id) ?? [];

        ActivityLogger::log(
            'employee.update',
            'Memperbarui karyawan ' . $this->label($after)
                . FieldChangeNarrator::describe($this->narrated($before), $this->narrated($after), self::CHANGE_LABELS),
            'employee',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Data karyawan berhasil diperbarui.', 'data' => $after];
    }

    /**
     * @return array{message: string}
     */
    public function delete(int $id): array
    {
        $employee = $this->deleteEmployee->execute($id);

        ActivityLogger::log(
            'employee.delete',
            'Menghapus karyawan ' . $this->label($employee),
            'employee',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Karyawan berhasil dihapus.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrFail(int $id): array
    {
        return $this->employees->find($id) ?? throw new NotFoundException('Karyawan tidak ditemukan.');
    }

    /**
     * @param array<string, mixed> $employee
     */
    private function label(array $employee): string
    {
        return trim((string)($employee['name'] ?? ''))
            . ' (' . trim((string)($employee['employee_number'] ?? '')) . ')';
    }

    /**
     * @param array<string, mixed> $employee
     */
    private function departmentNote(array $employee): string
    {
        $department = trim((string)($employee['department'] ?? ''));

        return $department === '' ? '' : ' pada departemen ' . $department;
    }

    /**
     * @param array<string, mixed> $employee
     * @return array<string, mixed>
     */
    private function narrated(array $employee): array
    {
        return [
            'name' => $employee['name'] ?? '',
            'department' => $employee['department'] ?? '',
            'position' => $employee['position'] ?? '',
            'email' => $employee['email'] ?? '',
            'phone' => $employee['phone'] ?? '',
            'status' => !empty($employee['is_active']) ? 'Aktif' : 'Nonaktif',
        ];
    }
}
