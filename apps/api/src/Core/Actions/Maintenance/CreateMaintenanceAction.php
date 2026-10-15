<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Maintenance;

use Inventra\Core\Actions\Concerns\AssetCustodyInput;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Enums\MaintenanceStatus;
use Inventra\Core\Enums\MaintenanceType;
use Inventra\Core\Enums\MovementType;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Repositories\AssetMaintenanceRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\MovementRepository;

/**
 * Mencatat pemeliharaan aset dan menandai asetnya berstatus maintenance. Aset
 * yang sudah dihapus, yang sedang dipelihara, atau yang masih berada di tangan
 * pemegang/peminjam ditolak supaya tidak ada perawatan atas aset yang tidak ada
 * di tempat. Actor diserahkan pemanggil, bukan dibaca dari sesi.
 */
final class CreateMaintenanceAction
{
    use AssetCustodyInput;

    public function __construct(
        private readonly AssetRepository $assets,
        private readonly AssetMaintenanceRepository $maintenances,
        private readonly AssetAssignmentRepository $assignments,
        private readonly AssetLoanRepository $loans,
        private readonly MovementRepository $movements,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param string $actorName Nama pelaku dari sesi, diserahkan Controller.
     * @return int Id baris pemeliharaan yang baru dibuat.
     */
    public function execute(array $payload, string $actorName): int
    {
        $assetId = $this->custodyInt($payload['asset_id'] ?? null);
        $rawType = $this->custodyText($payload['maintenance_type'] ?? null);
        $maintenanceDate = $this->custodyDate($payload['maintenance_date'] ?? null);
        $vendor = $this->custodyOptionalText($payload['vendor'] ?? null);
        $cost = $this->custodyDecimal($payload['cost'] ?? null);
        $damage = $this->custodyOptionalText($payload['damage_description'] ?? null);
        $nextSchedule = $this->custodyDate($payload['next_schedule'] ?? null);
        $notes = $this->custodyOptionalText($payload['notes'] ?? null);
        $errors = [];

        $asset = $assetId > 0 ? $this->assets->find($assetId) : null;
        if ($asset === null) {
            $errors['asset_id'] = 'Aset tidak ditemukan.';
        } elseif (AssetStatus::tryFrom((string)$asset['status']) === AssetStatus::Disposed) {
            $errors['asset_id'] = 'Aset yang sudah dihapus tidak dapat dipelihara.';
        } elseif ($this->maintenances->ongoingForAsset($assetId) !== null) {
            $errors['asset_id'] = 'Masih ada pemeliharaan yang belum selesai untuk aset ini.';
        } elseif ($this->loans->activeForAsset($assetId) !== null
            || $this->assignments->activeForAsset($assetId) !== null) {
            $errors['asset_id'] = 'Tarik kembali aset dari pemegang sebelum memeliharanya.';
        }

        $maintenanceType = MaintenanceType::tryFrom($rawType) ?? MaintenanceType::Corrective;
        if ($rawType !== '' && MaintenanceType::tryFrom($rawType) === null) {
            $errors['maintenance_type'] = 'Jenis pemeliharaan tidak valid.';
        }

        if ($this->custodyText($payload['maintenance_date'] ?? null) === '') {
            $errors['maintenance_date'] = 'Tanggal pemeliharaan wajib diisi.';
        } elseif ($maintenanceDate === null) {
            $errors['maintenance_date'] = 'Tanggal pemeliharaan tidak valid.';
        }

        if ($cost === null) {
            $errors['cost'] = 'Biaya pemeliharaan tidak valid.';
        } elseif ($cost < 0) {
            $errors['cost'] = 'Biaya pemeliharaan tidak boleh negatif.';
        }

        if ($this->custodyText($payload['next_schedule'] ?? null) !== '' && $nextSchedule === null) {
            $errors['next_schedule'] = 'Jadwal berikutnya tidak valid.';
        }

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        $id = $this->maintenances->create([
            'asset_id' => $assetId,
            'maintenance_type' => $maintenanceType->value,
            'maintenance_date' => (string)$maintenanceDate,
            'vendor' => $vendor,
            'cost' => $cost,
            'damage_description' => $damage,
            'next_schedule' => $nextSchedule,
            'status' => MaintenanceStatus::Ongoing->value,
            'performed_by_name' => $actorName,
            'notes' => $notes,
        ]);

        // Pemegang dan lokasi dibiarkan apa adanya: pemeliharaan menandai status
        // asetnya saja, bukan memindahkannya ke master lokasi lain.
        $this->assets->setHolder(
            $assetId,
            null,
            $asset['assigned_to'] !== null ? (string)$asset['assigned_to'] : null,
            null,
            AssetStatus::Maintenance
        );

        $this->movements->record([
            'asset_id' => $assetId,
            'movement_type' => MovementType::Repair->value,
            'from_location_id' => (int)$asset['location_id'],
            'to_location_id' => null,
            'actor_name' => $actorName,
            'recipient_name' => $vendor,
            'notes' => 'Masuk pemeliharaan',
        ]);

        return $id;
    }
}
