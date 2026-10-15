<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Location;

use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\LocationRepository;

/**
 * Menghapus lokasi yang tidak lagi dipakai aset mana pun, dan mengembalikan
 * baris yang terhapus untuk keperluan narasi log.
 */
final class DeleteLocationAction
{
    public function __construct(private readonly LocationRepository $locations)
    {
    }

    /**
     * @return array<string, mixed> Baris lokasi sebelum dihapus.
     */
    public function execute(int $id): array
    {
        $location = $this->locations->find($id);
        if ($location === null) {
            throw new NotFoundException('Lokasi tidak ditemukan.');
        }

        $assetCount = $this->locations->countAssetReferences($id);
        if ($assetCount > 0) {
            throw new ValidationException(
                'Lokasi tidak dapat dihapus karena masih dipakai oleh ' . $assetCount . ' aset.'
            );
        }

        $this->locations->delete($id);

        return $location;
    }
}
