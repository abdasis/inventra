<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Supplier;

use Inventra\Core\Actions\Supplier\Concerns\BuildsSupplierFromPayload;
use Inventra\Core\Repositories\SupplierRepository;

/**
 * Menambahkan satu supplier baru dan mengembalikan id hasil insert.
 */
final class CreateSupplierAction
{
    use BuildsSupplierFromPayload;

    public function __construct(private readonly SupplierRepository $suppliers)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): int
    {
        return $this->suppliers->create($this->supplierFromPayload($payload, null));
    }
}
