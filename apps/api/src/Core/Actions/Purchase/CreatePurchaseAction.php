<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Purchase;

use Inventra\Core\Actions\Purchase\Concerns\BuildsPurchaseFromPayload;
use Inventra\Core\Enums\PurchaseStatus;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Repositories\PurchaseRepository;
use Inventra\Core\Repositories\SupplierRepository;

/**
 * Mencatat pembelian baru sebagai draf. Nomor pembelian dibangkitkan otomatis
 * bila tidak diisi, dan totalnya dihitung dari baris pesanan — bukan dari
 * payload, supaya nilai header tidak bisa bertentangan dengan barisnya.
 */
final class CreatePurchaseAction
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
     * @param string $actorName Nama pelaku dari sesi, diserahkan Controller.
     */
    public function execute(array $payload, string $actorName): int
    {
        $input = $this->purchaseFromPayload($this->purchases, $this->items, $this->suppliers, $payload);
        $header = $input['header'];

        $header['status'] = PurchaseStatus::Draft->value;
        $header['created_by_name'] = $actorName;

        if ($header['purchase_number'] === '') {
            $header['purchase_number'] = $this->purchases->nextNumber();
        }

        return $this->purchases->create($header, $input['details']);
    }
}
