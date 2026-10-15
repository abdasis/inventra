<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Asset\Concerns;

use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Exceptions\ValidationException;
use InvalidArgumentException;

/**
 * Menerjemahkan payload mentah menjadi AssetDTO sekaligus mengubah kegagalan
 * validasi DTO menjadi ValidationException (422), bukan error server (500).
 */
trait BuildsAssetFromPayload
{
    /**
     * @param array<string, mixed> $payload
     */
    private function assetFromPayload(array $payload): AssetDTO
    {
        try {
            return AssetDTO::fromArray($payload);
        } catch (InvalidArgumentException $e) {
            throw new ValidationException($e->getMessage());
        }
    }
}
