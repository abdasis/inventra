<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Item;

use Inventra\Core\Actions\Item\Concerns\BuildsItemFromPayload;
use Inventra\Core\Repositories\ItemRepository;

/**
 * Mendaftarkan satu barang baru dan mengembalikan id hasil insert.
 */
final class CreateItemAction
{
    use BuildsItemFromPayload;

    public function __construct(private readonly ItemRepository $items)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): int
    {
        return $this->items->create($this->itemFromPayload($this->items, $payload));
    }
}
