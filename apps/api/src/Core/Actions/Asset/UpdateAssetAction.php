<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Asset;

use Inventra\Core\Actions\Asset\Concerns\BuildsAssetFromPayload;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetRepository;

/**
 * Memperbarui data aset yang sudah ada; aset yang tidak ada ditolak lebih dulu
 * supaya payload tidak pernah divalidasi untuk baris yang tidak eksis.
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
        if ($this->assets->find($id) === null) {
            throw new NotFoundException('Aset tidak ditemukan');
        }

        $this->assets->update($id, $this->assetFromPayload($payload));
    }
}
