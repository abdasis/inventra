<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Asset;

use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Repositories\AssetRepository;

/**
 * Serah terima aset: aset harus ada, penerima dan lokasi target wajib terisi,
 * lalu status aset berpindah ke in_use. Serah terima juga mencatat histori
 * penempatan: penempatan lama aset ditutup lebih dulu supaya tidak pernah ada dua
 * penempatan aktif sekaligus. Actor diserahkan pemanggil, bukan dibaca dari sesi,
 * supaya aturan ini bisa diuji tanpa konteks HTTP.
 */
final class CheckOutAssetAction
{
    private readonly AssetRepository $assets;

    private readonly AssetAssignmentRepository $assignments;

    public function __construct(AssetRepository $assets, ?AssetAssignmentRepository $assignments = null)
    {
        $this->assets = $assets;
        $this->assignments = $assignments ?? new AssetAssignmentRepository();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed> Baris aset sebelum serah terima.
     */
    public function execute(int $assetId, array $payload, string $actor): array
    {
        $asset = $this->assets->find($assetId);
        if ($asset === null) {
            throw new NotFoundException('Aset tidak ditemukan');
        }

        $recipient = trim((string)($payload['recipient_name'] ?? ''));
        $targetLocationId = (int)($payload['target_location_id'] ?? 0);
        $notes = !empty($payload['notes']) ? (string)$payload['notes'] : null;

        if ($recipient === '' || $targetLocationId <= 0) {
            throw new ValidationException('Nama penerima dan lokasi target wajib diisi');
        }

        $condition = (string)$asset['condition_level'];

        // Penempatan lama ditutup sebelum penempatan baru dibuka.
        $active = $this->assignments->activeForAsset($assetId);
        if ($active !== null) {
            $this->assignments->close((int)$active['id'], [
                'returned_at' => date('Y-m-d H:i:s'),
                'condition_in' => $condition,
                'received_by_name' => $recipient,
            ]);
        }

        $this->assets->checkOut(
            $assetId,
            $recipient,
            $targetLocationId,
            $actor,
            AssetStatus::InUse,
            (int)$asset['location_id'],
            $notes
        );

        $this->assignments->create([
            'asset_id' => $assetId,
            'employee_id' => null,
            'location_id' => $targetLocationId,
            'assigned_at' => date('Y-m-d H:i:s'),
            'condition_out' => $condition,
            'handed_by_name' => $actor,
            'received_by_name' => $recipient,
            'notes' => $notes,
        ]);

        return $asset;
    }
}
