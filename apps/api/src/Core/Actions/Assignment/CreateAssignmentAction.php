<?php

declare(strict_types=1);

namespace Inventra\Core\Actions\Assignment;

use Inventra\Core\Actions\Concerns\AssetCustodyInput;
use Inventra\Core\Enums\AssetStatus;
use Inventra\Core\Exceptions\ValidationException;
use Inventra\Core\Repositories\AssetAssignmentRepository;
use Inventra\Core\Repositories\AssetLoanRepository;
use Inventra\Core\Repositories\AssetRepository;
use Inventra\Core\Repositories\EmployeeRepository;
use Inventra\Core\Repositories\LocationRepository;

/**
 * Mencatat penempatan permanen aset kepada pemegang dan lokasi baru, lalu
 * memindahkan asetnya. Aset yang sudah dihapus, atau yang masih berada pada
 * pemegang/peminjaman lain, ditolak supaya tidak ada dua pemegang aktif untuk
 * satu aset. Actor diserahkan pemanggil, bukan dibaca dari sesi.
 */
final class CreateAssignmentAction
{
    use AssetCustodyInput;

    public function __construct(
        private readonly AssetRepository $assets,
        private readonly AssetAssignmentRepository $assignments,
        private readonly AssetLoanRepository $loans,
        private readonly EmployeeRepository $employees,
        private readonly LocationRepository $locations,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param string $actorName Nama pelaku dari sesi, diserahkan Controller.
     * @return int Id baris penempatan yang baru dibuat.
     */
    public function execute(array $payload, string $actorName): int
    {
        $assetId = $this->custodyInt($payload['asset_id'] ?? null);
        $locationId = $this->custodyInt($payload['location_id'] ?? null);
        $employeeId = $this->custodyOptionalInt($payload['employee_id'] ?? null);
        $receivedBy = $this->custodyOptionalText($payload['received_by_name'] ?? null);
        $assignedAt = $this->custodyDateTime($payload['assigned_at'] ?? null) ?? date('Y-m-d H:i:s');
        $notes = $this->custodyOptionalText($payload['notes'] ?? null);
        $errors = [];

        $asset = $assetId > 0 ? $this->assets->find($assetId) : null;
        if ($asset === null) {
            $errors['asset_id'] = 'Aset tidak ditemukan.';
        } elseif (AssetStatus::tryFrom((string)$asset['status']) === AssetStatus::Disposed) {
            $errors['asset_id'] = 'Aset yang sudah dihapus tidak dapat dipakai transaksi baru.';
        } elseif ($this->assignments->activeForAsset($assetId) !== null
            || $this->loans->activeForAsset($assetId) !== null) {
            $errors['asset_id'] = 'Aset masih berada pada pemegang atau peminjaman lain.';
        }

        if ($locationId <= 0 || $this->locations->find($locationId) === null) {
            $errors['location_id'] = 'Lokasi tidak ditemukan.';
        }

        $employee = $employeeId !== null ? $this->employees->find($employeeId) : null;
        if ($employeeId !== null && $employee === null) {
            $errors['employee_id'] = 'Karyawan tidak ditemukan.';
        }

        if ($this->custodyText($payload['assigned_at'] ?? null) !== '' && $this->custodyDateTime($payload['assigned_at'] ?? null) === null) {
            $errors['assigned_at'] = 'Tanggal serah terima tidak valid.';
        }

        [$conditionOut, $conditionValid] = $this->custodyCondition($payload['condition_out'] ?? null);
        if (!$conditionValid) {
            $errors['condition_out'] = 'Kondisi aset tidak valid.';
        }

        // Pemegang diambil dari nama karyawan; tanpa karyawan, nama penerima yang
        // diisi klien dipakai supaya penempatan tetap punya pemegang yang jelas.
        $holderName = $employee !== null ? (string)$employee['name'] : $receivedBy;
        if ($holderName === null || $holderName === '') {
            $errors['received_by_name'] = 'Nama penerima wajib diisi.';
        }

        if ($errors !== []) {
            throw new ValidationException((string)reset($errors), $errors);
        }

        $id = $this->assignments->create([
            'asset_id' => $assetId,
            'employee_id' => $employeeId,
            'location_id' => $locationId,
            'assigned_at' => $assignedAt,
            'condition_out' => $conditionOut->value,
            'handed_by_name' => $actorName,
            'received_by_name' => $holderName,
            'notes' => $notes,
        ]);

        // Pindah pemegang sekaligus mencatat pergerakan aset, seperti serah terima.
        $this->assets->checkOut(
            $assetId,
            (string)$holderName,
            $locationId,
            $actorName,
            AssetStatus::InUse,
            (int)$asset['location_id'],
            $notes
        );

        return $id;
    }
}
