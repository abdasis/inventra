<?php

declare(strict_types=1);

namespace Inventra\Core\Services;

use Inventra\Core\Actions\Asset\CheckInAssetAction;
use Inventra\Core\Actions\Asset\CheckOutAssetAction;
use Inventra\Core\Actions\Asset\CreateAssetAction;
use Inventra\Core\Actions\Asset\DeleteAssetAction;
use Inventra\Core\Actions\Asset\UpdateAssetAction;
use Inventra\Core\Database\Connection;
use Inventra\Core\DTO\AssetDTO;
use Inventra\Core\Enums\AssetCondition;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Support\ActivityLogger;
use Inventra\Core\Support\FieldChangeNarrator;

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
     * Penulisan massal boleh menyentuh banyak baris sekaligus, jadi seluruh
     * registrasi dipegang satu transaksi. Activity log ditulis setelah commit.
     *
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<int, array<string, mixed>>, count: int}
     */
    public function create(array $payload): array
    {
        $result = Connection::transaction(fn (): array => $this->createAsset->execute($payload));

        $rows = [];
        foreach ($result['ids'] as $id) {
            $row = $this->assets->find($id);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        $name = (string)($rows[0]['name'] ?? '');
        $firstCode = (string)($rows[0]['asset_code'] ?? $result['code']);
        $lastCode = $rows === [] ? $result['code'] : (string)$rows[count($rows) - 1]['asset_code'];
        $data = $this->presentMany($rows);

        ActivityLogger::log(
            'create',
            $result['quantity'] > 1
                ? 'Mendaftarkan ' . $result['quantity'] . ' unit aset ' . $name . ' (' . $firstCode . ' … ' . $lastCode . ')'
                : 'Mendaftarkan aset ' . $name . ' (' . $firstCode . ')',
            'asset',
            (int)($result['ids'][0] ?? 0),
            $this->ipAddress
        );

        return [
            'message' => $result['quantity'] > 1
                ? $result['quantity'] . ' aset berhasil didaftarkan'
                : 'Aset berhasil didaftarkan',
            'data' => $data,
            'count' => $result['quantity'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string, data: array<string, mixed>}
     */
    public function update(int $id, array $payload): array
    {
        $before = $this->assets->find($id) ?? [];
        $this->updateAsset->execute($id, $payload);
        $asset = $this->assets->find($id);

        ActivityLogger::log(
            'update',
            'Memperbarui data aset ' . $asset['name'] . ' (' . $asset['asset_code'] . ')' . $this->fieldChanges($before, $asset),
            'asset',
            $id,
            $this->ipAddress
        );

        return ['message' => 'Data aset berhasil diperbarui', 'data' => AssetDTO::fromArray($asset)->toArray()];
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

    /**
     * Perubahan penting pada data aset, dinarasikan dengan label Indonesia:
     * " — status berubah dari Tersedia menjadi Perbaikan".
     *
     * @param array<string, mixed> $before Baris sebelum update.
     * @param array<string, mixed> $after Baris sesudah update.
     */
    private function fieldChanges(array $before, array $after): string
    {
        $labels = ['status' => 'status', 'condition' => 'kondisi fisik', 'location' => 'lokasi'];

        return FieldChangeNarrator::describe(
            [
                'status' => $this->statusLabel($before['status'] ?? null),
                'condition' => $this->conditionLabel($before['condition_level'] ?? null),
                'location' => $before['location_name'] ?? null,
            ],
            [
                'status' => $this->statusLabel($after['status'] ?? null),
                'condition' => $this->conditionLabel($after['condition_level'] ?? null),
                'location' => $after['location_name'] ?? null,
            ],
            $labels
        );
    }

    private function statusLabel(mixed $value): string
    {
        $status = AssetStatus::tryFrom((string)$value);

        return $status !== null ? $status->label() : (string)$value;
    }

    private function conditionLabel(mixed $value): string
    {
        $condition = AssetCondition::tryFrom((string)$value);

        return $condition !== null ? $condition->label() : (string)$value;
    }

    /**
     * Baris DB dipetakan lewat DTO supaya label status/kondisi/sumber perolehan
     * dan format rupiah sampai ke klien; frontend tidak menghitungnya sendiri.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function presentMany(array $rows): array
    {
        return array_map(static fn (array $row): array => AssetDTO::fromArray($row)->toArray(), $rows);
    }
}
