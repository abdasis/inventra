<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Category;

use Inventra\Core\Actions\Category\Concerns\BuildsCategoryFromPayload;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\CategoryRepository;

/**
 * Memperbarui kategori yang sudah ada. Keberadaan baris diperiksa lebih dulu
 * supaya payload tidak pernah divalidasi untuk kategori yang tidak eksis.
 */
final class UpdateCategoryAction
{
    use BuildsCategoryFromPayload;

    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(int $id, array $payload): void
    {
        if ($this->categories->find($id) === null) {
            throw new NotFoundException('Kategori tidak ditemukan.');
        }

        $this->categories->update($id, $this->categoryFromPayload($payload, $id));
    }
}
