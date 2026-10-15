<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Supplier\CreateSupplierAction;
use Inventra\Core\Actions\Supplier\DeleteSupplierAction;
use Inventra\Core\Actions\Supplier\UpdateSupplierAction;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\SupplierRepository;
use Inventra\Core\Support\ActivityLogger;
use Inventra\Core\Support\FieldChangeNarrator;

/**
 * Orkestrasi use case supplier: memanggil Action lalu menarasikan activity log.
 * Penulisan hanya menyentuh satu tabel, jadi tidak ada transaksi di sini.
 */
final class SupplierService
{
    /** @var array<string, string> */
    private const CHANGE_LABELS = [
        'name' => 'nama',
        'contact_person' => 'kontak',
        'phone' => 'telepon',
        'email' => 'email',
        'address' => 'alamat',
        'status' => 'status',
    ];

    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly CreateSupplierAction $createSupplier,
        private readonly UpdateSupplierAction $updateSupplier,
        private readonly DeleteSupplierAction $deleteSupplier,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload): array
    {
        $id = $this->createSupplier->execute($payload);
        $supplier = $this->suppliers->find($id) ?? [];

        ActivityLogger::log(
            'supplier.create',
            'Menambahkan supplier ' . $this->label($supplier),
            'supplier',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Supplier berhasil ditambahkan.', 'data' => $supplier];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function update(int $id, array $payload): array
    {
        // Urutan lama dipertahankan: 404 lebih dulu, baru 422 validasi.
        $before = $this->findOrFail($id);
        $this->updateSupplier->execute($id, $payload);
        $after = $this->suppliers->find($id) ?? [];

        ActivityLogger::log(
            'supplier.update',
            'Memperbarui supplier ' . $this->label($after)
                . FieldChangeNarrator::describe($this->narrated($before), $this->narrated($after), self::CHANGE_LABELS),
            'supplier',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Data supplier berhasil diperbarui.', 'data' => $after];
    }

    /**
     * @return array{message: string}
     */
    public function delete(int $id): array
    {
        $supplier = $this->deleteSupplier->execute($id);

        ActivityLogger::log(
            'supplier.delete',
            'Menghapus supplier ' . $this->label($supplier),
            'supplier',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Supplier berhasil dihapus.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrFail(int $id): array
    {
        return $this->suppliers->find($id) ?? throw new NotFoundException('Supplier tidak ditemukan.');
    }

    /**
     * @param array<string, mixed> $supplier
     */
    private function label(array $supplier): string
    {
        return trim((string)($supplier['name'] ?? '')) . ' (' . trim((string)($supplier['code'] ?? '')) . ')';
    }

    /**
     * @param array<string, mixed> $supplier
     * @return array<string, mixed>
     */
    private function narrated(array $supplier): array
    {
        return [
            'name' => $supplier['name'] ?? '',
            'contact_person' => $supplier['contact_person'] ?? '',
            'phone' => $supplier['phone'] ?? '',
            'email' => $supplier['email'] ?? '',
            'address' => $supplier['address'] ?? '',
            'status' => !empty($supplier['is_active']) ? 'Aktif' : 'Nonaktif',
        ];
    }
}
