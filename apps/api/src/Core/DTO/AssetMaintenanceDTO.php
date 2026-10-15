<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

use Inventra\Core\Enums\MaintenanceStatus;
use Inventra\Core\Enums\MaintenanceType;

/**
 * Satu baris pemeliharaan aset siap tampil. Nama aset diisi dari join daftar,
 * sedangkan label jenis, label/badge status, dan format rupiah biaya dihitung di
 * sini supaya frontend tidak perlu tahu aturan tampilannya.
 */
final readonly class AssetMaintenanceDTO
{
    public function __construct(
        public ?int $id,
        public int $assetId,
        public ?string $assetCode,
        public ?string $assetName,
        public MaintenanceType $maintenanceType,
        public string $maintenanceDate,
        public ?string $vendor,
        public float $cost,
        public ?string $damageDescription,
        public ?string $workResult,
        public ?string $nextSchedule,
        public MaintenanceStatus $status,
        public string $performedByName,
        public ?string $completedAt,
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
            maintenanceType: MaintenanceType::tryFrom((string)($data['maintenance_type'] ?? ''))
                ?? MaintenanceType::Corrective,
            maintenanceDate: (string)($data['maintenance_date'] ?? ''),
            vendor: !empty($data['vendor']) ? (string)$data['vendor'] : null,
            cost: (float)($data['cost'] ?? 0.0),
            damageDescription: !empty($data['damage_description']) ? (string)$data['damage_description'] : null,
            workResult: !empty($data['work_result']) ? (string)$data['work_result'] : null,
            nextSchedule: !empty($data['next_schedule']) ? (string)$data['next_schedule'] : null,
            status: MaintenanceStatus::tryFrom((string)($data['status'] ?? '')) ?? MaintenanceStatus::Ongoing,
            performedByName: (string)($data['performed_by_name'] ?? ''),
            completedAt: !empty($data['completed_at']) ? (string)$data['completed_at'] : null,
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
            'maintenance_type' => $this->maintenanceType->value,
            'maintenance_type_label' => $this->maintenanceType->label(),
            'maintenance_date' => $this->maintenanceDate,
            'vendor' => $this->vendor,
            'cost' => $this->cost,
            'cost_formatted' => 'Rp ' . number_format($this->cost, 0, ',', '.'),
            'damage_description' => $this->damageDescription,
            'work_result' => $this->workResult,
            'next_schedule' => $this->nextSchedule,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_badge' => $this->status->badgeClass(),
            'performed_by_name' => $this->performedByName,
            'completed_at' => $this->completedAt,
            'notes' => $this->notes,
        ];
    }
}
