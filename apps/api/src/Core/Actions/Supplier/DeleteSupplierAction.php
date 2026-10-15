<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Supplier;

use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\SupplierRepository;

/**
 * Menghapus supplier dan mengembalikan baris yang terhapus untuk narasi log.
 */
final class DeleteSupplierAction
{
    public function __construct(private readonly SupplierRepository $suppliers)
    {
    }

    /**
     * @return array<string, mixed> Baris supplier sebelum dihapus.
     */
    public function execute(int $id): array
    {
        $supplier = $this->suppliers->find($id);
        if ($supplier === null) {
            throw new NotFoundException('Supplier tidak ditemukan.');
        }

        $this->suppliers->delete($id);

        return $supplier;
    }
}
