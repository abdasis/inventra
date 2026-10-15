<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Category;

use Inventra\Core\Actions\Category\Concerns\BuildsCategoryFromPayload;
use Inventra\Core\Repositories\CategoryRepository;

/**
 * Menambahkan satu kategori baru dan mengembalikan id hasil insert.
 */
final class CreateCategoryAction
{
    use BuildsCategoryFromPayload;

    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): int
    {
        return $this->categories->create($this->categoryFromPayload($payload, null));
    }
}
