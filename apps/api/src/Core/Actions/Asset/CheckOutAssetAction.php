<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Asset;

use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetRepository;

/**
 * Serah terima aset: aset harus ada, penerima dan lokasi target wajib terisi,
 * lalu status aset berpindah ke in_use. Actor diserahkan pemanggil, bukan dibaca
 * dari sesi, supaya aturan ini bisa diuji tanpa konteks HTTP.
 */
final class CheckOutAssetAction
{
    public function __construct(private readonly AssetRepository $assets)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Baris aset sebelum serah terima.
     */
    public function execute(int $assetId, array $payload, string $actor): array
    {
        $asset = $this->assets->find($assetId);
        if ($asset === null) {
            throw new NotFoundException('Aset tidak ditemukan');
        }

        $recipient = trim((string)($payload['recipient_name'] ?? ''));
        $targetLocationId = (int)($payload['target_location_id'] ?? 0);
        $notes = !empty($payload['notes']) ? (string)$payload['notes'] : null;

        if ($recipient === '' || $targetLocationId <= 0) {
            throw new ValidationException('Nama penerima dan lokasi target wajib diisi');
        }

        $this->assets->checkOut(
            $assetId,
            $recipient,
            $targetLocationId,
            $actor,
            AssetStatus::InUse,
            (int)$asset['location_id'],
            $notes
        );

        return $asset;
    }
}
