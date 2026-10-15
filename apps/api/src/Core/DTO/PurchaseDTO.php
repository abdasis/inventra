<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

use Inventra\Core\Enums\PurchaseStatus;

/**
 * Header pembelian siap tampil. Agregat baris (item_count, quantity_ordered,
 * quantity_received) diisi dari subquery daftar; pada baris tanpa detail
 * nilainya nol.
 */
final readonly class PurchaseDTO
{
    public function __construct(
        public ?int $id,
        public string $purchaseNumber,
        public ?int $supplierId,
        public ?string $supplierName,
        public string $purchaseDate,
        public PurchaseStatus $status,
        public ?string $reference,
        public ?string $notes,
        public float $totalAmount,
        public int $itemCount,
        public int $quantityOrdered,
        public int $quantityReceived,
        public ?string $createdByName,
        public ?string $receivedAt,
        public ?string $cancelledAt,
        public ?string $cancelReason,
        public ?string $createdAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = PurchaseStatus::tryFrom((string)($data['status'] ?? '')) ?? PurchaseStatus::Draft;

        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            purchaseNumber: (string)($data['purchase_number'] ?? ''),
            supplierId: isset($data['supplier_id']) ? (int)$data['supplier_id'] : null,
            supplierName: !empty($data['supplier_name']) ? (string)$data['supplier_name'] : null,
            purchaseDate: (string)($data['purchase_date'] ?? ''),
            status: $status,
            reference: !empty($data['reference']) ? (string)$data['reference'] : null,
            notes: !empty($data['notes']) ? (string)$data['notes'] : null,
            totalAmount: (float)($data['total_amount'] ?? 0.0),
            itemCount: (int)($data['item_count'] ?? 0),
            quantityOrdered: (int)($data['quantity_ordered'] ?? 0),
            quantityReceived: (int)($data['quantity_received'] ?? 0),
            createdByName: !empty($data['created_by_name']) ? (string)$data['created_by_name'] : null,
            receivedAt: !empty($data['received_at']) ? (string)$data['received_at'] : null,
            cancelledAt: !empty($data['cancelled_at']) ? (string)$data['cancelled_at'] : null,
            cancelReason: !empty($data['cancel_reason']) ? (string)$data['cancel_reason'] : null,
            createdAt: !empty($data['created_at']) ? (string)$data['created_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'purchase_number' => $this->purchaseNumber,
            'supplier_id' => $this->supplierId,
            'supplier_name' => $this->supplierName,
            'purchase_date' => $this->purchaseDate,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_badge' => $this->status->badgeClass(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'total_amount' => $this->totalAmount,
            'total_amount_formatted' => 'Rp ' . number_format($this->totalAmount, 0, ',', '.'),
            'item_count' => $this->itemCount,
            'quantity_ordered' => $this->quantityOrdered,
            'quantity_received' => $this->quantityReceived,
            'created_by_name' => $this->createdByName,
            'received_at' => $this->receivedAt,
            'cancelled_at' => $this->cancelledAt,
            'cancel_reason' => $this->cancelReason,
            'created_at' => $this->createdAt,
        ];
    }
}
