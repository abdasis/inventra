<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

use Inventra\Core\Enums\StockMovementType;

/**
 * Satu catatan pergerakan stok untuk riwayat yang bisa diaudit. Kuantitas selalu
 * positif; arahnya dibaca dari movement_type.
 */
final readonly class StockMovementDTO
{
    public function __construct(
        public ?int $id,
        public int $itemId,
        public ?string $itemCode,
        public ?string $itemName,
        public StockMovementType $movementType,
        public int $quantity,
        public int $balanceAfter,
        public ?string $referenceType,
        public ?int $referenceId,
        public ?string $department,
        public ?int $employeeId,
        public ?string $employeeName,
        public ?string $reason,
        public ?string $notes,
        public ?string $actorName,
        public ?string $createdAt,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $type = StockMovementType::tryFrom((string)($data['movement_type'] ?? '')) ?? StockMovementType::Adjustment;

        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            itemId: (int)($data['item_id'] ?? 0),
            itemCode: !empty($data['item_code']) ? (string)$data['item_code'] : null,
            itemName: !empty($data['item_name']) ? (string)$data['item_name'] : null,
            movementType: $type,
            quantity: (int)($data['quantity'] ?? 0),
            balanceAfter: (int)($data['balance_after'] ?? 0),
            referenceType: !empty($data['reference_type']) ? (string)$data['reference_type'] : null,
            referenceId: isset($data['reference_id']) ? (int)$data['reference_id'] : null,
            department: !empty($data['department']) ? (string)$data['department'] : null,
            employeeId: isset($data['employee_id']) ? (int)$data['employee_id'] : null,
            employeeName: !empty($data['employee_name']) ? (string)$data['employee_name'] : null,
            reason: !empty($data['reason']) ? (string)$data['reason'] : null,
            notes: !empty($data['notes']) ? (string)$data['notes'] : null,
            actorName: !empty($data['actor_name']) ? (string)$data['actor_name'] : null,
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
            'item_id' => $this->itemId,
            'item_code' => $this->itemCode,
            'item_name' => $this->itemName,
            'movement_type' => $this->movementType->value,
            'movement_type_label' => $this->movementType->label(),
            'quantity' => $this->quantity,
            'balance_after' => $this->balanceAfter,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'department' => $this->department,
            'employee_id' => $this->employeeId,
            'employee_name' => $this->employeeName,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'actor_name' => $this->actorName,
            'created_at' => $this->createdAt,
        ];
    }
}
