<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Item\CreateItemAction;
use Inventra\Core\Actions\Item\DeleteItemAction;
use Inventra\Core\Actions\Item\UpdateItemAction;
use Inventra\Core\DTO\ItemDTO;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\ItemRepository;
use Inventra\Core\Support\ActivityLogger;

/**
 * Orkestrasi use case barang: memanggil Action dan menarasikan activity log.
 * Penulisan hanya menyentuh satu tabel, jadi tidak ada transaksi yang perlu dipegang.
 */
final class ItemService
{
    public function __construct(
        private readonly ItemRepository $items,
        private readonly CreateItemAction $createItem,
        private readonly UpdateItemAction $updateItem,
        private readonly DeleteItemAction $deleteItem,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload): array
    {
        $id = $this->createItem->execute($payload);
        $item = $this->itemData($id);

        ActivityLogger::log(
            'create',
            'Menambahkan barang ' . $item['name'] . ' (' . $item['code'] . ')',
            'item',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Barang berhasil didaftarkan', 'data' => $item];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function update(int $id, array $payload): array
    {
        $before = $this->updateItem->execute($id, $payload);
        $item = $this->itemData($id);

        ActivityLogger::log(
            'update',
            'Memperbarui barang ' . $item['name'] . ' (' . $item['code'] . ')' . $this->nameChange($before, $item),
            'item',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Data barang berhasil diperbarui', 'data' => $item];
    }

    /**
     * @return array{message: string}
     */
    public function delete(int $id): array
    {
        $item = $this->deleteItem->execute($id);

        ActivityLogger::log(
            'delete',
            'Menghapus barang ' . $item['name'] . ' (' . $item['code'] . ')',
            'item',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Barang berhasil dihapus'];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemData(int $id): array
    {
        $row = $this->items->find($id) ?? throw new NotFoundException('Barang tidak ditemukan.');

        return ItemDTO::fromArray($row)->toArray();
    }

    /**
     * @param array<string, mixed> $before Baris sebelum update.
     * @param array<string, mixed> $after Data hasil update.
     */
    private function nameChange(array $before, array $after): string
    {
        $old = trim((string)($before['name'] ?? ''));
        $new = trim((string)($after['name'] ?? ''));

        return $old === $new ? '' : ' — nama berubah dari ' . $old . ' menjadi ' . $new;
    }
}
