<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Item;

use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\ItemRepository;

/**
 * Menghapus barang. Mengembalikan baris yang terhapus supaya pemanggil bisa
 * menarasikan log.
 */
final class DeleteItemAction
{
    public function __construct(private readonly ItemRepository $items)
    {
    }

    /**
     * @return array<string, mixed> Baris barang sebelum dihapus.
     */
    public function execute(int $id): array
    {
        $item = $this->items->find($id);
        if ($item === null) {
            throw new NotFoundException('Barang tidak ditemukan.');
        }

        $this->items->delete($id);

        return $item;
    }
}
