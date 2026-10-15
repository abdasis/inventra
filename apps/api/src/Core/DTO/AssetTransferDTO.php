<?php

declare(strict_types=1);

namespace Inventra\Core\DTO;

use Inventra\Core\Enums\TransferStatus;

/**
 * Satu baris mutasi aset siap tampil. Nama aset dan lokasi asal/tujuan diisi dari
 * join daftar supaya frontend tidak perlu meresolusinya sendiri; label dan badge
 * statusnya dihitung di sini.
 */
final readonly class AssetTransferDTO
{
    public function __construct(
        public ?int $id,
        public int $assetId,
        public ?string $assetCode,
        public ?string $assetName,
        public ?int $fromLocationId,
        public ?string $fromLocationName,
        public int $toLocationId,
        public ?string $toLocationName,
        public ?string $fromHolderName,
        public ?string $toHolderName,
        public string $transferDate,
        public ?string $reason,
        public TransferStatus $status,
        public string $executedByName,
        public ?string $completedAt,
        public ?string $cancelledAt,
        public ?string $cancelReason,
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
            fromLocationId: isset($data['from_location_id']) ? (int)$data['from_location_id'] : null,
            fromLocationName: !empty($data['from_location_name']) ? (string)$data['from_location_name'] : null,
            toLocationId: (int)($data['to_location_id'] ?? 0),
            toLocationName: !empty($data['to_location_name']) ? (string)$data['to_location_name'] : null,
            fromHolderName: !empty($data['from_holder_name']) ? (string)$data['from_holder_name'] : null,
            toHolderName: !empty($data['to_holder_name']) ? (string)$data['to_holder_name'] : null,
            transferDate: (string)($data['transfer_date'] ?? ''),
            reason: !empty($data['reason']) ? (string)$data['reason'] : null,
            status: TransferStatus::tryFrom((string)($data['status'] ?? '')) ?? TransferStatus::Pending,
            executedByName: (string)($data['executed_by_name'] ?? ''),
            completedAt: !empty($data['completed_at']) ? (string)$data['completed_at'] : null,
            cancelledAt: !empty($data['cancelled_at']) ? (string)$data['cancelled_at'] : null,
            cancelReason: !empty($data['cancel_reason']) ? (string)$data['cancel_reason'] : null,
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
            'from_location_id' => $this->fromLocationId,
            'from_location_name' => $this->fromLocationName,
            'to_location_id' => $this->toLocationId,
            'to_location_name' => $this->toLocationName,
            'from_holder_name' => $this->fromHolderName,
            'to_holder_name' => $this->toHolderName,
            'transfer_date' => $this->transferDate,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_badge' => $this->status->badgeClass(),
            'executed_by_name' => $this->executedByName,
            'completed_at' => $this->completedAt,
            'cancelled_at' => $this->cancelledAt,
            'cancel_reason' => $this->cancelReason,
            'notes' => $this->notes,
        ];
    }
}
