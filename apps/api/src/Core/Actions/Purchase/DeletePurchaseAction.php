<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Purchase;

use Inventra\Core\Enums\PurchaseStatus;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\PurchaseRepository;

/**
 * Menghapus pembelian draf. Pembelian yang sudah diproses tidak dihapus karena
 * penerimaannya sudah mengubah stok dan/atau melahirkan unit aset; pembatalan
 * dengan alasan adalah jalur yang menyimpan histori.
 */
final class DeletePurchaseAction
{
    public function __construct(private readonly PurchaseRepository $purchases)
    {
    }

    /**
     * @return array<string, mixed> Baris pembelian sebelum dihapus.
     */
    public function execute(int $id): array
    {
        $existing = $this->purchases->find($id)
            ?? throw new NotFoundException('Pembelian tidak ditemukan.');

        if (PurchaseStatus::tryFrom((string)$existing['status']) !== PurchaseStatus::Draft) {
            throw new ValidationException('Pembelian yang sudah diproses tidak dapat dihapus.');
        }

        $this->purchases->delete($id);

        return $existing;
    }
}
