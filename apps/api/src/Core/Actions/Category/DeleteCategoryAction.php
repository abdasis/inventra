<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Category;

use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\CategoryRepository;

/**
 * Menghapus kategori sekaligus menjaga referensinya: kategori yang masih dipakai
 * aset maupun barang tidak boleh hilang. Mengembalikan baris yang terhapus
 * supaya pemanggil bisa menarasikan log.
 */
final class DeleteCategoryAction
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * @return array<string, mixed> Baris kategori sebelum dihapus.
     */
    public function execute(int $id): array
    {
        $category = $this->categories->find($id);
        if ($category === null) {
            throw new NotFoundException('Kategori tidak ditemukan.');
        }

        $assetCount = $this->categories->countAssetReferences($id);
        $itemCount = $this->categories->countItemReferences($id);

        if ($assetCount + $itemCount > 0) {
            throw new ValidationException($this->referenceMessage($assetCount, $itemCount));
        }

        $this->categories->delete($id);

        return $category;
    }

    private function referenceMessage(int $assetCount, int $itemCount): string
    {
        $parts = [];

        if ($assetCount > 0) {
            $parts[] = $assetCount . ' aset';
        }

        if ($itemCount > 0) {
            $parts[] = $itemCount . ' barang';
        }

        return 'Kategori tidak dapat dihapus karena masih dipakai oleh ' . implode(' dan ', $parts) . '.';
    }
}
