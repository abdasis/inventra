<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Supplier;

use Inventra\Core\Actions\Supplier\Concerns\BuildsSupplierFromPayload;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\SupplierRepository;

/**
 * Memperbarui supplier yang sudah ada; kode tetap unik terhadap baris lain.
 */
final class UpdateSupplierAction
{
    use BuildsSupplierFromPayload;

    public function __construct(private readonly SupplierRepository $suppliers)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(int $id, array $payload): void
    {
        if ($this->suppliers->find($id) === null) {
            throw new NotFoundException('Supplier tidak ditemukan.');
        }

        $this->suppliers->update($id, $this->supplierFromPayload($payload, $id));
    }
}
