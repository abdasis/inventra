<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Transfer;

use Inventra\Core\Actions\Concerns\AssetCustodyInput;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Enums\TransferStatus;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\AssetTransferRepository;
use Inventra\Core\Repositories\LocationRepository;

/**
 * Mencatat rencana mutasi aset antarlokasi. Barisnya disimpan sebagai pending
 * TANPA menyentuh asetnya — lokasi aktif aset baru berpindah saat mutasi
 * diselesaikan, sehingga rencana yang dibatalkan tidak pernah mengubahnya. Satu
 * aset hanya boleh punya satu mutasi berjalan. Actor diserahkan pemanggil.
 */
final class CreateTransferAction
{
    use AssetCustodyInput;

    public function __construct(
        private readonly AssetRepository $assets,
        private readonly AssetTransferRepository $transfers,
        private readonly LocationRepository $locations,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param string $actorName Nama pelaku dari sesi, diserahkan Controller.
     * @return int Id baris mutasi yang baru dibuat.
     */
    public function execute(array $payload, string $actorName): int
    {
        $assetId = $this->custodyInt($payload['asset_id'] ?? null);
        $toLocationId = $this->custodyInt($payload['to_location_id'] ?? null);
        $toHolderName = $this->custodyOptionalText($payload['to_holder_name'] ?? null);
        $transferDate = $this->custodyDate($payload['transfer_date'] ?? null) ?? date('Y-m-d');
        $reason = $this->custodyOptionalText($payload['reason'] ?? null);
        $notes = $this->custodyOptionalText($payload['notes'] ?? null);
        $errors = [];

        $asset = $assetId > 0 ? $this->assets->find($assetId) : null;
        if ($asset === null) {
            $errors['asset_id'] = 'Aset tidak ditemukan.';
        } elseif (AssetStatus::tryFrom((string)$asset['status']) === AssetStatus::Disposed) {
            $errors['asset_id'] = 'Aset yang sudah dihapus tidak dapat dimutasi.';
        } elseif ($this->transfers->pendingForAsset($assetId) !== null) {
            $errors['asset_id'] = 'Masih ada mutasi yang belum diselesaikan untuk aset ini.';
        }

        $toLocation = $toLocationId > 0 ? $this->locations->find($toLocationId) : null;
        if ($toLocation === null) {
            $errors['to_location_id'] = 'Lokasi tujuan tidak ditemukan.';
        } elseif ($asset !== null && (int)$asset['location_id'] === $toLocationId) {
            $errors['to_location_id'] = 'Lokasi tujuan sama dengan lokasi aset saat ini.';
        }

        if ($this->custodyText($payload['transfer_date'] ?? null) !== ''
            && $this->custodyDate($payload['transfer_date'] ?? null) === null) {
            $errors['transfer_date'] = 'Tanggal mutasi tidak valid.';
        }

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        return $this->transfers->create([
            'asset_id' => $assetId,
            'from_location_id' => (int)$asset['location_id'],
            'to_location_id' => $toLocationId,
            'from_holder_name' => $asset['assigned_to'] !== null ? (string)$asset['assigned_to'] : null,
            'to_holder_name' => $toHolderName,
            'transfer_date' => $transferDate,
            'reason' => $reason,
            'status' => TransferStatus::Pending->value,
            'executed_by_name' => $actorName,
            'notes' => $notes,
        ]);
    }
}
