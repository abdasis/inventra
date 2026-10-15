<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Asset;

use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetRepository;

/**
 * Pengembalian aset: aset harus ada, lokasi penyimpanan wajib terisi, status
 * kembali ke available dan pemegang dilepas. Actor diserahkan pemanggil supaya
 * aturan ini bisa diuji tanpa konteks HTTP.
 */
final class CheckInAssetAction
{
    public function __construct(private readonly AssetRepository $assets)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Baris aset sebelum pengembalian.
     */
    public function execute(int $assetId, array $payload, string $actor): array
    {
        $asset = $this->assets->find($assetId);
        if ($asset === null) {
            throw new NotFoundException('Aset tidak ditemukan');
        }

        $returnLocationId = (int)($payload['return_location_id'] ?? 0);
        $notes = !empty($payload['notes']) ? (string)$payload['notes'] : null;

        if ($returnLocationId <= 0) {
            throw new ValidationException('Lokasi gudang/ruang penyimpanan wajib diisi');
        }

        $this->assets->checkIn(
            $assetId,
            $returnLocationId,
            $actor,
            AssetStatus::Available,
            (int)$asset['location_id'],
            $asset['assigned_to'] !== null ? (string)$asset['assigned_to'] : null,
            $notes
        );

        return $asset;
    }
}
