<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Asset\CheckInAssetAction;
use Inventra\Core\Actions\Asset\CheckOutAssetAction;
use Inventra\Core\Actions\Asset\CreateAssetAction;
use Inventra\Core\Actions\Asset\DeleteAssetAction;
use Inventra\Core\Actions\Asset\UpdateAssetAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Support\ActivityLogger;

/**
 * Orkestrasi use case aset: memanggil Action, memegang transaksi untuk use case
 * yang menulis lebih dari satu tabel, dan menarasikan activity log. Controller
 * hanya meneruskan hasilnya ke Response.
 */
final class AssetService
{
    public function __construct(
        private readonly AssetRepository $assets,
        private readonly CreateAssetAction $createAsset,
        private readonly UpdateAssetAction $updateAsset,
        private readonly DeleteAssetAction $deleteAsset,
        private readonly CheckOutAssetAction $checkOutAsset,
        private readonly CheckInAssetAction $checkInAsset,
        private readonly ?string $ipAddress = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function create(array $payload): array
    {
        $id = $this->createAsset->execute($payload);
        $asset = $this->assets->find($id);

        ActivityLogger::log(
            'create',
            'Menambahkan aset ' . $asset['name'] . ' (' . $asset['asset_code'] . ')',
            'asset',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Aset berhasil didaftarkan', 'data' => $asset];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function update(int $id, array $payload): array
    {
        $this->updateAsset->execute($id, $payload);
        $asset = $this->assets->find($id);

        ActivityLogger::log(
            'update',
            'Memperbarui data aset ' . $asset['name'] . ' (' . $asset['asset_code'] . ')',
            'asset',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Data aset berhasil diperbarui', 'data' => $asset];
    }

    /**
     * @return array{message: string}
     */
    public function delete(int $id, bool $isAdmin): array
    {
        $asset = $this->deleteAsset->execute($id, $isAdmin);

        ActivityLogger::log(
            'delete',
            'Menghapus aset ' . $asset['name'] . ' (' . $asset['asset_code'] . ')',
            'asset',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Aset berhasil dihapus'];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function checkOut(int $id, array $payload, string $actor): array
    {
        $asset = Connection::transaction(fn (): array => $this->checkOutAsset->execute($id, $payload, $actor));
        $updated = $this->assets->find($id);

        ActivityLogger::log(
            'check_out',
            'Menyerahkan aset ' . $asset['name'] . ' (' . $asset['asset_code'] . ') kepada ' . $updated['assigned_to'],
            'asset',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Serah terima / checkout aset berhasil', 'data' => $updated];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function checkIn(int $id, array $payload, string $actor): array
    {
        $asset = Connection::transaction(fn (): array => $this->checkInAsset->execute($id, $payload, $actor));
        $updated = $this->assets->find($id);

        ActivityLogger::log(
            'check_in',
            'Menerima kembali aset ' . $asset['name'] . ' (' . $asset['asset_code'] . ') ke lokasi penyimpanan',
            'asset',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Pengembalian / checkin aset berhasil', 'data' => $updated];
    }
}
