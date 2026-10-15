<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Maintenance;

use Inventra\Core\Actions\Concerns\AssetCustodyInput;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Enums\MaintenanceStatus;
use Inventra\Core\Enums\MovementType;
use Inventra\Core\Exceptions\NotFoundException;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetMaintenanceRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\MovementRepository;

/**
 * Menutup pemeliharaan: hasil pekerjaan wajib diisi, sedangkan biaya dan jadwal
 * berikutnya boleh dilengkapi di sini. Asetnya kembali berstatus tersedia tanpa
 * berpindah lokasi maupun berubah pemegang — pemeliharaan tidak pernah
 * memindahkan aset ke master lokasi lain. Catatan pergerakannya bertipe check_in
 * dengan hasil pekerjaan sebagai catatan.
 */
final class CompleteMaintenanceAction
{
    use AssetCustodyInput;

    public function __construct(
        private readonly AssetMaintenanceRepository $maintenances,
        private readonly AssetRepository $assets,
        private readonly MovementRepository $movements,
    ) {
    }

    /**
     * @param array<string, mixed> $payload work_result wajib; cost dan next_schedule opsional.
     * @return array<string, mixed> Baris pemeliharaan sesudah ditutup.
     */
    public function execute(int $id, array $payload, string $actor): array
    {
        $maintenance = $this->maintenances->find($id)
            ?? throw new NotFoundException('Pemeliharaan tidak ditemukan.');

        if (MaintenanceStatus::tryFrom((string)$maintenance['status']) !== MaintenanceStatus::Ongoing) {
            throw new ValidationException(
                'Pemeliharaan ini sudah selesai atau dibatalkan.',
                ['status' => 'Pemeliharaan ini sudah selesai atau dibatalkan.']
            );
        }

        $workResult = $this->custodyOptionalText($payload['work_result'] ?? null);
        $errors = [];

        if ($workResult === null) {
            $errors['work_result'] = 'Hasil pekerjaan wajib diisi.';
        }

        $extra = $this->completionExtras($payload, $errors);

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        $assetId = (int)$maintenance['asset_id'];
        $asset = $this->assets->find($assetId)
            ?? throw new NotFoundException('Aset tidak ditemukan.');

        $this->maintenances->update($id, array_merge([
            'status' => MaintenanceStatus::Completed->value,
            'completed_at' => date('Y-m-d H:i:s'),
            'work_result' => $workResult,
        ], $extra));

        // Lokasi dan pemegang dibiarkan apa adanya: aset kembali tersedia di
        // tempatnya semula, dan pemegang yang kosong tetap kosong.
        $this->assets->setHolder(
            $assetId,
            null,
            $asset['assigned_to'] !== null ? (string)$asset['assigned_to'] : null,
            null,
            AssetStatus::Available
        );

        $locationId = (int)$asset['location_id'];
        $this->movements->record([
            'asset_id' => $assetId,
            'movement_type' => MovementType::CheckIn->value,
            'from_location_id' => $locationId,
            'to_location_id' => $locationId,
            'actor_name' => $actor,
            'recipient_name' => null,
            'notes' => 'Selesai pemeliharaan: ' . (string)$workResult,
        ]);

        return $this->maintenances->find($id) ?? $maintenance;
    }

    /**
     * Biaya dan jadwal berikutnya hanya ditulis bila klien memang mengirimnya,
     * sehingga penutupan tanpa keduanya tidak menghapus nilai yang sudah tercatat.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $errors Dilengkapi di tempat.
     * @return array<string, mixed> Kolom tambahan untuk update().
     */
    private function completionExtras(array $payload, array &$errors): array
    {
        $extra = [];

        if ($this->custodyText($payload['cost'] ?? null) !== '') {
            $cost = $this->custodyDecimal($payload['cost'] ?? null);
            if ($cost === null) {
                $errors['cost'] = 'Biaya pemeliharaan tidak valid.';
            } elseif ($cost < 0) {
                $errors['cost'] = 'Biaya pemeliharaan tidak boleh negatif.';
            } else {
                $extra['cost'] = $cost;
            }
        }

        if ($this->custodyText($payload['next_schedule'] ?? null) !== '') {
            $nextSchedule = $this->custodyDate($payload['next_schedule'] ?? null);
            if ($nextSchedule === null) {
                $errors['next_schedule'] = 'Jadwal berikutnya tidak valid.';
            } else {
                $extra['next_schedule'] = $nextSchedule;
            }
        }

        return $extra;
    }
}
