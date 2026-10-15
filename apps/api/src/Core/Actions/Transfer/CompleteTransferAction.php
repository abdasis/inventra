<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Transfer;

use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Enums\MovementType;
use Inventra\Core\Enums\TransferStatus;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\AssetTransferRepository;
use Inventra\Core\Repositories\MovementRepository;

/**
 * Menyelesaikan mutasi: inilah satu-satunya jalur yang memindahkan lokasi aktif
 * aset. Pemegangnya ikut berubah hanya bila mutasi menyebut pemegang tujuan, dan
 * status operasional aset tidak diturunkan — kecuali aset yang tadinya tersedia
 * kini benar-benar punya pemegang baru. Mutasi yang sudah ditutup tidak bisa
 * diselesaikan dua kali.
 */
final class CompleteTransferAction
{
    public function __construct(
        private readonly AssetRepository $assets,
        private readonly AssetTransferRepository $transfers,
        private readonly MovementRepository $movements,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Baris mutasi sesudah diselesaikan.
     */
    public function execute(int $id, array $payload, string $actor): array
    {
        $transfer = $this->transfers->find($id)
            ?? throw new NotFoundException('Mutasi tidak ditemukan.');

        if (TransferStatus::tryFrom((string)$transfer['status']) !== TransferStatus::Pending) {
            throw new ValidationException(
                'Mutasi ini sudah diselesaikan atau dibatalkan.',
                ['status' => 'Mutasi ini sudah diselesaikan atau dibatalkan.']
            );
        }

        $assetId = (int)$transfer['asset_id'];
        $asset = $this->assets->find($assetId)
            ?? throw new NotFoundException('Aset tidak ditemukan.');

        $toLocationId = (int)$transfer['to_location_id'];
        $holderName = $transfer['to_holder_name'] !== null
            ? (string)$transfer['to_holder_name']
            : ($asset['assigned_to'] !== null ? (string)$asset['assigned_to'] : null);
        $currentStatus = AssetStatus::tryFrom((string)$asset['status']) ?? AssetStatus::Available;
        $status = $holderName !== null && $currentStatus === AssetStatus::Available
            ? AssetStatus::InUse
            : $currentStatus;

        $this->assets->setHolder($assetId, null, $holderName, $toLocationId, $status);

        $this->movements->record([
            'asset_id' => $assetId,
            'movement_type' => MovementType::Relocate->value,
            'from_location_id' => $transfer['from_location_id'] !== null
                ? (int)$transfer['from_location_id']
                : (int)$asset['location_id'],
            'to_location_id' => $toLocationId,
            'actor_name' => $actor,
            'recipient_name' => $holderName,
            'notes' => 'Mutasi diselesaikan',
        ]);

        $this->transfers->update($id, [
            'status' => TransferStatus::Completed->value,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->transfers->find($id) ?? $transfer;
    }
}
