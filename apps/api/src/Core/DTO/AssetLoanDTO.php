<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\CustodyStatus;

/**
 * Satu baris peminjaman aset siap tampil. is_overdue dihitung di sini: hanya
 * pinjaman yang masih aktif dan sudah melewati due_at yang dianggap terlambat,
 * sehingga frontend tidak mengulang perbandingan tanggalnya.
 */
final readonly class AssetLoanDTO
{
    public function __construct(
        public ?int $id,
        public int $assetId,
        public ?string $assetCode,
        public ?string $assetName,
        public ?int $employeeId,
        public ?string $employeeName,
        public ?int $locationId,
        public ?string $locationName,
        public string $loanedAt,
        public ?string $dueAt,
        public ?string $returnedAt,
        public bool $isOverdue,
        public AssetCondition $conditionOut,
        public ?AssetCondition $conditionIn,
        public string $handedByName,
        public ?string $receivedByName,
        public ?string $purpose,
        public CustodyStatus $status,
        public ?string $notes,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $dueAt = !empty($data['due_at']) ? (string)$data['due_at'] : null;
        $status = CustodyStatus::tryFrom((string)($data['status'] ?? '')) ?? CustodyStatus::Active;

        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            assetId: (int)($data['asset_id'] ?? 0),
            assetCode: !empty($data['asset_code']) ? (string)$data['asset_code'] : null,
            assetName: !empty($data['asset_name']) ? (string)$data['asset_name'] : null,
            employeeId: !empty($data['employee_id']) ? (int)$data['employee_id'] : null,
            employeeName: !empty($data['employee_name']) ? (string)$data['employee_name'] : null,
            locationId: !empty($data['location_id']) ? (int)$data['location_id'] : null,
            locationName: !empty($data['location_name']) ? (string)$data['location_name'] : null,
            loanedAt: (string)($data['loaned_at'] ?? ''),
            dueAt: $dueAt,
            returnedAt: !empty($data['returned_at']) ? (string)$data['returned_at'] : null,
            isOverdue: $status === CustodyStatus::Active && $dueAt !== null && $dueAt < date('Y-m-d'),
            conditionOut: AssetCondition::tryFrom((string)($data['condition_out'] ?? '')) ?? AssetCondition::Good,
            conditionIn: AssetCondition::tryFrom((string)($data['condition_in'] ?? '')) ?: null,
            handedByName: (string)($data['handed_by_name'] ?? ''),
            receivedByName: !empty($data['received_by_name']) ? (string)$data['received_by_name'] : null,
            purpose: !empty($data['purpose']) ? (string)$data['purpose'] : null,
            status: $status,
            notes: !empty($data['notes']) ? (string)$data['notes'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->assetId,
            'asset_code' => $this->assetCode,
            'asset_name' => $this->assetName,
            'employee_id' => $this->employeeId,
            'employee_name' => $this->employeeName,
            'location_id' => $this->locationId,
            'location_name' => $this->locationName,
            'loaned_at' => $this->loanedAt,
            'due_at' => $this->dueAt,
            'returned_at' => $this->returnedAt,
            'is_overdue' => $this->isOverdue,
            'condition_out' => $this->conditionOut->value,
            'condition_out_label' => $this->conditionOut->label(),
            'condition_in' => $this->conditionIn?->value,
            'condition_in_label' => $this->conditionIn?->label(),
            'handed_by_name' => $this->handedByName,
            'received_by_name' => $this->receivedByName,
            'purpose' => $this->purpose,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_badge' => $this->status->badgeClass(),
            'notes' => $this->notes,
        ];
    }
}
