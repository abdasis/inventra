<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Item;

use Inventra\Core\Actions\Item\Concerns\BuildsItemFromPayload;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\ItemRepository;

/**
 * Memperbarui barang yang sudah ada; barang yang tidak eksis ditolak lebih dulu,
 * baru payload divalidasi. Mengembalikan baris lama supaya Service bisa menarasikan
 * perubahan yang terjadi.
 */
final class UpdateItemAction
{
    use BuildsItemFromPayload;

    public function __construct(private readonly ItemRepository $items)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Baris barang sebelum diperbarui.
     */
    public function execute(int $id, array $payload): array
    {
        $before = $this->items->find($id);
        if ($before === null) {
            throw new NotFoundException('Barang tidak ditemukan.');
        }

        $this->items->update($id, $this->itemFromPayload($this->items, $payload, $id));

        return $before;
    }
}
