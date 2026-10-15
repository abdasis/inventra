<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Category\CreateCategoryAction;
use Inventra\Core\Actions\Category\DeleteCategoryAction;
use Inventra\Core\Actions\Category\UpdateCategoryAction;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\CategoryRepository;
use Inventra\Core\Support\ActivityLogger;
use Inventra\Core\Support\FieldChangeNarrator;

/**
 * Orkestrasi use case kategori: memanggil Action lalu menarasikan activity log.
 * Penulisan hanya menyentuh satu tabel, jadi tidak ada transaksi di sini.
 */
final class CategoryService
{
    /** @var array<string, string> */
    private const CHANGE_LABELS = [
        'name' => 'nama',
        'code' => 'kode',
        'description' => 'deskripsi',
        'parent' => 'kategori induk',
    ];

    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly CreateCategoryAction $createCategory,
        private readonly UpdateCategoryAction $updateCategory,
        private readonly DeleteCategoryAction $deleteCategory,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload): array
    {
        $id = $this->createCategory->execute($payload);
        $category = $this->categories->find($id) ?? [];

        ActivityLogger::log(
            'category.create',
            'Menambahkan kategori ' . $this->label($category) . $this->parentNote($category),
            'category',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Kategori berhasil ditambahkan.', 'data' => $category];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function update(int $id, array $payload): array
    {
        // Urutan lama dipertahankan: 404 lebih dulu, baru 422 validasi.
        $before = $this->findOrFail($id);
        $this->updateCategory->execute($id, $payload);
        $after = $this->categories->find($id) ?? [];

        ActivityLogger::log(
            'category.update',
            'Memperbarui kategori ' . $this->label($after)
                . FieldChangeNarrator::describe($this->narrated($before), $this->narrated($after), self::CHANGE_LABELS),
            'category',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Data kategori berhasil diperbarui.', 'data' => $after];
    }

    /**
     * @return array{message: string}
     */
    public function delete(int $id): array
    {
        $category = $this->deleteCategory->execute($id);

        ActivityLogger::log(
            'category.delete',
            'Menghapus kategori ' . $this->label($category),
            'category',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Kategori berhasil dihapus.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrFail(int $id): array
    {
        return $this->categories->find($id) ?? throw new NotFoundException('Kategori tidak ditemukan.');
    }

    /**
     * @param array<string, mixed> $category
     */
    private function label(array $category): string
    {
        return trim((string)($category['name'] ?? '')) . ' (' . trim((string)($category['code'] ?? '')) . ')';
    }

    /**
     * @param array<string, mixed> $category
     */
    private function parentNote(array $category): string
    {
        $parent = trim((string)($category['parent_name'] ?? ''));

        return $parent === '' ? '' : ' dengan kategori induk ' . $parent;
    }

    /**
     * @param array<string, mixed> $category
     * @return array<string, mixed>
     */
    private function narrated(array $category): array
    {
        return [
            'name' => $category['name'] ?? '',
            'code' => $category['code'] ?? '',
            'description' => $category['description'] ?? '',
            'parent' => $category['parent_name'] ?? '',
        ];
    }
}
