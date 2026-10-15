<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Asset;

use Inventra\Core\Actions\Asset\Concerns\BuildsAssetFromPayload;
use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetRepository;

/**
 * Memperbarui data aset yang sudah ada; aset yang tidak ada ditolak lebih dulu
 * supaya payload tidak pernah divalidasi untuk baris yang tidak eksis.
 *
 * Pemegang tidak diubah di sini: assigned_to hanya berubah lewat serah terima,
 * pengembalian, atau penempatan.
 */
final class UpdateAssetAction
{
    use BuildsAssetFromPayload;

    public function __construct(private readonly AssetRepository $assets)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(int $id, array $payload): void
    {
        $before = $this->assets->find($id);
        if ($before === null) {
            throw new NotFoundException('Aset tidak ditemukan');
        }

        // Kode aset yang tidak dikirim berarti tetap, bukan dibangkitkan ulang.
        if (trim((string)($payload['asset_code'] ?? '')) === '') {
            $payload['asset_code'] = (string)$before['asset_code'];
        }

        $this->assets->update($id, AssetDTO::fromArray($this->assetFromPayload($this->assets, $payload, $id)));
    }
}
