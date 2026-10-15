<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\CustodyStatus;

/**
 * Satu baris penempatan aset siap tampil. Nama aset, karyawan, dan lokasi diisi
 * dari join daftar supaya frontend tidak perlu meresolusinya sendiri; label dan
 * badge kondisi serta status dihitung di sini.
 */
final readonly class AssetAssignmentDTO
{
    public function __construct(
        public ?int $id,
        public int $assetId,
        public ?string $assetCode,
        public ?string $assetName,
        public ?int $employeeId,
        public ?string $employeeName,
        public int $locationId,
        public ?string $locationName,
        public string $assignedAt,
        public ?string $returnedAt,
        public AssetCondition $conditionOut,
        public ?AssetCondition $conditionIn,
        public string $handedByName,
        public ?string $receivedByName,
        public CustodyStatus $status,
        public ?string $notes,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int)$data['id'] : null,
            assetId: (int)($data['asset_id'] ?? 0),
            assetCode: !empty($data['asset_code']) ? (string)$data['asset_code'] : null,
            assetName: !empty($data['asset_name']) ? (string)$data['asset_name'] : null,
            employeeId: !empty($data['employee_id']) ? (int)$data['employee_id'] : null,
            employeeName: !empty($data['employee_name']) ? (string)$data['employee_name'] : null,
            locationId: (int)($data['location_id'] ?? 0),
            locationName: !empty($data['location_name']) ? (string)$data['location_name'] : null,
            assignedAt: (string)($data['assigned_at'] ?? ''),
            returnedAt: !empty($data['returned_at']) ? (string)$data['returned_at'] : null,
            conditionOut: AssetCondition::tryFrom((string)($data['condition_out'] ?? '')) ?? AssetCondition::Good,
            conditionIn: AssetCondition::tryFrom((string)($data['condition_in'] ?? '')) ?: null,
            handedByName: (string)($data['handed_by_name'] ?? ''),
            receivedByName: !empty($data['received_by_name']) ? (string)$data['received_by_name'] : null,
            status: CustodyStatus::tryFrom((string)($data['status'] ?? '')) ?? CustodyStatus::Active,
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
            'assigned_at' => $this->assignedAt,
            'returned_at' => $this->returnedAt,
            'condition_out' => $this->conditionOut->value,
            'condition_out_label' => $this->conditionOut->label(),
            'condition_in' => $this->conditionIn?->value,
            'condition_in_label' => $this->conditionIn?->label(),
            'handed_by_name' => $this->handedByName,
            'received_by_name' => $this->receivedByName,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_badge' => $this->status->badgeClass(),
            'notes' => $this->notes,
        ];
    }
}
