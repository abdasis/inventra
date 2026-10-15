<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Asset;

use Inventra\Core\Actions\Asset\Concerns\BuildsAssetFromPayload;
use Inventra\Core\Repositories\AssetRepository;

/**
 * Mendaftarkan satu aset baru dan mengembalikan id hasil insert.
 */
final class CreateAssetAction
{
    use BuildsAssetFromPayload;

    public function __construct(private readonly AssetRepository $assets)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): int
    {
        return $this->assets->create($this->assetFromPayload($payload));
    }
}
