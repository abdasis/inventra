<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Location;

use Inventra\Core\Actions\Location\Concerns\BuildsLocationFromPayload;
use Inventra\Core\Repositories\LocationRepository;

/**
 * Menambahkan satu lokasi baru dan mengembalikan id hasil insert.
 */
final class CreateLocationAction
{
    use BuildsLocationFromPayload;

    public function __construct(private readonly LocationRepository $locations)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): int
    {
        return $this->locations->create($this->locationFromPayload($payload, null));
    }
}
