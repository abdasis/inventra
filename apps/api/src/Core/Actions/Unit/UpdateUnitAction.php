<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Unit;

use Inventra\Core\Actions\Unit\Concerns\BuildsUnitFromPayload;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\UnitRepository;

/**
 * Memperbarui satuan yang sudah ada; satuan yang tidak eksis ditolak lebih dulu
 * supaya payload tidak pernah divalidasi untuk baris yang tidak ada.
 */
final class UpdateUnitAction
{
    use BuildsUnitFromPayload;

    public function __construct(private readonly UnitRepository $units)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(int $id, array $payload): void
    {
        if ($this->units->find($id) === null) {
            throw new NotFoundException('Satuan tidak ditemukan.');
        }

        $this->units->update($id, $this->unitFromPayload($this->units, $payload, $id));
    }
}
