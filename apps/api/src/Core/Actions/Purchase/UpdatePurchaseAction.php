<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Purchase;

use Inventra\Core\Actions\Purchase\Concerns\BuildsPurchaseFromPayload;
use Inventra\Core\Enums\PurchaseStatus;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Repositories\PurchaseRepository;
use Inventra\Core\Repositories\SupplierRepository;

/**
 * Mengubah header dan mengganti seluruh baris pesanan, hanya selama pembelian
 * masih draf. Setelah ada penerimaan, catatan penerimaan dan mutasi stok sudah
 * mengacu ke baris yang lama, jadi mengubahnya akan memutus jejak audit.
 */
final class UpdatePurchaseAction
{
    use BuildsPurchaseFromPayload;

    public function __construct(
        private readonly PurchaseRepository $purchases,
        private readonly ItemRepository $items,
        private readonly SupplierRepository $suppliers,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(int $id, array $payload): void
    {
        $existing = $this->purchases->find($id)
            ?? throw new NotFoundException('Pembelian tidak ditemukan.');

        if (PurchaseStatus::tryFrom((string)$existing['status']) !== PurchaseStatus::Draft) {
            throw new ValidationException('Pembelian yang sudah diproses tidak dapat diubah.');
        }

        // Nomor yang tidak dikirim dipertahankan supaya nomor otomatis tidak
        // berubah hanya karena klien tidak menyertakannya.
        if ($this->purchaseText($payload['purchase_number'] ?? '') === '') {
            $payload['purchase_number'] = $existing['purchase_number'];
        }

        $input = $this->purchaseFromPayload($this->purchases, $this->items, $this->suppliers, $payload, $id);

        $this->purchases->update($id, $input['header'], $input['details']);
    }
}
