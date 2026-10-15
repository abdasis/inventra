<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Unit;

use Inventra\Core\Actions\Unit\Concerns\BuildsUnitFromPayload;
use Inventra\Core\Repositories\UnitRepository;

/**
 * Mendaftarkan satu satuan baru dan mengembalikan id hasil insert.
 */
final class CreateUnitAction
{
    use BuildsUnitFromPayload;

    public function __construct(private readonly UnitRepository $units)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): int
    {
        return $this->units->create($this->unitFromPayload($this->units, $payload));
    }
}
