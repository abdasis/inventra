<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

/**
 * Satu baris pesanan pembelian. quantity_outstanding adalah sisa yang belum
 * diterima maupun ditolak, dihitung di sini supaya frontend tidak mengulang
 * aritmetika domainnya.
 */
final readonly class PurchaseDetailDTO
{
    public function __construct(
        public ?int $id,
        public int $purchaseId,
        public int $itemId,
        public ?string $itemCode,
        public ?string $itemName,
        public ?string $itemType,
        public ?string $unitName,
        public int $quantityOrdered,
        public int $quantityReceived,
        public int $quantityRejected,
        public int $quantityOutstanding,
        public float $unitPrice,
        public ?string $notes,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $ordered = (int)($data['quantity_ordered'] ?? 0);
        $received = (int)($data['quantity_received'] ?? 0);
        $rejected = (int)($data['quantity_rejected'] ?? 0);

        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            purchaseId: (int)($data['purchase_id'] ?? 0),
            itemId: (int)($data['item_id'] ?? 0),
            itemCode: !empty($data['item_code']) ? (string)$data['item_code'] : null,
            itemName: !empty($data['item_name']) ? (string)$data['item_name'] : null,
            itemType: !empty($data['item_type']) ? (string)$data['item_type'] : null,
            unitName: !empty($data['unit_name']) ? (string)$data['unit_name'] : null,
            quantityOrdered: $ordered,
            quantityReceived: $received,
            quantityRejected: $rejected,
            quantityOutstanding: $ordered - $received - $rejected,
            unitPrice: (float)($data['unit_price'] ?? 0.0),
            notes: !empty($data['notes']) ? (string)$data['notes'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $subtotal = $this->quantityOrdered * $this->unitPrice;

        return [
            'id' => $this->id,
            'purchase_id' => $this->purchaseId,
            'item_id' => $this->itemId,
            'item_code' => $this->itemCode,
            'item_name' => $this->itemName,
            'item_type' => $this->itemType,
            'unit_name' => $this->unitName,
            'quantity_ordered' => $this->quantityOrdered,
            'quantity_received' => $this->quantityReceived,
            'quantity_rejected' => $this->quantityRejected,
            'quantity_outstanding' => $this->quantityOutstanding,
            'unit_price' => $this->unitPrice,
            'unit_price_formatted' => 'Rp ' . number_format($this->unitPrice, 0, ',', '.'),
            'subtotal' => $subtotal,
            'subtotal_formatted' => 'Rp ' . number_format($subtotal, 0, ',', '.'),
            'notes' => $this->notes,
        ];
    }
}
