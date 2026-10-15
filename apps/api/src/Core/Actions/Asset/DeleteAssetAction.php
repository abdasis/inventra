<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Asset;

use Inventra\Core\Exceptions\ForbiddenException;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Repositories\AssetRepository;

/**
 * Menghapus aset. Peran diperiksa sebelum keberadaan aset, sehingga non-admin
 * selalu menerima 403 dan tidak bisa memakai 404 untuk menebak id yang ada.
 * Mengembalikan baris yang terhapus supaya pemanggil bisa menarasikan log.
 */
final class DeleteAssetAction
{
    public function __construct(private readonly AssetRepository $assets)
    {
    }

    /**
     * @return array<string, mixed> Baris aset sebelum dihapus.
     */
    public function execute(int $id, bool $isAdmin): array
    {
        if (!$isAdmin) {
            throw new ForbiddenException('Anda tidak memiliki izin untuk menghapus aset.');
        }

        $asset = $this->assets->find($id);
        if ($asset === null) {
            throw new NotFoundException('Aset tidak ditemukan');
        }

        $this->assets->delete($id);

        return $asset;
    }
}
