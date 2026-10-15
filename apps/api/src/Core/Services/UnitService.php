<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Unit\CreateUnitAction;
use Inventra\Core\Actions\Unit\DeleteUnitAction;
use Inventra\Core\Actions\Unit\UpdateUnitAction;
use Inventra\Core\Repositories\UnitRepository;
use Inventra\Core\Support\ActivityLogger;

/**
 * Orkestrasi use case satuan: memanggil Action dan menarasikan activity log.
 * Penulisan hanya menyentuh satu tabel, jadi tidak ada transaksi yang perlu dipegang.
 */
final class UnitService
{
    public function __construct(
        private readonly UnitRepository $units,
        private readonly CreateUnitAction $createUnit,
        private readonly UpdateUnitAction $updateUnit,
        private readonly DeleteUnitAction $deleteUnit,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload): array
    {
        $id = $this->createUnit->execute($payload);
        $unit = $this->units->find($id);

        ActivityLogger::log(
            'create',
            'Menambahkan satuan ' . $unit['name'] . ' (' . $unit['code'] . ')',
            'unit',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Satuan berhasil ditambahkan', 'data' => $unit];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function update(int $id, array $payload): array
    {
        $this->updateUnit->execute($id, $payload);
        $unit = $this->units->find($id);

        ActivityLogger::log(
            'update',
            'Memperbarui satuan ' . $unit['name'] . ' (' . $unit['code'] . ')',
            'unit',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Data satuan berhasil diperbarui', 'data' => $unit];
    }

    /**
     * @return array{message: string}
     */
    public function delete(int $id): array
    {
        $unit = $this->deleteUnit->execute($id);

        ActivityLogger::log(
            'delete',
            'Menghapus satuan ' . $unit['name'] . ' (' . $unit['code'] . ')',
            'unit',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Satuan berhasil dihapus'];
    }
}
