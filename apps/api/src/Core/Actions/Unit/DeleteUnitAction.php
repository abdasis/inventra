<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Unit;

use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\UnitRepository;

/**
 * Menghapus satuan. Satuan yang masih dirujuk barang ditolak supaya tidak ada
 * barang dengan satuan menggantung. Mengembalikan baris yang terhapus untuk narasi log.
 */
final class DeleteUnitAction
{
    public function __construct(private readonly UnitRepository $units)
    {
    }

    /**
     * @return array<string, mixed> Baris satuan sebelum dihapus.
     */
    public function execute(int $id): array
    {
        $unit = $this->units->find($id);
        if ($unit === null) {
            throw new NotFoundException('Satuan tidak ditemukan.');
        }

        $references = $this->units->countItemReferences($id);
        if ($references > 0) {
            throw new ValidationException(
                'Satuan tidak dapat dihapus karena masih dipakai oleh ' . $references . ' barang.'
            );
        }

        $this->units->delete($id);

        return $unit;
    }
}
