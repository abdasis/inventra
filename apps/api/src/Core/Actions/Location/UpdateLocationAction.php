<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Location;

use Inventra\Core\Actions\Location\Concerns\BuildsLocationFromPayload;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\LocationRepository;

/**
 * Memperbarui lokasi yang sudah ada; baris yang tidak eksis ditolak sebelum
 * payload divalidasi.
 */
final class UpdateLocationAction
{
    use BuildsLocationFromPayload;

    public function __construct(private readonly LocationRepository $locations)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(int $id, array $payload): void
    {
        if ($this->locations->find($id) === null) {
            throw new NotFoundException('Lokasi tidak ditemukan.');
        }

        $this->locations->update($id, $this->locationFromPayload($payload, $id));
    }
}
